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
