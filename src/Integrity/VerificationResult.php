<?php

namespace Chronicle\Integrity;

/**
 * Represents the result of a ledger verification.
 */
class VerificationResult
{
    protected bool $valid = true;

    protected ?string $failureType = null;

    protected ?string $entryId = null;

    protected int $checked = 0;

    public function fail(string $type, string $entryId): void
    {
        $this->valid = false;
        $this->failureType = $type;
        $this->entryId = $entryId;
    }

    public function success(int $count): void
    {
        $this->checked = $count;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function failureType(): ?string
    {
        return $this->failureType;
    }

    public function entryId(): ?string
    {
        return $this->entryId;
    }

    public function checked(): int
    {
        return $this->checked;
    }
}
