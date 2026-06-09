<?php

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
class AnchorCheckpointJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $checkpointId,
        public readonly string $providerName,
    ) {}

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
