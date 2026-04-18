<?php

namespace GhostCompiler\LaravelAuth\Services;

use GhostCompiler\LaravelAuth\Models\SocialAccount;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SocialAuthService
{
    public function __construct(protected SocialProviderResolver $providers)
    {
    }

    /**
     * @param  array<string, array<string, mixed>>  $runtimeProviders
     * @return array<int, array<string, mixed>>
     */
    public function configuredProviders(array $runtimeProviders = []): array
    {
        return $this->providers->configuredProviders($runtimeProviders);
    }

    /**
     * @param  array<string, mixed>  $runtimeConfig
     */
    public function redirect(string $provider, array $scopes = [], array $with = [], ?bool $stateless = null, array $runtimeConfig = []): RedirectResponse
    {
        $driver = $this->providers->driver($provider, $runtimeConfig, $stateless);
        $settings = $this->providers->effectiveSettings($provider, $runtimeConfig);
        $resolvedScopes = $scopes !== [] ? $scopes : array_values($settings['scopes'] ?? []);
        $resolvedWith = array_replace($settings['with'] ?? [], $with);

        if ($resolvedScopes !== []) {
            $driver = $driver->scopes($resolvedScopes);
        }

        if ($resolvedWith !== []) {
            $driver = $driver->with($resolvedWith);
        }

        return $driver->redirect();
    }

    /**
     * @param  array<string, mixed>  $runtimeConfig
     */
    public function resolveUser(string $provider, ?bool $stateless = null, array $runtimeConfig = []): array
    {
        return $this->normalizeProfile(
            $provider,
            $this->providers->driver($provider, $runtimeConfig, $stateless)->user()
        );
    }

    public function syncAccount(Authenticatable $user, string $provider, array|SocialiteUserContract $profile): SocialAccount
    {
        $normalized = $this->normalizeProfile($provider, $profile);
        $providerUserId = $normalized['provider_user_id'];

        if ($providerUserId === '') {
            throw new RuntimeException('The social provider did not return a valid account identifier.');
        }

        $conflict = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($conflict && !$this->belongsToUser($conflict, $user)) {
            throw new RuntimeException('This social account is already linked to another user.');
        }

        $account = SocialAccount::query()
            ->whereMorphedTo('authenticatable', $user)
            ->where('provider', $provider)
            ->first() ?? new SocialAccount();

        $account->forceFill([
            'authenticatable_type' => $user instanceof Model ? $user->getMorphClass() : $user::class,
            'authenticatable_id' => $user->getAuthIdentifier(),
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'provider_nickname' => $normalized['nickname'] ?: null,
            'provider_name' => $normalized['name'] ?: null,
            'provider_email' => $normalized['email'] ?: null,
            'provider_avatar' => $normalized['avatar'] ?: null,
            'access_token' => $this->encrypt($normalized['token']),
            'refresh_token' => $this->encrypt($normalized['refresh_token']),
            'token_expires_at' => $normalized['expires_at'],
            'approved_scopes' => $normalized['approved_scopes'],
            'profile' => [
                'raw' => $normalized['raw'],
                'access_token_response_body' => $normalized['access_token_response_body'],
            ],
            'last_used_at' => now(),
        ])->save();

        return $account->fresh() ?? $account;
    }

    public function findUserByAccount(string $provider, string|array $socialIdentity): ?Authenticatable
    {
        $providerUserId = is_array($socialIdentity)
            ? (string) ($socialIdentity['provider_user_id'] ?? $socialIdentity['id'] ?? '')
            : (string) $socialIdentity;

        if ($providerUserId === '') {
            return null;
        }

        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if (!$account) {
            return null;
        }

        $account->forceFill([
            'last_used_at' => now(),
        ])->save();

        return $account->authenticatable;
    }

    public function linkedAccounts(Authenticatable $user): array
    {
        return SocialAccount::query()
            ->whereMorphedTo('authenticatable', $user)
            ->orderBy('provider')
            ->get()
            ->map(fn (SocialAccount $account): array => $this->accountArray($account))
            ->values()
            ->all();
    }

    public function unlinkAccount(Authenticatable $user, string $provider, ?string $providerUserId = null): int
    {
        $query = SocialAccount::query()
            ->whereMorphedTo('authenticatable', $user)
            ->where('provider', $provider);

        if ($providerUserId !== null) {
            $query->where('provider_user_id', $providerUserId);
        }

        return $query->delete();
    }

    public function normalizeProfile(string $provider, array|SocialiteUserContract $profile): array
    {
        if ($profile instanceof SocialiteUserContract) {
            return [
                'provider' => $provider,
                'provider_user_id' => (string) ($profile->getId() ?? ''),
                'nickname' => (string) ($profile->getNickname() ?? ''),
                'name' => (string) ($profile->getName() ?? ''),
                'email' => (string) ($profile->getEmail() ?? ''),
                'avatar' => (string) ($profile->getAvatar() ?? ''),
                'token' => $profile->token ?? null,
                'refresh_token' => $profile->refreshToken ?? null,
                'expires_in' => $profile->expiresIn ?? null,
                'expires_at' => isset($profile->expiresIn) ? now()->addSeconds((int) $profile->expiresIn) : null,
                'approved_scopes' => array_values($profile->approvedScopes ?? []),
                'raw' => (array) ($profile->user ?? []),
                'access_token_response_body' => (array) ($profile->accessTokenResponseBody ?? []),
            ];
        }

        $expiresAt = $profile['expires_at'] ?? null;
        if (is_numeric($profile['expires_in'] ?? null) && $expiresAt === null) {
            $expiresAt = now()->addSeconds((int) $profile['expires_in']);
        }

        return [
            'provider' => $provider,
            'provider_user_id' => (string) ($profile['provider_user_id'] ?? $profile['id'] ?? ''),
            'nickname' => (string) ($profile['nickname'] ?? ''),
            'name' => (string) ($profile['name'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'avatar' => (string) ($profile['avatar'] ?? ''),
            'token' => $profile['token'] ?? null,
            'refresh_token' => $profile['refresh_token'] ?? null,
            'expires_in' => $profile['expires_in'] ?? null,
            'expires_at' => $expiresAt,
            'approved_scopes' => array_values($profile['approved_scopes'] ?? []),
            'raw' => (array) ($profile['raw'] ?? []),
            'access_token_response_body' => (array) ($profile['access_token_response_body'] ?? []),
        ];
    }

    private function belongsToUser(SocialAccount $account, Authenticatable $user): bool
    {
        $related = $account->authenticatable;

        if ($related instanceof Model && $user instanceof Model) {
            return $related->is($user);
        }

        return $account->authenticatable_type === ($user instanceof Model ? $user->getMorphClass() : $user::class)
            && (string) $account->authenticatable_id === (string) $user->getAuthIdentifier();
    }

    private function encrypt(?string $value): ?string
    {
        return $value !== null && $value !== '' ? Crypt::encryptString($value) : null;
    }

    private function accountArray(SocialAccount $account): array
    {
        return [
            'provider' => $account->provider,
            'provider_user_id' => $account->provider_user_id,
            'nickname' => $account->provider_nickname,
            'name' => $account->provider_name,
            'email' => $account->provider_email,
            'avatar' => $account->provider_avatar,
            'approved_scopes' => $account->approved_scopes ?? [],
            'last_used_at' => optional($account->last_used_at)->toISOString(),
            'created_at' => optional($account->created_at)->toISOString(),
            'updated_at' => optional($account->updated_at)->toISOString(),
        ];
    }
}
