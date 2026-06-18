<?php

declare(strict_types=1);

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Encryption\PayloadCipher;
use Chronicle\Encryption\SubjectKeyManager;
use Chronicle\Entry\PendingEntry;
use Exception;
use Illuminate\Support\Facades\Config;

/**
 * Encrypts the configured PII-bearing fields (default metadata/context/diff)
 * under the subject's DEK, BETWEEN canonicalization and hashing, so the
 * payload_hash and chain_hash cover ciphertext. It rewrites BOTH the hashed
 * payload copy AND the denormalized column copy of each field with the SAME
 * cipher envelope, so the v1.11 ColumnPayloadDivergence check still passes.
 *
 * Self-gates on chronicle.encryption.enabled - a no-op when disabled, so
 * behaviour is identical to pre-1.12.
 */
readonly class EncryptPayload implements EntryProcessor
{
    public function __construct(
        protected PayloadCipher $cipher,
        protected SubjectKeyManager $keys,
    ) {
        //
    }

    /**
     * @throws Exception
     */
    public function process(PendingEntry $entry): PendingEntry
    {
        if (Config::boolean('chronicle.encryption.enabled', false) !== true) {
            return $entry;
        }

        $payload = $entry->payload();

        $subjectType = $payload['subject_type'] ?? null;
        $subjectId = $payload['subject_id'] ?? null;

        // A per-subject DEK is keyed by the subject; without one we cannot
        // encrypt, so leave the entry cleartext.
        if (! is_string($subjectType) || $subjectType === ''
            || ! is_string($subjectId) || $subjectId === '') {
            return $entry;
        }

        // An erased subject has no DEK; new entries for it stay cleartext
        // (and must be PII-free by policy). This is what lets the post-erasure
        // `subject.erased` proof itself remain readable and verifiable.
        if ($this->keys->stateFor($subjectType, $subjectId)->erased) {
            return $entry;
        }

        /** @var string $id */
        $id = $payload['id'];
        /** @var string $action */
        $action = $payload['action'];

        $dek = $this->keys->getOrCreate($subjectType, $subjectId);
        $aad = PayloadCipher::aad($id, $subjectType, $subjectId, $action);

        foreach ($this->fields() as $field) {
            $value = $payload[$field] ?? null;

            if ($value === null || $value === []) {
                continue;
            }

            $envelope = $this->cipher->encrypt([$field => $value], $dek, $aad)->toArray();

            $payload[$field] = $envelope;
            $entry->setAttribute($field, $envelope);
        }

        $entry->setPayload($payload);

        return $entry;
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
