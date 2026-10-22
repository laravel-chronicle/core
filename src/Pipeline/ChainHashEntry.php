<?php

declare(strict_types=1);

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Entry\Entry;
use Chronicle\Entry\PendingEntry;
use Chronicle\Facades\Chronicle;
use Chronicle\Hashing\ChainHasher;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Pipeline processor that attaches the entry chain hash.
 *
 * Uses row-level locking to prevent concurrent chain forks. On PostgreSQL the
 * row lock alone does not serialise writers: a transaction that waited for the
 * lock on the head re-reads that same row once it is released, not the entry
 * committed in the meantime, and so takes the same sequence. A transaction-level
 * advisory lock is taken first there, so the head is read only after the
 * previous writer committed.
 *
 * This processor expects to run inside an open DB transaction.
 */
final class ChainHashEntry implements EntryProcessor
{
    /**
     * Key of the PostgreSQL advisory lock that serialises chain writes
     * ("chrn" as a 32-bit integer). Held until the transaction ends.
     */
    public const CHAIN_LOCK_KEY = 0x6368726E;

    protected ChainHasher $hasher;

    public function __construct(ChainHasher $hasher)
    {
        $this->hasher = $hasher;
    }

    public function process(PendingEntry $entry): PendingEntry
    {
        /** @var string|null $configured */
        $configured = Config::get('chronicle.connection');

        $db = is_string($configured) && $configured !== ''
            ? DB::connection($configured)
            : DB::connection();

        if ($db->transactionLevel() === 0) {
            throw new LogicException('ChainHashEntry must run inside an open DB transaction.');
        }

        if ($db->getDriverName() === 'pgsql') {
            $db->select('select pg_advisory_xact_lock(?)', [self::CHAIN_LOCK_KEY]);
        }

        /** @var Entry|null $head */
        $head = Chronicle::newEntryQuery()
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
