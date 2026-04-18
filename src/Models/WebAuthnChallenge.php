<?php

namespace GhostCompiler\LaravelAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WebAuthnChallenge extends Model
{
    protected $table = 'laravel_auth_webauthn_challenges';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
