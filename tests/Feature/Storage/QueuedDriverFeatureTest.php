<?php

declare(strict_types=1);

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Jobs\PersistChronicleEntryJob;
use Chronicle\Storage\QueuedDriver;
use Illuminate\Support\Facades\Queue;

it('does not write to the database synchronously when the queued driver is active', function () {
    Queue::fake();

    app('chronicle')->swapDriver(app(QueuedDriver::class));

    Chronicle::record()
        ->actor(ref('user-1'))
        ->action('invoice.sent')
        ->subject(ref('invoice-99'))
        ->commit();

    expect(Entry::count())->toBe(0);
    Queue::assertPushed(PersistChronicleEntryJob::class);
});

it('persists the entry when the job is processed synchronously', function () {
    config(['queue.default' => 'sync']);

    app('chronicle')->swapDriver(app(QueuedDriver::class));

    Chronicle::record()
        ->actor(ref('user-1'))
        ->action('invoice.sent')
        ->subject(ref('invoice-99'))
        ->commit();

    expect(Entry::count())->toBe(1)
        ->and(Entry::first()->action)->toBe('invoice.sent');
});

it('computes a valid chain hash when the job runs synchronously', function () {
    config(['queue.default' => 'sync']);

    app('chronicle')->swapDriver(app(QueuedDriver::class));

    Chronicle::record()->actor(ref('u1'))->action('a.created')->subject(ref('s1'))->commit();
    Chronicle::record()->actor(ref('u1'))->action('a.updated')->subject(ref('s1'))->commit();

    $entries = Entry::orderBy('id')->get();
    expect($entries)->toHaveCount(2);

    $first = $entries[0];
    $second = $entries[1];

    $expectedSecondChain = hash('sha256', $first->chain_hash.$second->payload_hash);
    expect($second->chain_hash)->toBe($expectedSecondChain);
});
