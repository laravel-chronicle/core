<?php

namespace Chronicle\Contracts;

use Chronicle\Anchoring\AnchorReceipt;
use Chronicle\Checkpoints\Checkpoint;

/**
 * Pluggable external attestation for checkpoints. Mirrors SigningProvider:
 * Chronicle delegates the act of anchoring (and verifying an anchor) to a
 * provider, so the trust root can live outside the application.
 */
interface AnchorProvider
{
    /**
     * Stable provider name, stored on each anchor row (e.g. 'null', 'rfc3161').
     */
    public function name(): string;

    /**
     * Anchor the checkpoint with the external sink and return the receipt.
     */
    public function anchor(Checkpoint $checkpoint): AnchorReceipt;

    /**
     * Verify a previously-issued receipt still attests this checkpoint.
     * Must be offline (no network) where the provider supports it.
     */
    public function verify(Checkpoint $checkpoint, AnchorReceipt $receipt): bool;
}
