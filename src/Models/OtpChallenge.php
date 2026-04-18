<?php

namespace GhostCompiler\LaravelAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OtpChallenge extends Model
{
    protected $table = 'laravel_auth_otp_challenges';

    protected $guarded = [];

    protected $casts = [
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
