<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidPayloadSizeException;
use Chronicle\Pipeline\ExtensionStage;
use Chronicle\Validation\PayloadSizeValidator;
use Illuminate\Support\Carbon;

function makePayloadSizePending(
    array $metadata = [],
    mixed $context = null,
    mixed $diff = null,
): PendingEntry {
    return new PendingEntry([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D1',
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '42',
        'action' => 'order.placed',
        'subject_type' => 'App\\Models\\Order',
        'subject_id' => '7',
        'metadata' => $metadata,
        'context' => $context,
        'diff' => $diff,
        'tags' => [],
        'correlation_id' => null,
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ]);
}

/**
 * Returns the byte length of the combined payload fields when JSON-encoded,
 * matching exactly how PayloadSizeValidator measures size.
 */
function encodedPayloadSize(array $metadata = [], mixed $context = null, mixed $diff = null): int
{
    return strlen((string) json_encode([
        'metadata' => $metadata,
        'context' => $context,
        'diff' => $diff,
    ]));
}

// ---------------------------------------------------------------------------
// Stage and priority
// ---------------------------------------------------------------------------

it('runs in the validate stage', function () {
    expect(app(PayloadSizeValidator::class)->stage())->toBe(ExtensionStage::VALIDATE);
});

it('has a priority between PayloadSerializableValidator (-50) and 0', function () {
    $priority = app(PayloadSizeValidator::class)->priority();

    expect($priority)->toBeGreaterThan(-50)->toBeLessThan(0);
});

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

it('accepts an entry with empty payload fields', function () {
    $entry = makePayloadSizePending();

    expect(app(PayloadSizeValidator::class)->process($entry))->toBe($entry);
});

it('accepts a payload under the configured limit', function () {
    config(['chronicle.validation.max_payload_size' => 1000]);

    $entry = makePayloadSizePending(metadata: ['key' => 'value']);

    expect(app(PayloadSizeValidator::class)->process($entry))->toBe($entry);
});

it('accepts a payload exactly at the configured limit', function () {
    $metadata = ['key' => 'value'];
    $limit = encodedPayloadSize($metadata);
    config(['chronicle.validation.max_payload_size' => $limit]);

    $entry = makePayloadSizePending(metadata: $metadata);

    expect(app(PayloadSizeValidator::class)->process($entry))->toBe($entry);
});

it('returns the same pending entry instance on success', function () {
    config(['chronicle.validation.max_payload_size' => 1000]);
    $entry = makePayloadSizePending(metadata: ['key' => 'value']);
    $result = app(PayloadSizeValidator::class)->process($entry);

    expect($result)->toBeInstanceOf(PendingEntry::class)->toBe($entry);
});

// ---------------------------------------------------------------------------
// Rejection
// ---------------------------------------------------------------------------

it('rejects a payload one byte over the configured limit', function () {
    $metadata = ['key' => 'value'];
    $limit = encodedPayloadSize($metadata) - 1;
    config(['chronicle.validation.max_payload_size' => $limit]);

    app(PayloadSizeValidator::class)->process(makePayloadSizePending(metadata: $metadata));
})->throws(InvalidPayloadSizeException::class);

it('rejects when metadata alone exceeds the limit', function () {
    $metadata = ['data' => str_repeat('x', 200)];
    config(['chronicle.validation.max_payload_size' => 50]);

    app(PayloadSizeValidator::class)->process(makePayloadSizePending(metadata: $metadata));
})->throws(InvalidPayloadSizeException::class);

it('rejects when context alone exceeds the limit', function () {
    $context = ['ip' => str_repeat('x', 200)];
    config(['chronicle.validation.max_payload_size' => 50]);

    app(PayloadSizeValidator::class)->process(makePayloadSizePending(context: $context));
})->throws(InvalidPayloadSizeException::class);

it('rejects when diff alone exceeds the limit', function () {
    $diff = ['field' => ['old' => str_repeat('x', 200), 'new' => str_repeat('y', 200)]];
    config(['chronicle.validation.max_payload_size' => 50]);

    app(PayloadSizeValidator::class)->process(makePayloadSizePending(diff: $diff));
})->throws(InvalidPayloadSizeException::class);

// ---------------------------------------------------------------------------
// Exception message content
// ---------------------------------------------------------------------------

it('includes the actual byte count in the exception message', function () {
    $metadata = ['key' => 'value'];
    $actualSize = encodedPayloadSize($metadata);
    $limit = $actualSize - 1;
    config(['chronicle.validation.max_payload_size' => $limit]);

    app(PayloadSizeValidator::class)->process(makePayloadSizePending(metadata: $metadata));
})->throws(InvalidPayloadSizeException::class, (string) encodedPayloadSize(['key' => 'value']));

it('includes the max byte count in the exception message', function () {
    $metadata = ['key' => 'value'];
    $limit = encodedPayloadSize($metadata) - 1;
    config(['chronicle.validation.max_payload_size' => $limit]);

    app(PayloadSizeValidator::class)->process(makePayloadSizePending(metadata: $metadata));
})->throws(InvalidPayloadSizeException::class, (string) (encodedPayloadSize(['key' => 'value']) - 1));
