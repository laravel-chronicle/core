<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Pipeline\ChainHashEntry;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

it('throws LogicException when called outside a DB transaction', function () {
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection->shouldReceive('transactionLevel')->andReturn(0);

    DB::shouldReceive('connection')
        ->withAnyArgs()
        ->andReturn($connection);

    $stage = new ChainHashEntry(new ChainHasher);
    $entry = new PendingEntry(['id' => 'test', 'payload_hash' => str_repeat('a', 64)]);

    $stage->process($entry);
})->throws(LogicException::class, 'DB transaction');
