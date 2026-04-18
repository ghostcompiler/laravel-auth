<?php

namespace GhostCompiler\LaravelAuth\Support;

use Illuminate\Contracts\Auth\Authenticatable;

class UserSecurity
{
    public static function enabled(Authenticatable $user): bool
    {
        return (bool) data_get($user, 'laravel_auth_two_factor_enabled');
    }

    public static function secret(Authenticatable $user): ?string
    {
        return data_get($user, 'laravel_auth_totp_secret');
    }
}
