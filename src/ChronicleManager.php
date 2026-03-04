<?php

namespace Chronicle;

use Chronicle\Contracts\EntryStore;
use Chronicle\Contracts\ReferenceResolver;
use Chronicle\Pipeline\EntryPipeline;
use Chronicle\Serialization\CanonicalPayloadSerializer;
use JsonException;

/**
 * Class ChronicleManager
 *
 * The ChronicleManager acts as the central entry point for
 * Chronicle's audit logging system.
 *
 * Responsibilities:
 *  - Create EntryBuilder instances
 *  - Dispatch built entries into the Chronicle processing pipeline
 *
 * The manager itself performs no processing. Instead, it delegates
 * entry handling to the EntryPipeline, which executes a sequence
 * of processors responsible for transforming and persisting the entry.
 */
class ChronicleManager
{
    /**
     * Entry storage implementation.
     */
//    protected EntryStore $store;

    /**
     * Reference resolver used for actors and subjects.
     */
    protected ReferenceResolver $resolver;

    /**
     * Entry processing pipeline.
     */
    protected EntryPipeline $pipeline;

//    protected CanonicalPayloadSerializer $serializer;

    /**
     * Create a new Chronicle manager instance.
     */
    public function __construct(
//        EntryStore $store,
        ReferenceResolver $resolver,
        EntryPipeline $pipeline
//        CanonicalPayloadSerializer $serializer
    ) {
//        $this->store = $store;
        $this->resolver = $resolver;
        $this->pipeline = $pipeline;
//        $this->serializer = $serializer;
    }

    /**
     * Create a new entry builder.
     *
     * Developers use this method to begin building
     * a Chronicle audit entry.
     *
     * Example:
     *
     * Chronicle::entry()
     *      ->actor($user)
     *      ->action('invoice.sent')
     *      ->subject($invoice)
     *      ->record();
     */
    public function entry(): EntryBuilder
    {
        return new EntryBuilder(
            resolver: $this->resolver,
            manager: $this,
        );
    }

    /**
     * Record an entry payload through the Chronicle pipeline.
     *
     * This method is called internally by EntryBuilder::record().
     *
     * The payload will pass through all configured processors
     * before being persisted.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(array $payload): void
    {
        $this->pipeline->process($payload);
//        $canonical = $this->serializer->serialize($payload);
//
//        $payload['payload'] = json_decode($canonical, true);
//
//        $this->store->append($payload);
    }
}
