<?php

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Entry\PendingEntry;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Models\Entry;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pipeline processor that attaches the entry chain hash.
 *
 * Uses row-level locking to prevent concurrent chain forks.
 */
class ChainHashEntry implements EntryProcessor
{
    protected ChainHasher $hasher;

    public function __construct(ChainHasher $hasher)
    {
        $this->hasher = $hasher;
    }

    /**
     * @throws Throwable
     */
    public function process(PendingEntry $entry): PendingEntry
    {
        DB::transaction(function () use ($entry) {
            /** @var string $previous */
            $previous = Entry::query()
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->value('chain_hash') ?? '0';

            /** @var string $payloadHash */
            $payloadHash = $entry->payloadHash();

            $chainHash = $this->hasher->hash(
                $previous,
                $payloadHash,
            );

            $entry->setChainHash($chainHash);
        });

        return $entry;
    }
}
