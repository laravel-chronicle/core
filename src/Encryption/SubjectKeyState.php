<?php

declare(strict_types=1);

namespace Chronicle\Encryption;

/**
 * Read-time snapshot of a subject's DEK state, produced by
 * SubjectKeyManager::stateFor(). `dek` is the process-local plaintext DEK when
 * the subject is active, null when erased or never keyed. `erasedAt` is the
 * ISO-8601 erasure timestamp for the read tombstone.
 */
final readonly class SubjectKeyState
{
    protected function __construct(
        public ?string $dek,
        public bool $erased,
        public ?string $erasedAt,
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
