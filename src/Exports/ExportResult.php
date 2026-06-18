<?php

declare(strict_types=1);

namespace Chronicle\Exports;

/**
 * Represents the result of a Chronicle dataset export.
 *
 * Returned by ExportManager after a successful export.
 */
final class ExportResult
{
    /**
     * Total number of exported entries.
     */
    public int $entryCount;

    /**
     * SHA-256 hash of the exported dataset.
     */
    public string $datasetHash;

    /**
     * Final chain hash of the exported ledger.
     */
    public ?string $chainHead;

    /**
     * Create a new export result instance.
     */
    public function __construct(
        int $entryCount,
        string $datasetHash,
        ?string $chainHead,
    ) {
        $this->entryCount = $entryCount;
        $this->datasetHash = $datasetHash;
        $this->chainHead = $chainHead;
    }

    /**
     * Determine whether the export produced any entries.
     */
    public function isEmpty(): bool
    {
        return $this->entryCount === 0;
    }
}
