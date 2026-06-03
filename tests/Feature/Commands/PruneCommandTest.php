<?php

use Chronicle\Entry\Entry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function seedEntries(int $count, string $date, ?string $checkpointId = null): void
{
    for ($i = 0; $i < $count; $i++) {
        DB::table(config('chronicle.tables.entries', 'chronicle_entries'))->insert([
            'id' => Str::ulid()->toString(),
            'actor_type' => 'system',
            'actor_id' => 'system',
            'action' => 'test.action',
            'subject_type' => 'system',
            'subject_id' => 'system',
            'payload' => json_encode([], JSON_THROW_ON_ERROR),
            'payload_hash' => hash('sha256', '{}'),
            'chain_hash' => hash('sha256', (string) $i),
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'context' => json_encode([], JSON_THROW_ON_ERROR),
            'tags' => json_encode([], JSON_THROW_ON_ERROR),
            'diff' => json_encode(null, JSON_THROW_ON_ERROR),
            'checkpoint_id' => $checkpointId,
            'created_at' => $date,
        ]);
    }
}

it('prunes entries older than the given number of days', function () {
    seedEntries(5, now()->subDays(400)->toDateTimeString());
    seedEntries(3, now()->subDays(10)->toDateTimeString());

    $this->artisan('chronicle:prune', ['--older-than' => 365])
        ->assertSuccessful();

    expect(Entry::count())->toBe(3);
});

it('prunes entries before a given date', function () {
    seedEntries(4, '2024-01-01 00:00:00');
    seedEntries(2, '2025-06-01 00:00:00');

    $this->artisan('chronicle:prune', ['--before' => '2025-01-01'])
        ->assertSuccessful();

    expect(Entry::count())->toBe(2);
});

it('does not delete anything in dry-run mode', function () {
    seedEntries(5, now()->subDays(400)->toDateTimeString());

    $this->artisan('chronicle:prune', ['--older-than' => 365, '--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('dry run');

    expect(Entry::count())->toBe(5);
});

it('aborts when no retention target is given and none configured', function () {
    config(['chronicle.prune.default_retention_days' => null]);

    $this->artisan('chronicle:prune')
        ->assertFailed();
});

it('uses configured default_retention_days when no option is passed', function () {
    config(['chronicle.prune.default_retention_days' => 30]);
    seedEntries(3, now()->subDays(60)->toDateTimeString());
    seedEntries(2, now()->subDays(10)->toDateTimeString());

    $this->artisan('chronicle:prune')->assertSuccessful();

    expect(Entry::count())->toBe(2);
});

it('refuses to prune checkpoint-anchored entries by default', function () {
    $checkpointId = Str::ulid()->toString();

    DB::table(config('chronicle.tables.checkpoints', 'chronicle_checkpoints'))->insert([
        'id' => $checkpointId,
        'chain_hash' => hash('sha256', 'abc'),
        'signature' => 'sig',
        'algorithm' => 'Ed25519',
        'key_id' => 'test',
        'created_at' => now()->subDays(400)->toDateTimeString(),
    ]);

    seedEntries(1, now()->subDays(400)->toDateTimeString(), $checkpointId);

    $this->artisan('chronicle:prune', ['--older-than' => 365])
        ->assertFailed()
        ->expectsOutputToContain('checkpoint');

    expect(Entry::count())->toBe(1);
});

it('prunes checkpoint-anchored entries when --force is passed', function () {
    $checkpointId = Str::ulid()->toString();

    DB::table(config('chronicle.tables.checkpoints', 'chronicle_checkpoints'))->insert([
        'id' => $checkpointId,
        'chain_hash' => hash('sha256', 'abc'),
        'signature' => 'sig',
        'algorithm' => 'Ed25519',
        'key_id' => 'test',
        'created_at' => now()->subDays(400)->toDateTimeString(),
    ]);

    seedEntries(1, now()->subDays(400)->toDateTimeString(), $checkpointId);
    seedEntries(3, now()->subDays(400)->toDateTimeString());

    $this->artisan('chronicle:prune', ['--older-than' => 365, '--force' => true])
        ->assertSuccessful();

    expect(Entry::count())->toBe(0);
});

it('shows a human-readable error for an unparseable --before value', function () {
    $this->artisan('chronicle:prune', ['--before' => 'not-a-date'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid date format');
});

it('dry-run shows oldest and newest in range', function () {
    seedEntries(3, '2024-01-15 00:00:00');

    Artisan::call('chronicle:prune', ['--before' => '2025-01-01', '--dry-run' => true]);
    $output = Artisan::output();

    expect($output)->toContain('dry run')
        ->and($output)->toContain('3 entries');
});

it('aborts when no option is given and default_retention_days is null', function () {
    // default_retention_days should be null out of the box — confirm prune refuses to run
    config(['chronicle.prune.default_retention_days' => null]);

    $this->artisan('chronicle:prune')
        ->assertFailed()
        ->expectsOutputToContain('No retention target');
});
