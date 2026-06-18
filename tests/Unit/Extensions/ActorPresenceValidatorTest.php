<?php

declare(strict_types=1);

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\MissingActorException;
use Chronicle\Validation\ActorPresenceValidator;
use Illuminate\Support\Carbon;

function makeActorPresencePending(array $overrides = []): PendingEntry
{
    return new PendingEntry(array_merge([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D1',
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '42',
        'action' => 'orders.created',
        'subject_type' => 'system',
        'subject_id' => 'system',
        'metadata' => [],
        'context' => [],
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ], $overrides));
}

it('accepts entries with actor type and id', function () {
    $entry = makeActorPresencePending();

    expect(app(ActorPresenceValidator::class)->process($entry))->toBe($entry);
});

it('accepts system generated entries', function () {
    $entry = makeActorPresencePending([
        'actor_type' => 'system',
        'actor_id' => 'system',
    ]);

    expect(app(ActorPresenceValidator::class)->process($entry))->toBe($entry);
});

it('rejects entries without actor type', function () {
    app(ActorPresenceValidator::class)->process(makeActorPresencePending([
        'actor_type' => null,
    ]));
})->throws(MissingActorException::class);

it('rejects entries without actor id', function () {
    app(ActorPresenceValidator::class)->process(makeActorPresencePending([
        'actor_id' => null,
    ]));
})->throws(MissingActorException::class);

it('rejects blank actor values', function () {
    app(ActorPresenceValidator::class)->process(makeActorPresencePending([
        'actor_type' => ' ',
        'actor_id' => '',
    ]));
})->throws(MissingActorException::class);
