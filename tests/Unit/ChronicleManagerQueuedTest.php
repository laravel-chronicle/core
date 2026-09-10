<?php

declare(strict_types=1);

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

it('dispatches a FIFO queue name unchanged', function () {
    Queue::fake();
    config(['chronicle.queue.name' => 'chronicle.fifo']);

    app('chronicle')->swapDriver(app(QueuedDriver::class));

    Chronicle::record()
        ->actor(ref('user-1'))
        ->action('invoice.sent')
        ->subject(ref('invoice-1'))
        ->commit();

    Queue::assertPushedOn('chronicle.fifo', PersistChronicleEntryJob::class);
});

it('dispatches to the connection default queue when the queue name is empty', function () {
    Queue::fake();
    config(['chronicle.queue.name' => '']);

    app('chronicle')->swapDriver(app(QueuedDriver::class));

    Chronicle::record()
        ->actor(ref('user-1'))
        ->action('invoice.sent')
        ->subject(ref('invoice-1'))
        ->commit();

    // A null queue on the job lets the connection resolve its own default - on
    // Laravel Cloud, the environment's default managed queue.
    Queue::assertPushed(PersistChronicleEntryJob::class, fn ($job) => $job->queue === null);
});

it('dispatches to the connection default queue when the queue name is unusable', function (mixed $configured) {
    Queue::fake();
    config(['chronicle.queue.name' => $configured]);

    app('chronicle')->swapDriver(app(QueuedDriver::class));

    Chronicle::record()
        ->actor(ref('user-1'))
        ->action('invoice.sent')
        ->subject(ref('invoice-1'))
        ->commit();

    // A non-string queue name must not reach onQueue(): SQS would coerce a bool
    // to a falsy queue and silently misroute the entry.
    Queue::assertPushed(PersistChronicleEntryJob::class, fn ($job) => $job->queue === null);
})->with([
    'null' => [null],
    'false' => [false],
    'true' => [true],
    'integer' => [42],
]);

it('ignores an unusable queue connection rather than misrouting the entry', function (mixed $configured) {
    Queue::fake();
    config(['chronicle.queue.connection' => $configured]);

    app('chronicle')->swapDriver(app(QueuedDriver::class));

    Chronicle::record()
        ->actor(ref('user-1'))
        ->action('invoice.sent')
        ->subject(ref('invoice-1'))
        ->commit();

    Queue::assertPushed(PersistChronicleEntryJob::class, fn ($job) => $job->connection === null);
})->with([
    'false' => [false],
    'integer' => [42],
]);

it('dispatches the job on the configured queue connection', function () {
    Queue::fake();
    config(['chronicle.queue.connection' => 'cloud']);

    app('chronicle')->swapDriver(app(QueuedDriver::class));

    Chronicle::record()
        ->actor(ref('user-1'))
        ->action('invoice.sent')
        ->subject(ref('invoice-1'))
        ->commit();

    Queue::assertPushed(PersistChronicleEntryJob::class, fn ($job) => $job->connection === 'cloud');
});
