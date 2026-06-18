<?php

declare(strict_types=1);

namespace Chronicle\Context;

use Illuminate\Contracts\Queue\Job;

final class QueueJobContext
{
    private ?Job $job = null;

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
