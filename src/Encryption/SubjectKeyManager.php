<?php

declare(strict_types=1);

namespace Chronicle\Encryption;

use Chronicle\Exceptions\EncryptionException;
use Chronicle\Support\MorphTypes;
use Exception;
use Illuminate\Database\Eloquent\Collection;

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
    protected array $cache = [];

    /**
     * Bumped per subject on destroy, so a DEK whose caching is still waiting
     * for its transaction to commit is not cached after the subject's erasure.
     *
     * @var array<string, int>
     */
    protected array $generations = [];

    public function __construct(
        protected readonly KeyEncryptionManager $kek,
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

            return $this->remember($cacheKey, $this->kek->provider()->unwrap($wrapped));
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

        return $this->remember($cacheKey, $dek);
    }

    /**
     * Read-only DEK lookup for the decrypt-on-read path. Never creates a key:
     * returns an active state (unwrapping + caching the DEK), an erased
     * tombstone state, or a missing state. An erased subject yields erased,
     * never a fresh DEK.
     */
    public function stateFor(string $subjectType, string $subjectId): SubjectKeyState
    {
        $cacheKey = $this->cacheKey($subjectType, $subjectId);

        if (isset($this->cache[$cacheKey])) {
            return SubjectKeyState::active($this->cache[$cacheKey]);
        }

        $row = $this->find($subjectType, $subjectId);

        if ($row === null) {
            return SubjectKeyState::missing();
        }

        $wrapped = $row->wrapped_dek;

        if ($row->isErased() || $wrapped === null) {
            return SubjectKeyState::erased($row->erased_at?->toIso8601String());
        }

        return SubjectKeyState::active(
            $this->remember($cacheKey, $this->kek->provider()->unwrap($wrapped)),
        );
    }

    /**
     * Erase a subject: destroy the wrapped DEK, tombstone the row, and purge
     * the cached plaintext DEK. Idempotent. Erased subjects stay erased - a
     * tombstone is written even for a never-seen subject. A subject with keys
     * under both its class name and its morph alias loses all of them.
     */
    public function destroy(string $subjectType, string $subjectId): void
    {
        foreach (MorphTypes::variants($subjectType) as $type) {
            $cacheKey = $this->cacheKey($type, $subjectId);

            unset($this->cache[$cacheKey]);
            $this->generations[$cacheKey] = ($this->generations[$cacheKey] ?? 0) + 1;
        }

        $rows = $this->rows($subjectType, $subjectId);

        if ($rows->isEmpty()) {
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

        foreach ($rows as $row) {
            if ($row->isErased()) {
                continue;
            }

            $row->wrapped_dek = null;
            $row->status = 'erased';
            $row->erased_at = now();
            $row->save();
        }
    }

    /**
     * Cache a DEK once the transaction it was read or created in commits.
     *
     * Inside a transaction the key row may be rolled back with it; a DEK
     * cached before that would outlive its row, and entries encrypted with
     * it later could never be decrypted. Outside a transaction it is cached
     * straight away. A subject destroyed before the commit is not cached.
     */
    protected function remember(string $cacheKey, string $dek): string
    {
        $generation = $this->generations[$cacheKey] ?? 0;

        (new SubjectKey)->getConnection()->afterCommit(function () use ($cacheKey, $dek, $generation) {
            if (($this->generations[$cacheKey] ?? 0) === $generation) {
                $this->cache[$cacheKey] = $dek;
            }
        });

        return $dek;
    }

    /**
     * The subject's key row. A subject recorded under both its class name and
     * its morph alias shares one key: an erased row wins, so a subject erased
     * under one name reads as erased under the other; otherwise the row under
     * the exact type, then the one under the other name.
     */
    protected function find(string $subjectType, string $subjectId): ?SubjectKey
    {
        $rows = $this->rows($subjectType, $subjectId);

        return $rows->first(fn (SubjectKey $row) => $row->isErased())
            ?? $rows->firstWhere('subject_type', $subjectType)
            ?? $rows->first();
    }

    /**
     * The subject's key rows under its class name and its morph alias.
     *
     * @return Collection<int, SubjectKey>
     */
    protected function rows(string $subjectType, string $subjectId): Collection
    {
        return SubjectKey::query()
            ->whereIn('subject_type', MorphTypes::variants($subjectType))
            ->where('subject_id', $subjectId)
            ->get();
    }

    protected function cacheKey(string $subjectType, string $subjectId): string
    {
        return $subjectType."\0".$subjectId;
    }
}
