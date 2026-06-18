<?php

declare(strict_types=1);

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Entry\PendingEntry;

/**
 * Pipeline stage that runs the registered entry extensions against a pending entry.
 */
final class RunExtensions implements EntryProcessor
{
    public function __construct(
        protected EntryExtensionRegistry $extensions
    ) {}

    public function process(PendingEntry $entry): PendingEntry
    {
        if ($this->extensions->isEmpty()) {
            return $entry;
        }

        foreach ($this->extensions->ordered() as $extension) {
            $entry = $extension->process($entry);
        }

        return $entry;
    }
}
