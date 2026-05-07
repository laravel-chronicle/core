<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\RequiredContextMissingException;
use Chronicle\Policy\ContextPolicy;
use Illuminate\Support\Carbon;

function makeContextPolicyPending(mixed $context = []): PendingEntry
{
    return new PendingEntry([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D1',
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '42',
        'action' => 'order.placed',
        'subject_type' => 'App\\Models\\Order',
        'subject_id' => '7',
        'metadata' => [],
        'context' => $context,
        'diff' => null,
        'tags' => [],
        'correlation_id' => null,
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ]);
}

it('passes when all required keys are present', function () {
    config(['chronicle.policy.required_context_keys' => ['tenant_id', 'environment']]);

    (new ContextPolicy)->enforce(makeContextPolicyPending(['tenant_id' => 1, 'environment' => 'prod']));
})->throwsNoExceptions();

it('passes when required_context_keys is empty', function () {
    config(['chronicle.policy.required_context_keys' => []]);

    (new ContextPolicy)->enforce(makeContextPolicyPending());
})->throwsNoExceptions();

it('throws when a required key is missing', function () {
    config(['chronicle.policy.required_context_keys' => ['tenant_id']]);

    expect(fn () => (new ContextPolicy)->enforce(makeContextPolicyPending()))
        ->toThrow(RequiredContextMissingException::class, 'tenant_id');
});

it('throws for the first missing key when multiple are required', function () {
    config(['chronicle.policy.required_context_keys' => ['tenant_id', 'environment']]);

    expect(fn () => (new ContextPolicy)->enforce(makeContextPolicyPending(['environment' => 'prod'])))
        ->toThrow(RequiredContextMissingException::class, 'tenant_id');
});

it('treats a null context attribute as an empty array', function () {
    config(['chronicle.policy.required_context_keys' => ['tenant_id']]);

    expect(fn () => (new ContextPolicy)->enforce(makeContextPolicyPending(null)))
        ->toThrow(RequiredContextMissingException::class, 'tenant_id');
});

it('treats a non-array context attribute as an empty array', function () {
    config(['chronicle.policy.required_context_keys' => ['tenant_id']]);

    expect(fn () => (new ContextPolicy)->enforce(makeContextPolicyPending('not-an-array')))
        ->toThrow(RequiredContextMissingException::class, 'tenant_id');
});
