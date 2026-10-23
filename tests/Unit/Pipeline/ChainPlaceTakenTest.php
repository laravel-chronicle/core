<?php

declare(strict_types=1);

use Chronicle\Pipeline\ChainPlaceTaken;
use Illuminate\Database\Connection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * A unique violation as Laravel raises it, with the driver's own error text.
 *
 * @param  array<int, mixed>  $bindings
 */
function uniqueViolation(string $driverMessage, string $sql = 'insert into "chronicle_entries" ("id") values (?)', array $bindings = []): UniqueConstraintViolationException
{
    $pdo = new PDOException('SQLSTATE[23000]: Integrity constraint violation: '.$driverMessage);
    $pdo->errorInfo = ['23000', 1, $driverMessage];

    return new UniqueConstraintViolationException('testing', $sql, $bindings, $pdo);
}

it('recognises a lost place in the chain from the driver\'s error', function (string $driverMessage) {
    expect(ChainPlaceTaken::by(uniqueViolation($driverMessage), DB::connection()))->toBeTrue();
})->with([
    'PostgreSQL, sequence' => "ERROR:  duplicate key value violates unique constraint \"chronicle_entries_sequence_unique\"\nDETAIL:  Key (sequence)=(2) already exists.",
    'PostgreSQL, chain hash' => "ERROR:  duplicate key value violates unique constraint \"chronicle_entries_chain_hash_unique\"\nDETAIL:  Key (chain_hash)=(ab) already exists.",
    'MySQL' => "Duplicate entry '2' for key 'chronicle_entries.chronicle_entries_sequence_unique'",
    'MariaDB' => "Duplicate entry '2' for key 'chronicle_entries_sequence_unique'",
    'SQL Server' => "[Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Cannot insert duplicate key row in object 'dbo.chronicle_entries' with unique index 'chronicle_entries_chain_hash_unique'. The duplicate key value is (ab).",
    'SQLite, sequence' => 'UNIQUE constraint failed: chronicle_entries.sequence',
    'SQLite, chain hash' => 'UNIQUE constraint failed: chronicle_entries.chain_hash',
]);

it('does not take any other unique violation for a lost place', function (string $driverMessage) {
    expect(ChainPlaceTaken::by(uniqueViolation($driverMessage), DB::connection()))->toBeFalse();
})->with([
    'PostgreSQL, entry id' => "ERROR:  duplicate key value violates unique constraint \"chronicle_entries_pkey\"\nDETAIL:  Key (id)=(01J2Q5M2M8M0P0X2A9BTD3M7D1) already exists.",
    'PostgreSQL, a listener\'s own sequence column' => "ERROR:  duplicate key value violates unique constraint \"receipts_sequence_unique\"\nDETAIL:  Key (sequence)=(1) already exists.",
    'PostgreSQL, a table whose name ends in the entries table' => 'ERROR:  duplicate key value violates unique constraint "old_chronicle_entries_sequence_unique"',
    'SQLite, entry id' => 'UNIQUE constraint failed: chronicle_entries.id',
    'SQLite, a table whose name ends in the entries table' => 'UNIQUE constraint failed: old_chronicle_entries.sequence',
    'SQLite, a longer column name' => 'UNIQUE constraint failed: chronicle_entries.sequence_number',
]);

it('reads only the driver\'s error, never the SQL or its bindings', function () {
    $e = uniqueViolation(
        'UNIQUE constraint failed: chronicle_entries.id',
        'insert into "chronicle_entries" ("id", "payload") values (?, ?)',
        ['01J2Q5M2M8M0P0X2A9BTD3M7D1', '{"note":"chronicle_entries_sequence_unique chronicle_entries.sequence"}'],
    );

    expect($e->getMessage())->toContain('chronicle_entries_sequence_unique')
        ->and(ChainPlaceTaken::by($e, DB::connection()))->toBeFalse();
});

it('follows the configured entries table and the connection\'s prefix', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('app_');

    config(['chronicle.tables.entries' => 'audit_entries']);

    try {
        expect(ChainPlaceTaken::by(uniqueViolation('UNIQUE constraint failed: app_audit_entries.sequence'), $connection))->toBeTrue()
            ->and(ChainPlaceTaken::by(uniqueViolation('Duplicate entry \'2\' for key \'audit_entries_sequence_unique\''), $connection))->toBeTrue()
            ->and(ChainPlaceTaken::by(uniqueViolation('UNIQUE constraint failed: chronicle_entries.sequence'), $connection))->toBeFalse();
    } finally {
        // The migrations roll back at teardown and read the table name too.
        config(['chronicle.tables.entries' => 'chronicle_entries']);
    }
});
