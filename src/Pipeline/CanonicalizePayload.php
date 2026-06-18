<?php

declare(strict_types=1);

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Entry\PendingEntry;
use Chronicle\Support\CanonicalPayloadSerializer;

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

    public function process(PendingEntry $entry): PendingEntry
    {
        /** @var array<string, mixed> $normalized */
        $normalized = $this->serializer->normalize($entry->attributes());

        $entry->setPayload($normalized);

        return $entry;
    }
}
