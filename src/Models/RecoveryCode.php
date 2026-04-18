<?php

namespace GhostCompiler\LaravelAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RecoveryCode extends Model
{
    protected $table = 'laravel_auth_recovery_codes';

    protected $guarded = [];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
