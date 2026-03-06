<?php

use Illuminate\Contracts\Console\Kernel;

it('installs chronicle and allows skipping optional follow-up actions', function () {
    $this->artisan('chronicle:install', ['--force' => true])
        ->expectsConfirmation('Would you like to run migrations now?', 'no')
        ->expectsConfirmation('Would you like to star our repo on GitHub?', 'no')
        ->expectsOutput('Installing Chronicle...')
        ->expectsOutput('Chronicle installed successfully.')
        ->assertSuccessful();

    expect(file_exists(config_path('chronicle.php')))->toBeTrue();

    $migrationFiles = glob(database_path('migrations/*_create_chronicle_entries_table.php'));

    expect($migrationFiles)->not->toBeFalse()
        ->and($migrationFiles)->not->toBeEmpty();
});

it('can run migrations during install when confirmed', function () {
    $this->artisan('chronicle:install')
        ->expectsConfirmation('Would you like to run migrations now?', 'yes')
        ->expectsOutput('Running Migrations...')
        ->expectsConfirmation('Would you like to star our repo on GitHub?', 'no')
        ->expectsOutput('Chronicle installed successfully.')
        ->assertSuccessful();
});

it('registers the chronicle install command', function () {
    $commands = array_keys($this->app[Kernel::class]->all());

    expect($commands)->toContain('chronicle:install');
});
