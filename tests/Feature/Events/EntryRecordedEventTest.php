<?php

use Chronicle\Entry\Entry;
use Chronicle\Events\EntryRecorded;
use Chronicle\Facades\Chronicle;
use Chronicle\Storage\NullDriver;
use Illuminate\Support\Facades\Event;

it('fires EntryRecorded after a successful commit', function () {
    Event::fake([EntryRecorded::class]);

    Chronicle::record()
        ->actor('system')
        ->action('invoice.sent')
        ->subject(ref('invoice-1'))
        ->commit();

    Event::assertDispatched(EntryRecorded::class, function (EntryRecorded $event): bool {
        return $event->entry->action === 'invoice.sent';
    });
});

it('EntryRecorded event carries a persisted Entry model', function () {
    $fired = null;

    Event::listen(EntryRecorded::class, function (EntryRecorded $event) use (&$fired): void {
        $fired = $event->entry;
    });

    Chronicle::record()
        ->actor('system')
        ->action('order.placed')
        ->subject(ref('order-1'))
        ->commit();

    expect($fired)->toBeInstanceOf(Entry::class)
        ->and($fired->exists)->toBeTrue()
        ->and($fired->action)->toBe('order.placed');
});

it('does not fire EntryRecorded when NullDriver is active', function () {
    Event::fake([EntryRecorded::class]);
    app('chronicle')->swapDriver(app(NullDriver::class));

    Chronicle::record()
        ->actor('system')
        ->action('invoice.sent')
        ->subject(ref('invoice-1'))
        ->commit();

    Event::assertNotDispatched(EntryRecorded::class);
});
