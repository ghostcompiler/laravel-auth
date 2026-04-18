<?php

namespace GhostCompiler\LaravelAuth;

use GhostCompiler\LaravelAuth\Contracts\LaravelAuthManager as LaravelAuthManagerContract;
use GhostCompiler\LaravelAuth\Enums\AuthState;
use GhostCompiler\LaravelAuth\Exceptions\TwoFactorRequiredException;
use GhostCompiler\LaravelAuth\Models\RecoveryCode;
use GhostCompiler\LaravelAuth\OTP\OtpChannelManager;
use GhostCompiler\LaravelAuth\Services\RecoveryCodeService;
use GhostCompiler\LaravelAuth\Services\SocialAuthService;
use GhostCompiler\LaravelAuth\Services\TotpService;
use GhostCompiler\LaravelAuth\Services\TrustedDeviceService;
use GhostCompiler\LaravelAuth\Services\WebAuthnService;
use GhostCompiler\LaravelAuth\Support\UserSecurity;
use GhostCompiler\LaravelAuth\Support\VerifiedFactor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LaravelAuthManager implements LaravelAuthManagerContract
{
    public function __construct(
        protected TotpService $totp,
        protected RecoveryCodeService $recoveryCodes,
        protected WebAuthnService $passkeys,
        protected OtpChannelManager $otpChannels,
        protected SocialAuthService $socialAuth,
        protected TrustedDeviceService $trustedDevices,
        protected Request $request
    ) {
    }

    public function enable2FA(Authenticatable $user): array
    {
        if (UserSecurity::enabled($user)) {
            throw new RuntimeException('Two-factor authentication is already enabled.');
        }

        $secret = $this->totp->generateSecret();

        $user->forceFill([
            'laravel_auth_totp_secret' => Crypt::encryptString($secret),
            'laravel_auth_two_factor_enabled' => false,
            'laravel_auth_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'otpauth_uri' => $this->totp->provisioningUri(
                $user->email ?? (string) $user->getAuthIdentifier(),
                $secret,
                (string) config('laravel-auth.totp.issuer', config('app.name', 'Laravel')),
                (int) config('laravel-auth.totp.digits', 6),
                (int) config('laravel-auth.totp.period', 30)
            ),
        ];
    }

    public function confirmTwoFactorSetup(Authenticatable $user, string $code): array
    {
        if (UserSecurity::enabled($user)) {
            throw new RuntimeException('Two-factor authentication is already enabled.');
        }

        $proof = $this->verifyOTPForProof($user, $code);

        if (!$proof) {
            throw new RuntimeException('The verification code is invalid.');
        }

        $user->forceFill([
            'laravel_auth_two_factor_enabled' => true,
            'laravel_auth_confirmed_at' => now(),
        ])->save();

        $this->markVerified($user, $proof);

        return [
            'recovery_codes' => $this->generateRecoveryCodes($user),
            'proof' => $proof,
        ];
    }

    public function disable2FA(Authenticatable $user): void
    {
        $user->forceFill([
            'laravel_auth_totp_secret' => null,
            'laravel_auth_two_factor_enabled' => false,
            'laravel_auth_confirmed_at' => null,
        ])->save();

        RecoveryCode::query()
            ->whereMorphedTo('authenticatable', $user)
            ->delete();

        $this->trustedDevices->forgetAll($user, $this->request);
        session()->forget($this->sessionKey($user));
        session()->forget($this->stateKey());
        session()->put($this->stateKey(), AuthState::FullyAuthenticated->value);
    }

    public function verifyOTP(Authenticatable $user, string $code): ?VerifiedFactor
    {
        return $this->verifyOTPForProof($user, $code);
    }

    public function generateRecoveryCodes(Authenticatable $user): array
    {
        return $this->recoveryCodes->regenerate(
            $user,
            (int) config('laravel-auth.recovery_codes.count', 8)
        );
    }

    public function registerPasskey(Authenticatable $user, ?string $name = null): array
    {
        return $this->passkeys->beginRegistration($user, $name);
    }

    public function finishPasskeyRegistration(Authenticatable $user, array $payload, ?string $name = null): array
    {
        return $this->passkeys->finishRegistration($user, $payload, $name);
    }

    public function requestPasskeyAssertion(Authenticatable $user): array
    {
        return $this->passkeys->beginAuthentication($user);
    }

    public function verifyPasskeyAssertion(Authenticatable $user, array $payload): ?VerifiedFactor
    {
        $this->throttle('passkey', $user);

        try {
            $this->passkeys->verifyAuthentication($user, $payload);
        } catch (RuntimeException $exception) {
            throw $this->failAttempt('passkey', $user, $exception);
        }

        $this->clearThrottle('passkey', $user);

        return VerifiedFactor::issue($user, 'passkey');
    }

    public function attemptOtp(Authenticatable $user, string $code, bool $rememberDevice = false, ?string $deviceName = null): bool
    {
        $proof = $this->verifyOTPForProof($user, $code);

        if (!$proof) {
            $this->setState(AuthState::TwoFactorPending);

            return false;
        }

        $this->markVerified($user, $proof, $rememberDevice, $deviceName);

        return true;
    }

    public function attemptPasskey(Authenticatable $user, array $payload, bool $rememberDevice = false, ?string $deviceName = null): bool
    {
        $proof = $this->verifyPasskeyAssertion($user, $payload);

        if (!$proof) {
            $this->setState(AuthState::TwoFactorPending);

            return false;
        }

        $this->markVerified($user, $proof, $rememberDevice, $deviceName);

        return true;
    }

    public function sendEmailOtp(Authenticatable $user, ?string $email = null, array $context = []): array
    {
        $destination = $email ?: (string) ($user->email ?? '');

        if ($destination === '') {
            throw new RuntimeException('An email address is required for email OTP delivery.');
        }

        return $this->otpChannels->send($user, 'email', $destination, $context);
    }

    public function verifyEmailOtp(Authenticatable $user, string $code, ?string $email = null, array $context = []): ?VerifiedFactor
    {
        $destination = $email ?: (string) ($user->email ?? '');

        if ($destination === '') {
            throw new RuntimeException('An email address is required for email OTP verification.');
        }

        return $this->otpChannels->verify($user, 'email', $destination, $code, $context);
    }

    public function attemptEmailOtp(Authenticatable $user, string $code, ?string $email = null, array $context = []): bool
    {
        $proof = $this->verifyEmailOtp($user, $code, $email, $context);

        if (!$proof) {
            $this->setState(AuthState::TwoFactorPending);

            return false;
        }

        $this->markVerified($user, $proof);

        return true;
    }

    public function sendSmsOtp(Authenticatable $user, string $phoneNumber, array $context = []): array
    {
        return $this->otpChannels->send($user, 'sms', $phoneNumber, $context);
    }

    public function verifySmsOtp(Authenticatable $user, string $phoneNumber, string $code, array $context = []): ?VerifiedFactor
    {
        return $this->otpChannels->verify($user, 'sms', $phoneNumber, $code, $context);
    }

    public function attemptSmsOtp(Authenticatable $user, string $phoneNumber, string $code, array $context = []): bool
    {
        $proof = $this->verifySmsOtp($user, $phoneNumber, $code, $context);

        if (!$proof) {
            $this->setState(AuthState::TwoFactorPending);

            return false;
        }

        $this->markVerified($user, $proof);

        return true;
    }

    public function sendWhatsAppOtp(Authenticatable $user, string $phoneNumber, array $context = []): array
    {
        return $this->otpChannels->send($user, 'whatsapp', $phoneNumber, $context);
    }

    public function verifyWhatsAppOtp(Authenticatable $user, string $phoneNumber, string $code, array $context = []): ?VerifiedFactor
    {
        return $this->otpChannels->verify($user, 'whatsapp', $phoneNumber, $code, $context);
    }

    public function attemptWhatsAppOtp(Authenticatable $user, string $phoneNumber, string $code, array $context = []): bool
    {
        $proof = $this->verifyWhatsAppOtp($user, $phoneNumber, $code, $context);

        if (!$proof) {
            $this->setState(AuthState::TwoFactorPending);

            return false;
        }

        $this->markVerified($user, $proof);

        return true;
    }

    public function socialProviders(array $runtimeProviders = []): array
    {
        return $this->socialAuth->configuredProviders($runtimeProviders);
    }

    public function redirectToSocialProvider(string $provider, array $scopes = [], array $with = [], ?bool $stateless = null, array $runtimeConfig = []): RedirectResponse
    {
        return $this->socialAuth->redirect($provider, $scopes, $with, $stateless, $runtimeConfig);
    }

    public function resolveSocialUser(string $provider, ?bool $stateless = null, array $runtimeConfig = []): array
    {
        return $this->socialAuth->resolveUser($provider, $stateless, $runtimeConfig);
    }

    public function syncSocialAccount(Authenticatable $user, string $provider, array $profile): array
    {
        $account = $this->socialAuth->syncAccount($user, $provider, $profile);

        return [
            'provider' => $account->provider,
            'provider_user_id' => $account->provider_user_id,
            'nickname' => $account->provider_nickname,
            'name' => $account->provider_name,
            'email' => $account->provider_email,
            'avatar' => $account->provider_avatar,
            'approved_scopes' => $account->approved_scopes ?? [],
            'last_used_at' => optional($account->last_used_at)->toISOString(),
            'created_at' => optional($account->created_at)->toISOString(),
            'updated_at' => optional($account->updated_at)->toISOString(),
        ];
    }

    public function findUserBySocialAccount(string $provider, string|array $socialIdentity): ?Authenticatable
    {
        return $this->socialAuth->findUserByAccount($provider, $socialIdentity);
    }

    public function linkedSocialAccounts(Authenticatable $user): array
    {
        return $this->socialAuth->linkedAccounts($user);
    }

    public function unlinkSocialAccount(Authenticatable $user, string $provider, ?string $providerUserId = null): int
    {
        return $this->socialAuth->unlinkAccount($user, $provider, $providerUserId);
    }

    public function state(?Authenticatable $user = null): AuthState
    {
        $user ??= $this->request->user() ?? auth()->user();

        if (!$user) {
            return AuthState::Guest;
        }

        $rawState = session()->get($this->stateKey());

        if (is_string($rawState)) {
            $state = AuthState::tryFrom($rawState);

            if ($state instanceof AuthState) {
                if ($state === AuthState::PasswordVerified) {
                    return $this->syncAuthenticatedState($user);
                }

                return $state;
            }
        }

        return $this->syncAuthenticatedState($user);
    }

    public function isVerified(?Authenticatable $user = null): bool
    {
        return $this->isFullyAuthenticated($user);
    }

    public function isFullyAuthenticated(?Authenticatable $user = null): bool
    {
        return $this->state($user) === AuthState::FullyAuthenticated;
    }

    public function isPending2FA(?Authenticatable $user = null): bool
    {
        return $this->state($user) === AuthState::TwoFactorPending;
    }

    public function enforce(?Authenticatable $user = null): void
    {
        if (!(bool) config('laravel-auth.enforce_2fa', true)) {
            return;
        }

        if (auth()->check() && !$this->isFullyAuthenticated($user ?? $this->request->user() ?? auth()->user())) {
            throw new TwoFactorRequiredException('2FA required');
        }
    }

    public function throttle(string $bucket, ?Authenticatable $user = null): void
    {
        if ($this->tooManyAttempts($bucket, $user)) {
            throw new RuntimeException('Too many attempts');
        }

        RateLimiter::hit(
            $this->throttleKey($bucket, $user),
            (int) config('laravel-auth.rate_limit.decay_seconds', 60)
        );
    }

    public function tooManyAttempts(string $bucket, ?Authenticatable $user = null): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->throttleKey($bucket, $user),
            $this->throttleAttempts($bucket)
        );
    }

    public function clearThrottle(string $bucket, ?Authenticatable $user = null): void
    {
        RateLimiter::clear($this->throttleKey($bucket, $user));
    }

    public function preset(string $name): array
    {
        $settings = (array) config("laravel-auth.presets.{$name}", []);

        if ($settings === []) {
            throw new RuntimeException("The [{$name}] preset is not configured.");
        }

        config([
            'laravel-auth' => array_replace_recursive(config('laravel-auth', []), $settings),
            'laravel-auth.preset' => $name,
        ]);

        return $settings;
    }

    public function requiresTwoFactor(Authenticatable $user): bool
    {
        return $this->syncAuthenticatedState($user) === AuthState::TwoFactorPending;
    }

    protected function markVerified(Authenticatable $user, VerifiedFactor $proof, bool $rememberDevice = false, ?string $deviceName = null): void
    {
        $proof->assertMatches($user);

        session()->put($this->sessionKey($user), true);
        $this->setState(AuthState::FullyAuthenticated);

        if ($rememberDevice) {
            $this->trustedDevices->trust($user, $this->request, $deviceName);
        }

        if (method_exists(session(), 'regenerate')) {
            session()->regenerate();
        }
    }

    public function syncAuthenticatedState(Authenticatable $user): AuthState
    {
        $this->setState(AuthState::PasswordVerified);

        if (!UserSecurity::enabled($user)) {
            session()->put($this->sessionKey($user), true);
            $this->setState(AuthState::FullyAuthenticated);

            return AuthState::FullyAuthenticated;
        }

        if (session()->get($this->sessionKey($user), false)) {
            $this->setState(AuthState::FullyAuthenticated);

            return AuthState::FullyAuthenticated;
        }

        if ($this->trustedDevices->isTrusted($user, $this->request)) {
            session()->put($this->sessionKey($user), true);
            $this->setState(AuthState::FullyAuthenticated);

            return AuthState::FullyAuthenticated;
        }

        $this->setState(AuthState::TwoFactorPending);

        return AuthState::TwoFactorPending;
    }

    private function verifyOTPForProof(Authenticatable $user, string $code): ?VerifiedFactor
    {
        $this->throttle('otp', $user);

        $secret = UserSecurity::secret($user);
        $verified = false;

        if ($secret) {
            $verified = $this->totp->verify(
                Crypt::decryptString($secret),
                $code,
                (int) config('laravel-auth.totp.window', 1),
                (int) config('laravel-auth.totp.digits', 6),
                (int) config('laravel-auth.totp.period', 30)
            );
        }

        if (!$verified) {
            $verified = $this->recoveryCodes->consume($user, strtoupper($code));
        }

        if (!$verified) {
            $this->setState(AuthState::TwoFactorPending);

            return null;
        }

        $this->clearThrottle('otp', $user);

        return VerifiedFactor::issue($user, 'otp');
    }

    private function failAttempt(string $bucket, ?Authenticatable $user, RuntimeException $exception): RuntimeException
    {
        $this->setState(AuthState::TwoFactorPending);

        return $exception;
    }

    private function throttleKey(string $bucket, ?Authenticatable $user = null): string
    {
        $userId = $user?->getAuthIdentifier() ?? 'guest';

        return sprintf(
            'laravel-auth:%s:%s:%s',
            $bucket,
            $userId,
            $this->request->ip() ?? 'unknown'
        );
    }

    private function throttleAttempts(string $bucket): int
    {
        return match ($bucket) {
            'passkey' => (int) config('laravel-auth.rate_limit.passkey_attempts', 5),
            default => (int) config('laravel-auth.rate_limit.otp_attempts', 5),
        };
    }

    private function setState(AuthState $state): void
    {
        session()->put($this->stateKey(), $state->value);
    }

    private function stateKey(): string
    {
        return 'laravel_auth.state';
    }

    private function sessionKey(Authenticatable $user): string
    {
        return 'laravel_auth.verified.' . $user->getAuthIdentifier();
    }
}
