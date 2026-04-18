<?php

namespace GhostCompiler\LaravelAuth\Facades;

use GhostCompiler\LaravelAuth\Enums\AuthState;
use GhostCompiler\LaravelAuth\Support\VerifiedFactor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @method static array enable2FA(Authenticatable $user)
 * @method static array confirmTwoFactorSetup(Authenticatable $user, string $code)
 * @method static void disable2FA(Authenticatable $user)
 * @method static ?VerifiedFactor verifyOTP(Authenticatable $user, string $code)
 * @method static array generateRecoveryCodes(Authenticatable $user)
 * @method static array registerPasskey(Authenticatable $user, ?string $name = null)
 * @method static array finishPasskeyRegistration(Authenticatable $user, array $payload, ?string $name = null)
 * @method static array requestPasskeyAssertion(Authenticatable $user)
 * @method static ?VerifiedFactor verifyPasskeyAssertion(Authenticatable $user, array $payload)
 * @method static bool attemptOtp(Authenticatable $user, string $code, bool $rememberDevice = false, ?string $deviceName = null)
 * @method static bool attemptPasskey(Authenticatable $user, array $payload, bool $rememberDevice = false, ?string $deviceName = null)
 * @method static array sendEmailOtp(Authenticatable $user, ?string $email = null, array $context = [])
 * @method static ?VerifiedFactor verifyEmailOtp(Authenticatable $user, string $code, ?string $email = null, array $context = [])
 * @method static bool attemptEmailOtp(Authenticatable $user, string $code, ?string $email = null, array $context = [])
 * @method static array sendSmsOtp(Authenticatable $user, string $phoneNumber, array $context = [])
 * @method static ?VerifiedFactor verifySmsOtp(Authenticatable $user, string $phoneNumber, string $code, array $context = [])
 * @method static bool attemptSmsOtp(Authenticatable $user, string $phoneNumber, string $code, array $context = [])
 * @method static array sendWhatsAppOtp(Authenticatable $user, string $phoneNumber, array $context = [])
 * @method static ?VerifiedFactor verifyWhatsAppOtp(Authenticatable $user, string $phoneNumber, string $code, array $context = [])
 * @method static bool attemptWhatsAppOtp(Authenticatable $user, string $phoneNumber, string $code, array $context = [])
 * @method static array socialProviders(array $runtimeProviders = [])
 * @method static RedirectResponse redirectToSocialProvider(string $provider, array $scopes = [], array $with = [], ?bool $stateless = null, array $runtimeConfig = [])
 * @method static array resolveSocialUser(string $provider, ?bool $stateless = null, array $runtimeConfig = [])
 * @method static array syncSocialAccount(Authenticatable $user, string $provider, array $profile)
 * @method static ?Authenticatable findUserBySocialAccount(string $provider, string|array $socialIdentity)
 * @method static array linkedSocialAccounts(Authenticatable $user)
 * @method static int unlinkSocialAccount(Authenticatable $user, string $provider, ?string $providerUserId = null)
 * @method static AuthState state(?Authenticatable $user = null)
 * @method static bool isVerified(?Authenticatable $user = null)
 * @method static bool isFullyAuthenticated(?Authenticatable $user = null)
 * @method static bool isPending2FA(?Authenticatable $user = null)
 * @method static void enforce(?Authenticatable $user = null)
 * @method static void throttle(string $bucket, ?Authenticatable $user = null)
 * @method static bool tooManyAttempts(string $bucket, ?Authenticatable $user = null)
 * @method static void clearThrottle(string $bucket, ?Authenticatable $user = null)
 * @method static array preset(string $name)
 * @method static bool requiresTwoFactor(Authenticatable $user)
 */
class LaravelAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \GhostCompiler\LaravelAuth\Contracts\LaravelAuthManager::class;
    }
}
