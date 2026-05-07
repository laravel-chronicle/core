<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidCorrelationIdException;
use Chronicle\Pipeline\ExtensionStage;
use Chronicle\Validation\CorrelationValidator;
use Illuminate\Support\Carbon;

function makeCorrelationValidatorPending(mixed $correlationId = null): PendingEntry
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
        'diff' => null,
        'tags' => [],
        'correlation_id' => $correlationId,
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ]);
}

// ---------------------------------------------------------------------------
// Stage and priority
// ---------------------------------------------------------------------------

it('runs in the validate stage', function () {
    expect(app(CorrelationValidator::class)->stage())->toBe(ExtensionStage::VALIDATE);
});

it('has a priority between ActionValidator (-100) and TagLimitValidator (-80)', function () {
    $priority = app(CorrelationValidator::class)->priority();

    expect($priority)->toBeGreaterThan(-100)->toBeLessThan(-80);
});

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

it('accepts a null correlation id', function () {
    $entry = makeCorrelationValidatorPending();

    expect(app(CorrelationValidator::class)->process($entry))->toBe($entry);
});

it('accepts a valid string correlation id', function () {
    $entry = makeCorrelationValidatorPending('req-abc-123');

    expect(app(CorrelationValidator::class)->process($entry))->toBe($entry);
});

it('accepts a uuid-style correlation id', function () {
    $entry = makeCorrelationValidatorPending('550e8400-e29b-41d4-a716-446655440000');

    expect(app(CorrelationValidator::class)->process($entry))->toBe($entry);
});

it('accepts a correlation id exactly at the max length', function () {
    config()->set('chronicle.validation.correlation_id_max_length', 10);

    $entry = makeCorrelationValidatorPending('1234567890');

    expect(app(CorrelationValidator::class)->process($entry))->toBe($entry);
});

it('returns the same pending entry instance on success', function () {
    $entry = makeCorrelationValidatorPending('req-abc-123');
    $result = app(CorrelationValidator::class)->process($entry);

    expect($result)->toBeInstanceOf(PendingEntry::class)->toBe($entry);
});

// ---------------------------------------------------------------------------
// Must be string (when not null)
// ---------------------------------------------------------------------------

it('rejects an integer correlation id', function () {
    app(CorrelationValidator::class)->process(makeCorrelationValidatorPending(42));
})->throws(InvalidCorrelationIdException::class, 'must be a string');

it('rejects a boolean correlation id', function () {
    app(CorrelationValidator::class)->process(makeCorrelationValidatorPending(true));
})->throws(InvalidCorrelationIdException::class, 'must be a string');

it('rejects an array correlation id', function () {
    app(CorrelationValidator::class)->process(makeCorrelationValidatorPending([]));
})->throws(InvalidCorrelationIdException::class, 'must be a string');

it('includes the actual type in the must-be-string exception message', function () {
    app(CorrelationValidator::class)->process(makeCorrelationValidatorPending(42));
})->throws(InvalidCorrelationIdException::class, 'int');

// ---------------------------------------------------------------------------
// Must not be blank
// ---------------------------------------------------------------------------

it('rejects an empty string correlation id', function () {
    app(CorrelationValidator::class)->process(makeCorrelationValidatorPending(''));
})->throws(InvalidCorrelationIdException::class, 'must not be blank');

it('rejects a whitespace-only correlation id', function () {
    app(CorrelationValidator::class)->process(makeCorrelationValidatorPending('   '));
})->throws(InvalidCorrelationIdException::class, 'must not be blank');

// ---------------------------------------------------------------------------
// Must respect max length
// ---------------------------------------------------------------------------

it('rejects a correlation id that exceeds the configured max length', function () {
    config()->set('chronicle.validation.correlation_id_max_length', 10);

    app(CorrelationValidator::class)->process(makeCorrelationValidatorPending('12345678901'));
})->throws(InvalidCorrelationIdException::class, 'exceeds the maximum length');

it('includes the offending value in the max-length exception message', function () {
    config()->set('chronicle.validation.correlation_id_max_length', 5);

    app(CorrelationValidator::class)->process(makeCorrelationValidatorPending('toolong'));
})->throws(InvalidCorrelationIdException::class, 'toolong');

it('reads max length from config', function () {
    config()->set('chronicle.validation.correlation_id_max_length', 3);

    app(CorrelationValidator::class)->process(makeCorrelationValidatorPending('abcd'));
})->throws(InvalidCorrelationIdException::class);
