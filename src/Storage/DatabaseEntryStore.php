<?php

namespace Chronicle\Storage;

use Chronicle\Contracts\EntryStore;
use Chronicle\Models\Entry;

/**
 * Class DatabaseEntryStore
 *
 * Default Chronicle storage implementation using
 * the application's database.
 *
 * This store performs append-only writes to the
 * chronicle_entries table.
 */
class DatabaseEntryStore implements EntryStore
{
    /**
     * Persist a Chronicle entry in the database.
     *
     * @param  array<string, mixed>  $payload
     */
    public function append(array $payload): void
    {
        Entry::create($payload);
    }
}
