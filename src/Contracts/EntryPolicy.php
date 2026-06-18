<?php

declare(strict_types=1);

namespace Chronicle\Contracts;

use Chronicle\Entry\PendingEntry;

/**
 * Contract for opt-in policies that may reject a pending entry.
 */
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
