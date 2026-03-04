<?php

namespace Chronicle\Storage;

use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\PendingEntry;
use Chronicle\Models\Entry;

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
            'payload' => $entry['payload'],
            'metadata' => $entry['metadata'],
            'context' => $entry['context'],
            //            'tags' => json_encode($entry['tags']),
            //            'chain_hash' => $entry['chainHash'],
            //            'checkpoint_id' => $entry['checkpointId'],
            'created_at' => $entry['created_at'],
        ];
    }
}
