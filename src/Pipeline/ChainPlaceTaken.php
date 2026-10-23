<?php

declare(strict_types=1);

namespace Chronicle\Pipeline;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use PDOException;

/**
 * Tells whether a unique violation means a write lost its place in the chain:
 * another writer committed the same `sequence` or `chain_hash` first.
 *
 * Only the database's own error text is read, never the SQL or its bindings,
 * which carry the entry's payload. PostgreSQL, MySQL, MariaDB and SQL Server
 * name the violated index (`{table}_sequence_unique`, `{table}_chain_hash_unique`,
 * as the entries migration creates them); SQLite names the column instead
 * (`{table}.sequence`). Any other unique violation - a duplicate entry `id`, a
 * custom store driver, a listener's own index - is not a lost place.
 *
 * @internal
 */
final class ChainPlaceTaken
{
    private const COLUMNS = ['sequence', 'chain_hash'];

    public static function by(UniqueConstraintViolationException $e, ConnectionInterface $db): bool
    {
        $message = self::databaseMessage($e);

        foreach (self::markers($db) as $marker) {
            if (preg_match('/(?<![\w$])'.preg_quote($marker, '/').'(?![\w$])/i', $message) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The driver's error text, without Laravel's "(Connection: ..., SQL: ...)".
     */
    private static function databaseMessage(UniqueConstraintViolationException $e): string
    {
        $info = $e->errorInfo;

        if (is_array($info) && isset($info[2]) && is_string($info[2]) && $info[2] !== '') {
            return $info[2];
        }

        $previous = $e->getPrevious();

        return $previous instanceof PDOException ? $previous->getMessage() : '';
    }

    /**
     * @return list<string>
     */
    private static function markers(ConnectionInterface $db): array
    {
        $table = Config::string('chronicle.tables.entries', 'chronicle_entries');
        $prefixed = ChainHashEntry::lockName($db);

        $markers = [];

        foreach (array_unique([$table, $prefixed]) as $name) {
            foreach (self::COLUMNS as $column) {
                $markers[] = "{$name}_{$column}_unique";
                $markers[] = "{$name}.{$column}";
            }
        }

        return $markers;
    }
}
