<?php

declare(strict_types=1);

namespace Chronicle\Contracts;

use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\ExtensionStage;

/**
 * Contract for pipeline extensions that observe or transform a pending entry.
 */
interface EntryExtension
{
    /**
     * Stage where this extension executes.
     */
    public function stage(): ExtensionStage;

    /**
     * Apply extension behavior to an entry.
     */
    public function process(PendingEntry $entry): PendingEntry;
}
