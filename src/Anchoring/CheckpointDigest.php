<?php

declare(strict_types=1);

namespace Chronicle\Anchoring;

use Chronicle\Checkpoints\Checkpoint;

/**
 * The stable value of anchor attests: sha256(id . chain_hash . created_at).
 * Binds a receipt to exactly one checkpoint, so it cannot be replayed, and
 * changes if any covered byte (notably chain_hash) is rewritten. Uses the
 * integer Unix timestamp, so it round-trips identically across databases.
 */
final class CheckpointDigest
{
    public static function for(Checkpoint $checkpoint): string
    {
        return hash('sha256', implode('', [
            $checkpoint->id,
            $checkpoint->chain_hash,
            (string) $checkpoint->created_at->getTimestamp(),
        ]));
    }
}
