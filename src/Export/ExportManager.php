<?php

namespace Chronicle\Export;

use Chronicle\Exceptions\ExportWriteException;

/**
 * Coordinates Chronicle dataset exports.
 */
class ExportManager
{
    protected EntryExporter $entryExporter;

    protected ExportHasher $hasher;

    protected ExportManifestBuilder $manifestBuilder;

    protected ExportSigner $signer;

    public function __construct(
        EntryExporter $entryExporter,
        ExportHasher $hasher,
        ExportManifestBuilder $manifestBuilder,
        ExportSigner $signer
    ) {
        $this->entryExporter = $entryExporter;
        $this->hasher = $hasher;
        $this->manifestBuilder = $manifestBuilder;
        $this->signer = $signer;
    }

    /**
     * Export the Chronicle dataset.
     */
    public function export(string $path): ExportResult
    {
        if (! is_dir($path) && ! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw ExportWriteException::directoryCreationFailed($path);
        }

        $entriesPath = $path.'/entries.ndjson';

        $export = $this->entryExporter->export($entriesPath);

        $datasetHash = $this->hasher->hashFile($entriesPath);

        $manifest = $this->manifestBuilder->build(
            entryCount: $export->entryCount,
            chainHead: $export->chainHead,
            datasetHash: $datasetHash,
            firstEntryId: $export->firstEntryId,
            lastEntryId: $export->lastEntryId,
        );

        $this->manifestBuilder->write($path.'/manifest.json', $manifest);

        $signature = $this->signer->sign($datasetHash);

        $this->signer->write($path.'/signature.json', $signature);

        return new ExportResult(
            entryCount: $export->entryCount,
            datasetHash: $datasetHash,
            chainHead: $export->chainHead,
        );
    }
}
