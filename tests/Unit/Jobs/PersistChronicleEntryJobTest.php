<?php

use Chronicle\Jobs\PersistChronicleEntryJob;

it('has tries set to 1', function () {
    $job = new PersistChronicleEntryJob(validEntryPayload());
    expect($job->tries)->toBe(1);
});

it('stores the serialized attributes on construction', function () {
    $payload = validEntryPayload();
    $job = new PersistChronicleEntryJob($payload);

    // Reflect to check stored attributes (they're protected)
    $reflection = new ReflectionProperty($job, 'attributes');
    $reflection->setAccessible(true);

    expect($reflection->getValue($job))->toBe($payload);
});
