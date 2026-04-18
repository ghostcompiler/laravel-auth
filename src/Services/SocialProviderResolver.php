<?php

namespace GhostCompiler\LaravelAuth\Services;

use Illuminate\Support\Arr;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\BitbucketProvider;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\GitlabProvider;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\LinkedInOpenIdProvider;
use Laravel\Socialite\Two\SlackOpenIdProvider;
use Laravel\Socialite\Two\SlackProvider;
use Laravel\Socialite\Two\XProvider;
use RuntimeException;

class SocialProviderResolver
{
    /**
     * @param  array<string, array<string, mixed>>  $runtimeProviders
     * @return array<int, array<string, mixed>>
     */
    public function configuredProviders(array $runtimeProviders = []): array
    {
        $providers = [];
        $configured = (array) config('laravel-auth.social.providers', []);
        $drivers = array_values(array_unique(array_merge(array_keys($configured), array_keys($runtimeProviders))));

        foreach ($drivers as $driver) {
            $runtimeConfig = $runtimeProviders[$driver] ?? [];
            $settings = $this->effectiveSettings($driver, $runtimeConfig);

            if ($runtimeConfig === [] && (!is_array($configured[$driver] ?? null) || !($configured[$driver]['enabled'] ?? false))) {
                continue;
            }

            if (blank($settings['client_id'] ?? null) || blank($settings['client_secret'] ?? null) || blank($settings['redirect'] ?? null)) {
                continue;
            }

            $providers[] = [
                'driver' => $driver,
                'label' => $settings['label'] ?? ucfirst(str_replace(['-', '_'], ' ', $driver)),
                'redirect' => $settings['redirect'],
                'scopes' => array_values($settings['scopes'] ?? []),
                'with' => $settings['with'] ?? [],
                'stateless' => (bool) ($settings['stateless'] ?? config('laravel-auth.social.default_stateless', false)),
                'source' => $runtimeConfig === [] ? 'config' : 'runtime',
            ];
        }

        return $providers;
    }

    /**
     * @param  array<string, mixed>  $runtimeConfig
     */
    public function driver(string $provider, array $runtimeConfig = [], ?bool $stateless = null): mixed
    {
        $settings = $this->effectiveSettings($provider, $runtimeConfig);
        $driver = $runtimeConfig === []
            ? $this->staticDriver($provider, $stateless, $settings)
            : $this->runtimeDriver($provider, $settings, $stateless);

        if ($stateless ?? (bool) ($settings['stateless'] ?? config('laravel-auth.social.default_stateless', false))) {
            $driver = $driver->stateless();
        }

        return $driver;
    }

    /**
     * @param  array<string, mixed>  $runtimeConfig
     * @return array<string, mixed>
     */
    public function effectiveSettings(string $provider, array $runtimeConfig = []): array
    {
        $settings = $this->providerSettings($provider);

        if ($runtimeConfig === []) {
            return $settings;
        }

        return array_replace($settings, array_filter($runtimeConfig, static fn ($value): bool => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function staticDriver(string $provider, ?bool $stateless, array $settings): mixed
    {
        $this->ensureStaticProviderConfigured($provider);

        return Socialite::driver($provider);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function runtimeDriver(string $provider, array $settings, ?bool $stateless): mixed
    {
        $providerClass = $this->providerClass($provider);

        if ($providerClass === null) {
            throw new RuntimeException("Runtime social config is not supported for [{$provider}] yet.");
        }

        if (blank($settings['client_id'] ?? null) || blank($settings['client_secret'] ?? null)) {
            throw new RuntimeException("The runtime social config for [{$provider}] must include client_id and client_secret.");
        }

        if (blank($settings['redirect'] ?? null)) {
            throw new RuntimeException("The runtime social config for [{$provider}] must include redirect or provide one via laravel-auth.social.providers.{$provider}.redirect.");
        }

        $driver = Socialite::buildProvider($providerClass, [
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'redirect' => $settings['redirect'],
            'scopes' => array_values($settings['scopes'] ?? []),
            'guzzle' => Arr::get($settings, 'guzzle', []),
        ]);

        if ($provider === 'gitlab' && method_exists($driver, 'setHost') && filled($settings['host'] ?? null)) {
            $driver = $driver->setHost($settings['host']);
        }

        return $driver;
    }

    /**
     * @return array<string, mixed>
     */
    private function providerSettings(string $provider): array
    {
        return (array) config("laravel-auth.social.providers.{$provider}", []);
    }

    private function ensureStaticProviderConfigured(string $provider): void
    {
        $settings = $this->providerSettings($provider);
        $serviceConfig = (array) config("services.{$provider}", []);

        if ($settings !== [] && !($settings['enabled'] ?? false)) {
            throw new RuntimeException("The [{$provider}] social provider is disabled in laravel-auth.php.");
        }

        if ($settings === [] && $serviceConfig === []) {
            throw new RuntimeException("The [{$provider}] social provider is not configured.");
        }

        if (blank($serviceConfig['client_id'] ?? null) || blank($serviceConfig['client_secret'] ?? null) || blank($serviceConfig['redirect'] ?? null)) {
            throw new RuntimeException("The [{$provider}] social provider is missing client_id, client_secret, or redirect configuration.");
        }
    }

    private function providerClass(string $provider): ?string
    {
        return match ($provider) {
            'google' => GoogleProvider::class,
            'github' => GithubProvider::class,
            'facebook' => FacebookProvider::class,
            'gitlab' => GitlabProvider::class,
            'bitbucket' => BitbucketProvider::class,
            'x' => XProvider::class,
            'linkedin-openid' => LinkedInOpenIdProvider::class,
            'slack' => SlackProvider::class,
            'slack-openid' => SlackOpenIdProvider::class,
            default => null,
        };
    }
}
