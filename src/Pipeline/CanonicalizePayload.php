<?php

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Entry\PendingEntry;
use Chronicle\Serialization\CanonicalPayloadSerializer;
use JsonException;

/**
 * Canonicalizes the entry payload before persistence.
 */
class CanonicalizePayload implements EntryProcessor
{
    protected CanonicalPayloadSerializer $serializer;

    public function __construct(CanonicalPayloadSerializer $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @throws JsonException
     */
    public function process(PendingEntry $entry): PendingEntry
    {
        $canonical = $this->serializer->serialize($entry->attributes());

        /** @var array<string, mixed> $payload */
        $payload = json_decode($canonical, true);

        $entry->setPayload($payload);

        return $entry;
    }
}
