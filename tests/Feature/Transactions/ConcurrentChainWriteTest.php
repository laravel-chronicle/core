<?php

declare(strict_types=1);

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Entry\Entry;
use Chronicle\Entry\PendingEntry;
use Chronicle\Events\EntryRecorded;
use Chronicle\Facades\Chronicle;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Pipeline\CanonicalizePayload;
use Chronicle\Pipeline\ChainHashEntry;
use Chronicle\Pipeline\EncryptPayload;
use Chronicle\Pipeline\EntryPipeline;
use Chronicle\Pipeline\HashPayload;
use Chronicle\Pipeline\PersistEntry;
use Chronicle\Pipeline\RunExtensions;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Counts how often the stages around the chain ran.
 */
final class ChainStageRuns
{
    public int $beforeChain = 0;

    public int $chain = 0;
}

/**
 * Rebuild the pipeline so that, for the given number of attempts, the chain
 * stage hands out the place of the current head: what a writer sees when a
 * concurrent one committed first. `$column` picks which unique index the
 * losing write then breaks.
 */
function losingChainWrites(int $times, string $column = 'sequence'): ChainStageRuns
{
    $runs = new ChainStageRuns;

    $countBefore = new class($runs) implements EntryProcessor
    {
        public function __construct(private readonly ChainStageRuns $runs) {}

        public function process(PendingEntry $entry): PendingEntry
        {
            $this->runs->beforeChain++;

            return $entry;
        }
    };

    $takePlaceOfHead = new class($runs, $times, $column) implements EntryProcessor
    {
        public function __construct(
            private readonly ChainStageRuns $runs,
            private int $times,
            private readonly string $column,
        ) {}

        public function process(PendingEntry $entry): PendingEntry
        {
            $this->runs->chain++;

            if ($this->times-- > 0) {
                /** @var Entry $head */
                $head = Entry::query()->orderByDesc('sequence')->firstOrFail();

                $this->column === 'chain_hash'
                    ? $entry->setChainHash($head->chain_hash)
                    : $entry->setSequence($head->sequence);
            }

            return $entry;
        }
    };

    app()->instance(EntryPipeline::class, new EntryPipeline([
        app(RunExtensions::class),
        app(CanonicalizePayload::class),
        app(EncryptPayload::class),
        app(HashPayload::class),
        $countBefore,
        app(ChainHashEntry::class),
        $takePlaceOfHead,
        app(PersistEntry::class),
    ]));

    app()->forgetInstance('chronicle');
    Chronicle::clearResolvedInstance('chronicle');

    return $runs;
}

function recordEntry(string $action): void
{
    Chronicle::record()
        ->actor('system')
        ->action($action)
        ->subject(ref('ledger'))
        ->commit();
}

/**
 * @return list<int>
 */
function chainSequences(): array
{
    /** @var list<int> */
    return Entry::query()->orderBy('sequence')->pluck('sequence')->map(fn ($sequence) => (int) $sequence)->all();
}

it('writes an entry that lost its place in the chain again, on the new head', function () {
    recordEntry('chain.first');
    losingChainWrites(1);

    recordEntry('chain.second');

    expect(Entry::query()->orderBy('sequence')->pluck('action')->all())->toBe(['chain.first', 'chain.second'])
        ->and(chainSequences())->toBe([1, 2]);
});

it('writes again when the chain hash of the head was taken', function () {
    recordEntry('chain.first');
    losingChainWrites(1, 'chain_hash');

    recordEntry('chain.second');

    expect(chainSequences())->toBe([1, 2])
        ->and(Entry::query()->distinct()->count('chain_hash'))->toBe(2);
});

it('runs the stages before the chain once, and only the chain and persist stages again', function () {
    recordEntry('chain.first');
    $runs = losingChainWrites(2);

    recordEntry('chain.second');

    expect($runs->beforeChain)->toBe(1)
        ->and($runs->chain)->toBe(3)
        ->and(chainSequences())->toBe([1, 2]);
});

it('writes again inside a caller transaction without aborting it', function () {
    recordEntry('chain.first');
    losingChainWrites(1);

    DB::transaction(function () {
        recordEntry('chain.second');
        recordEntry('chain.third');
    });

    expect(Entry::query()->orderBy('sequence')->pluck('action')->all())->toBe(['chain.first', 'chain.second', 'chain.third'])
        ->and(chainSequences())->toBe([1, 2, 3]);
});

it('keeps the caller\'s own work when a write in its transaction is tried again', function () {
    Schema::create('chronicle_test_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number')->unique();
    });

    recordEntry('chain.first');
    $runs = losingChainWrites(1);

    DB::transaction(function () {
        DB::table('chronicle_test_invoices')->insert(['number' => 'INV-1']);

        recordEntry('invoice.created');
    });

    expect(DB::table('chronicle_test_invoices')->pluck('number')->all())->toBe(['INV-1'])
        ->and(Entry::query()->orderBy('sequence')->pluck('action')->all())->toBe(['chain.first', 'invoice.created'])
        ->and($runs->chain)->toBe(2);
});

it('gives up after the last attempt and throws', function () {
    recordEntry('chain.first');
    $runs = losingChainWrites(PHP_INT_MAX);

    expect(fn () => recordEntry('chain.second'))->toThrow(UniqueConstraintViolationException::class)
        ->and($runs->chain)->toBe(3)
        ->and(Entry::query()->count())->toBe(1);
});

it('does not try again on a duplicate entry id', function () {
    recordEntry('chain.first');
    $runs = losingChainWrites(0);

    /** @var Entry $first */
    $first = Entry::query()->firstOrFail();

    expect(fn () => Chronicle::commit([
        'id' => $first->id,
        'actor_type' => 'system',
        'actor_id' => 'system',
        'action' => 'chain.duplicate',
        'subject_type' => 'system',
        'subject_id' => 'ledger',
        'metadata' => [],
        'context' => [],
        'diff' => null,
        'tags' => [],
        'correlation_id' => null,
        'created_at' => Carbon::now('UTC'),
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect($runs->chain)->toBe(1)
        ->and(Entry::query()->count())->toBe(1);
});

it('does not try again when an EntryRecorded listener breaks its own unique index', function () {
    Schema::create('chronicle_test_receipts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('sequence')->unique();
    });

    $calls = 0;

    Event::listen(EntryRecorded::class, function () use (&$calls) {
        $calls++;

        DB::table('chronicle_test_receipts')->insert(['sequence' => 1]);
    });

    recordEntry('chain.first');
    $runs = losingChainWrites(0);

    expect(fn () => recordEntry('chain.second'))->toThrow(UniqueConstraintViolationException::class);

    expect($calls)->toBe(2)
        ->and($runs->chain)->toBe(1)
        ->and(chainSequences())->toBe([1]);
});

it('takes the chain advisory lock on the entries table before reading the head on PostgreSQL', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('transactionLevel')->andReturn(1);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');
    $connection->shouldReceive('getTablePrefix')->andReturn('app_');
    $connection->shouldReceive('select')
        ->once()
        ->with('select pg_advisory_xact_lock(hashtext(?))', ['app_chronicle_entries'])
        ->andThrow(new RuntimeException('lock taken'));

    $databases = DB::getFacadeRoot();
    DB::shouldReceive('connection')->withAnyArgs()->andReturn($connection);

    try {
        expect(fn () => (new ChainHashEntry(app(ChainHasher::class)))
            ->process(new PendingEntry(['id' => (string) Str::ulid(), 'payload_hash' => str_repeat('a', 64)])))
            ->toThrow(RuntimeException::class, 'lock taken');
    } finally {
        // Give the real database manager back before teardown rolls back.
        DB::swap($databases);
    }
});
