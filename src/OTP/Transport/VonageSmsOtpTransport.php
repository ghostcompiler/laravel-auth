<?php

namespace GhostCompiler\LaravelAuth\OTP\Transport;

use GhostCompiler\LaravelAuth\Models\OtpChallenge;
use Illuminate\Contracts\Auth\Authenticatable;

class VonageSmsOtpTransport extends BaseOtpTransport
{
    public function sendCode(Authenticatable $user, OtpChallenge $challenge, string $message, array $context = []): void
    {
        $this->postJson('https://rest.nexmo.com/sms/json', [
            'api_key' => config('laravel-auth.otp_channels.sms.providers.vonage.key'),
            'api_secret' => config('laravel-auth.otp_channels.sms.providers.vonage.secret'),
            'to' => $context['destination'],
            'from' => config('laravel-auth.otp_channels.sms.providers.vonage.from'),
            'text' => $message,
        ]);
    }
}
