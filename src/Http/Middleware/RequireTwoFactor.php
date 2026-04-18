<?php

namespace GhostCompiler\LaravelAuth\Http\Middleware;

use Closure;
use GhostCompiler\LaravelAuth\Contracts\LaravelAuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactor
{
    public function __construct(protected LaravelAuthManager $secureAuth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && !$this->secureAuth->isFullyAuthenticated($request->user() ?? auth()->user())) {
            $payload = ['message' => '2FA required'];
            if (config('laravel-auth.verification_url')) {
                $payload['verification_url'] = config('laravel-auth.verification_url');
            }

            return new JsonResponse($payload, 403);
        }

        return $next($request);
    }
}
