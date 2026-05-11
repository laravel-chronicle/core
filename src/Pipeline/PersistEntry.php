<?php

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\PendingEntry;
use Chronicle\Events\EntryRecorded;
use Illuminate\Support\Facades\Event;

/**
 * Persists the entry using the configured store.
 */
class PersistEntry implements EntryProcessor
{
    protected StorageDriver $store;

    public function __construct(StorageDriver $store)
    {
        $this->store = $store;
    }

    public function process(PendingEntry $entry): PendingEntry
    {
        $stored = $this->store->store($entry->toDatabasePayload());

        if ($stored->exists) {
            Event::dispatch(new EntryRecorded($stored));
        }

        return $entry;
    }
}
