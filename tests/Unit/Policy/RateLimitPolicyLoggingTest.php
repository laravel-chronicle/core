<?php

use Chronicle\Exceptions\RateLimitExceededException;
use Chronicle\Policy\RateLimitPolicy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

it('logs a warning when an audit entry is dropped by the rate limiter', function () {
    config([
        'chronicle.policy.rate_limit.max_entries' => 1,
        'chronicle.policy.rate_limit.decay_seconds' => 60,
    ]);

    Log::spy();

    $policy = new RateLimitPolicy;
    $entry = makePolicyPending();

    $policy->enforce($entry); // first hit - allowed

    expect(fn () => $policy->enforce($entry))->toThrow(RateLimitExceededException::class);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'rate limit'))
        ->once();

    RateLimiter::clear('chronicle:rate:'.sha1('App\\Models\\User/42'));
});
