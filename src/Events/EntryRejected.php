<?php

namespace Chronicle\Events;

use Throwable;

/**
 * Fired after a Chronicle entry is rejected by a validator or policy.
 *
 * The $reason is the exception that caused the rejection.
 * The $payload is the raw entry attributes at the point of rejection.
 */
class EntryRejected
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly Throwable $reason,
        public readonly array $payload
    ) {
        //
    }
}
