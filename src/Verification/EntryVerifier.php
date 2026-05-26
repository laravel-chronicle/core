<?php

namespace Chronicle\Verification;

use Chronicle\Entry\Entry;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Support\CanonicalPayloadSerializer;
use JsonException;

class EntryVerifier
{
    public function __construct(
        private readonly CanonicalPayloadSerializer $serializer,
        private readonly ChainHasher $chainHasher,
    ) {
        //
    }

    /**
     * @throws JsonException
     */
    public function verify(string $id): EntryVerificationResult
    {
        $entry = Entry::find($id);

        if ($entry === null) {
            return EntryVerificationResult::notFound($id);
        }

        $canonical = $this->serializer->serialize($entry->payload);
        $expectedPayloadHash = hash('sha256', $canonical);

        if (! hash_equals($expectedPayloadHash, (string) $entry->payload_hash)) {
            return EntryVerificationResult::failure($entry, VerificationFailure::PayloadHashMismatch->value);
        }

        /** @var string $previousChainHash */
        $previousChainHash = Entry::query()
            ->where('id', '<', $entry->id)
            ->orderByDesc('id')
            ->value('chain_hash') ?? '0';

        $expectedChainHash = $this->chainHasher->hash($previousChainHash, $entry->payload_hash);

        if (! hash_equals($expectedChainHash, (string) $entry->chain_hash)) {
            return EntryVerificationResult::failure($entry, VerificationFailure::ChainHashMismatch->value);
        }

        return EntryVerificationResult::ok($entry);
    }
}
