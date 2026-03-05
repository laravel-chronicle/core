<?php

namespace Chronicle\Integrity;

use Chronicle\Hashing\ChainHasher;
use Chronicle\Models\Entry;
use Chronicle\Serialization\CanonicalPayloadSerializer;

/**
 * Performs full Chronicle entries integrity verification.
 */
class IntegrityVerifier
{
    protected CanonicalPayloadSerializer $serializer;

    protected ChainHasher $chainHasher;

    public function __construct(CanonicalPayloadSerializer $serializer, ChainHasher $chainHasher)
    {
        $this->serializer = $serializer;
        $this->chainHasher = $chainHasher;
    }

    /**
     * Verify the entire ledger.
     */
    public function verify(): VerificationResult
    {
        $previousChain = '0';
        $count = 0;

        $result = new VerificationResult;

        Entry::query()
            ->orderBy('recorded_at')
            ->chunk(500, function ($entries) use (&$previousChain, &$count, $result) {
                /** @var Entry $entry */
                foreach ($entries as $entry) {
                    $canonical = $this->serializer->serialize(
                        $entry->payload
                    );

                    $payloadHash = hash('sha256', $canonical);

                    if ($payloadHash !== $entry->payload_hash) {
                        $result->fail(
                            'payload_hash_mismatch',
                            $entry->id
                        );

                        return false;
                    }

                    $expectedChain = $this->chainHasher->hash(
                        $previousChain,
                        $payloadHash
                    );

                    if ($expectedChain !== $entry->chain_hash) {
                        $result->fail(
                            'chain_hash_mismatch',
                            $entry->id
                        );

                        return false;
                    }

                    $previousChain = $entry->chain_hash;

                    $count++;
                }
            });

        $result->success($count);

        return $result;
    }
}
