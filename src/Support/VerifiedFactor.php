<?php

namespace GhostCompiler\LaravelAuth\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class VerifiedFactor
{
    public function __construct(
        public readonly string $type,
        public readonly string $userId,
        public readonly int $verifiedAt
    ) {
    }

    public static function issue(Authenticatable $user, string $type): self
    {
        return new self(
            $type,
            (string) $user->getAuthIdentifier(),
            now()->timestamp
        );
    }

    public function assertMatches(Authenticatable $user): void
    {
        if ($this->userId !== (string) $user->getAuthIdentifier()) {
            throw new RuntimeException('Invalid verification proof.');
        }
    }
}
