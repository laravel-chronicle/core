<?php

namespace Chronicle\Export;

/**
 * Value object representing the result of exporting Chronicle entries.
 *
 * This object is returned by the EntryExporter after streaming entries
 * to the export file.
 */
class EntryExportResult
{
    /**
     * Number of exported entries.
     */
    public int $entryCount;

    /**
     * The final chain hash of the exported dataset.
     */
    public ?string $chainHead;

    /**
     * Create a new export result instance.
     */
    public function __construct(
        int $entryCount,
        ?string $chainHead,
        public ?string $firstEntryId,
        public ?string $lastEntryId,
    ) {
        $this->entryCount = $entryCount;
        $this->chainHead = $chainHead;
    }

    /**
     * Determine whether the export contained any entries.
     */
    public function isEmpty(): bool
    {
        return $this->entryCount === 0;
    }
}
