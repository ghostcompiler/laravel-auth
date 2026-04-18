<?php

namespace App\LaravelAuth;

use GhostCompiler\LaravelAuth\Contracts\OtpTransport;
use GhostCompiler\LaravelAuth\Models\OtpChallenge;
use Illuminate\Contracts\Auth\Authenticatable;

class SmsOtpTransport implements OtpTransport
{
    /**
     * Add your manual SMS OTP provider API call here.
     *
     * @param  array<string, mixed>  $context
     */
    public function sendCode(Authenticatable $user, OtpChallenge $challenge, string $message, array $context = []): void
    {
        // Example:
        // Http::withToken(config('services.sms.token'))->post('https://provider.example/send', [
        //     'to' => $context['destination'],
        //     'message' => $message,
        //     'code' => $context['code'],
        // ]);
    }

    /**
     * Add your manual SMS OTP verification API call here.
     * Return true when the provider confirms the code is valid.
     *
     * @param  array<string, mixed>  $context
     */
    public function verifyCode(Authenticatable $user, OtpChallenge $challenge, string $code, array $context = []): bool
    {
        // Example:
        // return Http::withToken(config('services.sms.token'))
        //     ->post('https://provider.example/verify', [
        //         'to' => $context['destination'] ?? $challenge->destination,
        //         'code' => $code,
        //     ])->successful();

        return true;
    }
}
