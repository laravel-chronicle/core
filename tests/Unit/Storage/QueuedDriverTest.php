<?php

declare(strict_types=1);

use Chronicle\Jobs\PersistChronicleEntryJob;
use Chronicle\Storage\QueuedDriver;

it('has tries set to 1 on the dispatched job', function () {
    $job = new PersistChronicleEntryJob([]);
    expect($job->tries)->toBe(1);
});

it('store() throws LogicException because dispatch is handled by ChronicleManager', function () {
    $driver = new QueuedDriver;
    expect(fn () => $driver->store(validEntryPayload()))
        ->toThrow(LogicException::class, 'QueuedDriver::store() must not be called directly');
});
