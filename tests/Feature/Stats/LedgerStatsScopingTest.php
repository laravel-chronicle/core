<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Query\LedgerStats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function insertRawEntry(string $date, string $action = 'raw.action'): void
{
    DB::table(config('chronicle.tables.entries', 'chronicle_entries'))->insert([
        'id' => Str::ulid()->toString(),
        'actor_type' => 'system',
        'actor_id' => 'system',
        'action' => $action,
        'subject_type' => 'system',
        'subject_id' => 'system',
        'payload' => json_encode([], JSON_THROW_ON_ERROR),
        'payload_hash' => hash('sha256', '{}'),
        'chain_hash' => hash('sha256', $date),
        'metadata' => json_encode([], JSON_THROW_ON_ERROR),
        'context' => json_encode([], JSON_THROW_ON_ERROR),
        'tags' => json_encode([], JSON_THROW_ON_ERROR),
        'diff' => json_encode(null, JSON_THROW_ON_ERROR),
        'created_at' => $date,
    ]);
}

it('compute() with no bounds returns all entries', function () {
    Chronicle::record()->actor('system')->action('a.created')->subject(ref('x'))->commit();
    Chronicle::record()->actor('system')->action('b.created')->subject(ref('x'))->commit();

    $stats = LedgerStats::compute();

    expect($stats->totalEntries())->toBe(2);
});

it('compute() respects a from bound', function () {
    insertRawEntry('2024-01-01 00:00:00', 'old.action');
    Chronicle::record()->actor('system')->action('new.action')->subject(ref('x'))->commit();

    $stats = LedgerStats::compute(from: Carbon::parse('2025-01-01'));

    expect($stats->totalEntries())->toBe(1);
});

it('compute() respects a to bound', function () {
    Chronicle::record()->actor('system')->action('a.created')->subject(ref('x'))->commit();
    insertRawEntry('2099-01-01 00:00:00', 'future.action');

    $stats = LedgerStats::compute(to: Carbon::now());

    expect($stats->totalEntries())->toBe(1);
});

it('compute() respects both from and to bounds', function () {
    insertRawEntry('2023-01-01 00:00:00', 'too.old');
    Chronicle::record()->actor('system')->action('in.range')->subject(ref('x'))->commit();
    insertRawEntry('2099-01-01 00:00:00', 'too.new');

    $stats = LedgerStats::compute(
        from: Carbon::parse('2025-01-01'),
        to: Carbon::parse('2099-01-01')->subSecond(),
    );

    expect($stats->totalEntries())->toBe(1);
});

it('topActions() is scoped by from/to', function () {
    insertRawEntry('2023-01-01 00:00:00', 'old.action');
    Chronicle::record()->actor('system')->action('new.action')->subject(ref('x'))->commit();

    $stats = LedgerStats::compute(from: Carbon::parse('2025-01-01'));

    $actions = collect($stats->topActions())->pluck('action')->all();

    expect($actions)->toContain('new.action')
        ->and($actions)->not->toContain('old.action');
});

it('dailyActivity() is scoped to the full from/to window, not capped at 30 days', function () {
    // Insert an entry 60 days ago — outside the hardcoded 30-day window
    insertRawEntry(
        Carbon::now()->subDays(60)->format('Y-m-d H:i:s'),
        'old.action'
    );

    $stats = LedgerStats::compute(from: Carbon::now()->subDays(90));

    $dates = collect($stats->dailyActivity())->pluck('date')->all();

    expect($dates)->not->toBeEmpty('dailyActivity should include entries from 60 days ago');
});

it('checkpointCount() is scoped by the from/to window', function () {
    // chronicle:checkpoint requires at least one entry to exist
    Chronicle::record()->actor('system')->action('cp.test')->subject(ref('x'))->commit();
    $this->artisan('chronicle:checkpoint')->assertExitCode(0);

    $stats = LedgerStats::compute(
        from: Carbon::now()->addMinute(),  // window starts in the future
        to: Carbon::now()->addHour(),
    );

    expect($stats->checkpointCount())->toBe(0);
});
