<?php

declare(strict_types=1);

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

it('reads the connection from chronicle.connection not chronicle.database.connection', function () {
    // chronicle.database.connection does not exist - if the job reads it, it gets null
    // and uses the default connection. Verify the job reads the correct key.
    config(['chronicle.connection' => 'chronicle_testing']);
    config(['chronicle.database.connection' => null]); // ensure the wrong key is null

    // The job's handle() reads the connection via config; we verify the correct key
    // is referenced by checking the config key is present in the job source.
    $source = file_get_contents(__DIR__.'/../../../src/Jobs/PersistChronicleEntryJob.php');
    expect($source)->toContain("Config::get('chronicle.connection')")
        ->and($source)->not->toContain("Config::get('chronicle.database.connection')");
});
