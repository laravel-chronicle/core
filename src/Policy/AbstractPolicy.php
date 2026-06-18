<?php

declare(strict_types=1);

namespace Chronicle\Policy;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\EntryPolicy;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\ExtensionStage;

/**
 * Base class for opt-in entry policies; runs in the POLICY stage and enforces a single rule.
 */
abstract class AbstractPolicy implements EntryExtension, EntryPolicy
{
    final public function stage(): ExtensionStage
    {
        return ExtensionStage::POLICY;
    }

    final public function process(PendingEntry $entry): PendingEntry
    {
        $this->enforce($entry);

        return $entry;
    }

    abstract public function enforce(PendingEntry $entry): void;
}
