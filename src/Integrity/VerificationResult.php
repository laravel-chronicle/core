<?php

namespace Chronicle\Integrity;

/**
 * Represents the outcome of a Chronicle ledger
 * integrity verification process.
 *
 * This object contains information about whether the
 * ledger is valid and, if not, where corruption begins.
 */
class VerificationResult
{
    /**
     * Whether the ledger passed verification.
     */
    protected bool $valid = true;

    /**
     * Type of integrity failure.
     */
    protected ?string $failureType = null;

    /**
     * Entry ID where corruption begins.
     */
    protected ?string $entryId = null;

    /**
     * Number of entries successfully verified.
     */
    protected int $checked = 0;

    /**
     * Mark verification as failed
     */
    public function fail(string $type, string $entryId): void
    {
        $this->valid = false;
        $this->failureType = $type;
        $this->entryId = $entryId;
    }

    /**
     * Mark verification as successful.
     */
    public function success(int $count): void
    {
        $this->checked = $count;
    }

    /**
     * Determine whether the ledger is valid.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Determine whether verification failed.
     */
    public function hasFailed(): bool
    {
        return ! $this->valid;
    }

    /**
     * Return failure type.
     */
    public function failureType(): ?string
    {
        return $this->failureType;
    }

    /**
     * Entry where corruption begins.
     */
    public function entryId(): ?string
    {
        return $this->entryId;
    }

    /**
     * Number of entries verified.
     */
    public function checked(): int
    {
        return $this->checked;
    }
}
