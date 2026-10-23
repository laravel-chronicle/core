<?php

declare(strict_types=1);

/*
 * A concurrent Chronicle writer on PostgreSQL, in a process of its own.
 *
 * It does what ChainHashEntry and the database driver do in one transaction -
 * take the advisory lock on the entries table, read the head with a row lock,
 * append the next entry - then prints "appended", holds the transaction open
 * for the given number of milliseconds and commits.
 *
 * Usage: php chain-writer.php <entries table> <hold ms>
 * Connection: CHRONICLE_RACE_DSN, CHRONICLE_RACE_USERNAME, CHRONICLE_RACE_PASSWORD.
 */

use Chronicle\Hashing\ChainHasher;
use Illuminate\Support\Str;

require __DIR__.'/../../../vendor/autoload.php';

[, $table, $holdMs] = $argv;

$pdo = new PDO(
    (string) getenv('CHRONICLE_RACE_DSN'),
    (string) getenv('CHRONICLE_RACE_USERNAME'),
    (string) getenv('CHRONICLE_RACE_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$pdo->beginTransaction();

$pdo->prepare('select pg_advisory_xact_lock(hashtext(?))')->execute([$table]);

$head = $pdo->query("select sequence, chain_hash from \"{$table}\" order by sequence desc limit 1 for update")
    ->fetch(PDO::FETCH_ASSOC);

$payload = json_encode(['action' => 'race.concurrent']);
$payloadHash = hash('sha256', (string) $payload);
$sequence = is_array($head) ? (int) $head['sequence'] + 1 : 1;
$previous = is_array($head) ? (string) $head['chain_hash'] : ChainHasher::GENESIS;

$pdo->prepare("insert into \"{$table}\" (id, actor_type, actor_id, action, subject_type, subject_id, payload, payload_hash, chain_hash, sequence, created_at) values (?, 'system', 'system', 'race.concurrent', 'system', 'ledger', ?, ?, ?, ?, now())")
    ->execute([
        strtolower((string) Str::ulid()),
        $payload,
        $payloadHash,
        (new ChainHasher)->hash($previous, $payloadHash),
        $sequence,
    ]);

fwrite(STDOUT, "appended\n");

usleep((int) $holdMs * 1000);

$pdo->commit();

fwrite(STDOUT, "committed\n");
