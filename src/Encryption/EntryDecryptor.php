<?php

declare(strict_types=1);

namespace Chronicle\Encryption;

use Chronicle\Entry\Entry;
use JsonException;
use SodiumException;

/**
 * Read-path decryption. Kept separate from the Entry model's attribute
 * accessors on purpose: verification reads the raw stored envelope via
 * $entry->metadata / $entry->payload and must never see decrypted data, so
 * decryption is an explicit, opt-in call.
 */
final readonly class EntryDecryptor
{
    public function __construct(
        protected PayloadCipher $cipher,
        protected SubjectKeyManager $keys,
    ) {
        //
    }

    /**
     * Decrypt a single configured field. Returns the plaintext when the DEK
     * exists, the value unchanged when it was never encrypted, or a tombstone
     * ['_erased' => true, 'erased_at' => ...] when the subject DEK is gone.
     *
     * @throws JsonException
     * @throws SodiumException
     */
    public function field(Entry $entry, string $field): mixed
    {
        /** @var array<string, mixed>|null $value */
        $value = $entry->getAttribute($field);

        if (! is_array($value) || ! CipherEnvelope::isEnvelope($value)) {
            return $value;
        }

        $state = $this->keys->stateFor(
            (string) $entry->subject_type,
            (string) $entry->subject_id,
        );

        if ($state->dek === null) {
            return ['_erased' => true, 'erased_at' => $state->erasedAt];
        }

        $aad = PayloadCipher::aad(
            $entry->id,
            $entry->subject_type,
            $entry->subject_id,
            $entry->action,
        );

        $decrypted = $this->cipher->decrypt(CipherEnvelope::fromArray($value), $state->dek, $aad);

        return $decrypted[$field] ?? null;
    }

    public function isErased(Entry $entry): bool
    {
        return $this->keys->stateFor(
            (string) $entry->subject_type,
            (string) $entry->subject_id,
        )->erased;
    }
}
