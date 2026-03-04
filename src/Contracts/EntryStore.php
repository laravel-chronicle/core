<?php

namespace Chronicle\Contracts;

/**
 * Interface EntryStore
 *
 * Defines the persistence mechanism used by Chronicle
 * to store audit entries.
 *
 * Chronicle intentionally abstracts storage so different
 * backend can be used (database, cloud storage, etc.).
 */
interface EntryStore
{
    /**
     * Persist a Chronicle entry.
     *
     * Implementations must ensure append-only behavior.
     *
     * @param  array<string, mixed>  $payload
     */
    public function append(array $payload): void;
}
