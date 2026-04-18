<?php

namespace GhostCompiler\LaravelAuth\Enums;

enum AuthState: string
{
    case Guest = 'guest';
    case PasswordVerified = 'password_verified';
    case TwoFactorPending = 'two_factor_pending';
    case FullyAuthenticated = 'fully_authenticated';
}
