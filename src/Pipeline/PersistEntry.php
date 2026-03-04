<?php

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\PendingEntry;

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
        $this->store->store($entry->toDatabasePayload());

        return $entry;
    }
}
