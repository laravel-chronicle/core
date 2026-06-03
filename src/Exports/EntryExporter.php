<?php

namespace Chronicle\Exports;

use Chronicle\Entry\Entry;
use Chronicle\Exceptions\ExportWriteException;
use JsonException;

/**
 * Streams Chronicle entries into NDJSON format.
 */
class EntryExporter
{
    /**
     * Export entries to an NDJSON file.
     */
    public function export(string $path): EntryExportResult
    {
        /** @var resource|false $handle */
        $handle = @fopen($path, 'wb');

        if (! is_resource($handle)) {
            throw ExportWriteException::writeFailed($path);
        }

        $count = 0;
        $chainHead = null;
        $firstEntryId = null;
        $lastEntryId = null;
        $hashContext = hash_init('sha256');

        try {
            Entry::query()
                ->orderBy('created_at')
                ->orderBy('id')
                ->chunk(500, function ($entries) use (
                    &$handle,
                    &$count,
                    &$chainHead,
                    &$firstEntryId,
                    &$lastEntryId,
                    &$hashContext,
                    $path,
                ) {
                    foreach ($entries as $entry) {
                        if ($firstEntryId === null) {
                            $firstEntryId = $entry->id;
                        }

                        $lastEntryId = $entry->id;

                        $line = $this->serializeEntry($entry)."\n";
                        hash_update($hashContext, $line);

                        $written = @fwrite($handle, $line);

                        if ($written === false || $written !== strlen($line)) {
                            throw ExportWriteException::writeFailed($path);
                        }

                        $count++;

                        $chainHead = $entry->chain_hash;
                    }
                });
        } finally {
            fclose($handle);
        }

        return new EntryExportResult(
            entryCount: $count,
            chainHead: $chainHead,
            firstEntryId: $firstEntryId,
            lastEntryId: $lastEntryId,
            datasetHash: hash_final($hashContext),
        );
    }

    /**
     * Serialize an entry deterministically.
     */
    protected function serializeEntry(Entry $entry): string
    {
        $data = [
            'id' => $entry->id,
            'actor_type' => $entry->actor_type,
            'actor_id' => $entry->actor_id,
            'action' => $entry->action,
            'subject_type' => $entry->subject_type,
            'subject_id' => $entry->subject_id,
            'metadata' => $entry->metadata,
            'context' => $entry->context,
            'payload' => $entry->payload,
            'payload_hash' => $entry->payload_hash,
            'chain_hash' => $entry->chain_hash,
            'checkpoint_id' => $entry->checkpoint_id,
            'tags' => $entry->tags,
            'diff' => $entry->diff,
            'correlation_id' => $entry->correlation_id,
            'created_at' => $entry->created_at,
        ];

        try {
            $json = json_encode(
                $data,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw ExportWriteException::encodeFailed('entries');
        }

        return $json;
    }
}
