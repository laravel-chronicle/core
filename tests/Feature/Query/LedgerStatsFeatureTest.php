<?php

declare(strict_types=1);

use Chronicle\Facades\Chronicle;
use Chronicle\Query\LedgerStats;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

it('compute() returns zero total entries on an empty ledger', function () {
    $stats = LedgerStats::compute();

    expect($stats->totalEntries())->toBe(0)
        ->and($stats->isEmpty())->toBeTrue()
        ->and($stats->oldestEntryAt())->toBeNull()
        ->and($stats->newestEntryAt())->toBeNull()
        ->and($stats->checkpointCount())->toBe(0)
        ->and($stats->topActions())->toBe([])
        ->and($stats->dailyActivity())->toBe([]);
});

it('compute() counts entries correctly after recording', function () {
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('invoice.sent')->subject(ref('ledger'))->commit();

    $stats = LedgerStats::compute();

    expect($stats->totalEntries())->toBe(3)
        ->and($stats->isEmpty())->toBeFalse();
});

it('topActions() returns actions sorted by count descending', function () {
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('invoice.sent')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('invoice.sent')->subject(ref('ledger'))->commit();

    $stats = LedgerStats::compute();
    $top = $stats->topActions();

    expect($top[0]['action'])->toBe('order.created')
        ->and($top[0]['count'])->toBe(3)
        ->and($top[1]['action'])->toBe('invoice.sent')
        ->and($top[1]['count'])->toBe(2);
});

it('topActions() returns at most 10 items even with more than 10 distinct actions', function () {
    foreach (range(1, 12) as $i) {
        Chronicle::record()->actor('system')->action("action.$i")->subject(ref('ledger'))->commit();
    }

    $stats = LedgerStats::compute();

    expect($stats->topActions())->toHaveCount(10);
});

it('oldestEntryAt() matches the first entry created_at', function () {
    Carbon::setTestNow('2026-01-01 10:00:00');
    Chronicle::record()->actor('system')->action('first.entry')->subject(ref('ledger'))->commit();

    Carbon::setTestNow('2026-06-01 10:00:00');
    Chronicle::record()->actor('system')->action('last.entry')->subject(ref('ledger'))->commit();
    Carbon::setTestNow();

    $stats = LedgerStats::compute();

    expect($stats->oldestEntryAt()?->format('Y-m-d'))->toBe('2026-01-01')
        ->and($stats->newestEntryAt()?->format('Y-m-d'))->toBe('2026-06-01');
});

it('dailyActivity() contains entries from the last 30 days', function () {
    Chronicle::record()->actor('system')->action('recent.entry')->subject(ref('ledger'))->commit();

    $stats = LedgerStats::compute();

    expect($stats->dailyActivity())->not->toBeEmpty()
        ->and($stats->dailyActivity()[0])->toHaveKey('date')
        ->and($stats->dailyActivity()[0])->toHaveKey('count');
});

it('dailyActivity() includes all entries when no from bound is given', function () {
    Carbon::setTestNow(now()->subDays(31));
    Chronicle::record()->actor('system')->action('old.entry')->subject(ref('ledger'))->commit();
    Carbon::setTestNow();

    $stats = LedgerStats::compute();

    expect($stats->dailyActivity())->not->toBe([]);
});

it('dailyActivity() excludes entries before the from bound', function () {
    Carbon::setTestNow(now()->subDays(31));
    Chronicle::record()->actor('system')->action('old.entry')->subject(ref('ledger'))->commit();
    Carbon::setTestNow();

    $stats = LedgerStats::compute(from: now()->subDays(30)->startOfDay());

    expect($stats->dailyActivity())->toBe([]);
});

it('checkpointCount() reflects created checkpoints', function () {
    Chronicle::record()->actor('system')->action('stats.seed')->subject(ref('ledger'))->commit();

    $this->artisan('chronicle:checkpoint')->assertExitCode(0);

    $stats = LedgerStats::compute();

    expect($stats->checkpointCount())->toBe(1);
});

it('compute() queries the connection configured in chronicle.connection, not the default', function () {
    // The TestCase configures both 'testing' (default) and 'chronicle_testing'
    // as separate in-memory SQLite databases. Migrations run on 'testing' only,
    // so 'chronicle_testing' has no tables.
    //
    // Pointing chronicle.connection to 'chronicle_testing' and calling compute()
    // must throw a QueryException (no such table). If compute() used the default
    // connection instead, it would return silently with zero entries.
    config()->set('chronicle.connection', 'chronicle_testing');

    expect(fn () => LedgerStats::compute())
        ->toThrow(QueryException::class);
});
