<?php

namespace GhostCompiler\LaravelAuth\Tests;

use GhostCompiler\LaravelAuth\LaravelAuthServiceProvider;
use GhostCompiler\LaravelAuth\Support\Base32;
use GhostCompiler\LaravelAuth\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelAuthServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $compiledPath = sys_get_temp_dir() . '/laravel-auth-testbench/views';

        $app['config']->set('app.key', str_repeat('a', 32));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('view.compiled', $compiledPath);
        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => User::class,
        ]);
    }

    protected function defineRoutes($router): void
    {
        $router->middleware(['web', 'auth'])->get('/protected', static fn () => response()->json(['ok' => true]));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = config('view.compiled');

        if (is_string($compiledPath) && $compiledPath !== '' && !is_dir($compiledPath)) {
            mkdir($compiledPath, 0777, true);
        }

        Relation::requireMorphMap(false);
        Relation::morphMap([], false);
        $this->setUpDatabase();
    }

    protected function currentOtp(string $secret): string
    {
        $counter = (int) floor(time() / 30);
        $binarySecret = Base32::decode($secret);
        $packedCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $packedCounter, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % (10 ** 6)), 6, '0', STR_PAD_LEFT);
    }

    private function setUpDatabase(): void
    {
        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken()->nullable();
            $table->timestamps();
        });

        foreach (glob(dirname(__DIR__) . '/database/migrations/*.php') ?: [] as $migrationPath) {
            $migration = require $migrationPath;
            $migration->up();
        }
    }
}
