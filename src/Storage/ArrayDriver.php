<?php

declare(strict_types=1);

namespace Chronicle\Storage;

use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\Entry;
use Illuminate\Support\Collection;
use JsonException;

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
final class ArrayDriver implements StorageDriver
{
    use SerializesEntryAttributes;

    /** @var array<int|string, mixed> */
    protected static array $entries = [];

    /** @var array<int|string, mixed> */
    protected array $instanceEntries = [];

    /**
     * @param  array<string, mixed>  $entry
     *
     * @throws JsonException
     */
    public function store(array $entry): Entry
    {
        ArrayDriver::$entries[] = $entry;
        $this->instanceEntries[] = $entry;

        $model = new Entry;

        $model->forceFill($this->toEntryAttributes($entry));

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

    /**
     * @return Collection<int|string, mixed>
     */
    public function allEntries(): Collection
    {
        return collect($this->instanceEntries);
    }
}
