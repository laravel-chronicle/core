<?php

namespace Chronicle\Contracts;

/**
 * Interface EntryProcessor
 *
 * Represents a stage in the Chronicle entry
 * processing pipeline.
 */
interface EntryProcessor
{
    /**
     * Process the entry payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function process(array $payload): array;
}
