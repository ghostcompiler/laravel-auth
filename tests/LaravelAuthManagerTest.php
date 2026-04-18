<?php

namespace GhostCompiler\LaravelAuth\Tests;

use GhostCompiler\LaravelAuth\Contracts\LaravelAuthManager;
use GhostCompiler\LaravelAuth\Enums\AuthState;
use GhostCompiler\LaravelAuth\Mail\OtpCodeMail;
use GhostCompiler\LaravelAuth\Models\OtpChallenge;
use GhostCompiler\LaravelAuth\OTP\OtpChannelManager;
use GhostCompiler\LaravelAuth\Services\ChallengeStore;
use GhostCompiler\LaravelAuth\Services\RecoveryCodeService;
use GhostCompiler\LaravelAuth\Services\SocialAuthService;
use GhostCompiler\LaravelAuth\Services\TotpService;
use GhostCompiler\LaravelAuth\Services\TrustedDeviceService;
use GhostCompiler\LaravelAuth\Services\WebAuthnService;
use GhostCompiler\LaravelAuth\LaravelAuthManager as ConcreteLaravelAuthManager;
use GhostCompiler\LaravelAuth\Tests\Fixtures\FakeSmsOtpTransport;
use GhostCompiler\LaravelAuth\Tests\Fixtures\FakeWhatsAppOtpTransport;
use GhostCompiler\LaravelAuth\Tests\Fixtures\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class LaravelAuthManagerTest extends TestCase
{
    public function test_attempt_otp_marks_user_as_verified_with_a_proof(): void
    {
        $manager = $this->app->make(LaravelAuthManager::class);
        $user = User::query()->create([
            'email' => 'otp@example.test',
            'password' => 'secret',
        ]);

        $setup = $manager->enable2FA($user);
        $manager->confirmTwoFactorSetup($user, $this->currentOtp($setup['secret']));

        session()->forget('laravel_auth.verified.' . $user->getAuthIdentifier());

        $result = $manager->attemptOtp($user, $this->currentOtp($setup['secret']));

        self::assertTrue($result);
        self::assertTrue($manager->isFullyAuthenticated($user));
        self::assertSame(AuthState::FullyAuthenticated, $manager->state($user));
    }

    public function test_verify_otp_returns_null_for_invalid_code(): void
    {
        $manager = $this->app->make(LaravelAuthManager::class);
        $user = User::query()->create([
            'email' => 'otp-fail@example.test',
            'password' => 'secret',
        ]);

        $setup = $manager->enable2FA($user);
        $manager->confirmTwoFactorSetup($user, $this->currentOtp($setup['secret']));

        session()->forget('laravel_auth.verified.' . $user->getAuthIdentifier());

        self::assertNull($manager->verifyOTP($user, '000000'));
        self::assertTrue($manager->isPending2FA($user));
        self::assertSame(AuthState::TwoFactorPending, $manager->state($user));
    }

    public function test_attempt_passkey_marks_the_session_fully_authenticated(): void
    {
        $user = User::query()->create([
            'email' => 'passkey@example.test',
            'password' => 'secret',
            'laravel_auth_two_factor_enabled' => true,
        ]);

        $manager = new ConcreteLaravelAuthManager(
            $this->app->make(TotpService::class),
            $this->app->make(RecoveryCodeService::class),
            new class extends WebAuthnService {
                public function verifyAuthentication($user, array $payload): bool
                {
                    return true;
                }
            },
            $this->app->make(OtpChannelManager::class),
            $this->app->make(SocialAuthService::class),
            $this->app->make(TrustedDeviceService::class),
            $this->app->make(Request::class)
        );

        self::assertTrue($manager->attemptPasskey($user, ['id' => 'credential']));
        self::assertSame(AuthState::FullyAuthenticated, $manager->state($user));
    }

    public function test_webauthn_challenges_are_single_use(): void
    {
        $user = User::query()->create([
            'email' => 'challenge@example.test',
            'password' => 'secret',
        ]);

        $store = $this->app->make(ChallengeStore::class);
        $challenge = $store->create($user, 'authentication', 'challenge-payload', 60);

        self::assertSame('challenge-payload', $store->consume($user, 'authentication', $challenge->getKey()));
        self::assertNull($store->consume($user, 'authentication', $challenge->getKey()));
    }

    public function test_trusted_devices_require_the_same_user_agent(): void
    {
        $user = User::query()->create([
            'email' => 'device@example.test',
            'password' => 'secret',
        ]);

        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'LaravelAuth Test Agent',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $service = $this->app->make(TrustedDeviceService::class);
        $service->trust($user, $request, 'Test Device');

        $queuedCookie = collect(Cookie::getQueuedCookies())->first();
        self::assertNotNull($queuedCookie);

        $sameRequest = Request::create('/', 'GET', [], [
            config('laravel-auth.trusted_devices.cookie') => $queuedCookie->getValue(),
        ], [], [
            'HTTP_USER_AGENT' => 'LaravelAuth Test Agent',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $differentAgentRequest = Request::create('/', 'GET', [], [
            config('laravel-auth.trusted_devices.cookie') => $queuedCookie->getValue(),
        ], [], [
            'HTTP_USER_AGENT' => 'Different Agent',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        self::assertTrue($service->isTrusted($user, $sameRequest));
        self::assertFalse($service->isTrusted($user, $differentAgentRequest));
    }

    public function test_email_otp_can_be_sent_and_verified(): void
    {
        Mail::fake();

        $manager = $this->app->make(LaravelAuthManager::class);
        $user = User::query()->create([
            'email' => 'mail@example.test',
            'password' => 'secret',
            'laravel_auth_two_factor_enabled' => true,
        ]);

        $manager->sendEmailOtp($user);
        $challenge = OtpChallenge::query()->latest('id')->first();
        self::assertNotNull($challenge);
        $challenge->forceFill([
            'code' => Hash::make('123456'),
        ])->save();

        Mail::assertSent(OtpCodeMail::class);
        self::assertTrue($manager->attemptEmailOtp($user, '123456'));
        self::assertSame(AuthState::FullyAuthenticated, $manager->state($user));
    }

    public function test_sms_otp_can_be_sent_and_verified(): void
    {
        Http::fake([
            '*' => Http::response(['sid' => 'sms-123'], 200),
        ]);

        $manager = $this->app->make(LaravelAuthManager::class);
        $user = User::query()->create([
            'email' => 'sms@example.test',
            'password' => 'secret',
            'laravel_auth_two_factor_enabled' => true,
        ]);

        $manager->sendSmsOtp($user, '+15550001111');

        $challenge = OtpChallenge::query()->latest('id')->first();
        self::assertNotNull($challenge);
        $challenge->forceFill([
            'code' => Hash::make('123456'),
        ])->save();

        Http::assertSentCount(1);
        self::assertTrue($manager->attemptSmsOtp($user, '+15550001111', '123456'));
        self::assertSame(AuthState::FullyAuthenticated, $manager->state($user));
    }

    public function test_sms_otp_can_use_a_custom_transport(): void
    {
        FakeSmsOtpTransport::$sent = [];
        config([
            'laravel-auth.otp_channels.sms.provider' => 'custom',
            'laravel-auth.otp_channels.sms.custom_transport' => FakeSmsOtpTransport::class,
        ]);

        $manager = $this->app->make(LaravelAuthManager::class);
        $user = User::query()->create([
            'email' => 'custom-sms@example.test',
            'password' => 'secret',
            'laravel_auth_two_factor_enabled' => true,
        ]);

        $manager->sendSmsOtp($user, '+15550003333');

        self::assertNotEmpty(FakeSmsOtpTransport::$sent);

        $code = FakeSmsOtpTransport::$sent[0]['code'] ?? null;

        self::assertIsString($code);
        self::assertTrue($manager->attemptSmsOtp($user, '+15550003333', $code));
        self::assertSame(AuthState::FullyAuthenticated, $manager->state($user));
    }

    public function test_whatsapp_otp_can_use_a_custom_transport(): void
    {
        FakeWhatsAppOtpTransport::$sent = [];
        config([
            'laravel-auth.otp_channels.whatsapp.enabled' => true,
            'laravel-auth.otp_channels.whatsapp.custom_transport' => FakeWhatsAppOtpTransport::class,
        ]);

        $manager = $this->app->make(LaravelAuthManager::class);
        $user = User::query()->create([
            'email' => 'whatsapp@example.test',
            'password' => 'secret',
            'laravel_auth_two_factor_enabled' => true,
        ]);

        $manager->sendWhatsAppOtp($user, '+15550002222');

        self::assertNotEmpty(FakeWhatsAppOtpTransport::$sent);

        $code = FakeWhatsAppOtpTransport::$sent[0]['code'] ?? null;

        self::assertIsString($code);
        self::assertTrue($manager->attemptWhatsAppOtp($user, '+15550002222', $code));
        self::assertSame(AuthState::FullyAuthenticated, $manager->state($user));
    }

    public function test_recovery_codes_are_written_with_the_user_morph_alias(): void
    {
        Relation::enforceMorphMap([
            'member' => User::class,
        ]);

        $manager = $this->app->make(LaravelAuthManager::class);
        $user = User::query()->create([
            'email' => 'morph@example.test',
            'password' => 'secret',
        ]);

        $manager->generateRecoveryCodes($user);

        self::assertSame('member', \GhostCompiler\LaravelAuth\Models\RecoveryCode::query()->value('authenticatable_type'));
    }

    public function test_otp_verification_failures_increment_the_rate_limiter_bucket(): void
    {
        $manager = $this->app->make(LaravelAuthManager::class);
        $user = User::query()->create([
            'email' => 'otp-throttle@example.test',
            'password' => 'secret',
            'laravel_auth_two_factor_enabled' => true,
        ]);

        $manager->sendEmailOtp($user);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertFalse($manager->attemptEmailOtp($user, '000000'));
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Too many OTP attempts. Please wait and try again.');

        $manager->verifyEmailOtp($user, '000000');
    }
}
