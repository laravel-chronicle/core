<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidTagsException;
use Chronicle\Pipeline\ExtensionStage;
use Chronicle\Validation\TagLimitValidator;
use Illuminate\Support\Carbon;

function makeTagLimitValidatorPending(mixed $tags = []): PendingEntry
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
    expect(app(TagLimitValidator::class)->stage())->toBe(ExtensionStage::VALIDATE);
});

it('has a priority between ActionValidator (-100) and TagsValidator (-75)', function () {
    $priority = app(TagLimitValidator::class)->priority();

    expect($priority)->toBeGreaterThan(-100)->toBeLessThan(-75);
});

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

it('accepts an empty tags array', function () {
    $entry = makeTagLimitValidatorPending();

    expect(app(TagLimitValidator::class)->process($entry))->toBe($entry);
});

it('accepts a tags array exactly at the limit', function () {
    config()->set('chronicle.validation.tag_limit', 3);

    $entry = makeTagLimitValidatorPending(['a', 'b', 'c']);

    expect(app(TagLimitValidator::class)->process($entry))->toBe($entry);
});

it('accepts a tags array well under the limit', function () {
    config()->set('chronicle.validation.tag_limit', 10);

    $entry = makeTagLimitValidatorPending(['billing', 'vip']);

    expect(app(TagLimitValidator::class)->process($entry))->toBe($entry);
});

it('returns the same pending entry instance on success', function () {
    $entry = makeTagLimitValidatorPending(['billing']);
    $result = app(TagLimitValidator::class)->process($entry);

    expect($result)->toBeInstanceOf(PendingEntry::class)->toBe($entry);
});

// ---------------------------------------------------------------------------
// Non-array tags — silently pass (TagsValidator handles type errors)
// ---------------------------------------------------------------------------

it('passes silently when tags is null', function () {
    $entry = makeTagLimitValidatorPending(null);

    expect(app(TagLimitValidator::class)->process($entry))->toBe($entry);
});

it('passes silently when tags is a string', function () {
    $entry = makeTagLimitValidatorPending('billing');

    expect(app(TagLimitValidator::class)->process($entry))->toBe($entry);
});

// ---------------------------------------------------------------------------
// Exceeds limit
// ---------------------------------------------------------------------------

it('rejects a tags array one over the limit', function () {
    config()->set('chronicle.validation.tag_limit', 3);

    app(TagLimitValidator::class)->process(makeTagLimitValidatorPending(['a', 'b', 'c', 'd']));
})->throws(InvalidTagsException::class, 'exceeds the tag limit');

it('rejects a tags array well over the limit', function () {
    config()->set('chronicle.validation.tag_limit', 2);

    app(TagLimitValidator::class)->process(makeTagLimitValidatorPending(['a', 'b', 'c', 'd', 'e']));
})->throws(InvalidTagsException::class, 'exceeds the tag limit');

it('includes the actual count in the exception message', function () {
    config()->set('chronicle.validation.tag_limit', 2);

    app(TagLimitValidator::class)->process(makeTagLimitValidatorPending(['a', 'b', 'c']));
})->throws(InvalidTagsException::class, '3');

it('includes the configured limit in the exception message', function () {
    config()->set('chronicle.validation.tag_limit', 2);

    app(TagLimitValidator::class)->process(makeTagLimitValidatorPending(['a', 'b', 'c']));
})->throws(InvalidTagsException::class, '2');

it('reads the tag limit from config', function () {
    config()->set('chronicle.validation.tag_limit', 1);

    app(TagLimitValidator::class)->process(makeTagLimitValidatorPending(['a', 'b']));
})->throws(InvalidTagsException::class);
