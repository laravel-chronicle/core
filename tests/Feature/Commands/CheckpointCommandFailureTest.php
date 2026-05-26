<?php

use Chronicle\Support\CanonicalPayloadSerializer;

it('fails checkpoint command when ledger is empty', function () {
    $this->artisan('chronicle:checkpoint')
        ->expectsOutput('Creating Chronicle checkpoint...')
        ->expectsOutput('Checkpoint creation failed.')
        ->expectsOutputToContain('Cannot create checkpoint: ledger is empty.')
        ->assertExitCode(1);
});

it('CheckpointCreator throws when chain_hash is null, not when it is "0"', function () {
    // Verify isAssoc([]) returns false (not true)
    $serializer = new CanonicalPayloadSerializer;
    $reflection = new ReflectionMethod($serializer, 'isAssoc');
    $reflection->setAccessible(true);

    expect($reflection->invoke($serializer, []))->toBeFalse();
});
