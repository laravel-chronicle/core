<?php

declare(strict_types=1);

namespace Chronicle\Context;

use Illuminate\Contracts\Queue\Job;

/**
 * Holds the queue job currently being processed so resolvers can attach queue context to entries.
 */
final class QueueJobContext
{
    protected ?Job $job = null;

    public function set(Job $job): void
    {
        $this->job = $job;
    }

    public function clear(): void
    {
        $this->job = null;
    }

    public function current(): ?Job
    {
        return $this->job;
    }
}
