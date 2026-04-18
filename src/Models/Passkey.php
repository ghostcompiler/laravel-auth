<?php

namespace GhostCompiler\LaravelAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Passkey extends Model
{
    protected $table = 'laravel_auth_passkeys';

    protected $guarded = [];

    protected $casts = [
        'signature_counter' => 'integer',
        'last_used_at' => 'datetime',
        'transports' => 'array',
    ];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
