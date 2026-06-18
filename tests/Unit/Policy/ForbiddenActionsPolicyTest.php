<?php

declare(strict_types=1);

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\ActionForbiddenException;
use Chronicle\Policy\ForbiddenActionsPolicy;
use Illuminate\Support\Carbon;

function makeForbiddenActionsPending(string $action): PendingEntry
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

it('passes when the action does not match any forbidden pattern', function () {
    config(['chronicle.policy.forbidden_actions' => ['debug.*']]);

    (new ForbiddenActionsPolicy)->enforce(makeForbiddenActionsPending('user.created'));
})->throwsNoExceptions();

it('passes when the forbidden list is empty', function () {
    config(['chronicle.policy.forbidden_actions' => []]);

    (new ForbiddenActionsPolicy)->enforce(makeForbiddenActionsPending('user.created'));
})->throwsNoExceptions();

it('throws when the action exactly matches a forbidden entry', function () {
    config(['chronicle.policy.forbidden_actions' => ['debug.dump']]);

    expect(fn () => (new ForbiddenActionsPolicy)->enforce(makeForbiddenActionsPending('debug.dump')))
        ->toThrow(ActionForbiddenException::class);
});

it('throws when the action matches a wildcard pattern', function () {
    config(['chronicle.policy.forbidden_actions' => ['debug.*']]);

    expect(fn () => (new ForbiddenActionsPolicy)->enforce(makeForbiddenActionsPending('debug.trace')))
        ->toThrow(ActionForbiddenException::class);
});

it('throws when the action matches one of multiple patterns', function () {
    config(['chronicle.policy.forbidden_actions' => ['debug.*', 'internal.*']]);

    expect(fn () => (new ForbiddenActionsPolicy)->enforce(makeForbiddenActionsPending('internal.sync')))
        ->toThrow(ActionForbiddenException::class);
});

it('includes the action in the exception message', function () {
    config(['chronicle.policy.forbidden_actions' => ['debug.*']]);

    expect(fn () => (new ForbiddenActionsPolicy)->enforce(makeForbiddenActionsPending('debug.trace')))
        ->toThrow(ActionForbiddenException::class, 'debug.trace');
});
