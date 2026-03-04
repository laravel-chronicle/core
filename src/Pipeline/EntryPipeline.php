<?php

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;

/**
 * Executes Chronicle entry processors sequentially.
 */
class EntryPipeline implements EntryProcessor
{
    /** @var array<string, mixed> */
    protected array $processors;

    public function __construct(array $processors)
    {
        $this->processors = $processors;
    }

    public function process(array $payload): array
    {
        foreach ($this->processors as $processor) {
            $payload = $processor->process($payload);
        }

        return $payload;
    }
}
