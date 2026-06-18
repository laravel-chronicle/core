<?php

declare(strict_types=1);

namespace Chronicle\Exports;

use Chronicle\Exceptions\ExportWriteException;
use JsonException;

/**
 * Coordinates Chronicle dataset exports.
 */
class ExportManager
{
    public function __construct(
        protected readonly EntryExporter $entryExporter,
        protected readonly ExportManifestBuilder $manifestBuilder,
        protected readonly ExportSigner $signer
    ) {
        //
    }

    /**
     * Export the Chronicle dataset.
     *
     * **Contract:** The export directory must not be written to by any other
     * process between the `entries.ndjson` write and the `hashFile()` call that
     * follows. If another process modifies `entries.ndjson` in that window, the
     * dataset hash will not match the actual exported data. In production, ensure
     * the export path is outside any publicly writable location and that no
     * concurrent export jobs target the same path.
     *
     * @throws JsonException
     */
    public function export(string $path): ExportResult
    {
        if (! is_dir($path) && ! @mkdir($path, 0700, true) && ! is_dir($path)) {
            $error = error_get_last();

            throw ExportWriteException::directoryCreationFailed($path, $error['message'] ?? null);
        }

        $entriesPath = $path.'/'.ExportFormat::ENTRIES;

        $export = $this->entryExporter->export($entriesPath);

        $datasetHash = $export->datasetHash;

        $manifest = $this->manifestBuilder->build(
            entryCount: $export->entryCount,
            chainHead: $export->chainHead,
            datasetHash: $datasetHash,
            firstEntryId: $export->firstEntryId,
            lastEntryId: $export->lastEntryId,
        );

        $this->manifestBuilder->write($path.'/'.ExportFormat::MANIFEST, $manifest);

        $signature = $this->signer->sign(
            datasetHash: $datasetHash,
            entryCount: $export->entryCount,
            firstEntryId: $export->firstEntryId,
            lastEntryId: $export->lastEntryId,
            chainHead: $export->chainHead,
        );

        $this->signer->write($path.'/'.ExportFormat::SIGNATURE, $signature);

        return new ExportResult(
            entryCount: $export->entryCount,
            datasetHash: $datasetHash,
            chainHead: $export->chainHead,
        );
    }
}
