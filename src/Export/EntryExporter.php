<?php

namespace Chronicle\Export;

use Chronicle\Models\Entry;

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
        /** @var resource $handle */
        $handle = fopen($path, 'w');

        $count = 0;
        $chainHead = null;
        $firstEntryId = null;
        $lastEntryId = null;

        Entry::query()
            ->orderBy('created_at')
            ->chunk(500, function ($entries) use (
                &$handle,
                &$count,
                &$chainHead,
                &$firstEntryId,
                &$lastEntryId,
            ) {
                foreach ($entries as $entry) {
                    if (! $firstEntryId) {
                        $firstEntryId = $entry->id;
                    }

                    $lastEntryId = $entry->id;

                    $line = $this->serializeEntry($entry);

                    fwrite($handle, $line."\n");

                    $count++;

                    $chainHead = $entry->chain_hash;
                }
            });

        fclose($handle);

        return new EntryExportResult(
            entryCount: $count,
            chainHead: $chainHead,
            firstEntryId: $firstEntryId,
            lastEntryId: $lastEntryId
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
            'payload' => $entry->payload,
            'payload_hash' => $entry->payload_hash,
            'chain_hash' => $entry->chain_hash,
            'checkpoint_id' => $entry->checkpoint_id,
            'tags' => $entry->tags,
            'diff' => $entry->diff,
            'correlation_id' => $entry->correlation_id,
            'created_at' => $entry->created_at,
        ];

        return (string) json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
