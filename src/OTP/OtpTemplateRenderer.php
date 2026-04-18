<?php

namespace GhostCompiler\LaravelAuth\OTP;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\View;

class OtpTemplateRenderer
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function render(Authenticatable $user, string $channel, string $code, array $context = []): string
    {
        $view = (string) config("laravel-auth.otp_channels.{$channel}.view");
        $ttlSeconds = (int) config('laravel-auth.otp_channels.ttl_seconds', 300);

        return trim(View::make($view, array_replace($context, [
            'user' => $user,
            'channel' => $channel,
            'code' => $code,
            'expiresInMinutes' => max((int) ceil($ttlSeconds / 60), 1),
        ]))->render());
    }
}
