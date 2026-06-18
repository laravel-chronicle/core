<?php

declare(strict_types=1);

namespace Chronicle\Events;

use Chronicle\Entry\Entry;

/**
 * Fired after a Chronicle entry has been successfully persisted.
 *
 * Note: when using the 'queued' driver, this event fires inside the
 * queued job - not in the HTTP request.
 */
class EntryRecorded
{
    public function __construct(
        public readonly Entry $entry,
    ) {
        //
    }
}
