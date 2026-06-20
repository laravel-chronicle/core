<?php

declare(strict_types=1);

namespace Chronicle\Storage;

use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Illuminate\Support\Facades\Config;
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
final class DatabaseDriver implements StorageDriver
{
    use SerializesEntryAttributes;

    /**
     * @param  array<string, mixed>  $entry
     *
     * @throws JsonException
     */
    public function store(array $entry): Entry
    {
        $table = Config::string('chronicle.tables.entries', 'chronicle_entries');

        /** @var string|null $configured */
        $configured = Config::get('chronicle.connection');

        $db = is_string($configured) && $configured !== ''
            ? DB::connection($configured)
            : DB::connection();

        $attributes = $this->toEntryAttributes($entry);

        $db->table($table)->insert($attributes);

        $class = Chronicle::entryModel();
        $model = new $class;

        $model->forceFill($attributes);

        $model->exists = true;

        return $model;
    }
}
