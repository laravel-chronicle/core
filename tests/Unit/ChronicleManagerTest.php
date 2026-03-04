<?php

use Chronicle\ChronicleManager;
use Chronicle\Contracts\ReferenceResolver;
use Chronicle\Entry\EntryBuilder;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\EntryPipeline;
use Chronicle\Storage\ArrayDriver;
use Chronicle\Storage\EloquentDriver;
use Chronicle\Storage\NullDriver;

it('creates entry builder', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
    );

    expect($manager->record())->toBeInstanceOf(EntryBuilder::class);
});

it('delegates recording to the pipeline', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);

    $payload = [
        'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'actor_type' => 'system',
        'actor_id' => 'system',
        'action' => 'test.event',
        'subject_type' => 'system',
        'subject_id' => 'system',
        'metadata' => [],
        'context' => [],
        'created_at' => now('UTC'),
    ];

    $pipeline
        ->shouldReceive('process')
        ->once()
        ->withArgs(function (PendingEntry $entry) use ($payload): bool {
            return $entry->attributes()['action'] === $payload['action'];
        })
        ->andReturnUsing(fn (PendingEntry $entry): PendingEntry => $entry);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
    );

    $manager->commit($payload);
});

it('resolves configured active driver', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);

    config()->set('chronicle.driver', 'array');

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
    );

    expect($manager->getActiveDriver())->toBeInstanceOf(ArrayDriver::class);
});

it('can swap the active driver', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
    );

    $driver = new NullDriver;
    $manager->swapDriver($driver);

    expect($manager->getActiveDriver())->toBe($driver);
});

it('resolves driver by name', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
    );

    expect($manager->driver('null'))->toBeInstanceOf(NullDriver::class)
        ->and($manager->driver('array'))->toBeInstanceOf(ArrayDriver::class)
        ->and($manager->driver('eloquent'))->toBeInstanceOf(EloquentDriver::class);
});

it('throws for unknown driver names', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
    );

    $manager->driver('unknown');
})->throws(InvalidArgumentException::class);
