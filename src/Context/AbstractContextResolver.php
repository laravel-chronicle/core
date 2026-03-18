<?php

namespace Chronicle\Context;

use Chronicle\Contracts\ContextResolver;
use Chronicle\Contracts\EntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\ExtensionStage;

abstract class AbstractContextResolver implements ContextResolver, EntryExtension
{
    public function stage(): ExtensionStage
    {
        return ExtensionStage::RESOLVE_CONTEXT;
    }

    public function process(PendingEntry $entry): PendingEntry
    {
        $resolved = $this->resolve($entry);

        if ($resolved === null) {
            return $entry;
        }

        $context = is_array($entry->attribute('context')) ? $entry->attribute('context') : [];
        $context[$this->contextKey()] = $resolved;
        $entry->setAttribute('context', $context);

        return $entry;
    }
}
