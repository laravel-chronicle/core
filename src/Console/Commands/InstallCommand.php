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

        $publishedConfig = $this->call('vendor:publish', [
            '--provider' => ChronicleServiceProvider::class,
            '--tag' => 'chronicle-config',
            '--force' => (bool) $this->option('force'),
        ]);

        if ($publishedConfig !== self::SUCCESS) {
            $this->error('Failed to publish Chronicle configuration.');

            return self::FAILURE;
        }

        if (! $this->publishMigrations()) {
            $this->error('Failed to publish Chronicle migrations.');

            return self::FAILURE;
        }

        $shouldMigrate = $this->option('migrate') || (! $this->option('no-interaction') && $this->confirm('Would you like to run migrations now?'));
        if ($shouldMigrate) {
            $this->comment('Running Migrations...');

            $migrated = $this->call('migrate');

            if ($migrated !== self::SUCCESS) {
                $this->error('Failed to run migrations now.');

                return self::FAILURE;
            }
        }

        if (! $this->option('no-interaction') && $this->confirm('Would you like to publish Chronicle views (customisable Blade UI)?')) {
            $this->call('vendor:publish', [
                '--provider' => ChronicleServiceProvider::class,
                '--tag' => 'chronicle-views',
                '--force' => (bool) $this->option('force'),
            ]);
        }

        if (! $this->option('no-interaction') && $this->confirm('Would you like to star our repo on GitHub?')) {
            $this->line('⭐ https://github.com/laravel-chronicle/core');
        }

        $this->info('Chronicle installed successfully.');

        return self::SUCCESS;
    }

    private function publishMigrations(): bool
    {
        $source = __DIR__.'/../../../database/migrations';
        $destination = database_path('migrations');
        $force = (bool) $this->option('force');

        $files = glob($source.'/*.php');
        if ($files === false) {
            return false;
        }

        sort($files);

        foreach ($files as $file) {
            $basename = basename($file);
            $target = $destination.'/'.$basename;

            if (! $force && $this->migrationExists($destination, $basename)) {
                $this->line("<info>Migration already exists:</info> $basename");

                continue;
            }

            if (! copy($file, $target)) {
                return false;
            }

            $this->line("<info>Published migration:</info> $basename");
        }

        return true;
    }

    private function migrationExists(string $directory, string $filename): bool
    {
        $existing = glob($directory.'/*.php');
        if ($existing === false) {
            return false;
        }

        $name = $this->stripTimestamp($filename);

        foreach ($existing as $file) {
            if ($this->stripTimestamp(basename($file)) === $name) {
                return true;
            }
        }

        return false;
    }

    private function stripTimestamp(string $filename): string
    {
        return (string) preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename);
    }
}
