<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\RateLimitExceededException;
use Chronicle\Policy\RateLimitPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

// Note: RateLimiter::fake() does not stub availableIn(), which is required
// for the retry-after test. Using the array cache driver instead gives full
// real-implementation coverage without a persistent cache backend.
beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
});

it('passes when the actor is under the rate limit', function () {
    config(['chronicle.policy.rate_limit' => ['max_entries' => 5, 'decay_seconds' => 60]]);

    (new RateLimitPolicy)->enforce(makePolicyPending());
})->throwsNoExceptions();

it('passes when the actor is exactly at the rate limit', function () {
    config(['chronicle.policy.rate_limit' => ['max_entries' => 5, 'decay_seconds' => 60]]);

    $policy = new RateLimitPolicy;

    // Hit the limit 5 times (max_entries = 5).
    foreach (range(1, 5) as $i) {
        $policy->enforce(makePolicyPending());
    }
})->throwsNoExceptions();

it('throws when the actor exceeds the rate limit', function () {
    config(['chronicle.policy.rate_limit' => ['max_entries' => 2, 'decay_seconds' => 60]]);

    $policy = new RateLimitPolicy;
    $policy->enforce(makePolicyPending());
    $policy->enforce(makePolicyPending());

    expect(fn () => $policy->enforce(makePolicyPending()))
        ->toThrow(RateLimitExceededException::class);
});

it('includes retry-after seconds in the exception message', function () {
    config(['chronicle.policy.rate_limit' => ['max_entries' => 1, 'decay_seconds' => 30]]);

    $policy = new RateLimitPolicy;
    $policy->enforce(makePolicyPending());

    expect(fn () => $policy->enforce(makePolicyPending()))
        ->toThrow(RateLimitExceededException::class, 'second');
});

it('uses a separate limit bucket per actor', function () {
    config(['chronicle.policy.rate_limit' => ['max_entries' => 1, 'decay_seconds' => 60]]);

    $policy = new RateLimitPolicy;

    // First actor hits their limit.
    $policy->enforce(makePolicyPending());

    // Second actor (different actor_id) should still pass.
    $secondEntry = new PendingEntry([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D2',
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '99',
        'action' => 'order.placed',
        'subject_type' => 'App\\Models\\Order',
        'subject_id' => '7',
        'metadata' => [],
        'context' => [],
        'diff' => null,
        'tags' => [],
        'correlation_id' => null,
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ]);

    $policy->enforce($secondEntry);
})->throwsNoExceptions();
