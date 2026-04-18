<?php

namespace GhostCompiler\LaravelAuth\OTP\Transport;

use GhostCompiler\LaravelAuth\Models\OtpChallenge;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

class CustomSmsOtpTransport extends BaseOtpTransport
{
    public function sendCode(Authenticatable $user, OtpChallenge $challenge, string $message, array $context = []): void
    {
        throw new RuntimeException('The custom SMS OTP transport is not implemented. Publish the stub and update laravel-auth.php to use your transport class.');
    }
}
