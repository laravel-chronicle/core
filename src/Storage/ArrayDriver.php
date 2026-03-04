<?php

namespace Chronicle\Storage;

use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\PendingEntry;
use Chronicle\Models\Entry;
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
            'metadata' => $entry['metadata'],
            'context' => $entry['context'],
            //            'tags' => $entry['tags'],
            //            'chain_hash' => $entry['chainHash'],
            //            'checkpoint_id' => $entry['checkpointId'],
            'created_at' => $entry['created_at'],
        ]);

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
