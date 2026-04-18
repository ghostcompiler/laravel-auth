<?php

namespace GhostCompiler\LaravelAuth\Services;

use GhostCompiler\LaravelAuth\Models\RecoveryCode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RecoveryCodeService
{
    public function regenerate(Authenticatable $user, int $count): array
    {
        RecoveryCode::query()
            ->whereMorphedTo('authenticatable', $user)
            ->delete();

        $plainCodes = Collection::times($count, function (): string {
            return strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4));
        })->all();

        foreach ($plainCodes as $code) {
            RecoveryCode::query()->create([
                'authenticatable_type' => $user instanceof Model ? $user->getMorphClass() : $user::class,
                'authenticatable_id' => $user->getAuthIdentifier(),
                'code' => Hash::make($code),
            ]);
        }

        return $plainCodes;
    }

    public function consume(Authenticatable $user, string $code): bool
    {
        $records = RecoveryCode::query()
            ->whereMorphedTo('authenticatable', $user)
            ->whereNull('used_at')
            ->get();

        foreach ($records as $record) {
            if (!Hash::check($code, $record->code)) {
                continue;
            }

            $record->forceFill([
                'used_at' => now(),
            ])->save();

            return true;
        }

        return false;
    }
}
