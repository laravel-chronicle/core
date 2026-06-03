<?php

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\RateLimitExceededException;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitPolicy extends AbstractPolicy
{
    private readonly int $maxEntries;

    private readonly int $decaySeconds;

    public function __construct()
    {
        /** @var int $entries */
        $entries = config('chronicle.policy.rate_limit.max_entries', 60);
        /** @var int $seconds */
        $seconds = config('chronicle.policy.rate_limit.decay_seconds', 60);
        $this->maxEntries = $entries;
        $this->decaySeconds = $seconds;
    }

    public function enforce(PendingEntry $entry): void
    {
        /** @var string $actorType */
        $actorType = $entry->attribute('actor_type');

        /** @var string $actorId */
        $actorId = $entry->attribute('actor_id');

        $key = 'chronicle:rate:'.sha1($actorType.'/'.$actorId);

        $attempts = RateLimiter::hit($key, $this->decaySeconds);

        if ($attempts > $this->maxEntries) {
            throw RateLimitExceededException::exceededLimit(
                RateLimiter::availableIn($key)
            );
        }
    }
}
