<?php

namespace GhostCompiler\LaravelAuth\Exceptions;

use RuntimeException;

class TwoFactorRequiredException extends RuntimeException
{
    public function __construct(string $message = 'Two-factor verification is required.')
    {
        parent::__construct($message);
    }
}
