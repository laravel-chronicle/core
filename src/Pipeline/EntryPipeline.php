<?php

declare(strict_types=1);

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

    /**
     * Split the pipeline before the first processor of the given class.
     *
     * Returns the processors before it, and the rest starting with it - null
     * when the pipeline holds no such processor.
     *
     * @param  class-string<EntryProcessor>  $class
     * @return array{0: EntryPipeline, 1: EntryPipeline|null}
     */
    public function splitBefore(string $class): array
    {
        $processors = array_values($this->processors);

        foreach ($processors as $index => $processor) {
            if ($processor instanceof $class) {
                return [
                    new self(array_slice($processors, 0, $index)),
                    new self(array_slice($processors, $index)),
                ];
            }
        }

        return [$this, null];
    }
}
