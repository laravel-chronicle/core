<?php

namespace Chronicle\Transaction;

use Chronicle\ChronicleManager;
use Chronicle\Entry\EntryBuilder;

/**
 * Represents a Chronicle transaction.
 *
 * A transaction groups multiple entries under a shared
 * correlation identifier.
 */
class ChronicleTransaction
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
}
