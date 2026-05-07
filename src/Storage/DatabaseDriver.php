<?php

namespace Chronicle\Storage;

use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\Entry;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Persists entries via Laravel DB query builder (not Eloquent save())
 *
 * Uses DB::table() deliberately:
 *  - No Eloquent model events fire
 *  - Created_at is never touched by Eloquent timestamp machinery
 *  - The insert is a single, transparent DB operation
 */
class DatabaseDriver implements StorageDriver
{
    use SerializesEntryAttributes;

    /**
     * @param  array<string, mixed>  $entry
     *
     * @throws JsonException
     */
    public function store(array $entry): Entry
    {
        /** @var string $table */
        $table = config('chronicle.tables.entries', 'chronicle_entries');

        /** @var string $connection */
        $connection = config('chronicle.connection');

        $attributes = $this->toEntryAttributes($entry);

        DB::connection($connection)->table($table)->insert($attributes);

        $model = new Entry;

        $model->forceFill($attributes);

        $model->exists = true;

        return $model;
    }
}
