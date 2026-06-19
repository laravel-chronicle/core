<?php

declare(strict_types=1);

namespace Chronicle\Verification;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Signing\KeyRing;
use JsonException;

/**
 * Fast attestation over the checkpoint chain - O(number of checkpoints).
 * Walks checkpoints oldest->newest verifying: signature, that chain_hash equals
 * the head entry's chain_hash, previous_checkpoint_id linkage, and entry_count
 * contiguity. Does NOT recompute per-entry hashes.
 */
final class CheckpointChainVerifier
{
    use VerifiesCheckpointSignature;

    protected KeyRing $keyRing;

    public function __construct(KeyRing $keyRing)
    {
        $this->keyRing = $keyRing;
    }

    /**
     * @param  callable(int $processed): void|null  $onProgress
     *
     * @throws JsonException
     */
    public function verify(?callable $onProgress = null): VerificationResult
    {
        $result = new VerificationResult;
        $count = 0;

        /** @var Checkpoint|null $previous */
        $previous = null;

        /** @var Checkpoint $checkpoint */
        foreach (
            Checkpoint::query()
                ->orderBy('created_at')
                ->orderBy('id')
                ->cursor() as $checkpoint
        ) {
            // 1. Signature (shared 1.10 path + legacy fallback).
            $signatureFailure = $this->checkpointSignatureFailure($checkpoint, $this->keyRing);

            if ($signatureFailure !== null) {
                $result->fail($signatureFailure, $checkpoint->id);

                return $result;
            }

            // 2. chain_hash must equal the head entry's chain_hash.
            $headChain = $checkpoint->head_id === null
                ? null
                : Chronicle::newEntryQuery()->whereKey($checkpoint->head_id)->value('chain_hash');

            if (! is_string($headChain) || ! hash_equals($headChain, $checkpoint->chain_hash)) {
                $result->fail(VerificationFailure::CheckpointHeadMismatch->value, $checkpoint->id);

                return $result;
            }

            // 3. previous_checkpoint_id linkage.
            if ($checkpoint->previous_checkpoint_id !== $previous?->id) {
                $result->fail(VerificationFailure::CheckpointChainBroken->value, $checkpoint->id);

                return $result;
            }

            // 4. entry_count strictly increasing along the chain.
            if ($previous !== null && (int) $checkpoint->entry_count <= (int) $previous->entry_count) {
                $result->fail(VerificationFailure::CheckpointChainBroken->value, $checkpoint->id);

                return $result;
            }

            $previous = $checkpoint;
            $count++;

            if ($onProgress) {
                $onProgress($count);
            }
        }

        $result->success($count);

        return $result;
    }
}
