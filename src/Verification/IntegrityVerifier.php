<?php

namespace Chronicle\Verification;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Entry\Entry;
use Chronicle\Exceptions\UnknownSigningKeyException;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Signing\KeyRing;
use Chronicle\Support\CanonicalPayloadSerializer;
use JsonException;

class IntegrityVerifier
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

        $afterSequence = Entry::query()
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
            Entry::query()
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
