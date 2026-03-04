<?php

namespace Chronicle;

use Chronicle\Contracts\EntryStore;
use Chronicle\Contracts\ReferenceResolver;

/**
 * Class ChronicleManager
 *
 * ChronicleManager is the central service responsible for
 * coordination Chronicle's audit logging pipeline.
 *
 * Responsibilities:
 *  - Create EntryBuilder instances
 *  - Resolve actor and subject references
 *  - Persist entries through the configured EntryStore
 *
 * The manager acts as the bridge between the developer-facing
 * Chronicle API and the internal Chronicle infrastructure.
 */
class ChronicleManager
{
    /**
     * Entry storage implementation.
     */
    protected EntryStore $store;

    /**
     * Reference resolver used for actors and subjects.
     */
    protected ReferenceResolver $resolver;

    /**
     * Create a new Chronicle manager instance.
     */
    public function __construct(EntryStore $store, ReferenceResolver $resolver)
    {
        $this->store = $store;
        $this->resolver = $resolver;
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
     * Persist an entry payload.
     *
     * This method is called internally by EntryBuilder
     * when record() is invoked.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(array $payload): void
    {
        $this->store->append($payload);
    }
}
