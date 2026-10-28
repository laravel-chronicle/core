<?php

declare(strict_types=1);

use Chronicle\Entry\Entry;
use Chronicle\Entry\PendingEntry;
use Chronicle\Facades\Chronicle;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Jobs\PersistChronicleEntryJob;
use Chronicle\Pipeline\ChainHashEntry;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/*
 * Races a real second connection on PostgreSQL. Skipped on any other database:
 * run with CHRONICLE_TEST_DB_CONNECTION=pgsql (see the PostgreSQL job in
 * .github/workflows/run-tests.yml).
 *
 * Chronicle writes on `chronicle_testing`, a connection RefreshDatabase does not
 * wrap in a transaction, so the concurrent writer in the other process sees its
 * commits and the other way round. The tables are emptied before and after.
 */

const RACE_HOLD_MS = 600;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Races a second connection on PostgreSQL.');
    }

    config(['chronicle.connection' => 'chronicle_testing']);

    emptyRaceLedger();
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'pgsql') {
        emptyRaceLedger();
    }
});

function emptyRaceLedger(): void
{
    $db = DB::connection('chronicle_testing');

    $db->table(config()->string('chronicle.tables.subject_keys', 'chronicle_subject_keys'))->delete();
    $db->table(config()->string('chronicle.tables.entries', 'chronicle_entries'))->delete();
}

/**
 * Start the concurrent writer and return once it has appended its entry and
 * holds the chain lock, uncommitted, for RACE_HOLD_MS.
 */
function startConcurrentWriter(): Process
{
    /** @var array{host: string, port: int, database: string, username: string, password: string} $config */
    $config = config('database.connections.chronicle_testing');

    $writer = new Process(
        [PHP_BINARY, __DIR__.'/../../Fixtures/pgsql/chain-writer.php', ChainHashEntry::lockName(DB::connection('chronicle_testing')), (string) RACE_HOLD_MS],
        null,
        [
            'CHRONICLE_RACE_DSN' => "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
            'CHRONICLE_RACE_USERNAME' => $config['username'],
            'CHRONICLE_RACE_PASSWORD' => $config['password'],
        ],
    );

    $writer->setTimeout(30);
    $writer->start();
    $writer->waitUntil(fn (string $type, string $output): bool => str_contains($writer->getOutput(), 'appended'));

    expect($writer->isRunning())->toBeTrue('The concurrent writer stopped early: '.$writer->getErrorOutput());

    return $writer;
}

/**
 * Record one entry while the concurrent writer holds the lock. Returns how long
 * the write took and how often it read the chain head: a write that lost its
 * place reads it again on the next attempt.
 *
 * @param  (callable(): void)|null  $write  a synchronous Chronicle write by default
 * @return array{elapsed_ms: float, head_reads: int}
 */
function recordWhileWriterHoldsLock(?callable $write = null): array
{
    $headReads = 0;
    $table = config()->string('chronicle.tables.entries', 'chronicle_entries');

    DB::connection('chronicle_testing')->listen(function (QueryExecuted $query) use (&$headReads, $table) {
        if (str_contains($query->sql, 'from "'.$table.'"') && str_contains($query->sql, 'for update')) {
            $headReads++;
        }
    });

    $write ??= fn () => Chronicle::record()
        ->actor('system')
        ->action('race.waiting')
        ->subject(ref('ledger'))
        ->commit();

    $started = hrtime(true);

    $write();

    return ['elapsed_ms' => (hrtime(true) - $started) / 1e6, 'head_reads' => $headReads];
}

it('makes a second writer wait for the chain head and append on the entry committed meanwhile', function () {
    Chronicle::record()->actor('system')->action('race.first')->subject(ref('ledger'))->commit();

    $writer = startConcurrentWriter();

    $write = recordWhileWriterHoldsLock();

    $writer->wait();

    /** @var list<Entry> $entries */
    $entries = Entry::query()->orderBy('sequence')->get()->all();

    expect($writer->isSuccessful())->toBeTrue($writer->getErrorOutput())
        ->and(array_map(fn (Entry $entry) => $entry->action, $entries))->toBe(['race.first', 'race.concurrent', 'race.waiting'])
        ->and(array_map(fn (Entry $entry) => (int) $entry->sequence, $entries))->toBe([1, 2, 3])
        ->and($entries[2]->chain_hash)->toBe((new ChainHasher)->hash($entries[1]->chain_hash, $entries[2]->payload_hash))
        // It waited for the other writer's commit and read the head once: it
        // never took the head's place and was not tried again.
        ->and($write['elapsed_ms'])->toBeGreaterThan(RACE_HOLD_MS / 2)
        ->and($write['head_reads'])->toBe(1);
});

it('makes a second writer wait on an empty ledger, where there is no head row to lock', function () {
    $writer = startConcurrentWriter();

    $write = recordWhileWriterHoldsLock();

    $writer->wait();

    /** @var list<Entry> $entries */
    $entries = Entry::query()->orderBy('sequence')->get()->all();

    expect($writer->isSuccessful())->toBeTrue($writer->getErrorOutput())
        ->and(array_map(fn (Entry $entry) => $entry->action, $entries))->toBe(['race.concurrent', 'race.waiting'])
        ->and(array_map(fn (Entry $entry) => (int) $entry->sequence, $entries))->toBe([1, 2])
        ->and($entries[1]->chain_hash)->toBe((new ChainHasher)->hash($entries[0]->chain_hash, $entries[1]->payload_hash))
        ->and($write['elapsed_ms'])->toBeGreaterThan(RACE_HOLD_MS / 2)
        ->and($write['head_reads'])->toBe(1);
});

it('makes a queued write wait for the chain head the same way', function () {
    Chronicle::record()->actor('system')->action('race.first')->subject(ref('ledger'))->commit();

    // What the queued driver dispatches: the entry through the early stages.
    $prepared = app('chronicle.pipeline.pre')->process(new PendingEntry([
        'id' => (string) Str::ulid(),
        'actor_type' => 'system',
        'actor_id' => 'system',
        'action' => 'race.waiting',
        'subject_type' => 'system',
        'subject_id' => 'ledger',
        'metadata' => [],
        'context' => [],
        'diff' => null,
        'tags' => [],
        'correlation_id' => null,
        'created_at' => Carbon::now('UTC'),
    ]));

    $writer = startConcurrentWriter();

    $write = recordWhileWriterHoldsLock(fn () => app()->call([new PersistChronicleEntryJob($prepared->toDatabasePayload()), 'handle']));

    $writer->wait();

    /** @var list<Entry> $entries */
    $entries = Entry::query()->orderBy('sequence')->get()->all();

    expect($writer->isSuccessful())->toBeTrue($writer->getErrorOutput())
        ->and(array_map(fn (Entry $entry) => $entry->action, $entries))->toBe(['race.first', 'race.concurrent', 'race.waiting'])
        ->and(array_map(fn (Entry $entry) => (int) $entry->sequence, $entries))->toBe([1, 2, 3])
        ->and($entries[2]->chain_hash)->toBe((new ChainHasher)->hash($entries[1]->chain_hash, $entries[2]->payload_hash))
        ->and($write['elapsed_ms'])->toBeGreaterThan(RACE_HOLD_MS / 2)
        ->and($write['head_reads'])->toBe(1);
});

it('locks only the chain of its own entries table', function () {
    $writer = startConcurrentWriter();

    $db = DB::connection('chronicle_testing');
    $own = ChainHashEntry::lockName($db);

    // The advisory lock of another ledger in the same database stays free
    // while this one is held.
    $free = $db->selectOne('select pg_try_advisory_lock(hashtext(?)) as taken', ['other_'.$own]);
    $db->select('select pg_advisory_unlock(hashtext(?))', ['other_'.$own]);

    $held = $db->selectOne('select pg_try_advisory_lock(hashtext(?)) as taken', [$own]);

    $writer->wait();

    expect($free->taken)->toBeTrue()
        ->and($held->taken)->toBeFalse();
});
