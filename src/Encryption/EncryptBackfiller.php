<?php

declare(strict_types=1);

namespace Chronicle\Encryption;

use Chronicle\Entry\Entry;
use Chronicle\Entry\PendingEntry;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Hashing\EntryHasher;
use Chronicle\Lifecycle\LegalHold;
use Exception;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use JsonException;

/**
 * Re-baselining engine for chronicle:encrypt-backfill. Walks the ledger in
 * sequence order from a start point to the head, encrypts the configured fields
 * of each eligible entry under its subject DEK (rewriting BOTH the hashed payload
 * copy and the denormalized column copy with the SAME envelope), recomputes
 * payload_hash, and re-links chain_hash for every entry from the first rewritten
 * one forward. Issues plain query-builder UPDATEs that deliberately bypass the
 * Entry immutability guard - this is the one sanctioned ledger rewrite.
 */
final readonly class EncryptBackfiller
{
    public function __construct(
        protected PayloadCipher $cipher,
        protected SubjectKeyManager $keys,
        protected EntryHasher $entryHasher,
        protected ChainHasher $chainHasher,
    ) {
        //
    }

    /**
     * @throws JsonException
     */
    public function run(?string $fromId, int $chunk, bool $dryRun): BackfillReport
    {
        $chunk = max(1, $chunk);

        [$startSequence, $previousChain] = $this->resolveStart($fromId);

        $scanned = 0;
        $encrypted = 0;
        $relinked = 0;
        $lastSequence = $startSequence - 1;

        do {
            $batch = Entry::query()
                ->where('sequence', '>', $lastSequence)
                ->orderBy('sequence')
                ->limit($chunk)
                ->get();

            foreach ($batch as $entry) {
                $scanned++;

                $payload = $entry->payload;
                $columnUpdates = [];
                $didEncrypt = $this->encryptFields($entry, $payload, $columnUpdates);

                if ($didEncrypt) {
                    $encrypted++;
                }

                $newPayloadHash = $this->payloadHash($payload);
                $newChainHash = $this->chainHasher->hash($previousChain, $newPayloadHash);

                $rowChanged = $didEncrypt
                    || $newPayloadHash !== $entry->payload_hash
                    || $newChainHash !== $entry->chain_hash;

                if ($rowChanged) {
                    $relinked++;

                    if (! $dryRun) {
                        Entry::query()->whereKey($entry->id)->update(array_merge($columnUpdates, [
                            'payload' => $payload,
                            'payload_hash' => $newPayloadHash,
                            'chain_hash' => $newChainHash,
                        ]));
                    }
                }

                $previousChain = $newChainHash;
                $lastSequence = $entry->sequence;
            }
        } while ($batch->count() === $chunk);

        return new BackfillReport(
            scanned: $scanned,
            encrypted: $encrypted,
            relinked: $relinked,
            changed: $relinked > 0,
            headChainHash: $scanned > 0 ? $previousChain : null,
            dryRun: $dryRun,
        );
    }

    /**
     * Encrypt the configured fields of $payload in place and collect the matching
     * column updates. Returns true if any field was newly encrypted. Leaves the
     * entry untouched when it has no subject, the subject is erased, the subject
     * is under a legal hold, or the field is empty / already an envelope.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<string, string>>  $columnUpdates
     *
     * @throws JsonException
     * @throws Exception
     */
    protected function encryptFields(Entry $entry, array &$payload, array &$columnUpdates): bool
    {
        $subjectType = $entry->subject_type;
        $subjectId = $entry->subject_id;

        if (! is_string($subjectType) || $subjectType === ''
            || ! is_string($subjectId) || $subjectId === '') {
            return false;
        }

        if ($this->keys->stateFor($subjectType, $subjectId)->erased) {
            return false;
        }

        if (LegalHold::isHeld($subjectType, $subjectId)) {
            return false;
        }

        $dek = null;
        $aad = null;
        $didEncrypt = false;

        foreach ($this->fields() as $field) {
            $value = $payload[$field] ?? null;

            if ($value === null || $value === []) {
                continue;
            }

            if (is_array($value) && CipherEnvelope::isEnvelope($value)) {
                continue;
            }

            if ($dek === null) {
                $dek = $this->keys->getOrCreate($subjectType, $subjectId);
                $aad = PayloadCipher::aad($entry->id, $subjectType, $subjectId, $entry->action);
            }

            /** @var string $aad */
            $envelope = $this->cipher->encrypt([$field => $value], $dek, $aad)->toArray();

            $payload[$field] = $envelope;
            $columnUpdates[$field] = $envelope;
            $didEncrypt = true;
        }

        return $didEncrypt;
    }

    /**
     * Resolve the start sequence and the chain hash seeding the walk. From genesis
     * the seed is GENESIS; from a specific entry the seed is the chain hash of the
     * entry immediately before it.
     *
     * @return array{0: int, 1: string}
     */
    protected function resolveStart(?string $fromId): array
    {
        if ($fromId === null || $fromId === '') {
            return [1, ChainHasher::GENESIS];
        }

        /** @var Entry|null $from */
        $from = Entry::query()->whereKey($fromId)->first(['sequence']);

        if ($from === null) {
            throw new InvalidArgumentException("No entry found with id [$fromId].");
        }

        $startSequence = $from->sequence;

        /** @var Entry|null $before */
        $before = Entry::query()
            ->where('sequence', '<', $startSequence)
            ->orderByDesc('sequence')
            ->first(['chain_hash']);

        return [$startSequence, $before->chain_hash ?? ChainHasher::GENESIS];
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    protected function payloadHash(array $payload): string
    {
        $pending = new PendingEntry([]);
        $pending->setPayload($payload);

        return $this->entryHasher->hash($pending);
    }

    /**
     * @return list<string>
     */
    protected function fields(): array
    {
        /** @var list<string> $fields */
        $fields = Config::array('chronicle.encryption.fields', ['metadata', 'context', 'diff']);

        return $fields;
    }
}
