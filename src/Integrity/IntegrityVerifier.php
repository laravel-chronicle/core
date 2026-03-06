<?php

namespace Chronicle\Integrity;

use Chronicle\Contracts\SigningProvider;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Models\Checkpoint;
use Chronicle\Models\Entry;
use Chronicle\Serialization\CanonicalPayloadSerializer;

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
     */
    public function verify(?callable $onProgress = null): VerificationResult
    {
        $previousChain = '0';
        $count = 0;
        $total = Entry::query()->count();

        $result = new VerificationResult;

        /** @var Entry $entry */
        foreach (Entry::query()->orderBy('created_at')->orderBy('id')->cursor() as $entry) {
            // Payload verification
            $canonical = $this->serializer->serialize(
                $entry->payload
            );

            $payloadHash = hash('sha256', $canonical);

            if ($payloadHash !== $entry->payload_hash) {
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

            if ($expectedChain !== $entry->chain_hash) {
                $result->fail(
                    'chain_hash_mismatch',
                    $entry->id
                );

                return $result;
            }

            // Checkpoint verification
            if ($entry->checkpoint_id) {
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
            }

            $previousChain = $entry->chain_hash;
            $count++;

            if ($onProgress) {
                $onProgress($count, $total);
            }
        }

        $result->success($count);

        return $result;
    }
}
