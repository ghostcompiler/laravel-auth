<?php

namespace GhostCompiler\LaravelAuth\OTP;

use GhostCompiler\LaravelAuth\Contracts\OtpTransport;
use GhostCompiler\LaravelAuth\Models\OtpChallenge;
use GhostCompiler\LaravelAuth\Support\VerifiedFactor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

class OtpChannelManager
{
    public function __construct(protected OtpTemplateRenderer $renderer)
    {
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{channel:string,destination:string,expires_at:?string}
     */
    public function send(Authenticatable $user, string $channel, string $destination, array $context = []): array
    {
        $this->ensureEnabled($channel);

        $code = $this->generateCode();
        $provider = $this->providerName($channel);

        OtpChallenge::query()
            ->whereMorphedTo('authenticatable', $user)
            ->where('channel', $channel)
            ->where('destination', $destination)
            ->whereNull('consumed_at')
            ->delete();

        $challenge = OtpChallenge::query()->create([
            'authenticatable_type' => $user instanceof Model ? $user->getMorphClass() : $user::class,
            'authenticatable_id' => $user->getAuthIdentifier(),
            'channel' => $channel,
            'destination' => $destination,
            'provider' => $provider,
            'code' => Hash::make($code),
            'max_attempts' => (int) config('laravel-auth.otp_channels.max_attempts', 5),
            'expires_at' => now()->addSeconds((int) config('laravel-auth.otp_channels.ttl_seconds', 300)),
            'meta' => $context,
        ]);

        $message = $this->renderer->render($user, $channel, $code, $context);
        $this->transport($channel)->sendCode($user, $challenge, $message, array_replace($context, [
            'code' => $code,
            'destination' => $destination,
        ]));

        return [
            'channel' => $channel,
            'destination' => $destination,
            'expires_at' => optional($challenge->expires_at)->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function verify(Authenticatable $user, string $channel, string $destination, string $code, array $context = []): ?VerifiedFactor
    {
        $this->ensureEnabled($channel);
        $this->throttle($channel, $user, $destination);

        $challenge = OtpChallenge::query()
            ->whereMorphedTo('authenticatable', $user)
            ->where('channel', $channel)
            ->where('destination', $destination)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (!$challenge) {
            throw new RuntimeException('No active OTP challenge found.');
        }

        if ($challenge->attempts >= $challenge->max_attempts) {
            throw new RuntimeException('This OTP challenge has exceeded the maximum attempts.');
        }

        $transportVerified = $this->transport($channel)->verifyCode($user, $challenge, $code, $context);
        $localVerified = Hash::check($code, $challenge->code);

        if (!$transportVerified || !$localVerified) {
            $challenge->increment('attempts');
            RateLimiter::hit(
                $this->throttleKey($channel, $user, $destination),
                (int) config('laravel-auth.rate_limit.decay_seconds', 60)
            );

            return null;
        }

        $challenge->forceFill([
            'consumed_at' => now(),
        ])->save();

        RateLimiter::clear($this->throttleKey($channel, $user, $destination));

        return VerifiedFactor::issue($user, $channel . '_otp');
    }

    protected function transport(string $channel): OtpTransport
    {
        $transport = match ($channel) {
            'email' => \GhostCompiler\LaravelAuth\OTP\Transport\MailOtpTransport::class,
            'sms' => match ($this->providerName($channel)) {
                'vonage' => \GhostCompiler\LaravelAuth\OTP\Transport\VonageSmsOtpTransport::class,
                'messagebird' => \GhostCompiler\LaravelAuth\OTP\Transport\MessageBirdSmsOtpTransport::class,
                'msg91' => \GhostCompiler\LaravelAuth\OTP\Transport\Msg91SmsOtpTransport::class,
                'custom' => (string) config('laravel-auth.otp_channels.sms.custom_transport', \GhostCompiler\LaravelAuth\OTP\Transport\CustomSmsOtpTransport::class),
                default => \GhostCompiler\LaravelAuth\OTP\Transport\TwilioSmsOtpTransport::class,
            },
            'whatsapp' => $this->providerName($channel) === 'twilio'
                ? \GhostCompiler\LaravelAuth\OTP\Transport\TwilioWhatsAppOtpTransport::class
                : (string) config('laravel-auth.otp_channels.whatsapp.custom_transport', \GhostCompiler\LaravelAuth\OTP\Transport\CustomWhatsAppOtpTransport::class),
            default => throw new RuntimeException("Unsupported OTP channel [{$channel}]."),
        };

        return app($transport);
    }

    protected function providerName(string $channel): string
    {
        return (string) config("laravel-auth.otp_channels.{$channel}.provider", 'mail');
    }

    protected function ensureEnabled(string $channel): void
    {
        if (!(bool) config("laravel-auth.otp_channels.{$channel}.enabled", false)) {
            throw new RuntimeException("The [{$channel}] OTP channel is disabled.");
        }
    }

    protected function throttle(string $channel, Authenticatable $user, string $destination): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey($channel, $user, $destination), (int) config('laravel-auth.otp_channels.max_attempts', 5))) {
            throw new RuntimeException('Too many OTP attempts. Please wait and try again.');
        }
    }

    protected function throttleKey(string $channel, Authenticatable $user, string $destination): string
    {
        return sprintf(
            'laravel-auth:%s:%s:%s:%s',
            $channel,
            $user->getAuthIdentifier(),
            $destination,
            request()->ip() ?? 'unknown'
        );
    }

    protected function generateCode(): string
    {
        $length = max((int) config('laravel-auth.otp_channels.length', 6), 4);

        return Str::padLeft((string) random_int(0, (10 ** $length) - 1), $length, '0');
    }
}
