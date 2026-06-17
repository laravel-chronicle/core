<?php

namespace Chronicle\Encryption;

/**
 * Read-time snapshot of a subject's DEK state, produced by
 * SubjectKeyManager::stateFor(). `dek` is the process-local plaintext DEK when
 * the subject is active, null when erased or never keyed. `erasedAt` is the
 * ISO-8601 erasure timestamp for the read tombstone.
 */
final class SubjectKeyState
{
    private function __construct(
        public readonly ?string $dek,
        public readonly bool $erased,
        public readonly ?string $erasedAt,
    ) {
        //
    }

    public static function active(string $dek): self
    {
        return new self($dek, false, null);
    }

    public static function erased(?string $erasedAt): self
    {
        return new self(null, true, $erasedAt);
    }

    public static function missing(): self
    {
        return new self(null, false, null);
    }
}
