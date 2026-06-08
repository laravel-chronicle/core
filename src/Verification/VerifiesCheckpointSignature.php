<?php

namespace Chronicle\Verification;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Exceptions\UnknownSigningKeyException;
use Chronicle\Signing\KeyRing;
use JsonException;

/**
 * Single source of truth for verifying a checkpoint's signature: resolve the
 * key via the ring, verify the v1.11 metadata-bound payload, and fall back to
 * the legacy bare-chain-hash format so pre-1.11 checkpoints still verify.
 *
 * Returns null when valid, otherwise the VerificationFailure value describing
 * why (UnknownKey when the key is not in the ring, CheckpointSignatureInvalid
 * when the signature does not verify).
 */
trait VerifiesCheckpointSignature
{
    /**
     * @throws JsonException
     */
    protected function checkpointSignatureFailure(Checkpoint $checkpoint, KeyRing $keyRing): ?string
    {
        try {
            $provider = $keyRing->resolve($checkpoint->algorithm, $checkpoint->key_id);
        } catch (UnknownSigningKeyException) {
            return VerificationFailure::UnknownKey->value;
        }

        $signaturePayload = CheckpointCreator::signaturePayload(
            id: $checkpoint->id,
            chainHash: $checkpoint->chain_hash,
            algorithm: $checkpoint->algorithm,
            keyId: $checkpoint->key_id,
            createdAt: $checkpoint->created_at->getTimestamp(),
        );

        $valid = $provider->verify($signaturePayload, $checkpoint->signature)
            // Legacy checkpoints (pre-metadata-signing) signed only the bare chain hash.
            || $provider->verify($checkpoint->chain_hash, $checkpoint->signature);

        return $valid ? null : VerificationFailure::CheckpointSignatureInvalid->value;
    }
}
