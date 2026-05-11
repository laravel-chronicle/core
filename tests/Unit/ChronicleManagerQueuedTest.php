<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Jobs\PersistChronicleEntryJob;
use Chronicle\Storage\QueuedDriver;
use Illuminate\Support\Facades\Queue;

it('dispatches a job instead of writing synchronously when using QueuedDriver', function () {
    Queue::fake();

    app('chronicle')->swapDriver(app(QueuedDriver::class));

    Chronicle::record()
        ->actor(ref('user-1'))
        ->action('invoice.sent')
        ->subject(ref('invoice-1'))
        ->commit();

    Queue::assertPushed(PersistChronicleEntryJob::class);
});

it('dispatches the job to the configured queue name', function () {
    Queue::fake();
    config(['chronicle.queue.name' => 'my-chronicle']);

    app('chronicle')->swapDriver(app(QueuedDriver::class));

    Chronicle::record()
        ->actor(ref('user-1'))
        ->action('invoice.sent')
        ->subject(ref('invoice-1'))
        ->commit();

    Queue::assertPushedOn('my-chronicle', PersistChronicleEntryJob::class);
});
