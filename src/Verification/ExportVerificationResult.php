<?php

declare(strict_types=1);

namespace Chronicle\Verification;

/**
 * Result of verifying a Chronicle export dataset.
 */
final class ExportVerificationResult
{
    protected bool $valid;

    protected ?string $failure;

    protected ?int $entryCount;

    protected ?string $datasetHash;

    protected ?string $chainHead;

    protected function __construct(
        bool $valid,
        ?string $failure = null,
        ?int $entryCount = null,
        ?string $datasetHash = null,
        ?string $chainHead = null
    ) {
        $this->valid = $valid;
        $this->failure = $failure;
        $this->entryCount = $entryCount;
        $this->datasetHash = $datasetHash;
        $this->chainHead = $chainHead;
    }

    public static function success(
        int $entryCount,
        string $datasetHash,
        ?string $chainHead,
    ): ExportVerificationResult {
        return new ExportVerificationResult(
            valid: true,
            failure: null,
            entryCount: $entryCount,
            datasetHash: $datasetHash,
            chainHead: $chainHead,
        );
    }

    public static function failure(string $reason): ExportVerificationResult
    {
        return new ExportVerificationResult(valid: false, failure: $reason);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function failureCode(): ?string
    {
        return $this->failure;
    }

    public function entryCount(): ?int
    {
        return $this->entryCount;
    }

    public function datasetHash(): ?string
    {
        return $this->datasetHash;
    }

    public function chainHead(): ?string
    {
        return $this->chainHead;
    }
}
