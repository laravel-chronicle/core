<?php

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Entry\PendingEntry;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Models\Entry;

/**
 * Pipeline processor that attaches the entry chain hash.
 *
 * Uses row-level locking to prevent concurrent chain forks.
 *
 * This processor expects to run inside an open DB transaction.
 */
class ChainHashEntry implements EntryProcessor
{
    protected ChainHasher $hasher;

    public function __construct(ChainHasher $hasher)
    {
        $this->hasher = $hasher;
    }

    public function process(PendingEntry $entry): PendingEntry
    {
        /** @var string $previous */
        $previous = Entry::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('chain_hash') ?? '0';

        /** @var string $payloadHash */
        $payloadHash = $entry->payloadHash();

        $chainHash = $this->hasher->hash(
            $previous,
            $payloadHash,
        );

        $entry->setChainHash($chainHash);

        return $entry;
    }
}
