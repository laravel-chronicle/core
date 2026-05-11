<?php

use Chronicle\Jobs\PersistChronicleEntryJob;
use Chronicle\Storage\QueuedDriver;
use Illuminate\Support\Facades\Queue;

it('dispatches PersistChronicleEntryJob when store() is called', function () {
    Queue::fake();

    $driver = app(QueuedDriver::class);
    $driver->store(validEntryPayload());

    Queue::assertPushed(PersistChronicleEntryJob::class);
});

it('dispatches the job to the configured chronicle queue', function () {
    Queue::fake();
    config(['chronicle.queue.name' => 'my-chronicle-queue']);

    $driver = app(QueuedDriver::class);
    $driver->store(validEntryPayload());

    Queue::assertPushedOn('my-chronicle-queue', PersistChronicleEntryJob::class);
});

it('returns an Entry with exists = false', function () {
    Queue::fake();

    $driver = app(QueuedDriver::class);
    $entry = $driver->store(validEntryPayload());

    expect($entry->exists)->toBeFalse();
});

it('has tries set to 1 on the dispatched job', function () {
    $job = new PersistChronicleEntryJob([]);
    expect($job->tries)->toBe(1);
});
