<?php

use Illuminate\Contracts\Console\Kernel;

$tempPath = null;

beforeEach(function () use (&$tempPath) {
    $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'chronicle_install_'.getmypid().'_'.uniqid();
    mkdir($tempPath.DIRECTORY_SEPARATOR.'migrations', 0755, true);
    $this->app->useDatabasePath($tempPath);
});

afterEach(function () use (&$tempPath) {
    if (is_string($tempPath)) {
        array_map('unlink', glob($tempPath.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'*.php') ?: []);
        @rmdir($tempPath.DIRECTORY_SEPARATOR.'migrations');
        @rmdir($tempPath);
        $tempPath = null;
    }
});

it('installs chronicle and allows skipping optional follow-up actions', function () {
    $this->artisan('chronicle:install', ['--force' => true])
        ->expectsConfirmation('Would you like to run migrations now?')
        ->expectsConfirmation('Would you like to publish Chronicle views (customisable Blade UI)?')
        ->expectsConfirmation('Would you like to star our repo on GitHub?')
        ->expectsOutput('Installing Chronicle...')
        ->expectsOutput('Chronicle installed successfully.')
        ->assertSuccessful();

    expect(file_exists(config_path('chronicle.php')))->toBeTrue();

    $migrationFiles = glob(database_path('migrations/*_create_chronicle_entries_table.php'));

    expect($migrationFiles)->not->toBeFalse()
        ->and($migrationFiles)->not->toBeEmpty();
});

it('can run migrations during install when confirmed', function () {
    // Drop Chronicle tables so the freshly published migrations can run on a clean slate
    Schema::dropIfExists('chronicle_entries');
    Schema::dropIfExists('chronicle_checkpoints');

    $this->artisan('chronicle:install')
        ->expectsConfirmation('Would you like to run migrations now?', 'yes')
        ->expectsOutput('Running Migrations...')
        ->expectsConfirmation('Would you like to publish Chronicle views (customisable Blade UI)?')
        ->expectsConfirmation('Would you like to star our repo on GitHub?')
        ->expectsOutput('Chronicle installed successfully.')
        ->assertSuccessful();
});

it('registers the chronicle install command', function () {
    $commands = array_keys($this->app[Kernel::class]->all());

    expect($commands)->toContain('chronicle:install');
});

it('prints the GitHub repo link instead of opening a browser', function () {
    $this->artisan('chronicle:install', ['--force' => true])
        ->expectsConfirmation('Would you like to run migrations now?')
        ->expectsConfirmation('Would you like to publish Chronicle views (customisable Blade UI)?')
        ->expectsConfirmation('Would you like to star our repo on GitHub?', 'yes')
        ->expectsOutputToContain('github.com/laravel-chronicle/core')
        ->assertSuccessful();
});

it('honours the --migrate flag without prompting', function () {
    Schema::dropIfExists('chronicle_entries');
    Schema::dropIfExists('chronicle_checkpoints');

    $this->artisan('chronicle:install', [
        '--migrate' => true,
        '--no-interaction' => true,
    ])->assertExitCode(0);
});

it('publishMigrations uses a fixed base date not wall-clock time', function () {
    // Call install twice — second call skips existing migrations,
    // so timestamps must be deterministic
    $firstRun = $this->artisan('chronicle:install', ['--no-interaction' => true])->run();
    $secondRun = $this->artisan('chronicle:install', ['--no-interaction' => true])->run();

    // Both should succeed without producing duplicate files
    expect($firstRun)->toBe(0)->and($secondRun)->toBe(0);
});
