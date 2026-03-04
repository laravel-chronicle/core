<?php

namespace Chronicle\Hashing;

use Chronicle\Entry\PendingEntry;
use Chronicle\Serialization\CanonicalPayloadSerializer;
use JsonException;

/**
 * EntryHasher
 *
 * Computes an SHA-256 hash of the canonical payload.
 *
 * This hash is stored alongside the entry and later used
 * for integrity verification and chain hashing.
 */
class EntryHasher
{
    protected CanonicalPayloadSerializer $serializer;

    public function __construct(CanonicalPayloadSerializer $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Generate the payload hash for an entry.
     *
     * @throws JsonException
     */
    public function hash(PendingEntry $entry): string
    {
        $canonical = $this->serializer->serialize($entry->payload());

        return hash('sha256', $canonical);
    }
}
