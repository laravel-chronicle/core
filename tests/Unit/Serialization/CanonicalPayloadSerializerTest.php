<?php

declare(strict_types=1);

use Chronicle\Support\CanonicalPayloadSerializer;

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

it('casts Stringable objects to string', function () {
    $serializer = new CanonicalPayloadSerializer;

    $obj = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'hello';
        }
    };

    $result = $serializer->normalize($obj);
    expect($result)->toBe('hello');
});

it('casts backed enums to their value', function () {
    $serializer = new CanonicalPayloadSerializer;

    // Use a simple local-backed enum
    enum Color: string
    {
        case Red = 'red';
    }

    $result = $serializer->normalize(Color::Red);
    expect($result)->toBe('red');
});

it('uses the name for unit enums', function () {
    $serializer = new CanonicalPayloadSerializer;

    enum Status
    {
        case Active;
    }

    $result = $serializer->normalize(Status::Active);
    expect($result)->toBe('Active');
});

it('throws for unhandled object types', function () {
    $serializer = new CanonicalPayloadSerializer;
    $serializer->normalize(new stdClass);
})->throws(UnexpectedValueException::class);
