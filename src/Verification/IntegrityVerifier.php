<?php

declare(strict_types=1);

namespace Chronicle\Verification;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Entry\Entry;
use Chronicle\Exceptions\UnknownSigningKeyException;
use Chronicle\Facades\Chronicle;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Signing\KeyRing;
use Chronicle\Support\CanonicalPayloadSerializer;
use InvalidArgumentException;
use JsonException;

/**
 * Verifies the full ledger (or a bounded segment) hash chain and checkpoint signatures.
 */
final class IntegrityVerifier
{
    use ComparesEntryColumns, VerifiesCheckpointSignature;

    protected CanonicalPayloadSerializer $serializer;

    protected ChainHasher $chainHasher;

    protected KeyRing $keyRing;

    public function __construct(
        CanonicalPayloadSerializer $serializer,
        ChainHasher $chainHasher,
        KeyRing $keyRing
    ) {
        $this->serializer = $serializer;
        $this->chainHasher = $chainHasher;
        $this->keyRing = $keyRing;
    }

    /**
     * Verify the entire ledger.
     *
     * @param  callable(int $processed): void|null  $onProgress
     *
     * @throws JsonException
     */
    public function verify(?callable $onProgress = null): VerificationResult
    {
        return $this->walk(previousChain: ChainHasher::GENESIS, afterSequence: 0, onProgress: $onProgress);
    }

    /**
     * Verify the ledger starting from a known-good checkpoint, for ledgers whose
     * earlier history has been pruned. The checkpoint's signature is verified first;
     * its chain_hash seeds the walk and only entries after it are checked.
     *
     * @param  callable(int $processed): void|null  $onProgress
     *
     * @throws JsonException
     */
    public function verifyFrom(Checkpoint $checkpoint, ?callable $onProgress = null): VerificationResult
    {
        $result = new VerificationResult;

        $signatureFailure = $this->checkpointSignatureFailure($checkpoint, $this->keyRing);

        if ($signatureFailure !== null) {
            $result->fail($signatureFailure, $checkpoint->id);

            return $result;
        }

        $afterSequence = Chronicle::newEntryQuery()
            ->where('chain_hash', $checkpoint->chain_hash)
            ->value('sequence');

        return $this->walk(
            previousChain: $checkpoint->chain_hash,
            afterSequence: is_numeric($afterSequence) ? (int) $afterSequence : 0,
            onProgress: $onProgress,
        );
    }

    /**
     * Verify a bounded segment of the ledger: entries with sequence in
     * (afterSequence, throughSequence), recomputed from a trusted starting
     * chain hash and required to end exactly at $expectedEndingChain. Reuses the
     * same payload/column/chain checks as full verification.
     *
     * @param  callable(int $processed): void|null  $onProgress
     *
     * @throws JsonException
     */
    public function verifySegment(
        string $previousChain,
        int $afterSequence,
        int $throughSequence,
        string $expectedEndingChain,
        ?callable $onProgress = null,
    ): VerificationResult {
        return $this->walk(
            previousChain: $previousChain,
            afterSequence: $afterSequence,
            onProgress: $onProgress,
            throughSequence: $throughSequence,
            expectedEndingChain: $expectedEndingChain,
        );
    }

    /**
     * Verify an arbitrary entry range [fromSequence, toSequence] without the
     * caller deriving checkpoint bounds. Trust stays anchored on signed
     * checkpoints: the enclosing signed checkpoints around the range are
     * resolved, their signatures verified, and the trusted previousChain /
     * expectedEndingChain are derived from them before delegating to
     * verifySegment.
     *
     * - Range inside a single checkpoint segment or spanning several: bounded
     *   by the enclosing signed checkpoints.
     * - Range starting at or before the first checkpoint: previousChain is
     *   GENESIS (recomputed from the start of the ledger).
     * - Range extending past the last checkpoint (unanchored tail): there is no
     *   trailing-signed anchor, so the tail is recomputed from the last
     *   enclosing signed checkpoint to the head via verifyFrom() (or verify()
     *   from genesis when no checkpoint encloses the start). This carries the
     *   same trust as chronicle:verify --since-last-checkpoint.
     *
     * @param  callable(int $processed): void|null  $onProgress
     *
     * @throws JsonException
     */
    public function verifyEntryRange(
        int $fromSequence,
        int $toSequence,
        ?callable $onProgress = null,
    ): VerificationResult {
        if ($fromSequence < 1 || $toSequence < $fromSequence) {
            throw new InvalidArgumentException(
                "Invalid entry range [$fromSequence, $toSequence]: require 1 <= fromSequence <= toSequence."
            );
        }

        $result = new VerificationResult;
        $resolver = new EnclosingCheckpointResolver;

        $startCheckpoint = $resolver->start($fromSequence);

        // The start anchor's head is excluded from the segment walk, so its
        // signature must be verified here (the walk never re-checks it).
        if ($startCheckpoint !== null) {
            $startFailure = $this->checkpointSignatureFailure($startCheckpoint, $this->keyRing);

            if ($startFailure !== null) {
                $result->fail($startFailure, $startCheckpoint->id);

                return $result;
            }
        }

        $previousChain = $startCheckpoint->chain_hash ?? ChainHasher::GENESIS;
        $afterSequence = $startCheckpoint !== null
            ? ($resolver->headSequence($startCheckpoint) ?? 0)
            : 0;

        $endCheckpoint = $resolver->end($toSequence);

        // Unanchored tail: no trailing signed checkpoint at or beyond toSequence.
        // Recompute from the last enclosing signed checkpoint to the head.
        if ($endCheckpoint === null) {
            return $startCheckpoint !== null
                ? $this->verifyFrom($startCheckpoint, $onProgress)
                : $this->verify($onProgress);
        }

        $endFailure = $this->checkpointSignatureFailure($endCheckpoint, $this->keyRing);

        if ($endFailure !== null) {
            $result->fail($endFailure, $endCheckpoint->id);

            return $result;
        }

        $throughSequence = $resolver->headSequence($endCheckpoint);

        if ($throughSequence === null) {
            $result->fail(VerificationFailure::CheckpointMissing->value, $endCheckpoint->id);

            return $result;
        }

        // Defense in depth: the signed anchors MUST actually enclose [from, to].
        // head_id is not signed, so a tamperer could mis-point it; if the
        // derived bounds do not cover the request, fail closed rather than
        // verify a narrower span.
        if ($afterSequence >= $fromSequence || $throughSequence < $toSequence) {
            $result->fail(VerificationFailure::SegmentDiscontinuous->value, $endCheckpoint->id);

            return $result;
        }

        return $this->verifySegment(
            previousChain: $previousChain,
            afterSequence: $afterSequence,
            throughSequence: $throughSequence,
            expectedEndingChain: $endCheckpoint->chain_hash,
            onProgress: $onProgress,
        );
    }

    /**
     * @param  callable(int $processed): void|null  $onProgress
     *
     * @throws JsonException
     */
    protected function walk(
        string $previousChain,
        int $afterSequence,
        ?callable $onProgress,
        ?int $throughSequence = null,
        ?string $expectedEndingChain = null,
    ): VerificationResult {
        $count = 0;
        $result = new VerificationResult;

        /** @var array<string, bool> $verifiedCheckpoints */
        $verifiedCheckpoints = [];

        $lastEntryId = '';

        /** @var Entry $entry */
        foreach (
            Chronicle::newEntryQuery()
                ->where('sequence', '>', $afterSequence)
                ->when($throughSequence !== null, fn ($q) => $q->where('sequence', '<=', $throughSequence))
                ->orderBy('sequence')
                ->cursor() as $entry
        ) {
            // Payload verification
            $canonical = $this->serializer->serialize($entry->payload);
            $payloadHash = hash('sha256', $canonical);

            if (! hash_equals($payloadHash, (string) $entry->payload_hash)) {
                $result->fail(VerificationFailure::PayloadHashMismatch->value, $entry->id);

                return $result;
            }

            // Column / payload divergence (Task 2)
            if (! $this->columnsMatchPayload($entry, $entry->payload, $this->serializer)) {
                $result->fail(VerificationFailure::ColumnPayloadDivergence->value, $entry->id);

                return $result;
            }

            // Chain verification
            $expectedChain = $this->chainHasher->hash($previousChain, $payloadHash);

            if (! hash_equals($expectedChain, (string) $entry->chain_hash)) {
                $result->fail(VerificationFailure::ChainHashMismatch->value, $entry->id);

                return $result;
            }

            // Checkpoint verification - each unique checkpoint is fetched and verified once
            if ($entry->checkpoint_id && ! isset($verifiedCheckpoints[$entry->checkpoint_id])) {
                $checkpoint = Checkpoint::find($entry->checkpoint_id);

                if (! $checkpoint) {
                    $result->fail(VerificationFailure::CheckpointMissing->value, $entry->id);

                    return $result;
                }

                try {
                    $provider = $this->keyRing->resolve($checkpoint->algorithm, $checkpoint->key_id);
                } catch (UnknownSigningKeyException) {
                    $result->fail(VerificationFailure::UnknownKey->value, $entry->id);

                    return $result;
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

                if (! $valid) {
                    $result->fail(VerificationFailure::CheckpointSignatureInvalid->value, $entry->id);

                    return $result;
                }

                $verifiedCheckpoints[$entry->checkpoint_id] = true;
            }

            $previousChain = $entry->chain_hash;
            $count++;
            $lastEntryId = $entry->id;

            if ($onProgress) {
                $onProgress($count);
            }
        }

        if ($expectedEndingChain !== null && ! hash_equals($previousChain, $expectedEndingChain)) {
            $result->fail(VerificationFailure::SegmentDiscontinuous->value, $lastEntryId);

            return $result;
        }

        $result->success($count);

        return $result;
    }
}
