<?php

declare(strict_types=1);

namespace Chronicle\Context;

use Chronicle\Contracts\ContextResolver;
use Chronicle\Contracts\EntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\ExtensionStage;

/**
 * Base class for context resolvers; runs as an entry extension and merges its resolved data under a named context key.
 */
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
