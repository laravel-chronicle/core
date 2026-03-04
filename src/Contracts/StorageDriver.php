<?php

namespace Chronicle\Contracts;

use Chronicle\Entry\PendingEntry;
use Chronicle\Models\Entry;

/**
 * Contract for all Chronicle storage backends.
 *
 * A driver receives a PendingEntry (the fully-built, validated entry value
 * object) and is responsible for persisting it and returning a hydrated Entry
 * model.
 *
 * Drivers must be stateless - each store() call is independent.
 */
interface StorageDriver
{
    /**
     * Persist the pending entry and return the stored Entry model.
     *
     * Implementations must guarantee that the returned Entry reflects
     * exactly what was stored - no field may be silently altered.
     *
     * @param  array<string, mixed>  $entry
     */
    public function store(array $entry): Entry;
}
