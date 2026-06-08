<?php

namespace Chronicle\Verification;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Entry\Entry;
use Chronicle\Exceptions\UnknownSigningKeyException;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Signing\KeyRing;
use Chronicle\Support\CanonicalPayloadSerializer;
use JsonException;

class IntegrityVerifier
{
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
        $previousChain = '0';
        $count = 0;

        $result = new VerificationResult;

        /** @var array<string, bool> $verifiedCheckpoints */
        $verifiedCheckpoints = [];

        /** @var Entry $entry */
        foreach (Entry::query()->orderBy('sequence')->cursor() as $entry) {
            // Payload verification
            $canonical = $this->serializer->serialize(
                $entry->payload
            );

            $payloadHash = hash('sha256', $canonical);

            if (! hash_equals($payloadHash, (string) $entry->payload_hash)) {
                $result->fail(
                    VerificationFailure::PayloadHashMismatch->value,
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
                    VerificationFailure::ChainHashMismatch->value,
                    $entry->id
                );

                return $result;
            }

            // Checkpoint verification — each unique checkpoint is fetched and verified once
            if ($entry->checkpoint_id && ! isset($verifiedCheckpoints[$entry->checkpoint_id])) {
                $checkpoint = Checkpoint::find($entry->checkpoint_id);

                if (! $checkpoint) {
                    $result->fail(
                        VerificationFailure::CheckpointMissing->value,
                        $entry->id
                    );

                    return $result;
                }

                $algorithm = $checkpoint->algorithm;
                $keyId = $checkpoint->key_id;

                try {
                    $provider = $this->keyRing->resolve($algorithm, $keyId);
                } catch (UnknownSigningKeyException) {
                    $result->fail(
                        VerificationFailure::UnknownKey->value,
                        $entry->id
                    );

                    return $result;
                }

                $valid = $provider->verify(
                    $checkpoint->chain_hash,
                    $checkpoint->signature,
                );

                if (! $valid) {
                    $result->fail(
                        VerificationFailure::CheckpointSignatureInvalid->value,
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
