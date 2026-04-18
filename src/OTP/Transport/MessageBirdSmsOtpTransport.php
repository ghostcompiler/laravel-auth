<?php

namespace GhostCompiler\LaravelAuth\OTP\Transport;

use GhostCompiler\LaravelAuth\Models\OtpChallenge;
use Illuminate\Contracts\Auth\Authenticatable;

class MessageBirdSmsOtpTransport extends BaseOtpTransport
{
    public function sendCode(Authenticatable $user, OtpChallenge $challenge, string $message, array $context = []): void
    {
        $this->postJson('https://rest.messagebird.com/messages', [
            'originator' => config('laravel-auth.otp_channels.sms.providers.messagebird.originator'),
            'recipients' => [$context['destination']],
            'body' => $message,
        ], [
            'headers' => [
                'Authorization' => 'AccessKey ' . config('laravel-auth.otp_channels.sms.providers.messagebird.access_key'),
            ],
        ]);
    }
}
