<?php

namespace GhostCompiler\LaravelAuth\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'ghost:laravel-auth {--force : Overwrite existing LaravelAuth files}';

    protected $description = 'Install LaravelAuth by publishing its config and migrations';

    public function handle(): int
    {
        $this->components->info('Publishing LaravelAuth configuration...');

        $this->call('vendor:publish', array_filter([
            '--tag' => 'laravel-auth-config',
            '--force' => $this->option('force') ? true : null,
        ]));

        $this->newLine();
        $this->components->info('Publishing LaravelAuth migrations...');

        $this->call('vendor:publish', array_filter([
            '--tag' => 'laravel-auth-migrations',
            '--force' => $this->option('force') ? true : null,
        ]));

        $this->newLine();
        $this->components->info('Publishing LaravelAuth OTP assets...');

        $this->call('vendor:publish', array_filter([
            '--tag' => 'laravel-auth-views',
            '--force' => $this->option('force') ? true : null,
        ]));

        $this->call('vendor:publish', array_filter([
            '--tag' => 'laravel-auth-stubs',
            '--force' => $this->option('force') ? true : null,
        ]));

        $this->newLine();
        $this->components->info('LaravelAuth installation completed.');
        $this->line('Run <comment>php artisan migrate</comment> to finish setup.');

        return self::SUCCESS;
    }
}
