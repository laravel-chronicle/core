<?php

namespace Chronicle\Storage;

use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\Entry;
use Illuminate\Support\Collection;

/**
 * Stores entries in memory for the duration of the request/test.
 *
 * This driver is the backbone of Chronicle::fake(). It stores PendingEntry
 * objects in a static array so that test assertions can inspect them without
 * touching the database.
 *
 * Always call ArrayDriver::flush() between tests (the HasChronicle trait
 * does this automatically).
 */
class ArrayDriver implements StorageDriver
{
    /** @var array<int|string, mixed> */
    private static array $entries = [];

    /**
     * @param  array<string, mixed>  $entry
     */
    public function store(array $entry): Entry
    {
        ArrayDriver::$entries[] = $entry;

        $model = new Entry;

        $model->forceFill([
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
        ]);

        $model->exists = true;

        return $model;
    }

    /**
     * Return all stored PendingEntry objects as a Collection
     *
     * @return Collection<int|string, mixed>
     */
    public static function all(): Collection
    {
        return collect(ArrayDriver::$entries);
    }

    /**
     * Return the count of stored entries.
     */
    public static function count(): int
    {
        return count(ArrayDriver::$entries);
    }

    /**
     * Clear all stored entries. Call between tests.
     */
    public static function flush(): void
    {
        ArrayDriver::$entries = [];
    }
}
