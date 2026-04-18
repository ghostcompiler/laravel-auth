<?php

namespace GhostCompiler\LaravelAuth\Http\Middleware;

use Closure;
use GhostCompiler\LaravelAuth\Contracts\LaravelAuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ThrottleSensitiveAuth
{
    public function __construct(protected LaravelAuthManager $secureAuth)
    {
    }

    public function handle(Request $request, Closure $next, string $bucket = 'otp'): Response
    {
        try {
            if ($this->secureAuth->tooManyAttempts($bucket, $request->user())) {
                throw new RuntimeException('Too many attempts');
            }
        } catch (RuntimeException $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage(),
            ], 429);
        }

        return $next($request);
    }
}
