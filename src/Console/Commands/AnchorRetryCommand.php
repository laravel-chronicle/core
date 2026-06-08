<?php

namespace Chronicle\Console\Commands;

use Chronicle\Anchoring\CheckpointAnchor;
use Chronicle\Anchoring\CheckpointAnchorer;
use Chronicle\Checkpoints\Checkpoint;
use Illuminate\Console\Command;
use Throwable;

class AnchorRetryCommand extends Command
{
    protected $signature = 'chronicle:anchor:retry {--status=failed : Retry anchors in this status (pending|failed)}';

    protected $description = 'Re-attempt outstanding checkpoint anchors';

    public function handle(CheckpointAnchorer $anchorer): int
    {
        /** @var string $status */
        $status = $this->option('status');

        $rows = CheckpointAnchor::query()->where('status', $status)->get();

        if ($rows->isEmpty()) {
            $this->info("No anchors in status [$status].");

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($rows as $row) {
            $checkpoint = Checkpoint::find($row->checkpoint_id);

            if ($checkpoint === null) {
                continue;
            }

            try {
                $anchorer->anchor($checkpoint, $row->provider);
                $this->line("Re-anchored checkpoint [$row->checkpoint_id] via [$row->provider].");
            } catch (Throwable $e) {
                $failures++;
                $this->error("Retry failed for [$row->checkpoint_id] via [$row->provider]: {$e->getMessage()}");
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
