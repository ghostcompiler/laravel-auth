<?php

namespace GhostCompiler\LaravelAuth\OTP\Transport;

use GhostCompiler\LaravelAuth\Models\OtpChallenge;
use Illuminate\Contracts\Auth\Authenticatable;

class Msg91SmsOtpTransport extends BaseOtpTransport
{
    public function sendCode(Authenticatable $user, OtpChallenge $challenge, string $message, array $context = []): void
    {
        $this->postJson('https://control.msg91.com/api/v5/flow/', [
            'template_id' => config('laravel-auth.otp_channels.sms.providers.msg91.template_id'),
            'sender' => config('laravel-auth.otp_channels.sms.providers.msg91.sender'),
            'short_url' => 0,
            'mobiles' => '91' . preg_replace('/\D+/', '', (string) $context['destination']),
            'VAR1' => $context['code'] ?? '',
        ], [
            'headers' => [
                'authkey' => (string) config('laravel-auth.otp_channels.sms.providers.msg91.auth_key'),
            ],
        ]);
    }
}
