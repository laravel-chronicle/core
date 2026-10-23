<?php

declare(strict_types=1);

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Entry\Entry;
use Chronicle\Entry\PendingEntry;
use Chronicle\Facades\Chronicle;
use Chronicle\Hashing\ChainHasher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Pipeline processor that attaches the entry chain hash.
 *
 * Uses row-level locking to prevent concurrent chain forks. On PostgreSQL the
 * row lock alone does not serialise writers: a transaction that waited for the
 * lock on the head re-reads that same row once it is released, not the entry
 * committed in the meantime, and so takes the same sequence. On an empty ledger
 * there is no row to lock at all. A transaction-level advisory lock is taken
 * first there, keyed on the entries table, so the head is read only after the
 * previous writer committed. It is held until the transaction ends.
 *
 * Both locks assume READ COMMITTED, the default on PostgreSQL, where every
 * statement reads the latest committed data. Under REPEATABLE READ or
 * SERIALIZABLE the snapshot is taken at the transaction's first statement, so
 * a writer that waited for the lock still reads the head as it was then, and
 * loses its place however often it is tried. A caller that wraps Chronicle
 * writes in a transaction at one of those levels must handle the unique
 * violation itself.
 *
 * This processor expects to run inside an open DB transaction.
 */
final class ChainHashEntry implements EntryProcessor
{
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
            $db->select('select pg_advisory_xact_lock(hashtext(?))', [self::lockName($db)]);
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

    /**
     * Name the advisory lock is keyed on: the entries table as the connection
     * sees it, prefix included. Two applications, or two Chronicle ledgers, in
     * one database lock only their own chain.
     */
    public static function lockName(ConnectionInterface $db): string
    {
        $prefix = method_exists($db, 'getTablePrefix') ? $db->getTablePrefix() : '';

        return (is_string($prefix) ? $prefix : '').Config::string('chronicle.tables.entries', 'chronicle_entries');
    }
}
