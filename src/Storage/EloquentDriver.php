<?php

namespace Chronicle\Storage;

use Chronicle\Contracts\StorageDriver;
use Chronicle\Models\Entry;
use Illuminate\Support\Facades\DB;

/**
 * Persists entries via Laravel DB query builder (not Eloquent save())
 *
 * Uses DB::table() deliberately:
 *  - No Eloquent model events fire
 *  - created_at is never touched by Eloquent timestamp machinery
 *  - The insert is a single, transparent DB operation
 */
class EloquentDriver implements StorageDriver
{
    /**
     * @param  array<string, mixed>  $entry
     */
    public function store(array $entry): Entry
    {
        /** @var string $table */
        $table = config('chronicle.tables.entries', 'chronicle_entries');

        /** @var string $connection */
        $connection = config('chronicle.connection');

        DB::connection($connection)->table($table)->insert([
            'id' => $entry['id'],
            'actor_type' => $entry['actor_type'],
            'actor_id' => $entry['actor_id'],
            'action' => $entry['action'],
            'subject_type' => $entry['subject_type'],
            'subject_id' => $entry['subject_id'],
            'payload' => json_encode($entry['payload']),
            'payload_hash' => $entry['payload_hash'],
            'chain_hash' => $entry['chain_hash'],
            'metadata' => json_encode($entry['metadata']),
            'context' => json_encode($entry['context']),
            'tags' => json_encode($entry['tags']),
            'diff' => json_encode($entry['diff']),
            'correlation_id' => $entry['correlation_id'],
            'checkpoint_id' => $entry['checkpoint_id'],
            'created_at' => $entry['created_at'],
        ]);

        $model = new Entry;

        $model->forceFill([
            'id' => $entry['id'],
            'actor_type' => $entry['actor_type'],
            'actor_id' => $entry['actor_id'],
            'action' => $entry['action'],
            'subject_type' => $entry['subject_type'],
            'subject_id' => $entry['subject_id'],
            'payload' => json_encode($entry['payload']),
            'payload_hash' => $entry['payload_hash'],
            'chain_hash' => $entry['chain_hash'],
            'metadata' => json_encode($entry['metadata']),
            'context' => json_encode($entry['context']),
            'tags' => json_encode($entry['tags']),
            'diff' => json_encode($entry['diff']),
            'correlation_id' => $entry['correlation_id'],
            'checkpoint_id' => $entry['checkpoint_id'],
            'created_at' => $entry['created_at'],
        ]);

        $model->exists = true;

        return $model;
    }
}
