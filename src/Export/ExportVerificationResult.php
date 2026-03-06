<?php

namespace Chronicle\Export;

/**
 * Result of verifying a Chronicle export dataset.
 */
class ExportVerificationResult
{
    public bool $valid;

    public ?string $failure;

    public ?int $entryCount;

    public ?string $datasetHash;

    public ?string $chainHead;

    private function __construct(
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
}
