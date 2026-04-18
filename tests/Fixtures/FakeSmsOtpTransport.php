<?php

namespace GhostCompiler\LaravelAuth\Tests\Fixtures;

use GhostCompiler\LaravelAuth\Contracts\OtpTransport;
use GhostCompiler\LaravelAuth\Models\OtpChallenge;
use Illuminate\Contracts\Auth\Authenticatable;

class FakeSmsOtpTransport implements OtpTransport
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public static array $sent = [];

    public function sendCode(Authenticatable $user, OtpChallenge $challenge, string $message, array $context = []): void
    {
        self::$sent[] = [
            'user_id' => $user->getAuthIdentifier(),
            'message' => $message,
            'destination' => $context['destination'] ?? $challenge->destination,
            'code' => $context['code'] ?? null,
        ];
    }

    public function verifyCode(Authenticatable $user, OtpChallenge $challenge, string $code, array $context = []): bool
    {
        return true;
    }
}
