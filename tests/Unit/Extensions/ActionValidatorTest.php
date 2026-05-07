<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidActionException;
use Chronicle\Validation\ActionValidator;
use Illuminate\Support\Carbon;

function makeActionValidatorPending(mixed $action = 'orders.created'): PendingEntry
{
    return new PendingEntry([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D1',
        'actor_type' => 'system',
        'actor_id' => 'system',
        'action' => $action,
        'subject_type' => 'system',
        'subject_id' => 'system',
        'metadata' => [],
        'context' => [],
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ]);
}

it('accepts actions with exactly two segments', function () {
    $entry = makeActionValidatorPending();

    expect(app(ActionValidator::class)->process($entry))->toBe($entry);
});

it('rejects non-string actions', function () {
    app(ActionValidator::class)->process(makeActionValidatorPending(['orders.created']));
})->throws(InvalidActionException::class, 'must be a string');

it('rejects actions without dot notation', function () {
    app(ActionValidator::class)->process(makeActionValidatorPending('orders'));
})->throws(InvalidActionException::class, 'must use dot notation');

it('rejects actions with empty dot segments', function () {
    app(ActionValidator::class)->process(makeActionValidatorPending('orders..created'));
})->throws(InvalidActionException::class, 'must use dot notation');

it('rejects actions with more than two segments', function () {
    app(ActionValidator::class)->process(makeActionValidatorPending('orders.created.pending'));
})->throws(InvalidActionException::class, 'must use dot notation');

it('rejects actions that exceed the configured maximum length', function () {
    config()->set('chronicle.validation.action_max_length', 12);

    app(ActionValidator::class)->process(makeActionValidatorPending());
})->throws(InvalidActionException::class, 'maximum length');
