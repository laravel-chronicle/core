<?php

use Chronicle\Serialization\CanonicalPayloadSerializer;

it('produces deterministic json', function () {
    $serializer = new CanonicalPayloadSerializer;

    $payloadA = [
        'b' => 2,
        'a' => 1,
        'c' => null,
        'd' => 'string',
        'e' => true,
    ];

    $payloadB = [
        'c' => null,
        'd' => 'string',
        'a' => 1,
        'e' => true,
        'b' => 2,
    ];

    $jsonA = $serializer->serialize($payloadA);
    $jsonB = $serializer->serialize($payloadB);

    expect($jsonA)->toBe($jsonB);
});
it('sorts nested arrays', function () {
    $serializer = new CanonicalPayloadSerializer;

    $payload = [
        'metadata' => [
            'b' => 2,
            'a' => 1,
        ],
    ];

    $json = $serializer->serialize($payload);

    expect($json)->toContain('"a":1');
});

it('normalizes datetime objects', function () {
    $serializer = new CanonicalPayloadSerializer;

    $payload = [
        'recorded_at' => now(),
    ];

    $json = $serializer->serialize($payload);

    expect($json)->toContain('T');
});
