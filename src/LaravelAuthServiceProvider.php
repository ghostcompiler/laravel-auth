<?php

namespace GhostCompiler\LaravelAuth;

use GhostCompiler\LaravelAuth\Console\InstallCommand;
use GhostCompiler\LaravelAuth\Console\PublishOtpAssetsCommand;
use GhostCompiler\LaravelAuth\Contracts\LaravelAuthManager as LaravelAuthManagerContract;
use GhostCompiler\LaravelAuth\Enums\AuthState;
use GhostCompiler\LaravelAuth\Http\Middleware\RequireTwoFactor;
use GhostCompiler\LaravelAuth\Http\Middleware\ThrottleSensitiveAuth;
use GhostCompiler\LaravelAuth\OTP\OtpChannelManager;
use GhostCompiler\LaravelAuth\OTP\OtpTemplateRenderer;
use GhostCompiler\LaravelAuth\Services\SocialProviderResolver;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LaravelAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-auth.php', 'laravel-auth');
        $this->applyConfiguredPreset();

        $this->app->singleton(OtpTemplateRenderer::class, OtpTemplateRenderer::class);
        $this->app->singleton(OtpChannelManager::class, OtpChannelManager::class);
        $this->app->singleton(SocialProviderResolver::class, SocialProviderResolver::class);
        $this->app->singleton(LaravelAuthManagerContract::class, LaravelAuthManager::class);
        $this->app->alias(LaravelAuthManagerContract::class, 'laravel-auth');
    }

    public function boot(): void
    {
        $this->mergeSocialiteProviderConfig();
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-auth');

        $this->app['router']->aliasMiddleware('2fa', RequireTwoFactor::class);
        $this->app['router']->aliasMiddleware('laravel-auth.2fa', RequireTwoFactor::class);
        $this->app['router']->aliasMiddleware('laravel-auth.enforce', RequireTwoFactor::class);
        $this->app['router']->aliasMiddleware('laravel-auth.throttle', ThrottleSensitiveAuth::class);

        if ((bool) config('laravel-auth.enforce_2fa', true)) {
            foreach ((array) config('laravel-auth.enforce_middleware_groups', ['web']) as $group) {
                if (is_string($group) && $group !== '') {
                    $this->app['router']->pushMiddlewareToGroup($group, RequireTwoFactor::class);
                }
            }
        }

        Event::listen(Login::class, function (Login $event): void {
            if (app()->bound(LaravelAuthManagerContract::class)) {
                app(LaravelAuthManagerContract::class)->clearThrottle('otp', $event->user);
                app(LaravelAuthManagerContract::class)->clearThrottle('passkey', $event->user);
            }

            if (function_exists('session')) {
                session()->regenerate();
                session()->forget('laravel_auth.used_proofs');
                session()->forget('laravel_auth.verified.' . $event->user->getAuthIdentifier());
                session()->put('laravel_auth.state', AuthState::PasswordVerified->value);
            }

            if (app()->bound(LaravelAuthManagerContract::class)) {
                app(LaravelAuthManagerContract::class)->state($event->user);
            }
        });

        Event::listen(Logout::class, function (): void {
            if (function_exists('session')) {
                session()->forget('laravel_auth.used_proofs');
                session()->forget('laravel_auth.state');
                session()->put('laravel_auth.state', AuthState::Guest->value);
            }
        });

        $this->publishes([
            __DIR__ . '/../config/laravel-auth.php' => config_path('laravel-auth.php'),
        ], 'laravel-auth-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'laravel-auth-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views/' => resource_path('views/vendor/laravel-auth'),
        ], 'laravel-auth-views');

        $this->publishes([
            __DIR__ . '/../stubs/App/LaravelAuth/SmsOtpTransport.php' => app_path('LaravelAuth/SmsOtpTransport.php'),
            __DIR__ . '/../stubs/App/LaravelAuth/WhatsAppOtpTransport.php' => app_path('LaravelAuth/WhatsAppOtpTransport.php'),
        ], 'laravel-auth-stubs');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                PublishOtpAssetsCommand::class,
            ]);
        }
    }

    private function applyConfiguredPreset(): void
    {
        $preset = config('laravel-auth.preset');

        if (!is_string($preset) || $preset === '') {
            return;
        }

        $settings = (array) config("laravel-auth.presets.{$preset}", []);

        if ($settings === []) {
            return;
        }

        config([
            'laravel-auth' => array_replace_recursive(config('laravel-auth', []), $settings),
        ]);
    }

    private function mergeSocialiteProviderConfig(): void
    {
        foreach ((array) config('laravel-auth.social.providers', []) as $provider => $settings) {
            if (!is_array($settings)) {
                continue;
            }

            $existing = (array) config("services.{$provider}", []);

            config([
                "services.{$provider}" => array_replace($settings, $existing),
            ]);
        }
    }
}
