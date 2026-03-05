<?php

namespace Chronicle\Repositories;

use Chronicle\Models\Entry;

/**
 * Provides read operations for Chronicle entries.
 */
class EntryRepository
{
    /**
     * Get the most recent chain hash.
     */
    public function latestChainHash(): mixed
    {
        $last = Entry::query()
            ->latest('created_at')
            ->value('chain_hash');

        return $last ?? '0';
    }
}
