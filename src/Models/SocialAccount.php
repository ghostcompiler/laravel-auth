<?php

namespace GhostCompiler\LaravelAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SocialAccount extends Model
{
    protected $table = 'laravel_auth_social_accounts';

    protected $guarded = [];

    protected $casts = [
        'approved_scopes' => 'array',
        'profile' => 'array',
        'token_expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
