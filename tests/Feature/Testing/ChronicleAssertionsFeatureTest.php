<?php

declare(strict_types=1);

use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Storage\ArrayDriver;
use Chronicle\Testing\ChronicleAssertions;

it('Chronicle::fake() returns a ChronicleAssertions instance', function () {
    $fake = Chronicle::fake();
    expect($fake)->toBeInstanceOf(ChronicleAssertions::class);
});

it('Chronicle::fake() swaps the driver to ArrayDriver', function () {
    $fake = Chronicle::fake();

    Chronicle::record()
        ->actor('system')
        ->action('invoice.sent')
        ->subject(ref('invoice-1'))
        ->commit();

    // Nothing in the real DB
    expect(Entry::count())->toBe(0);

    // Fake captured it
    $fake->assertRecorded(fn ($e) => $e['action'] === 'invoice.sent');
});

it('Chronicle::fake() flushes previous entries', function () {
    $fake1 = Chronicle::fake();
    Chronicle::record()->actor('system')->action('a.created')->subject(ref('x'))->commit();
    $fake1->assertRecordedCount(1);

    $fake2 = Chronicle::fake();
    $fake2->assertNothingRecorded();
});

it('assertRecorded inspects entry fields', function () {
    $fake = Chronicle::fake();

    Chronicle::record()
        ->actor('system')
        ->action('order.placed')
        ->subject(ref('order-99'))
        ->tags(['billing', 'express'])
        ->commit();

    $fake->assertRecorded(function (array $entry): bool {
        return $entry['action'] === 'order.placed'
            && in_array('billing', $entry['tags'], true);
    });
});

it('assertNothingRecorded passes before any commit', function () {
    $fake = Chronicle::fake();
    $fake->assertNothingRecorded();
});

it('restore() clears the ArrayDriver binding so the next resolution uses the real driver', function () {
    $fake = Chronicle::fake();

    Chronicle::record()
        ->actor('system')
        ->action('test.event')
        ->subject(ref('x'))
        ->commit();

    $fake->assertRecordedCount(1);

    $fake->restore();

    // After restore, the container binding is cleared.
    // Re-resolving the driver should NOT be ArrayDriver.
    $driver = app(StorageDriver::class);
    expect($driver)->not->toBeInstanceOf(ArrayDriver::class);
});
