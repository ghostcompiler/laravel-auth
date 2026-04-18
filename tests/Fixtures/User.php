<?php

namespace GhostCompiler\LaravelAuth\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'laravel_auth_two_factor_enabled' => 'boolean',
        'laravel_auth_confirmed_at' => 'datetime',
    ];
}
