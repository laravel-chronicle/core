<?php

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Entry\PendingEntry;
use Chronicle\Hashing\EntryHasher;
use JsonException;

/**
 * Pipeline processor responsible for computing
 * the payload hash.
 */
class HashPayload implements EntryProcessor
{
    protected EntryHasher $hasher;

    public function __construct(EntryHasher $hasher)
    {
        $this->hasher = $hasher;
    }

    /**
     * @throws JsonException
     */
    public function process(PendingEntry $entry): PendingEntry
    {
        $hash = $this->hasher->hash($entry);

        $entry->setPayloadHash($hash);

        return $entry;
    }
}
