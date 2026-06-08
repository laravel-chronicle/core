<?php

namespace Chronicle\Anchoring;

use Chronicle\Checkpoints\Checkpoint;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes chronicle_checkpoint_anchors rows. Shared by the queued job and the
 * CLI commands so anchoring behaves identically sync and async. A provider
 * failure marks the row 'failed' and rethrows so the queue can retry; it never
 * affects the checkpoint itself (the checkpoint is already committed).
 */
class CheckpointAnchorer
{
    public function __construct(private readonly AnchorManager $manager)
    {
        //
    }

    /**
     * @throws Throwable
     */
    public function anchor(Checkpoint $checkpoint, string $providerName): CheckpointAnchor
    {
        $provider = $this->manager->provider($providerName);

        $row = CheckpointAnchor::query()->firstOrNew([
            'checkpoint_id' => $checkpoint->id,
            'provider' => $providerName,
        ]);

        if (! $row->exists) {
            $row->id = (string) Str::ulid();
            $row->created_at = now();
        }

        $row->status = 'pending';
        $row->save();

        try {
            $receipt = $provider->anchor($checkpoint);
        } catch (Throwable $e) {
            $row->status = 'failed';
            $row->save();

            throw $e;
        }

        $row->reference = $receipt->reference;
        $row->proof = $receipt->proof;
        $row->status = 'anchored';
        $row->anchored_at = $receipt->anchoredAt;
        $row->save();

        return $row;
    }
}
