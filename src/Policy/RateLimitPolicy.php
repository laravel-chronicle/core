<?php

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\RateLimitExceededException;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitPolicy extends AbstractPolicy
{
    public function enforce(PendingEntry $entry): void
    {
        /** @var int $maxEntries */
        $maxEntries = config('chronicle.policy.rate_limit.max_entries', 60);

        /** @var int $decaySeconds */
        $decaySeconds = config('chronicle.policy.rate_limit.decay_seconds', 60);

        /** @var string $actorType */
        $actorType = $entry->attribute('actor_type');

        /** @var string $actorId */
        $actorId = $entry->attribute('actor_id');

        $key = 'chronicle:rate:'.sha1($actorType.'/'.$actorId);

        if (RateLimiter::tooManyAttempts($key, $maxEntries)) {
            throw RateLimitExceededException::exceededLimit(
                RateLimiter::availableIn($key)
            );
        }

        RateLimiter::hit($key, $decaySeconds);
    }
}
