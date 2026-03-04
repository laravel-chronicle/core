<?php

namespace Chronicle\Contracts;

use Chronicle\Entry\PendingEntry;

/**
 * Interface EntryProcessor
 *
 * Represents a stage in the Chronicle entry
 * processing pipeline.
 */
interface EntryProcessor
{
    /**
     * Process the entry payload.
     */
    public function process(PendingEntry $entry): PendingEntry;
}
