<?php

namespace Chronicle\Exports;

/**
 * Value object representing the result of exporting Chronicle entries.
 *
 * This object is returned by the EntryExporter after streaming entries
 * to the export file.
 */
class EntryExportResult
{
    /**
     * Create a new export result instance.
     */
    public function __construct(
        public int $entryCount,
        public ?string $chainHead,
        public ?string $firstEntryId,
        public ?string $lastEntryId,
    ) {
        //
    }

    /**
     * Determine whether the export contained any entries.
     */
    public function isEmpty(): bool
    {
        return $this->entryCount === 0;
    }
}
