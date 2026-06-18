<?php

declare(strict_types=1);

namespace Chronicle\Transaction;

use Chronicle\ChronicleManager;
use Chronicle\Entry\EntryBuilder;

/**
 * Represents a Chronicle transaction.
 *
 * A transaction groups multiple entries under a shared
 * correlation identifier.
 */
final class ChronicleTransaction
{
    protected ChronicleManager $manager;

    protected string $correlationId;

    public function __construct(ChronicleManager $manager, string $correlationId)
    {
        $this->manager = $manager;
        $this->correlationId = $correlationId;
    }

    /**
     * Start a new entry inside this transaction.
     */
    public function entry(): EntryBuilder
    {
        return $this->manager
            ->record()
            ->correlation($this->correlationId);
    }

    /**
     * Get the transaction correlation id.
     */
    public function id(): string
    {
        return $this->correlationId;
    }

    /**
     * End the transaction and remove it from the correlation stack.
     *
     * Only required when using the manual transaction style:
     *
     *   $tx = Chronicle::transaction();
     *   $tx->entry()->...->commit();
     *   $tx->end();
     *
     * Callback-style transactions are ended automatically.
     */
    public function end(): void
    {
        $this->manager->endTransaction();
    }
}
