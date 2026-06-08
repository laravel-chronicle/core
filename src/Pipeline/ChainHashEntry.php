<?php

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Entry\Entry;
use Chronicle\Entry\PendingEntry;
use Chronicle\Hashing\ChainHasher;
use Illuminate\Support\Facades\DB;
use LogicException;

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
        /** @var string|null $configured */
        $configured = config('chronicle.connection');

        $db = is_string($configured) && $configured !== ''
            ? DB::connection($configured)
            : DB::connection();

        if ($db->transactionLevel() === 0) {
            throw new LogicException('ChainHashEntry must run inside an open DB transaction.');
        }

        /** @var Entry|null $head */
        $head = Entry::query()
            ->orderByDesc('sequence')
            ->lockForUpdate()
            ->first(['sequence', 'chain_hash']);

        $previous = $head->chain_hash ?? ChainHasher::GENESIS;
        $sequence = ($head->sequence ?? 0) + 1;

        /** @var string $payloadHash */
        $payloadHash = $entry->payloadHash();

        $chainHash = $this->hasher->hash(
            $previous,
            $payloadHash,
        );

        $entry->setChainHash($chainHash);
        $entry->setSequence($sequence);

        return $entry;
    }
}
