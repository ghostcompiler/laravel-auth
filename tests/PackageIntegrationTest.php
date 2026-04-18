<?php

namespace GhostCompiler\LaravelAuth\Tests;

use GhostCompiler\LaravelAuth\Contracts\LaravelAuthManager;
use GhostCompiler\LaravelAuth\Facades\LaravelAuth;
use GhostCompiler\LaravelAuth\LaravelAuthManager as ConcreteLaravelAuthManager;
use GhostCompiler\LaravelAuth\Models\SocialAccount;
use GhostCompiler\LaravelAuth\Tests\Fixtures\User;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PackageIntegrationTest extends TestCase
{
    public function test_contract_string_alias_and_facade_resolve_the_same_manager(): void
    {
        $contract = $this->app->make(LaravelAuthManager::class);
        $alias = $this->app->make('laravel-auth');
        $facadeRoot = LaravelAuth::getFacadeRoot();

        self::assertInstanceOf(ConcreteLaravelAuthManager::class, $contract);
        self::assertSame($contract, $alias);
        self::assertSame($contract, $facadeRoot);
    }

    public function test_package_migration_creates_expected_tables_and_user_columns(): void
    {
        self::assertTrue(Schema::hasColumn('users', 'laravel_auth_totp_secret'));
        self::assertTrue(Schema::hasColumn('users', 'laravel_auth_two_factor_enabled'));
        self::assertTrue(Schema::hasColumn('users', 'laravel_auth_confirmed_at'));

        foreach ([
            'laravel_auth_recovery_codes',
            'laravel_auth_trusted_devices',
            'laravel_auth_passkeys',
            'laravel_auth_webauthn_challenges',
            'laravel_auth_social_accounts',
            'laravel_auth_otp_challenges',
        ] as $table) {
            self::assertTrue(Schema::hasTable($table), "Failed asserting that [{$table}] exists.");
        }
    }

    public function test_social_accounts_can_be_linked_found_listed_and_unlinked(): void
    {
        $manager = $this->app->make(LaravelAuthManager::class);
        $user = User::query()->create([
            'email' => 'linked@example.test',
            'password' => 'secret',
        ]);

        $linked = $manager->syncSocialAccount($user, 'github', [
            'provider_user_id' => 'github-user-42',
            'name' => 'Linked User',
            'email' => 'linked@example.test',
            'approved_scopes' => ['read:user'],
        ]);

        self::assertSame('github', $linked['provider']);
        self::assertSame('github-user-42', $linked['provider_user_id']);

        $resolved = $manager->findUserBySocialAccount('github', 'github-user-42');
        self::assertInstanceOf(User::class, $resolved);
        self::assertTrue($user->is($resolved));

        $accounts = $manager->linkedSocialAccounts($user);
        self::assertCount(1, $accounts);
        self::assertSame('github-user-42', $accounts[0]['provider_user_id']);

        self::assertSame(1, $manager->unlinkSocialAccount($user, 'github', 'github-user-42'));
        self::assertCount(0, $manager->linkedSocialAccounts($user));
    }

    public function test_social_accounts_cannot_be_linked_to_multiple_users(): void
    {
        $manager = $this->app->make(LaravelAuthManager::class);
        $firstUser = User::query()->create([
            'email' => 'first@example.test',
            'password' => 'secret',
        ]);
        $secondUser = User::query()->create([
            'email' => 'second@example.test',
            'password' => 'secret',
        ]);

        $manager->syncSocialAccount($firstUser, 'google', [
            'provider_user_id' => 'google-user-77',
            'email' => 'social@example.test',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This social account is already linked to another user.');

        $manager->syncSocialAccount($secondUser, 'google', [
            'provider_user_id' => 'google-user-77',
            'email' => 'social@example.test',
        ]);
    }

    public function test_social_account_tokens_are_stored_encrypted(): void
    {
        $manager = $this->app->make(LaravelAuthManager::class);
        $user = User::query()->create([
            'email' => 'tokens@example.test',
            'password' => 'secret',
        ]);

        $manager->syncSocialAccount($user, 'github', [
            'provider_user_id' => 'token-user-1',
            'token' => 'plain-access-token',
            'refresh_token' => 'plain-refresh-token',
        ]);

        $account = SocialAccount::query()->where('provider_user_id', 'token-user-1')->first();

        self::assertNotNull($account);
        self::assertNotSame('plain-access-token', $account->access_token);
        self::assertNotSame('plain-refresh-token', $account->refresh_token);
    }
}
