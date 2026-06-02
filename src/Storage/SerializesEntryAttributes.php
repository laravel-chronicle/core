<?php

namespace Chronicle\Storage;

use JsonException;

trait SerializesEntryAttributes
{
    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    protected function toEntryAttributes(array $entry): array
    {
        return [
            'id' => $entry['id'],
            'actor_type' => $entry['actor_type'],
            'actor_id' => $entry['actor_id'],
            'action' => $entry['action'],
            'subject_type' => $entry['subject_type'],
            'subject_id' => $entry['subject_id'],
            'payload' => json_encode($entry['payload'], JSON_THROW_ON_ERROR),
            'payload_hash' => $entry['payload_hash'],
            'chain_hash' => $entry['chain_hash'],
            'metadata' => json_encode($entry['metadata'], JSON_THROW_ON_ERROR),
            'context' => json_encode($entry['context'], JSON_THROW_ON_ERROR),
            'tags' => json_encode($entry['tags'], JSON_THROW_ON_ERROR),
            'diff' => $entry['diff'] !== null ? json_encode($entry['diff'], JSON_THROW_ON_ERROR) : null,
            'correlation_id' => $entry['correlation_id'],
            'checkpoint_id' => $entry['checkpoint_id'],
            'created_at' => $entry['created_at'],
        ];
    }
}
