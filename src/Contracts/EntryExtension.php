<?php

namespace Chronicle\Contracts;

use Chronicle\Entry\PendingEntry;
use Chronicle\Extensions\ExtensionStage;

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
