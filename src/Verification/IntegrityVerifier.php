<?php

namespace Chronicle\Verification;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Contracts\SigningProvider;
use Chronicle\Entry\Entry;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Support\CanonicalPayloadSerializer;
use JsonException;

/**
 * Performs full Chronicle entries integrity verification.
 */
class IntegrityVerifier
{
    protected CanonicalPayloadSerializer $serializer;

    protected ChainHasher $chainHasher;

    protected SigningProvider $signer;

    public function __construct(
        CanonicalPayloadSerializer $serializer,
        ChainHasher $chainHasher,
        SigningProvider $signer
    ) {
        $this->serializer = $serializer;
        $this->chainHasher = $chainHasher;
        $this->signer = $signer;
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
        $previousChain = '0';
        $count = 0;

        $result = new VerificationResult;

        /** @var array<string, bool> $verifiedCheckpoints */
        $verifiedCheckpoints = [];

        /** @var Entry $entry */
        foreach (Entry::query()->orderBy('id')->cursor() as $entry) {
            // Payload verification
            $canonical = $this->serializer->serialize(
                $entry->payload
            );

            $payloadHash = hash('sha256', $canonical);

            if (! hash_equals($payloadHash, (string) $entry->payload_hash)) {
                $result->fail(
                    'payload_hash_mismatch',
                    $entry->id
                );

                return $result;
            }

            // Chain verification
            $expectedChain = $this->chainHasher->hash(
                $previousChain,
                $payloadHash
            );

            if (! hash_equals($expectedChain, (string) $entry->chain_hash)) {
                $result->fail(
                    'chain_hash_mismatch',
                    $entry->id
                );

                return $result;
            }

            // Checkpoint verification — each unique checkpoint is fetched and verified once
            if ($entry->checkpoint_id && ! isset($verifiedCheckpoints[$entry->checkpoint_id])) {
                $checkpoint = Checkpoint::find($entry->checkpoint_id);

                if (! $checkpoint) {
                    $result->fail(
                        'checkpoint_missing',
                        $entry->id
                    );

                    return $result;
                }

                $valid = $this->signer->verify(
                    $checkpoint->chain_hash,
                    $checkpoint->signature,
                );

                if (! $valid) {
                    $result->fail(
                        'checkpoint_signature_invalid',
                        $entry->id
                    );

                    return $result;
                }

                $verifiedCheckpoints[$entry->checkpoint_id] = true;
            }

            $previousChain = $entry->chain_hash;
            $count++;

            if ($onProgress) {
                $onProgress($count);
            }
        }

        $result->success($count);

        return $result;
    }
}
