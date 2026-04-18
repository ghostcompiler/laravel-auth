<?php

namespace GhostCompiler\LaravelAuth\OTP\Transport;

use GhostCompiler\LaravelAuth\Mail\OtpCodeMail;
use GhostCompiler\LaravelAuth\Models\OtpChallenge;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class MailOtpTransport extends BaseOtpTransport
{
    public function sendCode(Authenticatable $user, OtpChallenge $challenge, string $message, array $context = []): void
    {
        $email = $context['destination'] ?? ($user->email ?? null);

        if (!is_string($email) || $email === '') {
            throw new RuntimeException('A destination email address is required for email OTP delivery.');
        }

        Mail::to($email)->send(new OtpCodeMail(
            (string) config('laravel-auth.otp_channels.email.view', 'laravel-auth::mail.otp'),
            (string) config('laravel-auth.otp_channels.email.subject', 'Your verification code'),
            array_replace($context, [
                'user' => $user,
                'message' => $message,
                'code' => $context['code'] ?? null,
                'expiresInMinutes' => max((int) ceil(((int) config('laravel-auth.otp_channels.ttl_seconds', 300)) / 60), 1),
            ])
        ));
    }
}
