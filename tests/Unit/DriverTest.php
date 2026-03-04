<?php

use Chronicle\Models\Entry;
use Chronicle\Storage\ArrayDriver;
use Chronicle\Storage\NullDriver;
use Illuminate\Support\Carbon;

function makePending(string $action = 'test.event', array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? str_pad('T', 26, '0', STR_PAD_LEFT),
        'actor_type' => $overrides['actor_type'] ?? 'system',
        'actor_id' => $overrides['actor_id'] ?? 'system',
        'action' => $action,
        'subject_type' => $overrides['subject_type'] ?? 'system',
        'subject_id' => $overrides['subject_id'] ?? 'system',
        'payload' => $overrides['payload'] ?? ['action' => $action],
        'payload_hash' => $overrides['payload_hash'] ?? '',
        'metadata' => $overrides['metadata'] ?? null,
        'context' => $overrides['context'] ?? ['transport' => 'cli', 'sapi' => 'cli'],
        'created_at' => $overrides['created_at'] ?? Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ];
}

describe('NullDriver', function () {
    it('returns a hydrated Entry without persisting anything', function () {
        $driver = new NullDriver;

        $pending = makePending('order.placed');

        $entry = $driver->store($pending);

        expect($entry)
            ->toBeInstanceOf(Entry::class)
            ->and($entry->action)->toBe('order.placed')
            ->and($entry->exists)->toBe(false);
    });

    it('hydrates all fields onto the returned Entry', function () {
        $driver = new NullDriver;

        $pending = makePending('invoice.approved', [
            'actor_type' => 'App\\Models\\User',
            'actor_id' => '22',
            'subject_type' => 'App\\Models\\Invoice',
            'subject_id' => '99',
            'metadata' => ['amount' => 100],
        ]);

        $entry = $driver->store($pending);

        expect($entry->actor_type)->toBe('App\\Models\\User')
            ->and($entry->actor_id)->toBe('22')
            ->and($entry->subject_type)->toBe('App\\Models\\Invoice')
            ->and($entry->subject_id)->toBe('99')
            ->and($entry->metadata['amount'])->toBe(100);
    });

    it('does not write to the database', function () {
        $driver = new NullDriver;

        $driver->store(makePending());

        expect(ArrayDriver::count())->toBe(0);
    });
});

describe('ArrayDriver', function () {
    beforeEach(fn () => ArrayDriver::flush());
    afterEach(fn () => ArrayDriver::flush());

    it('stores entries in memory', function () {
        $driver = new ArrayDriver;

        $driver->store(makePending('order.placed'));

        expect(ArrayDriver::count())->toBe(1)
            ->and(ArrayDriver::all()->first()['action'])->toBe('order.placed');
    });

    it('accumulates multiple entries', function () {
        $driver = new ArrayDriver;

        $driver->store(makePending('order.placed'));
        $driver->store(makePending('invoice.approved'));
        $driver->store(makePending('user.created'));

        expect(ArrayDriver::count())->toBe(3);
    });

    it('returns a hydrated Entry with exists=false (not a real DB row)', function () {
        $driver = new ArrayDriver;

        $entry = $driver->store(makePending());

        expect($entry)->toBeInstanceOf(Entry::class)
            ->and($entry->action)->toBe('test.event');
    });

    it('all() returns a Collection of arrays', function () {
        $driver = new ArrayDriver;

        $driver->store(makePending('a.one'));
        $driver->store(makePending('a.two'));

        $all = ArrayDriver::all();

        expect($all)->toHaveCount(2)
            ->and($all->first())->toBeArray();
    });

    it('flush() clears all stored entries', function () {
        $driver = new ArrayDriver;

        $driver->store(makePending());
        $driver->store(makePending());

        ArrayDriver::flush();

        expect(ArrayDriver::count())->toBe(0);
    });

    it('stores the full payload structure faithfully', function () {
        $driver = new ArrayDriver;

        $pending = makePending('invoice.approved', [
            'actor_type' => 'App\\Models\\User',
            'actor_id' => '5',
            'subject_type' => 'App\\Models\\Invoice',
            'subject_id' => '33',
            'metadata' => ['amount' => 100, 'currency' => 'EUR'],
        ]);

        $driver->store($pending);

        $stored = ArrayDriver::all()->first();

        expect($stored['actor_type'])->toBe('App\\Models\\User')
            ->and($stored['actor_id'])->toBe('5')
            ->and($stored['subject_type'])->toBe('App\\Models\\Invoice')
            ->and($stored['subject_id'])->toBe('33')
            ->and($stored['metadata'])->toBe(['amount' => 100, 'currency' => 'EUR']);
    });
});
