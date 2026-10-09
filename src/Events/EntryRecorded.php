<?php

declare(strict_types=1);

namespace Chronicle\Events;

use Chronicle\Entry\Entry;

/**
 * Fired after a Chronicle entry has been successfully persisted.
 *
 * Note: when using the 'queued' driver, this event fires inside the
 * queued job - not in the HTTP request - and after the job's transaction
 * has committed, so a throwing listener fails the job but keeps the entry.
 * With a synchronous driver it fires inside the write transaction.
 */
readonly class EntryRecorded
{
    public function __construct(
        public Entry $entry,
    ) {
        //
    }
}
