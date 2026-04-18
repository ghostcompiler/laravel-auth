<?php

namespace GhostCompiler\LaravelAuth\Contracts;

use GhostCompiler\LaravelAuth\Enums\AuthState;
use GhostCompiler\LaravelAuth\Support\VerifiedFactor;
use Illuminate\Contracts\Auth\Authenticatable;
use Symfony\Component\HttpFoundation\RedirectResponse;

interface LaravelAuthManager
{
    public function enable2FA(Authenticatable $user): array;

    /**
     * @return array{recovery_codes: array<int, string>}
     */
    public function confirmTwoFactorSetup(Authenticatable $user, string $code): array;

    public function disable2FA(Authenticatable $user): void;

    public function verifyOTP(Authenticatable $user, string $code): ?VerifiedFactor;

    public function generateRecoveryCodes(Authenticatable $user): array;

    public function registerPasskey(Authenticatable $user, ?string $name = null): array;

    public function finishPasskeyRegistration(Authenticatable $user, array $payload, ?string $name = null): array;

    public function requestPasskeyAssertion(Authenticatable $user): array;

    public function verifyPasskeyAssertion(Authenticatable $user, array $payload): ?VerifiedFactor;

    public function attemptOtp(Authenticatable $user, string $code, bool $rememberDevice = false, ?string $deviceName = null): bool;

    public function attemptPasskey(Authenticatable $user, array $payload, bool $rememberDevice = false, ?string $deviceName = null): bool;

    /**
     * @param  array<string, mixed>  $context
     * @return array{channel:string,destination:string,expires_at:?string}
     */
    public function sendEmailOtp(Authenticatable $user, ?string $email = null, array $context = []): array;

    /**
     * @param  array<string, mixed>  $context
     */
    public function verifyEmailOtp(Authenticatable $user, string $code, ?string $email = null, array $context = []): ?VerifiedFactor;

    /**
     * @param  array<string, mixed>  $context
     */
    public function attemptEmailOtp(Authenticatable $user, string $code, ?string $email = null, array $context = []): bool;

    /**
     * @param  array<string, mixed>  $context
     * @return array{channel:string,destination:string,expires_at:?string}
     */
    public function sendSmsOtp(Authenticatable $user, string $phoneNumber, array $context = []): array;

    /**
     * @param  array<string, mixed>  $context
     */
    public function verifySmsOtp(Authenticatable $user, string $phoneNumber, string $code, array $context = []): ?VerifiedFactor;

    /**
     * @param  array<string, mixed>  $context
     */
    public function attemptSmsOtp(Authenticatable $user, string $phoneNumber, string $code, array $context = []): bool;

    /**
     * @param  array<string, mixed>  $context
     * @return array{channel:string,destination:string,expires_at:?string}
     */
    public function sendWhatsAppOtp(Authenticatable $user, string $phoneNumber, array $context = []): array;

    /**
     * @param  array<string, mixed>  $context
     */
    public function verifyWhatsAppOtp(Authenticatable $user, string $phoneNumber, string $code, array $context = []): ?VerifiedFactor;

    /**
     * @param  array<string, mixed>  $context
     */
    public function attemptWhatsAppOtp(Authenticatable $user, string $phoneNumber, string $code, array $context = []): bool;

    /**
     * @param  array<string, array<string, mixed>>  $runtimeProviders
     */
    public function socialProviders(array $runtimeProviders = []): array;

    /**
     * @param  array<string, mixed>  $runtimeConfig
     */
    public function redirectToSocialProvider(string $provider, array $scopes = [], array $with = [], ?bool $stateless = null, array $runtimeConfig = []): RedirectResponse;

    /**
     * @param  array<string, mixed>  $runtimeConfig
     */
    public function resolveSocialUser(string $provider, ?bool $stateless = null, array $runtimeConfig = []): array;

    public function syncSocialAccount(Authenticatable $user, string $provider, array $profile): array;

    public function findUserBySocialAccount(string $provider, string|array $socialIdentity): ?Authenticatable;

    public function linkedSocialAccounts(Authenticatable $user): array;

    public function unlinkSocialAccount(Authenticatable $user, string $provider, ?string $providerUserId = null): int;

    public function state(?Authenticatable $user = null): AuthState;

    public function isVerified(?Authenticatable $user = null): bool;

    public function isFullyAuthenticated(?Authenticatable $user = null): bool;

    public function isPending2FA(?Authenticatable $user = null): bool;

    public function enforce(?Authenticatable $user = null): void;

    public function throttle(string $bucket, ?Authenticatable $user = null): void;

    public function tooManyAttempts(string $bucket, ?Authenticatable $user = null): bool;

    public function clearThrottle(string $bucket, ?Authenticatable $user = null): void;

    public function preset(string $name): array;

    public function requiresTwoFactor(Authenticatable $user): bool;
}
