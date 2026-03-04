<?php

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
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
    public function process(array $payload): array
    {
        $canonical = $this->serializer->serialize($payload);

        $payload['payload'] = json_decode($canonical, true);

        return $payload;
    }
}
