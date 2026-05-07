<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\UnserializablePayloadException;
use Chronicle\Pipeline\ExtensionStage;
use Chronicle\Validation\PayloadSerializableValidator;
use Illuminate\Support\Carbon;

function makePayloadValidatorPending(
    array $metadata = [],
    array $context = [],
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

// ---------------------------------------------------------------------------
// Stage and priority
// ---------------------------------------------------------------------------

it('runs in the validate stage', function () {
    expect(app(PayloadSerializableValidator::class)->stage())->toBe(ExtensionStage::VALIDATE);
});

it('has a priority after ActionValidator (-100)', function () {
    $priority = app(PayloadSerializableValidator::class)->priority();

    expect($priority)->toBeGreaterThan(-100)->toBeLessThan(0);
});

// ---------------------------------------------------------------------------
// Happy path — allowed value types
// ---------------------------------------------------------------------------

it('accepts an empty metadata and context', function () {
    $entry = makePayloadValidatorPending();

    expect(app(PayloadSerializableValidator::class)->process($entry))->toBe($entry);
});

it('accepts metadata with scalar values', function () {
    $entry = makePayloadValidatorPending(metadata: [
        'amount' => 9900,
        'currency' => 'GBP',
        'refund' => false,
        'note' => null,
        'rate' => 1.5,
    ]);

    expect(app(PayloadSerializableValidator::class)->process($entry))->toBe($entry);
});

it('accepts context with scalar values', function () {
    $entry = makePayloadValidatorPending(context: [
        'ip' => '127.0.0.1',
        'request_id' => 'abc-123',
    ]);

    expect(app(PayloadSerializableValidator::class)->process($entry))->toBe($entry);
});

it('accepts metadata with nested arrays', function () {
    $entry = makePayloadValidatorPending(metadata: [
        'items' => [
            ['sku' => 'ABC', 'qty' => 2],
            ['sku' => 'XYZ', 'qty' => 1],
        ],
    ]);

    expect(app(PayloadSerializableValidator::class)->process($entry))->toBe($entry);
});

it('accepts a diff with scalar old and new values', function () {
    $entry = makePayloadValidatorPending(diff: [
        'status' => ['old' => 'draft', 'new' => 'published'],
    ]);

    expect(app(PayloadSerializableValidator::class)->process($entry))->toBe($entry);
});

it('returns the same pending entry instance on success', function () {
    $entry = makePayloadValidatorPending(metadata: ['key' => 'value']);
    $result = app(PayloadSerializableValidator::class)->process($entry);

    expect($result)->toBeInstanceOf(PendingEntry::class)->toBe($entry);
});

// ---------------------------------------------------------------------------
// Closures
// ---------------------------------------------------------------------------

it('rejects a closure in metadata', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(metadata: ['fn' => fn () => 'x'])
    );
})->throws(UnserializablePayloadException::class, 'must not contain closures');

it('rejects a closure nested inside metadata', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(metadata: ['nested' => ['deep' => fn () => 'x']])
    );
})->throws(UnserializablePayloadException::class, 'must not contain closures');

it('rejects a closure in context', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(context: ['fn' => fn () => 'x'])
    );
})->throws(UnserializablePayloadException::class, 'must not contain closures');

it('rejects a closure in diff', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(diff: ['field' => ['old' => fn () => 'x', 'new' => 'val']])
    );
})->throws(UnserializablePayloadException::class, 'must not contain closures');

// ---------------------------------------------------------------------------
// Resources
// ---------------------------------------------------------------------------

it('rejects a resource in metadata', function () {
    $handle = fopen('php://memory', 'r');

    try {
        app(PayloadSerializableValidator::class)->process(
            makePayloadValidatorPending(metadata: ['file' => $handle])
        );
    } finally {
        fclose($handle);
    }
})->throws(UnserializablePayloadException::class, 'must not contain resources');

it('rejects a resource nested inside metadata', function () {
    $handle = fopen('php://memory', 'r');

    try {
        app(PayloadSerializableValidator::class)->process(
            makePayloadValidatorPending(metadata: ['meta' => ['stream' => $handle]])
        );
    } finally {
        fclose($handle);
    }
})->throws(UnserializablePayloadException::class, 'must not contain resources');

it('rejects a resource in context', function () {
    $handle = fopen('php://memory', 'r');

    try {
        app(PayloadSerializableValidator::class)->process(
            makePayloadValidatorPending(context: ['stream' => $handle])
        );
    } finally {
        fclose($handle);
    }
})->throws(UnserializablePayloadException::class, 'must not contain resources');

it('rejects a resource in diff', function () {
    $handle = fopen('php://memory', 'r');

    try {
        app(PayloadSerializableValidator::class)->process(
            makePayloadValidatorPending(diff: ['field' => ['old' => $handle, 'new' => 'val']])
        );
    } finally {
        fclose($handle);
    }
})->throws(UnserializablePayloadException::class, 'must not contain resources');

// ---------------------------------------------------------------------------
// Objects — all rejected regardless of serializability
// ---------------------------------------------------------------------------

it('rejects a stdClass object in metadata', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(metadata: ['data' => new stdClass])
    );
})->throws(UnserializablePayloadException::class, 'must not contain objects');

it('includes the class name in the object exception message', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(metadata: ['model' => new stdClass])
    );
})->throws(UnserializablePayloadException::class, 'stdClass');

it('rejects a JsonSerializable object in metadata', function () {
    $obj = new class implements JsonSerializable
    {
        public function jsonSerialize(): array
        {
            return ['ok' => true];
        }
    };

    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(metadata: ['data' => $obj])
    );
})->throws(UnserializablePayloadException::class, 'must not contain objects');

it('rejects an object nested inside metadata', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(metadata: ['wrapper' => ['inner' => new stdClass]])
    );
})->throws(UnserializablePayloadException::class, 'must not contain objects');

it('rejects an object in context', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(context: ['obj' => new stdClass])
    );
})->throws(UnserializablePayloadException::class, 'must not contain objects');

it('rejects an object in diff', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(diff: ['field' => ['old' => new stdClass, 'new' => 'val']])
    );
})->throws(UnserializablePayloadException::class, 'must not contain objects');

// ---------------------------------------------------------------------------
// Non-serializable scalars (INF / NAN) — caught by json_encode catch-all
// ---------------------------------------------------------------------------

it('rejects INF in metadata', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(metadata: ['value' => INF])
    );
})->throws(UnserializablePayloadException::class);

it('rejects NAN in metadata', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(metadata: ['value' => NAN])
    );
})->throws(UnserializablePayloadException::class);

it('rejects INF nested inside metadata', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(metadata: ['nested' => ['value' => INF]])
    );
})->throws(UnserializablePayloadException::class);

it('rejects INF in context', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(context: ['value' => INF])
    );
})->throws(UnserializablePayloadException::class);

it('rejects a non-array diff containing INF', function () {
    app(PayloadSerializableValidator::class)->process(
        makePayloadValidatorPending(diff: INF)
    );
})->throws(UnserializablePayloadException::class);
