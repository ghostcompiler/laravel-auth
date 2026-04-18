<?php

namespace GhostCompiler\LaravelAuth\Services;

use GhostCompiler\LaravelAuth\Models\Passkey;
use GhostCompiler\LaravelAuth\Support\WebAuthnPayload;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use RuntimeException;

class WebAuthnService
{
    public function beginRegistration(Authenticatable $user, ?string $displayName = null): array
    {
        $webauthn = $this->driver();
        $identifier = (string) $user->getAuthIdentifier();
        $label = method_exists($user, 'getAuthIdentifierName') ? (string) $user->{$user->getAuthIdentifierName()} : $identifier;

        $excludeCredentialIds = Passkey::query()
            ->whereMorphedTo('authenticatable', $user)
            ->pluck('credential_id')
            ->map(fn (string $credentialId) => base64_decode($credentialId, true))
            ->filter(fn ($binary) => is_string($binary) && $binary !== '')
            ->values()
            ->all();

        $rawOptions = $webauthn->getCreateArgs(
            hex2bin(bin2hex($identifier)),
            $label,
            $displayName ?: ($user->name ?? $label),
            (int) config('laravel-auth.webauthn.timeout', 240),
            (bool) config('laravel-auth.webauthn.require_resident_key', true),
            (string) config('laravel-auth.webauthn.user_verification', 'preferred'),
            $this->crossPlatformAttachment(),
            $excludeCredentialIds
        );

        $challenge = app(ChallengeStore::class)->create(
            $user,
            'registration',
            $webauthn->getChallenge(),
            (int) config('laravel-auth.webauthn.timeout', 240)
        );

        $options = $this->optionsPayload($rawOptions);

        return [
            'challenge_id' => $challenge->getKey(),
            'publicKey' => $options['publicKey'] ?? $options,
        ];
    }

    public function finishRegistration(Authenticatable $user, array $payload, ?string $name = null): array
    {
        $challenge = app(ChallengeStore::class)->consume($user, 'registration', Arr::get($payload, 'challenge_id'));

        if (!$challenge) {
            throw new RuntimeException('The passkey registration challenge is invalid or expired.');
        }

        $webauthn = $this->driver();

        try {
            $record = $webauthn->processCreate(
                WebAuthnPayload::decode(Arr::get($payload, 'clientDataJSON')),
                WebAuthnPayload::decode(Arr::get($payload, 'attestationObject')),
                $challenge,
                config('laravel-auth.webauthn.user_verification') === 'required',
                true,
                false,
                false,
            );
        } catch (WebAuthnException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        $record->signatureCounter ??= 0;

        $passkey = Passkey::query()->create([
            'authenticatable_type' => $user instanceof Model ? $user->getMorphClass() : $user::class,
            'authenticatable_id' => $user->getAuthIdentifier(),
            'name' => $name ?: 'Passkey ' . now()->format('Y-m-d H:i'),
            'credential_id' => base64_encode($record->credentialId),
            'credential_public_key' => base64_encode($record->credentialPublicKey),
            'aaguid' => isset($record->AAGUID) ? bin2hex($record->AAGUID) : null,
            'signature_counter' => (int) $record->signatureCounter,
            'transports' => Arr::wrap(Arr::get($payload, 'transports')),
            'last_used_at' => now(),
        ]);

        return $passkey->toArray();
    }

    public function beginAuthentication(Authenticatable $user): array
    {
        $credentialIds = Passkey::query()
            ->whereMorphedTo('authenticatable', $user)
            ->pluck('credential_id')
            ->map(fn (string $credentialId) => base64_decode($credentialId))
            ->filter()
            ->values()
            ->all();

        $webauthn = $this->driver();
        $rawOptions = $webauthn->getGetArgs(
            $credentialIds,
            (int) config('laravel-auth.webauthn.timeout', 240),
            (bool) config('laravel-auth.webauthn.allow_usb', true),
            (bool) config('laravel-auth.webauthn.allow_nfc', true),
            (bool) config('laravel-auth.webauthn.allow_ble', true),
            (bool) config('laravel-auth.webauthn.allow_hybrid', true),
            (bool) config('laravel-auth.webauthn.allow_internal', true),
            (string) config('laravel-auth.webauthn.user_verification', 'preferred')
        );

        $challenge = app(ChallengeStore::class)->create(
            $user,
            'authentication',
            $webauthn->getChallenge(),
            (int) config('laravel-auth.webauthn.timeout', 240)
        );

        $options = $this->optionsPayload($rawOptions);

        return [
            'challenge_id' => $challenge->getKey(),
            'publicKey' => $options['publicKey'] ?? $options,
        ];
    }

    public function verifyAuthentication(Authenticatable $user, array $payload): bool
    {
        $challenge = app(ChallengeStore::class)->consume($user, 'authentication', Arr::get($payload, 'challenge_id'));

        if (!$challenge) {
            throw new RuntimeException('The passkey authentication challenge is invalid or expired.');
        }

        $credentialId = base64_encode(WebAuthnPayload::decode(Arr::get($payload, 'id')) ?? '');

        $passkey = Passkey::query()
            ->whereMorphedTo('authenticatable', $user)
            ->where('credential_id', $credentialId)
            ->first();

        if (!$passkey) {
            throw new RuntimeException('The supplied passkey is not registered for this user.');
        }

        $driver = $this->driver();

        try {
            $driver->processGet(
                WebAuthnPayload::decode(Arr::get($payload, 'clientDataJSON')),
                WebAuthnPayload::decode(Arr::get($payload, 'authenticatorData')),
                WebAuthnPayload::decode(Arr::get($payload, 'signature')),
                base64_decode($passkey->credential_public_key),
                $challenge,
                $passkey->signature_counter,
                config('laravel-auth.webauthn.user_verification') === 'required'
            );
        } catch (WebAuthnException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        $newCounter = $driver->getSignatureCounter();

        if (is_int($newCounter) && $newCounter > 0 && $newCounter <= $passkey->signature_counter) {
            throw new RuntimeException('Possible cloned authenticator detected.');
        }

        $passkey->forceFill([
            'last_used_at' => now(),
            'signature_counter' => is_int($newCounter) && $newCounter > 0
                ? $newCounter
                : $passkey->signature_counter,
        ])->save();

        return true;
    }

    private function driver(): WebAuthn
    {
        // lbuchs/webauthn WebAuthn::__construct overwrites ByteBuffer::$useBase64UrlEncoding from arg 4.
        // Must pass true so JSON options use base64url (browser expects it); default false uses RFC 1342 MIME.
        return new WebAuthn(
            (string) config('laravel-auth.webauthn.rp_name', config('app.name', 'Laravel')),
            $this->rpId(),
            config('laravel-auth.webauthn.attestation_formats', ['none']),
            true
        );
    }

    private function rpId(): string
    {
        $configured = config('laravel-auth.webauthn.rp_id');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        // Prefer the browser hostname so RP ID matches when APP_URL differs (e.g. Valet vs ngrok).
        if (! app()->runningInConsole()) {
            $host = request()->getHost();
            if (is_string($host) && $host !== '') {
                return $host;
            }
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    private function crossPlatformAttachment(): ?bool
    {
        $crossPlatform = [
            (bool) config('laravel-auth.webauthn.allow_usb', true),
            (bool) config('laravel-auth.webauthn.allow_nfc', true),
            (bool) config('laravel-auth.webauthn.allow_ble', true),
            (bool) config('laravel-auth.webauthn.allow_hybrid', true),
        ];

        $internal = (bool) config('laravel-auth.webauthn.allow_internal', true);

        if (in_array(true, $crossPlatform, true) && !$internal) {
            return true;
        }

        if (!in_array(true, $crossPlatform, true) && $internal) {
            return false;
        }

        return null;
    }

    /**
     * lbuchs/webauthn returns nested stdClass; JSON responses need arrays.
     *
     * @return array<string, mixed>
     */
    private function optionsPayload(object|array $raw): array
    {
        $decoded = json_decode(json_encode($raw), true);

        return is_array($decoded) ? $decoded : [];
    }
}
