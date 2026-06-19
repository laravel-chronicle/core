<?php

declare(strict_types=1);

namespace Chronicle\Verification;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Facades\Chronicle;

/**
 * Resolves the signed checkpoints that enclose an arbitrary entry range, so a
 * caller with only entry sequences can verify a range whose trust still rides
 * on signed checkpoint anchors. A checkpoint's head sequence is the sequence of
 * the entry referenced by its head_id (checkpoints store no sequence column).
 */
final class EnclosingCheckpointResolver
{
    /**
     * The latest checkpoint whose head sequence is strictly less than
     * $fromSequence. Null means the range starts at or before the first
     * checkpoint (verify from genesis).
     */
    public function start(int $fromSequence): ?Checkpoint
    {
        $headId = Chronicle::newEntryQuery()
            ->whereIn('id', Checkpoint::query()->whereNotNull('head_id')->select('head_id'))
            ->where('sequence', '<', $fromSequence)
            ->orderByDesc('sequence')
            ->value('id');

        if (! is_string($headId)) {
            return null;
        }

        return Checkpoint::query()->where('head_id', $headId)->first();
    }

    /**
     * The earliest checkpoint whose head sequence is greater than or equal to
     * $toSequence. Null means the range extends past the last checkpoint (the
     * unanchored tail).
     */
    public function end(int $toSequence): ?Checkpoint
    {
        $headId = Chronicle::newEntryQuery()
            ->whereIn('id', Checkpoint::query()->whereNotNull('head_id')->select('head_id'))
            ->where('sequence', '>=', $toSequence)
            ->orderBy('sequence')
            ->value('id');

        if (! is_string($headId)) {
            return null;
        }

        return Checkpoint::query()->where('head_id', $headId)->first();
    }

    /**
     * The sequence of the checkpoint's head entry, or null if the checkpoint
     * has no head_id or the head entry no longer exists (e.g. pruned).
     */
    public function headSequence(Checkpoint $checkpoint): ?int
    {
        if ($checkpoint->head_id === null) {
            return null;
        }

        $sequence = Chronicle::newEntryQuery()->whereKey($checkpoint->head_id)->value('sequence');

        return is_numeric($sequence) ? (int) $sequence : null;
    }
}
