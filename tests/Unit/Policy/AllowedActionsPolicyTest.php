<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\ActionNotAllowedException;
use Chronicle\Policy\AllowedActionsPolicy;
use Illuminate\Support\Carbon;

function makeAllowedActionsPending(string $action): PendingEntry
{
    return new PendingEntry([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D1',
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '42',
        'action' => $action,
        'subject_type' => 'App\\Models\\Order',
        'subject_id' => '7',
        'metadata' => [],
        'context' => [],
        'diff' => null,
        'tags' => [],
        'correlation_id' => null,
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ]);
}

it('passes when the action exactly matches an allowed entry', function () {
    config(['chronicle.policy.allowed_actions' => ['order.placed']]);

    (new AllowedActionsPolicy)->enforce(makeAllowedActionsPending('order.placed'));
})->throwsNoExceptions();

it('passes when the action matches a wildcard pattern', function () {
    config(['chronicle.policy.allowed_actions' => ['user.*']]);

    (new AllowedActionsPolicy)->enforce(makeAllowedActionsPending('user.created'));
})->throwsNoExceptions();

it('passes when the action matches one of multiple patterns', function () {
    config(['chronicle.policy.allowed_actions' => ['user.*', 'order.placed']]);

    (new AllowedActionsPolicy)->enforce(makeAllowedActionsPending('order.placed'));
})->throwsNoExceptions();

it('throws when the action does not match any allowed pattern', function () {
    config(['chronicle.policy.allowed_actions' => ['user.*']]);

    expect(fn () => (new AllowedActionsPolicy)->enforce(makeAllowedActionsPending('payment.captured')))
        ->toThrow(ActionNotAllowedException::class);
});

it('throws for every action when the allowlist is empty', function () {
    config(['chronicle.policy.allowed_actions' => []]);

    expect(fn () => (new AllowedActionsPolicy)->enforce(makeAllowedActionsPending('user.created')))
        ->toThrow(ActionNotAllowedException::class);
});

it('includes the action in the exception message', function () {
    config(['chronicle.policy.allowed_actions' => ['user.*']]);

    expect(fn () => (new AllowedActionsPolicy)->enforce(makeAllowedActionsPending('payment.captured')))
        ->toThrow(ActionNotAllowedException::class, 'payment.captured');
});
