<?php

namespace Chronicle\Console\Commands;

use Chronicle\ChronicleServiceProvider;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'chronicle:install
        {--force : Overwrite any existing published files}
        {--migrate : Run migrations after publishing}';

    protected $description = 'Install Chronicle by publishing config and migrations';

    public function handle(): int
    {
        $this->info('Installing Chronicle...');

        $shared = [
            '--provider' => ChronicleServiceProvider::class,
            '--force' => (bool) $this->option('force'),
        ];

        $publishedConfig = $this->call('vendor:publish', [
            ...$shared,
            '--tag' => 'chronicle-config',
        ]);

        if ($publishedConfig !== self::SUCCESS) {
            $this->error('Failed to publish Chronicle configuration.');

            return self::FAILURE;
        }

        $publishedMigrations = $this->call('vendor:publish', [
            ...$shared,
            '--tag' => 'chronicle-migrations',
        ]);

        if ($publishedMigrations !== self::SUCCESS) {
            $this->error('Failed to publish Chronicle migrations.');

            return self::FAILURE;
        }

        if ($this->confirm('Would you like to run migrations now?')) {
            $this->comment('Running Migrations...');

            $migrated = $this->call('migrate');

            if ($migrated !== self::SUCCESS) {
                $this->error('Failed to run migrations now.');

                return self::FAILURE;
            }
        }

        if ($this->confirm('Would you like to star our repo on GitHub?')) {
            $repoUrl = 'https://github.com/laravel-chronicle/core';

            if (PHP_OS_FAMILY == 'Darwin') {
                exec("open $repoUrl");
            }

            if (PHP_OS_FAMILY == 'Windows') {
                exec("start $repoUrl");
            }

            if (PHP_OS_FAMILY == 'Linux') {
                exec("xdg-open $repoUrl");
            }
        }

        $this->info('Chronicle installed successfully.');

        return self::SUCCESS;
    }
}
