<?php

use Chronicle\Checkpoint\CheckpointCreator;
use Chronicle\Facades\Chronicle;
use Chronicle\Models\Checkpoint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('rolls back checkpoint creation on non-default chronicle connection when a post-insert failure occurs', function () {
    $altDatabase = sys_get_temp_dir().'/chronicle-alt-'.Str::uuid().'.sqlite';
    touch($altDatabase);

    config()->set('database.connections.chronicle_alt', [
        'driver' => 'sqlite',
        'database' => $altDatabase,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    config()->set('chronicle.connection', 'chronicle_alt');

    $migrationsPath = realpath(__DIR__.'/../../../database/migrations');

    expect($migrationsPath)->not->toBeFalse();

    $migrateExit = Artisan::call('migrate', [
        '--database' => 'chronicle_alt',
        '--path' => $migrationsPath,
        '--realpath' => true,
        '--force' => true,
    ]);

    expect($migrateExit)->toBe(0);

    Chronicle::record()
        ->actor('system')
        ->action('checkpoint.atomicity')
        ->subject('ledger')
        ->commit();

    $event = 'eloquent.created: '.Checkpoint::class;

    Event::listen($event, function (): void {
        throw new RuntimeException('post-insert failure');
    });

    try {
        expect(fn () => app(CheckpointCreator::class)->create())
            ->toThrow(RuntimeException::class, 'post-insert failure');
    } finally {
        app('events')->forget($event);
        @unlink($altDatabase);
    }

    expect(Checkpoint::query()->count())->toBe(0);
});

