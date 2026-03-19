<?php

namespace Chronicle\Contracts;

use Chronicle\Entry\PendingEntry;

interface EntryPolicy
{
    /**
     * Enforce this policy against the pending entry.
     *
     * Return normally to allow the entry to proceed.
     * Throw a PolicyViolationException to reject it.
     */
    public function enforce(PendingEntry $entry): void;
}
