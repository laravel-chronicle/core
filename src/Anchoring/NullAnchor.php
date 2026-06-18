<?php

declare(strict_types=1);

namespace Chronicle\Anchoring;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Contracts\AnchorProvider;

/**
 * Dev/test anchor: records the checkpoint digest as its proof and verifies by
 * recomputing it. NOTE: the proof lives in the same database, so this provides
 * NO external trust - it is not a production anchor.
 */
class NullAnchor implements AnchorProvider
{
    /**
     * @param  array<string, mixed>  $config  Accepted for AnchorManager makeWith() compatibility.
     */
    public function __construct(
        protected array $config = [],
    ) {
        //
    }

    public function name(): string
    {
        return 'null';
    }

    public function anchor(Checkpoint $checkpoint): AnchorReceipt
    {
        return new AnchorReceipt(
            provider: $this->name(),
            reference: null,
            proof: CheckpointDigest::for($checkpoint),
            anchoredAt: now()->toImmutable(),
        );
    }

    public function verify(Checkpoint $checkpoint, AnchorReceipt $receipt): bool
    {
        return $receipt->proof !== null
            && hash_equals(CheckpointDigest::for($checkpoint), $receipt->proof);
    }
}
