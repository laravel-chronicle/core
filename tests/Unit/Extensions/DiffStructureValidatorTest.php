<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidDiffException;
use Chronicle\Pipeline\ExtensionStage;
use Chronicle\Validation\DiffStructureValidator;
use Illuminate\Support\Carbon;

function makeDiffValidatorPending(mixed $diff = null): PendingEntry
{
    return new PendingEntry([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D1',
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '42',
        'action' => 'order.placed',
        'subject_type' => 'App\\Models\\Order',
        'subject_id' => '7',
        'metadata' => [],
        'context' => [],
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
    expect(app(DiffStructureValidator::class)->stage())->toBe(ExtensionStage::VALIDATE);
});

it('has a priority between TagsValidator (-75) and PayloadSerializableValidator (-50)', function () {
    $priority = app(DiffStructureValidator::class)->priority();

    expect($priority)->toBeGreaterThan(-75)->toBeLessThan(-50);
});

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

it('accepts a null diff', function () {
    $entry = makeDiffValidatorPending(null);

    expect(app(DiffStructureValidator::class)->process($entry))->toBe($entry);
});

it('accepts an empty diff array', function () {
    $entry = makeDiffValidatorPending([]);

    expect(app(DiffStructureValidator::class)->process($entry))->toBe($entry);
});

it('accepts a valid single-entry diff', function () {
    $entry = makeDiffValidatorPending(['status' => ['old' => 'pending', 'new' => 'paid']]);

    expect(app(DiffStructureValidator::class)->process($entry))->toBe($entry);
});

it('accepts a valid multi-entry diff', function () {
    $entry = makeDiffValidatorPending([
        'status' => ['old' => 'pending', 'new' => 'paid'],
        'amount' => ['old' => 100, 'new' => 200],
    ]);

    expect(app(DiffStructureValidator::class)->process($entry))->toBe($entry);
});

it('accepts a diff with scalar old and new values', function () {
    $entry = makeDiffValidatorPending([
        'active' => ['old' => false, 'new' => true],
        'count' => ['old' => 0, 'new' => 5],
        'label' => ['old' => null, 'new' => 'ready'],
    ]);

    expect(app(DiffStructureValidator::class)->process($entry))->toBe($entry);
});

it('accepts a diff with nested array values in old and new', function () {
    $entry = makeDiffValidatorPending([
        'address' => ['old' => ['city' => 'London'], 'new' => ['city' => 'Paris']],
    ]);

    expect(app(DiffStructureValidator::class)->process($entry))->toBe($entry);
});

it('returns the same pending entry instance on success', function () {
    $entry = makeDiffValidatorPending(['status' => ['old' => 'a', 'new' => 'b']]);
    $result = app(DiffStructureValidator::class)->process($entry);

    expect($result)->toBeInstanceOf(PendingEntry::class)->toBe($entry);
});

// ---------------------------------------------------------------------------
// Top-level type — diff must be array or null
// ---------------------------------------------------------------------------

it('rejects a string diff', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending('not-an-array'));
})->throws(InvalidDiffException::class, 'must be an array');

it('rejects an integer diff', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending(42));
})->throws(InvalidDiffException::class, 'must be an array');

it('rejects a boolean diff', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending(true));
})->throws(InvalidDiffException::class, 'must be an array');

it('includes the actual type in the top-level exception message', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending('not-an-array'));
})->throws(InvalidDiffException::class, 'string');

// ---------------------------------------------------------------------------
// Entry value must be an array
// ---------------------------------------------------------------------------

it('rejects a diff entry whose value is a string', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending(['status' => 'paid']));
})->throws(InvalidDiffException::class, 'must be an array');

it('rejects a diff entry whose value is null and includes the type in the message', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending(['status' => null]));
})->throws(InvalidDiffException::class, 'null');

it('includes the diff key name in the entry-not-array exception message', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending(['my_field' => 'bad']));
})->throws(InvalidDiffException::class, 'my_field');

// ---------------------------------------------------------------------------
// Missing keys — old and new are both required
// ---------------------------------------------------------------------------

it('rejects an entry missing the old key', function () {
    app(DiffStructureValidator::class)->process(
        makeDiffValidatorPending(['status' => ['new' => 'paid']])
    );
})->throws(InvalidDiffException::class, 'old');

it('rejects an entry missing the new key', function () {
    app(DiffStructureValidator::class)->process(
        makeDiffValidatorPending(['status' => ['old' => 'pending']])
    );
})->throws(InvalidDiffException::class, 'new');

it('rejects an entry missing both keys and reports old first', function () {
    app(DiffStructureValidator::class)->process(
        makeDiffValidatorPending(['status' => []])
    );
})->throws(InvalidDiffException::class, 'old');

// ---------------------------------------------------------------------------
// Extra keys — only old and new are permitted
// ---------------------------------------------------------------------------

it('rejects an entry with an extra key alongside old and new', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending([
        'status' => ['old' => 'pending', 'new' => 'paid', 'by' => 'system'],
    ]));
})->throws(InvalidDiffException::class, 'by');

it('includes the diff key name in the extra-keys exception message', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending([
        'my_field' => ['old' => 'a', 'new' => 'b', 'extra' => 'c'],
    ]));
})->throws(InvalidDiffException::class, 'my_field');

// ---------------------------------------------------------------------------
// Serializable — Closure
// ---------------------------------------------------------------------------

it('rejects a Closure in the old value', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending([
        'status' => ['old' => fn () => 'x', 'new' => 'paid'],
    ]));
})->throws(InvalidDiffException::class, 'old');

it('rejects a Closure in the new value', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending([
        'status' => ['old' => 'pending', 'new' => fn () => 'x'],
    ]));
})->throws(InvalidDiffException::class, 'new');

it('includes the diff key name in the closure exception message', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending([
        'my_field' => ['old' => fn () => 'x', 'new' => 'b'],
    ]));
})->throws(InvalidDiffException::class, 'my_field');

// ---------------------------------------------------------------------------
// Serializable — resource
// ---------------------------------------------------------------------------

it('rejects a resource in the old value', function () {
    $handle = fopen('php://memory', 'r');

    try {
        app(DiffStructureValidator::class)->process(makeDiffValidatorPending([
            'status' => ['old' => $handle, 'new' => 'paid'],
        ]));
    } finally {
        fclose($handle);
    }
})->throws(InvalidDiffException::class, 'old');

it('rejects a resource in the new value', function () {
    $handle = fopen('php://memory', 'r');

    try {
        app(DiffStructureValidator::class)->process(makeDiffValidatorPending([
            'status' => ['old' => 'pending', 'new' => $handle],
        ]));
    } finally {
        fclose($handle);
    }
})->throws(InvalidDiffException::class, 'new');

it('includes the diff key name in the resource exception message', function () {
    $handle = fopen('php://memory', 'r');

    try {
        app(DiffStructureValidator::class)->process(makeDiffValidatorPending([
            'my_field' => ['old' => $handle, 'new' => 'b'],
        ]));
    } finally {
        fclose($handle);
    }
})->throws(InvalidDiffException::class, 'my_field');

// ---------------------------------------------------------------------------
// Serializable — object
// ---------------------------------------------------------------------------

it('rejects an object in the old value', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending([
        'status' => ['old' => new stdClass, 'new' => 'paid'],
    ]));
})->throws(InvalidDiffException::class, 'old');

it('includes the class name in the object exception message', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending([
        'status' => ['old' => new stdClass, 'new' => 'paid'],
    ]));
})->throws(InvalidDiffException::class, 'stdClass');

it('includes the diff key name in the object exception message', function () {
    app(DiffStructureValidator::class)->process(makeDiffValidatorPending([
        'my_field' => ['old' => new stdClass, 'new' => 'b'],
    ]));
})->throws(InvalidDiffException::class, 'my_field');
