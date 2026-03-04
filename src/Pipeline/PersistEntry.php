<?php

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Contracts\EntryStore;

/**
 * Persists the entry using the configured store.
 */
class PersistEntry implements EntryProcessor
{
    protected EntryStore $store;

    public function __construct(EntryStore $store)
    {
        $this->store = $store;
    }

    public function process(array $payload): array
    {
        $this->store->append($payload);

        return $payload;
    }
}
