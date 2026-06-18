<?php

declare(strict_types=1);

namespace Chronicle\Context;

use Chronicle\Entry\PendingEntry;

/**
 * Context resolver that captures queue job context when recording inside a queued job.
 */
final class QueueContextResolver extends AbstractContextResolver
{
    public function __construct(
        protected readonly QueueJobContext $jobContext,
    ) {
        //
    }

    public function contextKey(): string
    {
        return 'queue';
    }

    public function resolve(PendingEntry $entry): ?array
    {
        $job = $this->jobContext->current();

        if ($job === null) {
            return null;
        }

        $jobId = $job->getJobId();

        return [
            'job_id' => $jobId,
            'connection' => $job->getConnectionName(),
            'queue' => $job->getQueue(),
        ];
    }
}
