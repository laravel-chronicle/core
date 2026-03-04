<?php

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Entry\PendingEntry;

/**
 * Executes Chronicle entry processors sequentially.
 */
class EntryPipeline implements EntryProcessor
{
    /** @var array<int, EntryProcessor> */
    protected array $processors;

    /** @param array<int, EntryProcessor> $processors */
    public function __construct(array $processors)
    {
        $this->processors = $processors;
    }

    public function process(PendingEntry $entry): PendingEntry
    {
        foreach ($this->processors as $processor) {
            $entry = $processor->process($entry);
        }

        return $entry;
    }
}
