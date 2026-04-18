<?php

namespace GhostCompiler\LaravelAuth\OTP\Transport;

use GhostCompiler\LaravelAuth\Models\OtpChallenge;
use Illuminate\Contracts\Auth\Authenticatable;

class TwilioSmsOtpTransport extends BaseOtpTransport
{
    public function sendCode(Authenticatable $user, OtpChallenge $challenge, string $message, array $context = []): void
    {
        $this->postJson('https://api.twilio.com/2010-04-01/Accounts/' . config('laravel-auth.otp_channels.sms.providers.twilio.account_sid') . '/Messages.json', [
            'To' => $context['destination'],
            'From' => config('laravel-auth.otp_channels.sms.providers.twilio.from'),
            'Body' => $message,
        ], [
            'basic' => [
                (string) config('laravel-auth.otp_channels.sms.providers.twilio.account_sid'),
                (string) config('laravel-auth.otp_channels.sms.providers.twilio.auth_token'),
            ],
        ]);
    }
}
