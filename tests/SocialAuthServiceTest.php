<?php

namespace GhostCompiler\LaravelAuth\Tests;

use GhostCompiler\LaravelAuth\Contracts\LaravelAuthManager;
use GhostCompiler\LaravelAuth\Services\SocialAuthService;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\GoogleProvider;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SocialAuthServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_static_config_driven_social_provider_listing_still_works(): void
    {
        Config::set('laravel-auth.social.providers.google.enabled', true);
        Config::set('laravel-auth.social.providers.google.client_id', 'static-google-client');
        Config::set('laravel-auth.social.providers.google.client_secret', 'static-google-secret');
        Config::set('laravel-auth.social.providers.google.redirect', 'https://app.example.test/auth/google/callback');

        $providers = $this->app->make(LaravelAuthManager::class)->socialProviders();

        self::assertCount(1, $providers);
        self::assertSame('google', $providers[0]['driver']);
        self::assertSame('config', $providers[0]['source']);
    }

    public function test_social_provider_listing_accepts_runtime_tenant_configs(): void
    {
        $providers = $this->app->make(LaravelAuthManager::class)->socialProviders([
            'google' => [
                'client_id' => 'tenant-google-client',
                'client_secret' => 'tenant-google-secret',
                'redirect' => 'https://tenant.example.test/auth/google/callback',
            ],
            'github' => [
                'client_id' => 'tenant-github-client',
                'client_secret' => 'tenant-github-secret',
                'redirect' => 'https://tenant.example.test/auth/github/callback',
            ],
        ]);

        self::assertCount(2, $providers);
        self::assertSame(['google', 'github'], array_column($providers, 'driver'));
        self::assertSame(['runtime', 'runtime'], array_column($providers, 'source'));
    }

    public function test_redirect_uses_static_socialite_driver_when_runtime_config_is_not_provided(): void
    {
        Config::set('laravel-auth.social.providers.google.enabled', true);
        Config::set('laravel-auth.social.providers.google.client_id', 'static-google-client');
        Config::set('laravel-auth.social.providers.google.client_secret', 'static-google-secret');
        Config::set('laravel-auth.social.providers.google.redirect', 'https://app.example.test/auth/google/callback');
        Config::set('laravel-auth.social.providers.google.scopes', []);
        Config::set('laravel-auth.social.providers.google.with', []);
        Config::set('services.google', [
            'client_id' => 'static-google-client',
            'client_secret' => 'static-google-secret',
            'redirect' => 'https://app.example.test/auth/google/callback',
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('redirect')
            ->once()
            ->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/auth'));

        $factory = Mockery::mock();
        $factory->shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($driver);
        $factory->shouldReceive('buildProvider')->never();
        Socialite::swap($factory);

        $response = $this->app->make(SocialAuthService::class)->redirect('google');

        self::assertSame('https://accounts.google.com/o/oauth2/auth', $response->getTargetUrl());
    }

    public function test_google_runtime_config_uses_socialite_build_provider(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('redirect')
            ->once()
            ->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/auth'));

        $factory = Mockery::mock();
        $factory->shouldReceive('buildProvider')
            ->once()
            ->with(GoogleProvider::class, Mockery::on(function (array $config): bool {
                return $config['client_id'] === 'tenant-google-client'
                    && $config['client_secret'] === 'tenant-google-secret'
                    && $config['redirect'] === 'https://tenant.example.test/auth/google/callback';
            }))
            ->andReturn($driver);
        $factory->shouldReceive('driver')->never();
        Socialite::swap($factory);

        $response = $this->app->make(LaravelAuthManager::class)->redirectToSocialProvider(
            'google',
            runtimeConfig: [
                'client_id' => 'tenant-google-client',
                'client_secret' => 'tenant-google-secret',
                'redirect' => 'https://tenant.example.test/auth/google/callback',
                'scopes' => [],
                'with' => [],
            ]
        );

        self::assertSame('https://accounts.google.com/o/oauth2/auth', $response->getTargetUrl());
    }

    public function test_github_runtime_config_can_resolve_callback_user(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn([
            'id' => 'github-user-1',
            'name' => 'Tenant User',
            'email' => 'tenant@example.test',
        ]);

        $factory = Mockery::mock();
        $factory->shouldReceive('buildProvider')
            ->once()
            ->with(GithubProvider::class, Mockery::on(function (array $config): bool {
                return $config['client_id'] === 'tenant-github-client'
                    && $config['client_secret'] === 'tenant-github-secret'
                    && $config['redirect'] === 'https://tenant.example.test/auth/github/callback';
            }))
            ->andReturn($driver);
        $factory->shouldReceive('driver')->never();
        Socialite::swap($factory);

        $profile = $this->app->make(LaravelAuthManager::class)->resolveSocialUser(
            'github',
            runtimeConfig: [
                'client_id' => 'tenant-github-client',
                'client_secret' => 'tenant-github-secret',
                'redirect' => 'https://tenant.example.test/auth/github/callback',
                'stateless' => true,
            ]
        );

        self::assertSame('github-user-1', $profile['provider_user_id']);
        self::assertSame('Tenant User', $profile['name']);
        self::assertSame('tenant@example.test', $profile['email']);
    }

    public function test_runtime_social_config_validation_error_is_clear_when_incomplete(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The runtime social config for [google] must include client_id and client_secret.');

        $this->app->make(SocialAuthService::class)->redirect('google', runtimeConfig: [
            'client_id' => 'tenant-google-client',
        ]);
    }
}
