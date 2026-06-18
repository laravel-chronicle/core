<?php

declare(strict_types=1);

namespace Chronicle\Storage;

use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\Entry;
use LogicException;

/**
 * Storage driver that defers entry persistence to a queued job on the single-worker chronicle queue.
 */
final class QueuedDriver implements StorageDriver
{
    /**
     * @param  array<string, mixed>  $entry
     */
    public function store(array $entry): Entry
    {
        // ChronicleManager::runCommit() detects QueuedDriver and dispatches
        // PersistChronicleEntryJob directly - this method is never called on
        // the normal code path. Throw to make any accidental direct call visible.
        throw new LogicException('QueuedDriver::store() must not be called directly. Dispatch is handled by ChronicleManager::runCommit().');
    }
}
