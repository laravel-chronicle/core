<?php

namespace Chronicle\Encryption;

use Chronicle\Exceptions\EncryptionException;
use Exception;

/**
 * Owns the per-subject DEK lifecycle: get-or-create (wrap + persist),
 * unwrap + process-local cache, and destroy (the GDPR erasure primitive).
 *
 * The plaintext DEK cache is process-local only - never persisted, logged,
 * or shared across requests.
 */
final class SubjectKeyManager
{
    /**
     * Process-local plaintext DEK cache, keyed by subject reference.
     *
     * @var array<string, string>
     */
    private array $cache = [];

    public function __construct(
        private readonly KeyEncryptionManager $kek,
    ) {
        //
    }

    /**
     * Return the plaintext DEK for a subject, creating + wrapping + persisting
     * one on first use. Throws if the subject has been erased.
     *
     * @throws Exception
     */
    public function getOrCreate(string $subjectType, string $subjectId): string
    {
        $cacheKey = $this->cacheKey($subjectType, $subjectId);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $row = $this->find($subjectType, $subjectId);

        if ($row !== null) {
            // Capture into a local so the null-narrowing survives the
            // provider() method call below (PHPStan re-widens magic-property
            // re-reads to string|null otherwise).
            $wrapped = $row->wrapped_dek;

            if ($row->isErased() || $wrapped === null) {
                throw EncryptionException::subjectErased($subjectType, $subjectId);
            }

            return $this->cache[$cacheKey] = $this->kek->provider()->unwrap($wrapped);
        }

        $provider = $this->kek->provider();
        $dek = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);

        SubjectKey::create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'wrapped_dek' => $provider->wrap($dek),
            'kek_id' => $provider->kekId(),
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $this->cache[$cacheKey] = $dek;
    }

    /**
     * Erase a subject: destroy the wrapped DEK, tombstone the row, and purge
     * the cached plaintext DEK. Idempotent. Erased subjects stay erased - a
     * tombstone is written even for a never-seen subject.
     */
    public function destroy(string $subjectType, string $subjectId): void
    {
        unset($this->cache[$this->cacheKey($subjectType, $subjectId)]);

        $row = $this->find($subjectType, $subjectId);

        if ($row === null) {
            SubjectKey::create([
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'wrapped_dek' => null,
                'kek_id' => $this->kek->provider()->kekId(),
                'status' => 'erased',
                'created_at' => now(),
                'erased_at' => now(),
            ]);

            return;
        }

        if ($row->isErased()) {
            return;
        }

        $row->wrapped_dek = null;
        $row->status = 'erased';
        $row->erased_at = now();
        $row->save();
    }

    private function find(string $subjectType, string $subjectId): ?SubjectKey
    {
        return SubjectKey::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->first();
    }

    private function cacheKey(string $subjectType, string $subjectId): string
    {
        return $subjectType."\0".$subjectId;
    }
}
