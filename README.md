<p align="center">
  <img src="assets/logo/logo.png" alt="Laravel Auth Logo" width="180">
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-10%20to%2013-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Built%20With-Laravel%20Auth-0F172A?style=for-the-badge" alt="Laravel Auth">
</p>

# LaravelAuth

Headless Laravel authentication hardening with:

- TOTP 2FA
- recovery codes
- passkeys via WebAuthn
- email, SMS, and WhatsApp OTP
- trusted devices
- Socialite-based social account linking
- runtime tenant OAuth credentials for social login

This package does not replace your login system. It adds security layers on top of your existing auth flow.

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- database access for package tables
- HTTPS for browser passkey flows
- `laravel/socialite` support for social login helpers

## Installation

```bash
composer require ghostcompiler/laravel-auth
php artisan ghost:laravel-auth
php artisan migrate
```

Force republishing if you want to overwrite previously published files:

```bash
php artisan ghost:laravel-auth --force
```

What `ghost:laravel-auth` publishes:

- `config/laravel-auth.php`
- one package migration file
- package OTP views to `resources/views/vendor/laravel-auth`
- SMS and WhatsApp transport stubs to `app/LaravelAuth`

Publish only OTP assets later if needed:

```bash
php artisan laravel-auth:otp:publish
```

## Local Package Development

To test this package from another Laravel app through a local path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "/Users/ghostcompiler/Desktop/GhostCompiler/laravel-auth",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "ghostcompiler/laravel-auth": "*"
  }
}
```

Then in the app:

```bash
composer require ghostcompiler/laravel-auth
php artisan ghost:laravel-auth
php artisan migrate
php artisan optimize:clear
```

If the app does not pick up local changes automatically:

```bash
composer update ghostcompiler/laravel-auth
composer dump-autoload
php artisan optimize:clear
```

## What The Package Adds

Middleware aliases:

- `2fa`
- `laravel-auth.2fa`
- `laravel-auth.enforce`
- `laravel-auth.throttle`

Published config:

- [config/laravel-auth.php](/Users/ghostcompiler/Desktop/GhostCompiler/laravel-auth/config/laravel-auth.php:1)

Main facade contract:

- `src/Contracts/LaravelAuthManager.php`

Single package migration:

- [database/migrations/2026_04_12_000001_create_laravel_auth_schema.php](/Users/ghostcompiler/Desktop/GhostCompiler/laravel-auth/database/migrations/2026_04_12_000001_create_laravel_auth_schema.php:1)

Database objects created:

- user table columns:
  - `laravel_auth_totp_secret`
  - `laravel_auth_two_factor_enabled`
  - `laravel_auth_confirmed_at`
- `laravel_auth_recovery_codes`
- `laravel_auth_trusted_devices`
- `laravel_auth_passkeys`
- `laravel_auth_webauthn_challenges`
- `laravel_auth_social_accounts`
- `laravel_auth_otp_challenges`

## Current Defaults

From the package config:

- `enforce_2fa` is `true`
- 2FA enforcement is pushed into the `web` middleware group
- OTP TTL is 300 seconds
- OTP max attempts is 5
- rate limit decay is 60 seconds
- TOTP uses 6 digits, 30-second period, 1-step window
- trusted devices are bound to user agent by default
- trusted-device IP binding is off by default
- WhatsApp OTP is disabled by default
- social runtime stateless mode defaults to `false`

## Recommended Base Route Protection

```php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'laravel-auth.2fa'])->group(function () {
    Route::get('/billing', fn () => 'protected');
    Route::get('/settings/security', fn () => 'security');
});
```

Add throttling to sensitive verification endpoints:

```php
Route::post('/security/otp/verify', [SecurityController::class, 'verifyOtp'])
    ->middleware(['auth', 'laravel-auth.throttle:otp']);

Route::post('/security/passkey/verify', [SecurityController::class, 'verifyPasskey'])
    ->middleware(['auth', 'laravel-auth.throttle:passkey']);
```

## TOTP 2FA Setup

Enable 2FA for a user:

```php
$setup = LaravelAuth::enable2FA(auth()->user());

return response()->json([
    'secret' => $setup['secret'],
    'otpauth_uri' => $setup['otpauth_uri'],
]);
```

Confirm setup:

```php
$result = LaravelAuth::confirmTwoFactorSetup(
    auth()->user(),
    $request->string('code')
);

return response()->json([
    'recovery_codes' => $result['recovery_codes'],
]);
```

Disable 2FA:

```php
LaravelAuth::disable2FA(auth()->user());
```

## Demo 2FA Controller

```php
<?php

namespace App\Http\Controllers\Security;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaravelAuth;

class TwoFactorController extends Controller
{
    public function begin(Request $request)
    {
        $setup = LaravelAuth::enable2FA($request->user());

        return response()->json($setup);
    }

    public function confirm(Request $request)
    {
        $result = LaravelAuth::confirmTwoFactorSetup(
            $request->user(),
            $request->string('code')
        );

        return response()->json($result);
    }

    public function disable(Request $request)
    {
        LaravelAuth::disable2FA($request->user());

        return response()->json(['status' => 'disabled']);
    }
}
```

## Verifying 2FA During Login

TOTP or recovery code:

```php
$ok = LaravelAuth::attemptOtp(
    $user,
    $request->string('code'),
    rememberDevice: (bool) $request->boolean('remember_device'),
    deviceName: $request->string('device_name')->toString() ?: null,
);

abort_unless($ok, 422, 'Invalid code.');
```

You can also verify without fully marking the session:

```php
$proof = LaravelAuth::verifyOTP($user, $request->string('code'));
```

## Recovery Codes

Generate or regenerate recovery codes:

```php
$codes = LaravelAuth::generateRecoveryCodes(auth()->user());
```

Recovery codes are consumed automatically when passed through `verifyOTP()` or `attemptOtp()`.

## Passkeys / WebAuthn

Begin passkey registration:

```php
$options = LaravelAuth::registerPasskey(auth()->user(), 'MacBook Pro');
```

Finish registration:

```php
$passkey = LaravelAuth::finishPasskeyRegistration(
    auth()->user(),
    $request->all(),
    'MacBook Pro'
);
```

Begin assertion:

```php
$options = LaravelAuth::requestPasskeyAssertion(auth()->user());
```

Verify assertion only:

```php
$proof = LaravelAuth::verifyPasskeyAssertion(auth()->user(), $request->all());
```

Attempt assertion and fully authenticate:

```php
$ok = LaravelAuth::attemptPasskey(
    auth()->user(),
    $request->all(),
    rememberDevice: true,
    deviceName: 'Office Laptop'
);
```

## OTP Channels

### Email OTP

Send:

```php
LaravelAuth::sendEmailOtp(auth()->user());
```

Verify only:

```php
$proof = LaravelAuth::verifyEmailOtp(auth()->user(), $request->string('code'));
```

Attempt and mark session:

```php
$ok = LaravelAuth::attemptEmailOtp(auth()->user(), $request->string('code'));
```

### SMS OTP

Send:

```php
LaravelAuth::sendSmsOtp(auth()->user(), '+15550001111');
```

Verify:

```php
$proof = LaravelAuth::verifySmsOtp(
    auth()->user(),
    '+15550001111',
    $request->string('code')
);
```

Attempt:

```php
$ok = LaravelAuth::attemptSmsOtp(
    auth()->user(),
    '+15550001111',
    $request->string('code')
);
```

### WhatsApp OTP

Enable it first in `config/laravel-auth.php` or your published config.

Send:

```php
LaravelAuth::sendWhatsAppOtp(auth()->user(), '+15550002222');
```

Verify:

```php
$proof = LaravelAuth::verifyWhatsAppOtp(
    auth()->user(),
    '+15550002222',
    $request->string('code')
);
```

Attempt:

```php
$ok = LaravelAuth::attemptWhatsAppOtp(
    auth()->user(),
    '+15550002222',
    $request->string('code')
);
```

## Demo OTP Controller

```php
<?php

namespace App\Http\Controllers\Security;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaravelAuth;

class OtpController extends Controller
{
    public function sendEmail(Request $request)
    {
        return response()->json(
            LaravelAuth::sendEmailOtp($request->user())
        );
    }

    public function verifyEmail(Request $request)
    {
        $ok = LaravelAuth::attemptEmailOtp(
            $request->user(),
            $request->string('code')
        );

        abort_unless($ok, 422, 'Invalid email OTP.');

        return response()->json(['status' => 'verified']);
    }

    public function sendSms(Request $request)
    {
        return response()->json(
            LaravelAuth::sendSmsOtp($request->user(), $request->string('phone'))
        );
    }

    public function verifySms(Request $request)
    {
        $ok = LaravelAuth::attemptSmsOtp(
            $request->user(),
            $request->string('phone'),
            $request->string('code')
        );

        abort_unless($ok, 422, 'Invalid SMS OTP.');

        return response()->json(['status' => 'verified']);
    }
}
```

## Trusted Devices

Trusted devices are created automatically when you use:

- `attemptOtp(..., rememberDevice: true, ...)`
- `attemptPasskey(..., rememberDevice: true, ...)`

Relevant config keys:

- `trusted_devices.cookie`
- `trusted_devices.ttl_days`
- `trusted_devices.bind_user_agent`
- `trusted_devices.bind_ip`

## Social Login

LaravelAuth supports two social-login modes:

1. static/global provider config
2. runtime provider config for tenant-specific OAuth credentials

Backward compatibility is preserved. Existing static Socialite-based setups continue to work.

### Static Social Login Setup

Enable providers in your published `config/laravel-auth.php`.

Example:

```php
'social' => [
    'default_stateless' => false,
    'providers' => [
        'google' => [
            'enabled' => true,
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect' => env('GOOGLE_REDIRECT_URI'),
            'scopes' => ['openid', 'profile', 'email'],
            'with' => [],
        ],
        'github' => [
            'enabled' => true,
            'client_id' => env('GITHUB_CLIENT_ID'),
            'client_secret' => env('GITHUB_CLIENT_SECRET'),
            'redirect' => env('GITHUB_REDIRECT_URI'),
            'scopes' => ['read:user', 'user:email'],
            'with' => [],
        ],
    ],
],
```

Discover configured providers:

```php
$providers = LaravelAuth::socialProviders();
```

Redirect:

```php
return LaravelAuth::redirectToSocialProvider('google');
```

Callback:

```php
$profile = LaravelAuth::resolveSocialUser('google');
```

Link:

```php
$linked = LaravelAuth::syncSocialAccount(auth()->user(), 'google', $profile);
```

Find local user:

```php
$user = LaravelAuth::findUserBySocialAccount('google', $profile);
```

List linked accounts:

```php
$accounts = LaravelAuth::linkedSocialAccounts(auth()->user());
```

Unlink:

```php
LaravelAuth::unlinkSocialAccount(auth()->user(), 'google');
```

### Demo Static Social Controller

```php
<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Routing\Controller;
use LaravelAuth;

class SocialAuthController extends Controller
{
    public function redirect(string $provider)
    {
        return LaravelAuth::redirectToSocialProvider($provider);
    }

    public function callback(string $provider)
    {
        $profile = LaravelAuth::resolveSocialUser($provider);
        $user = LaravelAuth::findUserBySocialAccount($provider, $profile);

        if (! $user) {
            return redirect('/login')->withErrors([
                'email' => 'No linked account found.',
            ]);
        }

        auth()->login($user);

        return redirect('/dashboard');
    }

    public function connect(string $provider)
    {
        return LaravelAuth::redirectToSocialProvider($provider);
    }

    public function connectCallback(string $provider)
    {
        $profile = LaravelAuth::resolveSocialUser($provider);

        LaravelAuth::syncSocialAccount(auth()->user(), $provider, $profile);

        return redirect('/settings/connections')->with('status', 'Provider linked.');
    }
}
```

### Runtime Tenant Social Login

Use runtime config when each tenant stores its own OAuth credentials.

Supported runtime keys:

- `client_id`
- `client_secret`
- `redirect`
- `scopes`
- `with`
- `stateless`

Tenant provider discovery:

```php
$providers = LaravelAuth::socialProviders([
    'google' => [
        'client_id' => $tenant->google_client_id,
        'client_secret' => $tenant->google_client_secret,
        'redirect' => route('tenant.social.callback', ['provider' => 'google']),
    ],
    'github' => [
        'client_id' => $tenant->github_client_id,
        'client_secret' => $tenant->github_client_secret,
        'redirect' => route('tenant.social.callback', ['provider' => 'github']),
    ],
]);
```

Named-parameter redirect:

```php
return LaravelAuth::redirectToSocialProvider(
    'google',
    runtimeConfig: [
        'client_id' => $tenant->google_client_id,
        'client_secret' => $tenant->google_client_secret,
        'redirect' => route('tenant.social.callback', ['provider' => 'google']),
        'scopes' => ['openid', 'profile', 'email'],
        'stateless' => true,
    ],
);
```

Positional redirect:

```php
$config = [
    'client_id' => $tenant->google_client_id,
    'client_secret' => $tenant->google_client_secret,
    'redirect' => route('tenant.social.callback', ['provider' => 'google']),
    'scopes' => ['openid', 'profile', 'email'],
    'stateless' => true,
];

return LaravelAuth::redirectToSocialProvider('google', [], [], null, $config);
```

Named-parameter callback resolution:

```php
$profile = LaravelAuth::resolveSocialUser(
    'github',
    runtimeConfig: [
        'client_id' => $tenant->github_client_id,
        'client_secret' => $tenant->github_client_secret,
        'redirect' => route('tenant.social.callback', ['provider' => 'github']),
        'stateless' => true,
    ],
);
```

Positional callback resolution:

```php
$profile = LaravelAuth::resolveSocialUser('github', null, $config);
```

### Demo Tenant Social Controller

```php
<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaravelAuth;

class TenantSocialAuthController extends Controller
{
    public function redirect(Request $request, string $provider)
    {
        $config = $this->runtimeConfig($request, $provider);

        return LaravelAuth::redirectToSocialProvider($provider, [], [], null, $config);
    }

    public function callback(Request $request, string $provider)
    {
        $config = $this->runtimeConfig($request, $provider);
        $profile = LaravelAuth::resolveSocialUser($provider, null, $config);
        $user = LaravelAuth::findUserBySocialAccount($provider, $profile);

        if (! $user) {
            return redirect('/login')->withErrors([
                'email' => 'No linked account found for this social login.',
            ]);
        }

        auth()->login($user);

        return redirect('/dashboard');
    }

    public function connect(Request $request, string $provider)
    {
        $config = $this->runtimeConfig($request, $provider);

        return LaravelAuth::redirectToSocialProvider($provider, [], [], null, $config);
    }

    public function connectCallback(Request $request, string $provider)
    {
        $config = $this->runtimeConfig($request, $provider);
        $profile = LaravelAuth::resolveSocialUser($provider, null, $config);

        LaravelAuth::syncSocialAccount($request->user(), $provider, $profile);

        return redirect('/settings/connections')->with('status', 'Provider linked.');
    }

    private function runtimeConfig(Request $request, string $provider): array
    {
        $tenant = tenant();
        $oauth = $tenant->oauthProviders()->where('provider', $provider)->first();

        abort_unless($oauth, 404, 'Provider not configured for this tenant.');

        return [
            'client_id' => $oauth->client_id,
            'client_secret' => $oauth->client_secret,
            'redirect' => route('tenant.social.callback', ['provider' => $provider]),
            'scopes' => $this->defaultScopes($provider),
            'stateless' => true,
        ];
    }

    private function defaultScopes(string $provider): array
    {
        return match ($provider) {
            'google' => ['openid', 'profile', 'email'],
            'github' => ['read:user', 'user:email'],
            default => [],
        };
    }
}
```

Suggested routes:

```php
use App\Http\Controllers\Auth\TenantSocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/{provider}', [TenantSocialAuthController::class, 'redirect'])
    ->name('tenant.social.redirect');

Route::get('/auth/{provider}/callback', [TenantSocialAuthController::class, 'callback'])
    ->name('tenant.social.callback');

Route::middleware('auth')->group(function () {
    Route::get('/settings/connections/{provider}', [TenantSocialAuthController::class, 'connect'])
        ->name('tenant.social.connect');

    Route::get('/settings/connections/{provider}/callback', [TenantSocialAuthController::class, 'connectCallback'])
        ->name('tenant.social.connect.callback');
});
```

Suggested tenant credential table in your app:

```text
tenant_oauth_providers
- id
- tenant_id
- provider
- client_id
- client_secret
- created_at
- updated_at
```

### Important Tenant Limitation

Runtime tenant credentials are supported, but linked social identities are still globally unique by:

- `provider`
- `provider_user_id`

That means the same Google or GitHub identity cannot currently be linked separately in multiple tenants through the package table.

Current package fit:

- good for tenant-specific OAuth credentials
- good for integer, UUID, ULID, or string-like local user IDs
- good when social identity should remain globally unique across the whole app

Not yet built into the package:

- tenant-scoped uniqueness for `laravel_auth_social_accounts`
- tenant-aware social account lookup columns like `tenant_type` / `tenant_id`

## Session State Helpers

```php
$state = LaravelAuth::state($user = null);
$verified = LaravelAuth::isVerified($user = null);
$full = LaravelAuth::isFullyAuthenticated($user = null);
$pending = LaravelAuth::isPending2FA($user = null);
$required = LaravelAuth::requiresTwoFactor($user);
```

Enforce manually if needed:

```php
LaravelAuth::enforce($user = null);
```

## Rate Limiting Helpers

```php
LaravelAuth::throttle('otp', $user = null);
LaravelAuth::tooManyAttempts('otp', $user = null);
LaravelAuth::clearThrottle('otp', $user = null);
```

Buckets used by the package:

- `otp`
- `passkey`

## Strict Preset

Apply the built-in strict preset:

```php
LaravelAuth::preset('strict');
```

This tightens:

- enforced 2FA
- OTP TTL and max attempts
- passkey throttle settings
- trusted-device IP binding
- WebAuthn verification requirements

## OTP Provider Configuration

### Mail

Enabled by default.

Relevant config:

- `otp_channels.email.enabled`
- `otp_channels.email.view`
- `otp_channels.email.subject`

### SMS providers

Supported built-ins:

- Twilio
- Vonage
- MessageBird
- MSG91
- custom

Set the provider in config:

```php
'otp_channels' => [
    'sms' => [
        'provider' => env('LARAVEL_AUTH_SMS_PROVIDER', 'twilio'),
    ],
],
```

### WhatsApp providers

Supported built-ins:

- Twilio
- custom

WhatsApp is disabled by default. Enable it in your published config before use.

### Custom transports

Publish stubs:

```bash
php artisan laravel-auth:otp:publish
```

Then wire your own classes in the published config:

```php
'sms' => [
    'provider' => 'custom',
    'custom_transport' => App\LaravelAuth\SmsOtpTransport::class,
],

'whatsapp' => [
    'enabled' => true,
    'provider' => 'custom',
    'custom_transport' => App\LaravelAuth\WhatsAppOtpTransport::class,
],
```

## Troubleshooting

### 2FA required unexpectedly

- confirm the user session completed a second factor
- confirm the verification endpoint calls an `attempt*` method
- confirm the trusted-device cookie still matches the request

### Passkey challenge invalid or expired

- send `challenge_id` back from the frontend
- do not reuse the same challenge
- confirm RP ID matches the real app hostname

### Social provider missing

- confirm the provider is enabled in `laravel-auth.social.providers`
- confirm static config has `client_id`, `client_secret`, and `redirect`
- for runtime config, confirm `client_id` and `client_secret` are present
- if runtime config omits `redirect`, make sure a fallback redirect exists in `laravel-auth.social.providers.{provider}.redirect`

### Runtime config named parameter error in IDE

If your app or IDE says `Unknown named parameter $runtimeConfig`, refresh the package in the app:

```bash
composer update ghostcompiler/laravel-auth
composer dump-autoload
php artisan optimize:clear
```

Or use positional arguments:

```php
LaravelAuth::redirectToSocialProvider($provider, [], [], null, $config);
LaravelAuth::resolveSocialUser($provider, null, $config);
```

### OTP verification keeps failing

- confirm destination matches the original challenge destination
- confirm provider credentials and sender configuration
- confirm the code is not expired
- confirm the attempt bucket is not rate limited

## Testing

Run the package tests:

```bash
composer install
vendor/bin/phpunit
```

or:

```bash
composer test
```

## Public API Reference

### 2FA / TOTP / Recovery

```php
LaravelAuth::enable2FA($user);
LaravelAuth::confirmTwoFactorSetup($user, $code);
LaravelAuth::disable2FA($user);
LaravelAuth::verifyOTP($user, $code);
LaravelAuth::generateRecoveryCodes($user);
```

### Passkeys

```php
LaravelAuth::registerPasskey($user, $name = null);
LaravelAuth::finishPasskeyRegistration($user, $payload, $name = null);
LaravelAuth::requestPasskeyAssertion($user);
LaravelAuth::verifyPasskeyAssertion($user, $payload);
LaravelAuth::attemptPasskey($user, $payload, $rememberDevice = false, $deviceName = null);
```

### OTP Channels

```php
LaravelAuth::sendEmailOtp($user, $email = null, $context = []);
LaravelAuth::verifyEmailOtp($user, $code, $email = null, $context = []);
LaravelAuth::attemptEmailOtp($user, $code, $email = null, $context = []);

LaravelAuth::sendSmsOtp($user, $phoneNumber, $context = []);
LaravelAuth::verifySmsOtp($user, $phoneNumber, $code, $context = []);
LaravelAuth::attemptSmsOtp($user, $phoneNumber, $code, $context = []);

LaravelAuth::sendWhatsAppOtp($user, $phoneNumber, $context = []);
LaravelAuth::verifyWhatsAppOtp($user, $phoneNumber, $code, $context = []);
LaravelAuth::attemptWhatsAppOtp($user, $phoneNumber, $code, $context = []);
```

### Social Helpers

```php
LaravelAuth::socialProviders($runtimeProviders = []);
LaravelAuth::redirectToSocialProvider($provider, $scopes = [], $with = [], $stateless = null, $runtimeConfig = []);
LaravelAuth::resolveSocialUser($provider, $stateless = null, $runtimeConfig = []);
LaravelAuth::syncSocialAccount($user, $provider, $profile);
LaravelAuth::findUserBySocialAccount($provider, $socialIdentity);
LaravelAuth::linkedSocialAccounts($user);
LaravelAuth::unlinkSocialAccount($user, $provider, $providerUserId = null);
```

### State / Enforcement / Utility

```php
LaravelAuth::state($user = null);
LaravelAuth::isVerified($user = null);
LaravelAuth::isFullyAuthenticated($user = null);
LaravelAuth::isPending2FA($user = null);
LaravelAuth::enforce($user = null);
LaravelAuth::throttle('otp', $user = null);
LaravelAuth::tooManyAttempts('otp', $user = null);
LaravelAuth::clearThrottle('otp', $user = null);
LaravelAuth::preset('strict');
LaravelAuth::requiresTwoFactor($user);
```

## License

MIT. See [LICENSE](LICENSE).
