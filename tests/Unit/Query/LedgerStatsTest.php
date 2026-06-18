<?php

declare(strict_types=1);

use Chronicle\Query\LedgerStats;
use Illuminate\Support\Carbon;

it('totalEntries() returns the count', function () {
    $stats = new LedgerStats(
        totalEntries: 42,
        oldestEntryAt: null,
        newestEntryAt: null,
        checkpointCount: 0,
        topActions: [],
        dailyActivity: [],
    );

    expect($stats->totalEntries())->toBe(42);
});

it('isEmpty() returns true when totalEntries is zero', function () {
    $stats = new LedgerStats(0, null, null, 0, [], []);

    expect($stats->isEmpty())->toBeTrue();
});

it('isEmpty() returns false when entries exist', function () {
    $stats = new LedgerStats(1, null, null, 0, [], []);

    expect($stats->isEmpty())->toBeFalse();
});

it('oldestEntryAt() returns null when no entries', function () {
    $stats = new LedgerStats(0, null, null, 0, [], []);

    expect($stats->oldestEntryAt())->toBeNull();
});

it('newestEntryAt() returns null when no entries', function () {
    $stats = new LedgerStats(0, null, null, 0, [], []);

    expect($stats->newestEntryAt())->toBeNull();
});

it('oldestEntryAt() and newestEntryAt() return CarbonInterface when set', function () {
    $oldest = Carbon::parse('2026-01-01 00:00:00', 'UTC');
    $newest = Carbon::parse('2026-05-07 12:00:00', 'UTC');

    $stats = new LedgerStats(10, $oldest, $newest, 0, [], []);

    expect($stats->oldestEntryAt())->toEqual($oldest)
        ->and($stats->newestEntryAt())->toEqual($newest);
});

it('checkpointCount() returns the count', function () {
    $stats = new LedgerStats(0, null, null, 14, [], []);

    expect($stats->checkpointCount())->toBe(14);
});

it('topActions() returns at most 10 items', function () {
    $actions = array_map(
        fn (int $i) => ['action' => "action.$i", 'count' => $i],
        range(1, 15)
    );

    $stats = new LedgerStats(100, null, null, 0, array_slice($actions, 0, 10), []);

    expect($stats->topActions())->toHaveCount(10);
});

it('topActions() each item has action and count keys', function () {
    $stats = new LedgerStats(
        totalEntries: 5,
        oldestEntryAt: null,
        newestEntryAt: null,
        checkpointCount: 0,
        topActions: [['action' => 'order.created', 'count' => 5]],
        dailyActivity: [],
    );

    $first = $stats->topActions()[0];

    expect($first)->toHaveKey('action')
        ->and($first)->toHaveKey('count')
        ->and($first['action'])->toBe('order.created')
        ->and($first['count'])->toBe(5);
});

it('dailyActivity() each item has date and count keys', function () {
    $stats = new LedgerStats(
        totalEntries: 3,
        oldestEntryAt: null,
        newestEntryAt: null,
        checkpointCount: 0,
        topActions: [],
        dailyActivity: [['date' => '2026-05-07', 'count' => 3]],
    );

    $first = $stats->dailyActivity()[0];

    expect($first)->toHaveKey('date')
        ->and($first)->toHaveKey('count')
        ->and($first['date'])->toBe('2026-05-07')
        ->and($first['count'])->toBe(3);
});
