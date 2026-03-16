<?php

namespace Chronicle\Storage;

use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\Entry;

/**
 * NullDriver
 *
 * Discards all entries silently.
 *
 * Use cases:
 *  - Local development where you don't want audit noise
 *  - Tests where Chronicle calls must succeed but entries don't matter
 *  - Environments where audit logging is explicitly disabled
 *
 * Returns an unsaved Entry model hydrated from the PendingEntry so that
 * call sites which use the return value don't need null checks.
 */
class NullDriver implements StorageDriver
{
    /**
     * @param  array<string, mixed>  $entry
     */
    public function store(array $entry): Entry
    {
        $model = new Entry;

        $model->forceFill($this->toEntryAttributes($entry));

        // Deliberately not saved - this driver is a black hole.
        return $model;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function toEntryAttributes(array $entry): array
    {
        return [
            'id' => $entry['id'],
            'actor_type' => $entry['actor_type'],
            'actor_id' => $entry['actor_id'],
            'action' => $entry['action'],
            'subject_type' => $entry['subject_type'],
            'subject_id' => $entry['subject_id'],
            'payload' => json_encode($entry['payload']),
            'payload_hash' => $entry['payload_hash'],
            'chain_hash' => $entry['chain_hash'],
            'metadata' => json_encode($entry['metadata']),
            'context' => json_encode($entry['context']),
            'tags' => json_encode($entry['tags']),
            'diff' => json_encode($entry['diff']),
            'correlation_id' => $entry['correlation_id'],
            'checkpoint_id' => $entry['checkpoint_id'],
            'created_at' => $entry['created_at'],
        ];
    }
}
