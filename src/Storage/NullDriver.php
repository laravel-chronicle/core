<?php

declare(strict_types=1);

namespace Chronicle\Storage;

use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use JsonException;

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
final class NullDriver implements StorageDriver
{
    use SerializesEntryAttributes;

    /**
     * @param  array<string, mixed>  $entry
     *
     * @throws JsonException
     */
    public function store(array $entry): Entry
    {
        $class = Chronicle::entryModel();
        $model = new $class;

        $model->forceFill($this->toEntryAttributes($entry));

        // Deliberately not saved - this driver is a black hole.
        return $model;
    }
}
