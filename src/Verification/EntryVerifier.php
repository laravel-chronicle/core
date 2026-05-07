<?php

namespace Chronicle\Verification;

use Chronicle\Entry\Entry;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Support\CanonicalPayloadSerializer;
use Illuminate\Database\Eloquent\Builder;
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

        if ($expectedPayloadHash !== $entry->payload_hash) {
            return EntryVerificationResult::failure($entry, 'payload_hash_mismatch');
        }

        $previousChainHash = Entry::query()
            ->where(function (Builder $q) use ($entry): void {
                $q->where('created_at', '<', $entry->created_at)
                    ->orWhere(function (Builder $q) use ($entry): void {
                        $q->where('created_at', $entry->created_at)
                            ->where('id', '<', $entry->id);
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('chain_hash') ?? '0';

        $expectedChainHash = $this->chainHasher->hash($previousChainHash, $entry->payload_hash);

        if ($expectedChainHash !== $entry->chain_hash) {
            return EntryVerificationResult::failure($entry, 'chain_hash_mismatch');
        }

        return EntryVerificationResult::ok($entry);
    }
}
