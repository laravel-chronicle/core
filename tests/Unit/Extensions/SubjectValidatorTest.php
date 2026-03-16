<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\MissingSubjectException;
use Chronicle\Pipeline\ExtensionStage;
use Chronicle\Validation\SubjectValidator;
use Illuminate\Support\Carbon;

function makeSubjectValidatorPending(array $overrides = []): PendingEntry
{
    return new PendingEntry(array_merge([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D1',
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '42',
        'action' => 'invoice.sent',
        'subject_type' => 'App\\Models\\Invoice',
        'subject_id' => '99',
        'metadata' => [],
        'context' => [],
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Stage and priority
// ---------------------------------------------------------------------------

it('runs in the validate stage', function () {
    expect(app(SubjectValidator::class)->stage())->toBe(ExtensionStage::VALIDATE);
});

it('has a priority between ActorPresenceValidator (-200) and ActionValidator (-100)', function () {
    $priority = app(SubjectValidator::class)->priority();

    expect($priority)->toBeGreaterThan(-200)->toBeLessThan(-100);
});

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

it('accepts entries with a valid subject type and id', function () {
    $entry = makeSubjectValidatorPending();

    expect(app(SubjectValidator::class)->process($entry))->toBe($entry);
});

it('returns the same pending entry instance on success', function () {
    $entry = makeSubjectValidatorPending();
    $result = app(SubjectValidator::class)->process($entry);

    expect($result)->toBeInstanceOf(PendingEntry::class)->toBe($entry);
});

// ---------------------------------------------------------------------------
// System actor bypass
// ---------------------------------------------------------------------------

it('allows a system actor entry with no subject type', function () {
    $entry = makeSubjectValidatorPending([
        'actor_type' => 'system',
        'actor_id' => 'system',
        'subject_type' => null,
    ]);

    expect(app(SubjectValidator::class)->process($entry))->toBe($entry);
});

it('allows a system actor entry with no subject id', function () {
    $entry = makeSubjectValidatorPending([
        'actor_type' => 'system',
        'actor_id' => 'system',
        'subject_id' => null,
    ]);

    expect(app(SubjectValidator::class)->process($entry))->toBe($entry);
});

it('allows a system actor entry with both subject fields absent', function () {
    $entry = makeSubjectValidatorPending([
        'actor_type' => 'system',
        'actor_id' => 'system',
        'subject_type' => null,
        'subject_id' => null,
    ]);

    expect(app(SubjectValidator::class)->process($entry))->toBe($entry);
});

it('allows a system actor entry with blank subject fields', function () {
    $entry = makeSubjectValidatorPending([
        'actor_type' => 'system',
        'actor_id' => 'system',
        'subject_type' => '',
        'subject_id' => '   ',
    ]);

    expect(app(SubjectValidator::class)->process($entry))->toBe($entry);
});

// ---------------------------------------------------------------------------
// Missing subject type
// ---------------------------------------------------------------------------

it('rejects entries without a subject type', function () {
    app(SubjectValidator::class)->process(makeSubjectValidatorPending([
        'subject_type' => null,
    ]));
})->throws(MissingSubjectException::class);

it('rejects entries with an empty subject type', function () {
    app(SubjectValidator::class)->process(makeSubjectValidatorPending([
        'subject_type' => '',
    ]));
})->throws(MissingSubjectException::class);

it('rejects entries with a blank subject type', function () {
    app(SubjectValidator::class)->process(makeSubjectValidatorPending([
        'subject_type' => '   ',
    ]));
})->throws(MissingSubjectException::class);

it('rejects entries with a non-string subject type', function () {
    app(SubjectValidator::class)->process(makeSubjectValidatorPending([
        'subject_type' => 123,
    ]));
})->throws(MissingSubjectException::class);

// ---------------------------------------------------------------------------
// Missing subject id
// ---------------------------------------------------------------------------

it('rejects entries without a subject id', function () {
    app(SubjectValidator::class)->process(makeSubjectValidatorPending([
        'subject_id' => null,
    ]));
})->throws(MissingSubjectException::class);

it('rejects entries with an empty subject id', function () {
    app(SubjectValidator::class)->process(makeSubjectValidatorPending([
        'subject_id' => '',
    ]));
})->throws(MissingSubjectException::class);

it('rejects entries with a blank subject id', function () {
    app(SubjectValidator::class)->process(makeSubjectValidatorPending([
        'subject_id' => '   ',
    ]));
})->throws(MissingSubjectException::class);

it('rejects entries with a non-string subject id', function () {
    app(SubjectValidator::class)->process(makeSubjectValidatorPending([
        'subject_id' => false,
    ]));
})->throws(MissingSubjectException::class);

// ---------------------------------------------------------------------------
// Both fields missing
// ---------------------------------------------------------------------------

it('rejects entries with both subject fields absent', function () {
    app(SubjectValidator::class)->process(makeSubjectValidatorPending([
        'subject_type' => null,
        'subject_id' => null,
    ]));
})->throws(MissingSubjectException::class);

// ---------------------------------------------------------------------------
// Non-system actor with a valid subject is not bypassed
// ---------------------------------------------------------------------------

it('does not bypass validation for non-system actor types that look similar', function () {
    app(SubjectValidator::class)->process(makeSubjectValidatorPending([
        'actor_type' => 'System',  // capital S — not the system bypass
        'subject_type' => null,
    ]));
})->throws(MissingSubjectException::class);
