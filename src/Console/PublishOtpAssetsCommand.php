<?php

namespace GhostCompiler\LaravelAuth\Console;

use Illuminate\Console\Command;

class PublishOtpAssetsCommand extends Command
{
    protected $signature = 'laravel-auth:otp:publish {--force : Overwrite published OTP assets}';

    protected $description = 'Publish LaravelAuth OTP templates and transport stubs';

    public function handle(): int
    {
        $this->components->info('Publishing LaravelAuth OTP views...');

        $this->call('vendor:publish', array_filter([
            '--tag' => 'laravel-auth-views',
            '--force' => $this->option('force') ? true : null,
        ]));

        $this->newLine();
        $this->components->info('Publishing LaravelAuth OTP transport stubs...');

        $this->call('vendor:publish', array_filter([
            '--tag' => 'laravel-auth-stubs',
            '--force' => $this->option('force') ? true : null,
        ]));

        $this->newLine();
        $this->components->info('OTP assets published.');

        return self::SUCCESS;
    }
}
