<?php

namespace GhostCompiler\LaravelAuth\Contracts;

use GhostCompiler\LaravelAuth\Models\OtpChallenge;
use Illuminate\Contracts\Auth\Authenticatable;

interface OtpTransport
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function sendCode(Authenticatable $user, OtpChallenge $challenge, string $message, array $context = []): void;

    /**
     * @param  array<string, mixed>  $context
     */
    public function verifyCode(Authenticatable $user, OtpChallenge $challenge, string $code, array $context = []): bool;
}
