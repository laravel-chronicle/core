<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidTagsException;
use Chronicle\Pipeline\ExtensionStage;
use Chronicle\Validation\TagsValidator;
use Illuminate\Support\Carbon;

function makeTagsValidatorPending(mixed $tags = []): PendingEntry
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
        'tags' => $tags,
        'correlation_id' => null,
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ]);
}

// ---------------------------------------------------------------------------
// Stage and priority
// ---------------------------------------------------------------------------

it('runs in the validate stage', function () {
    expect(app(TagsValidator::class)->stage())->toBe(ExtensionStage::VALIDATE);
});

it('has a priority between ActionValidator (-100) and PayloadSerializableValidator (-50)', function () {
    $priority = app(TagsValidator::class)->priority();

    expect($priority)->toBeGreaterThan(-100)->toBeLessThan(-50);
});

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

it('accepts an empty tags array', function () {
    $entry = makeTagsValidatorPending();

    expect(app(TagsValidator::class)->process($entry))->toBe($entry);
});

it('accepts a single valid tag', function () {
    $entry = makeTagsValidatorPending(['billing']);

    expect(app(TagsValidator::class)->process($entry))->toBe($entry);
});

it('accepts multiple unique string tags', function () {
    $entry = makeTagsValidatorPending(['billing', 'refund', 'vip']);

    expect(app(TagsValidator::class)->process($entry))->toBe($entry);
});

it('returns the same pending entry instance on success', function () {
    $entry = makeTagsValidatorPending(['billing']);
    $result = app(TagsValidator::class)->process($entry);

    expect($result)->toBeInstanceOf(PendingEntry::class)->toBe($entry);
});

// ---------------------------------------------------------------------------
// Must be array
// ---------------------------------------------------------------------------

it('rejects a null tags value', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending(null));
})->throws(InvalidTagsException::class, 'must be an array');

it('rejects a string tags value', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending('billing'));
})->throws(InvalidTagsException::class, 'must be an array');

it('rejects an integer tags value', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending(42));
})->throws(InvalidTagsException::class, 'must be an array');

// ---------------------------------------------------------------------------
// Must contain only strings
// ---------------------------------------------------------------------------

it('rejects tags containing an integer', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending(['billing', 42]));
})->throws(InvalidTagsException::class, 'must contain only strings');

it('rejects tags containing null', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending(['billing', null]));
})->throws(InvalidTagsException::class, 'must contain only strings');

it('rejects tags containing a boolean', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending([true]));
})->throws(InvalidTagsException::class, 'must contain only strings');

it('rejects tags containing a nested array', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending([['nested']]));
})->throws(InvalidTagsException::class, 'must contain only strings');

it('rejects tags containing an object', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending([new stdClass]));
})->throws(InvalidTagsException::class, 'must contain only strings');

it('includes the offending index in the non-string exception message', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending(['billing', 99]));
})->throws(InvalidTagsException::class, '[1]');

// ---------------------------------------------------------------------------
// Must not be empty (checked after string type is confirmed)
// ---------------------------------------------------------------------------

it('rejects an empty string tag', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending(['']));
})->throws(InvalidTagsException::class, 'must not be empty');

it('rejects a whitespace-only tag', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending(['   ']));
})->throws(InvalidTagsException::class, 'must not be empty');

it('includes the offending index in the empty-tag exception message', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending(['billing', '']));
})->throws(InvalidTagsException::class, '[1]');

// ---------------------------------------------------------------------------
// Must be unique
// ---------------------------------------------------------------------------

it('rejects duplicate tags', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending(['billing', 'billing']));
})->throws(InvalidTagsException::class, 'must be unique');

it('includes the duplicate tag value in the uniqueness exception message', function () {
    app(TagsValidator::class)->process(makeTagsValidatorPending(['billing', 'billing']));
})->throws(InvalidTagsException::class, 'billing');

it('uniqueness check is case-sensitive', function () {
    $entry = makeTagsValidatorPending(['billing', 'Billing']);

    expect(app(TagsValidator::class)->process($entry))->toBe($entry);
});

// ---------------------------------------------------------------------------
// Must respect max length
// ---------------------------------------------------------------------------

it('rejects a tag that exceeds the configured max length', function () {
    config()->set('chronicle.validation.tag_max_length', 5);

    app(TagsValidator::class)->process(makeTagsValidatorPending(['toolong']));
})->throws(InvalidTagsException::class, 'exceeds the maximum length');

it('accepts a tag exactly at the max length', function () {
    config()->set('chronicle.validation.tag_max_length', 5);

    $entry = makeTagsValidatorPending(['exact']);

    expect(app(TagsValidator::class)->process($entry))->toBe($entry);
});

it('includes the offending tag in the max-length exception message', function () {
    config()->set('chronicle.validation.tag_max_length', 5);

    app(TagsValidator::class)->process(makeTagsValidatorPending(['toolong']));
})->throws(InvalidTagsException::class, 'toolong');

it('reads max length from config', function () {
    config()->set('chronicle.validation.tag_max_length', 3);

    app(TagsValidator::class)->process(makeTagsValidatorPending(['abcd']));
})->throws(InvalidTagsException::class);
