<?php

declare(strict_types=1);

namespace Chronicle\Jobs;

use Chronicle\Anchoring\CheckpointAnchorer;
use Chronicle\Checkpoints\Checkpoint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Anchors a single checkpoint with a single provider. Retryable: a provider
 * failure rethrows from the anchorer so the queue re-runs it. Dispatched after
 * the checkpoint transaction commits, so it can never roll the checkpoint back.
 */
final class AnchorCheckpointJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $checkpointId,
        public readonly string $providerName,
    ) {}

    /**
     * The SQS message group this anchor is dispatched under.
     *
     * FIFO queues reject any message without a group. Anchoring is not order
     * sensitive - each (checkpoint, provider) pair writes its own row - so the
     * checkpoint ID is used as the group: anchors for different checkpoints stay
     * parallelisable, while a retrying anchor only ever blocks its own checkpoint.
     * A checkpoint ID is always a ULID, so it is always a valid group ID.
     *
     * Attached whatever the queue type, for the same reason as the persist job:
     * the framework resolves the real target queue after this runs, so the job
     * cannot tell whether a group is required. On a standard AWS queue it is
     * merely an unused fair-queue tenant marker.
     *
     * Overridden by ->onGroup() on the dispatched job, which the framework reads
     * from the $messageGroup property in preference to this method.
     */
    public function messageGroup(): string
    {
        return $this->checkpointId;
    }

    /**
     * @throws Throwable
     */
    public function handle(CheckpointAnchorer $anchorer): void
    {
        $checkpoint = Checkpoint::find($this->checkpointId);

        if ($checkpoint === null) {
            return;
        }

        $anchorer->anchor($checkpoint, $this->providerName);
    }
}
