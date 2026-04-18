<?php

namespace GhostCompiler\LaravelAuth\Services;

use GhostCompiler\LaravelAuth\Models\WebAuthnChallenge;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ChallengeStore
{
    public function create(Authenticatable $user, string $type, string $payload, int $ttlSeconds): WebAuthnChallenge
    {
        return WebAuthnChallenge::query()->create([
            'authenticatable_type' => $user instanceof Model ? $user->getMorphClass() : $user::class,
            'authenticatable_id' => $user->getAuthIdentifier(),
            'type' => $type,
            'payload' => Crypt::encryptString(base64_encode($payload)),
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);
    }

    public function consume(Authenticatable $user, string $type, int|string|null $id): ?string
    {
        $challenge = WebAuthnChallenge::query()
            ->whereKey($id)
            ->whereMorphedTo('authenticatable', $user)
            ->where('type', $type)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$challenge) {
            return null;
        }

        $challenge->forceFill([
            'consumed_at' => now(),
        ])->save();

        $payload = base64_decode(Crypt::decryptString($challenge->payload), true);

        return $payload === false ? null : $payload;
    }
}
