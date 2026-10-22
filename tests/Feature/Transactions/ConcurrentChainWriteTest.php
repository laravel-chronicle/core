<?php

declare(strict_types=1);

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Entry\Entry;
use Chronicle\Entry\PendingEntry;
use Chronicle\Facades\Chronicle;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Pipeline\CanonicalizePayload;
use Chronicle\Pipeline\ChainHashEntry;
use Chronicle\Pipeline\EncryptPayload;
use Chronicle\Pipeline\EntryPipeline;
use Chronicle\Pipeline\HashPayload;
use Chronicle\Pipeline\PersistEntry;
use Chronicle\Pipeline\RunExtensions;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Replace the chain stage with one that, for the given number of attempts,
 * hands out the sequence of the current head: what a writer sees on
 * PostgreSQL when it waited for the row lock while another one committed.
 */
function losingChainWrites(int $times): void
{
    $stale = new class(app(ChainHashEntry::class), $times) implements EntryProcessor
    {
        public function __construct(private readonly ChainHashEntry $chain, private int $times) {}

        public function process(PendingEntry $entry): PendingEntry
        {
            $entry = $this->chain->process($entry);

            if ($this->times-- > 0) {
                $entry->setSequence((int) Entry::query()->max('sequence'));
            }

            return $entry;
        }
    };

    app()->instance(EntryPipeline::class, new EntryPipeline([
        app(RunExtensions::class),
        app(CanonicalizePayload::class),
        app(EncryptPayload::class),
        app(HashPayload::class),
        $stale,
        app(PersistEntry::class),
    ]));

    app()->forgetInstance('chronicle');
    Chronicle::clearResolvedInstance('chronicle');
}

function recordEntry(string $action): void
{
    Chronicle::record()
        ->actor('system')
        ->action($action)
        ->subject(ref('ledger'))
        ->commit();
}

it('writes an entry that lost its place in the chain again, on the new head', function () {
    recordEntry('chain.first');
    losingChainWrites(1);

    recordEntry('chain.second');

    expect(Entry::query()->orderBy('sequence')->pluck('action')->all())->toBe(['chain.first', 'chain.second'])
        ->and(Entry::query()->orderBy('sequence')->pluck('sequence')->all())->toBe([1, 2]);
});

it('writes again inside a caller transaction without aborting it', function () {
    recordEntry('chain.first');
    losingChainWrites(1);

    DB::transaction(function () {
        recordEntry('chain.second');
        recordEntry('chain.third');
    });

    expect(Entry::query()->orderBy('sequence')->pluck('action')->all())->toBe(['chain.first', 'chain.second', 'chain.third'])
        ->and(Entry::query()->orderBy('sequence')->pluck('sequence')->all())->toBe([1, 2, 3]);
});

it('gives up after the last attempt and throws', function () {
    recordEntry('chain.first');
    losingChainWrites(PHP_INT_MAX);

    expect(fn () => recordEntry('chain.second'))->toThrow(UniqueConstraintViolationException::class)
        ->and(Entry::query()->count())->toBe(1);
});

it('takes the chain advisory lock before reading the head on PostgreSQL', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection->shouldReceive('transactionLevel')->andReturn(1);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');
    $connection->shouldReceive('select')
        ->once()
        ->with('select pg_advisory_xact_lock(?)', [ChainHashEntry::CHAIN_LOCK_KEY])
        ->andThrow(new RuntimeException('lock taken'));

    DB::shouldReceive('connection')->withAnyArgs()->andReturn($connection);

    (new ChainHashEntry(app(ChainHasher::class)))
        ->process(new PendingEntry(['id' => 'test', 'payload_hash' => str_repeat('a', 64)]));
})->throws(RuntimeException::class, 'lock taken');
