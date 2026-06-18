<?php

declare(strict_types=1);

namespace Chronicle\Verification;

use Chronicle\Entry\Entry;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Support\CanonicalPayloadSerializer;
use JsonException;

/**
 * Verifies a single entry's payload hash and chain link to its predecessor.
 */
final class EntryVerifier
{
    use ComparesEntryColumns;

    public function __construct(
        protected readonly CanonicalPayloadSerializer $serializer,
        protected readonly ChainHasher $chainHasher,
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

        if (! $this->columnsMatchPayload($entry, $entry->payload, $this->serializer)) {
            return EntryVerificationResult::failure($entry, VerificationFailure::ColumnPayloadDivergence->value);
        }

        /** @var string $previousChainHash */
        $previousChainHash = Entry::query()
            ->where('sequence', '<', $entry->sequence)
            ->orderByDesc('sequence')
            ->value('chain_hash') ?? ChainHasher::GENESIS;

        $expectedChainHash = $this->chainHasher->hash($previousChainHash, $entry->payload_hash);

        if (! hash_equals($expectedChainHash, (string) $entry->chain_hash)) {
            return EntryVerificationResult::failure($entry, VerificationFailure::ChainHashMismatch->value);
        }

        return EntryVerificationResult::ok($entry);
    }
}
