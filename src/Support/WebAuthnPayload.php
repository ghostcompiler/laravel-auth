<?php

namespace GhostCompiler\LaravelAuth\Support;

class WebAuthnPayload
{
    public static function decode(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        return $decoded === false ? null : $decoded;
    }
}
