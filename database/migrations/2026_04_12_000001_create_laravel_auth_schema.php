<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (!Schema::hasColumn('users', 'laravel_auth_totp_secret')) {
                    $table->text('laravel_auth_totp_secret')->nullable()->after('password');
                }

                if (!Schema::hasColumn('users', 'laravel_auth_two_factor_enabled')) {
                    $table->boolean('laravel_auth_two_factor_enabled')->default(false)->after('laravel_auth_totp_secret');
                }

                if (!Schema::hasColumn('users', 'laravel_auth_confirmed_at')) {
                    $table->timestamp('laravel_auth_confirmed_at')->nullable()->after('laravel_auth_two_factor_enabled');
                }
            });
        }

        if (!Schema::hasTable('laravel_auth_recovery_codes')) {
            Schema::create('laravel_auth_recovery_codes', function (Blueprint $table): void {
                $table->id();
                $table->string('authenticatable_type');
                $table->string('authenticatable_id');
                $table->string('code');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();

                $table->index(['authenticatable_type', 'authenticatable_id'], 'laravel_auth_recovery_codes_authenticatable_index');
            });
        }

        if (!Schema::hasTable('laravel_auth_trusted_devices')) {
            Schema::create('laravel_auth_trusted_devices', function (Blueprint $table): void {
                $table->id();
                $table->string('authenticatable_type');
                $table->string('authenticatable_id');
                $table->string('name')->nullable();
                $table->string('token');
                $table->ipAddress('ip_address')->nullable();
                $table->string('ip_hash')->nullable();
                $table->text('user_agent')->nullable();
                $table->string('user_agent_hash')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->index(['authenticatable_type', 'authenticatable_id'], 'laravel_auth_trusted_devices_authenticatable_index');
            });
        }

        if (!Schema::hasTable('laravel_auth_passkeys')) {
            Schema::create('laravel_auth_passkeys', function (Blueprint $table): void {
                $table->id();
                $table->string('authenticatable_type');
                $table->string('authenticatable_id');
                $table->string('name')->nullable();
                $table->text('credential_id')->unique();
                $table->longText('credential_public_key');
                $table->string('aaguid')->nullable();
                $table->unsignedBigInteger('signature_counter')->default(0);
                $table->json('transports')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index(['authenticatable_type', 'authenticatable_id'], 'laravel_auth_passkeys_authenticatable_index');
            });
        }

        if (!Schema::hasTable('laravel_auth_webauthn_challenges')) {
            Schema::create('laravel_auth_webauthn_challenges', function (Blueprint $table): void {
                $table->id();
                $table->string('authenticatable_type');
                $table->string('authenticatable_id');
                $table->string('type');
                $table->longText('payload');
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();

                $table->index(['authenticatable_type', 'authenticatable_id'], 'laravel_auth_webauthn_challenges_authenticatable_index');
            });
        }

        if (!Schema::hasTable('laravel_auth_social_accounts')) {
            Schema::create('laravel_auth_social_accounts', function (Blueprint $table): void {
                $table->id();
                $table->string('authenticatable_type');
                $table->string('authenticatable_id');
                $table->string('provider');
                $table->string('provider_user_id');
                $table->string('provider_nickname')->nullable();
                $table->string('provider_name')->nullable();
                $table->string('provider_email')->nullable();
                $table->text('provider_avatar')->nullable();
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->json('approved_scopes')->nullable();
                $table->json('profile')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'provider_user_id']);
                $table->unique(['authenticatable_type', 'authenticatable_id', 'provider'], 'laravel_auth_social_accounts_user_provider_unique');
            });
        }

        if (!Schema::hasTable('laravel_auth_otp_challenges')) {
            Schema::create('laravel_auth_otp_challenges', function (Blueprint $table): void {
                $table->id();
                $table->string('authenticatable_type');
                $table->string('authenticatable_id');
                $table->string('channel');
                $table->string('destination');
                $table->string('provider')->nullable();
                $table->string('code');
                $table->unsignedInteger('attempts')->default(0);
                $table->unsignedInteger('max_attempts')->default(5);
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['authenticatable_type', 'authenticatable_id'], 'laravel_auth_otp_challenges_authenticatable_index');
                $table->index(['channel', 'destination']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('laravel_auth_otp_challenges');
        Schema::dropIfExists('laravel_auth_social_accounts');
        Schema::dropIfExists('laravel_auth_webauthn_challenges');
        Schema::dropIfExists('laravel_auth_passkeys');
        Schema::dropIfExists('laravel_auth_trusted_devices');
        Schema::dropIfExists('laravel_auth_recovery_codes');

        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('users', 'laravel_auth_totp_secret') ? 'laravel_auth_totp_secret' : null,
                Schema::hasColumn('users', 'laravel_auth_two_factor_enabled') ? 'laravel_auth_two_factor_enabled' : null,
                Schema::hasColumn('users', 'laravel_auth_confirmed_at') ? 'laravel_auth_confirmed_at' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
