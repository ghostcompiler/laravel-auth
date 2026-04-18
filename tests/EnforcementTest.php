<?php

namespace GhostCompiler\LaravelAuth\Tests;

use GhostCompiler\LaravelAuth\Contracts\LaravelAuthManager;
use GhostCompiler\LaravelAuth\Http\Middleware\RequireTwoFactor;
use GhostCompiler\LaravelAuth\Enums\AuthState;
use GhostCompiler\LaravelAuth\Tests\Fixtures\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class EnforcementTest extends TestCase
{
    public function test_global_enforcement_is_registered_and_blocks_pending_two_factor_sessions(): void
    {
        $user = User::query()->create([
            'email' => 'enforce@example.test',
            'password' => 'secret',
            'laravel_auth_totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'laravel_auth_two_factor_enabled' => true,
        ]);

        $middlewareGroups = $this->app['router']->getMiddlewareGroups();
        self::assertContains(RequireTwoFactor::class, $middlewareGroups['web']);

        $this->actingAs($user);

        $request = Request::create('/protected', 'GET');
        $request->setLaravelSession($this->app['session.store']);

        $middleware = $this->app->make(RequireTwoFactor::class);

        $blocked = $middleware->handle($request, static fn () => response()->json(['ok' => true]));
        self::assertSame(403, $blocked->getStatusCode());
        self::assertSame(AuthState::TwoFactorPending->value, session()->get('laravel_auth.state'));

        session()->put('laravel_auth.verified.' . $user->getAuthIdentifier(), true);
        session()->put('laravel_auth.state', AuthState::FullyAuthenticated->value);

        $allowed = $middleware->handle($request, static fn () => response()->json(['ok' => true]));
        self::assertSame(200, $allowed->getStatusCode());
    }

    public function test_login_event_resets_prior_verified_session_state_and_recomputes_two_factor_status(): void
    {
        $user = User::query()->create([
            'email' => 'login-reset@example.test',
            'password' => 'secret',
            'laravel_auth_totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'laravel_auth_two_factor_enabled' => true,
        ]);

        session()->put('laravel_auth.verified.' . $user->getAuthIdentifier(), true);
        session()->put('laravel_auth.state', AuthState::FullyAuthenticated->value);

        event(new Login('web', $user, false));

        self::assertFalse(session()->has('laravel_auth.verified.' . $user->getAuthIdentifier()));
        self::assertSame(AuthState::TwoFactorPending, $this->app->make(LaravelAuthManager::class)->state($user));
    }
}
