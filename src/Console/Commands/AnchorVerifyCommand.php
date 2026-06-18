<?php

declare(strict_types=1);

namespace Chronicle\Console\Commands;

use Chronicle\Anchoring\AnchorManager;
use Chronicle\Anchoring\AnchorReceipt;
use Chronicle\Anchoring\CheckpointAnchor;
use Chronicle\Checkpoints\Checkpoint;
use Illuminate\Console\Command;

/**
 * Artisan command that verifies stored checkpoint anchors against their providers.
 */
final class AnchorVerifyCommand extends Command
{
    protected $signature = 'chronicle:anchor:verify {--checkpoint= : Verify anchors for a single checkpoint ULID (default: all anchored)}';

    protected $description = 'Verify stored checkpoint anchors against their providers';

    public function handle(AnchorManager $manager): int
    {
        /** @var string|null $checkpointId */
        $checkpointId = $this->option('checkpoint');

        $query = CheckpointAnchor::query()->where('status', 'anchored');
        if ($checkpointId !== null) {
            $query->where('checkpoint_id', $checkpointId);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->warn('No anchored receipts found for the given scope.');

            return self::FAILURE;
        }

        $failures = 0;

        foreach ($rows as $row) {
            $checkpoint = Checkpoint::find($row->checkpoint_id);
            $ok = $checkpoint !== null && $manager->provider($row->provider)->verify(
                $checkpoint,
                new AnchorReceipt($row->provider, $row->reference, $row->proof, $row->anchored_at ?? now()->toImmutable()),
            );

            if ($ok) {
                $this->line("✓ [$row->provider] checkpoint [$row->checkpoint_id]");
            } else {
                $failures++;
                $this->error("✗ [$row->provider] checkpoint [$row->checkpoint_id]");
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
