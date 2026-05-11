<?php

use Chronicle\ChronicleManager;
use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\LedgerReader;
use Chronicle\Contracts\ReferenceResolver;
use Chronicle\Entry\EntryBuilder;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\EntryExtensionRegistry;
use Chronicle\Pipeline\EntryPipeline;
use Chronicle\Pipeline\ExtensionStage;
use Chronicle\Storage\ArrayDriver;
use Chronicle\Storage\DatabaseDriver;
use Chronicle\Storage\DriverResolver;
use Chronicle\Storage\NullDriver;

it('creates entry builder', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);
    $reader = mock(LedgerReader::class);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
        prePipeline: mock(EntryPipeline::class),
        reader: $reader,
        drivers: app(DriverResolver::class),
        extensions: app(EntryExtensionRegistry::class),
    );

    expect($manager->record())->toBeInstanceOf(EntryBuilder::class);
});

it('delegates recording to the pipeline', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);
    $reader = mock(LedgerReader::class);

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
        prePipeline: mock(EntryPipeline::class),
        reader: $reader,
        drivers: app(DriverResolver::class),
        extensions: app(EntryExtensionRegistry::class),
    );

    $manager->commit($payload);
});

it('resolves configured active driver', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);
    $reader = mock(LedgerReader::class);

    config()->set('chronicle.driver', 'array');

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
        prePipeline: mock(EntryPipeline::class),
        reader: $reader,
        drivers: app(DriverResolver::class),
        extensions: app(EntryExtensionRegistry::class),
    );

    expect($manager->getActiveDriver())->toBeInstanceOf(ArrayDriver::class);
});

it('can swap the active driver', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);
    $reader = mock(LedgerReader::class);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
        prePipeline: mock(EntryPipeline::class),
        reader: $reader,
        drivers: app(DriverResolver::class),
        extensions: app(EntryExtensionRegistry::class),
    );

    $driver = new NullDriver;
    $manager->swapDriver($driver);

    expect($manager->getActiveDriver())->toBe($driver);
});

it('resolves driver by name', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);
    $reader = mock(LedgerReader::class);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
        prePipeline: mock(EntryPipeline::class),
        reader: $reader,
        drivers: app(DriverResolver::class),
        extensions: app(EntryExtensionRegistry::class),
    );

    expect($manager->driver('null'))->toBeInstanceOf(NullDriver::class)
        ->and($manager->driver('array'))->toBeInstanceOf(ArrayDriver::class)
        ->and($manager->driver('eloquent'))->toBeInstanceOf(DatabaseDriver::class);
});

it('throws for unknown driver names', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);
    $reader = mock(LedgerReader::class);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
        prePipeline: mock(EntryPipeline::class),
        reader: $reader,
        drivers: app(DriverResolver::class),
        extensions: app(EntryExtensionRegistry::class),
    );

    $manager->driver('unknown');
})->throws(InvalidArgumentException::class);

it('can register and resolve custom drivers', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);
    $reader = mock(LedgerReader::class);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
        prePipeline: mock(EntryPipeline::class),
        reader: $reader,
        drivers: app(DriverResolver::class),
        extensions: app(EntryExtensionRegistry::class),
    );

    $manager->extendDriver('custom', fn (): NullDriver => new NullDriver);

    expect($manager->driver('custom'))->toBeInstanceOf(NullDriver::class);
});

it('throws when attempting to override reserved driver names', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);
    $reader = mock(LedgerReader::class);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
        prePipeline: mock(EntryPipeline::class),
        reader: $reader,
        drivers: app(DriverResolver::class),
        extensions: app(EntryExtensionRegistry::class),
    );

    $manager->extendDriver('eloquent', fn (): NullDriver => new NullDriver);
})->throws(InvalidArgumentException::class, 'is reserved and cannot be overridden');

it('throws when attempting to register the same custom driver twice', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);
    $reader = mock(LedgerReader::class);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
        prePipeline: mock(EntryPipeline::class),
        reader: $reader,
        drivers: app(DriverResolver::class),
        extensions: app(EntryExtensionRegistry::class),
    );

    $manager->extendDriver('duplicate-custom', fn (): NullDriver => new NullDriver);
    $manager->extendDriver('duplicate-custom', fn (): ArrayDriver => new ArrayDriver);
})->throws(InvalidArgumentException::class, 'is already registered');

it('throws when custom driver factory returns invalid type', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);
    $reader = mock(LedgerReader::class);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
        prePipeline: mock(EntryPipeline::class),
        reader: $reader,
        drivers: app(DriverResolver::class),
        extensions: app(EntryExtensionRegistry::class),
    );

    $manager->extendDriver('invalid', fn (): stdClass => new stdClass);
    $manager->driver('invalid');
})->throws(InvalidArgumentException::class);

it('returns injected ledger reader instance', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);
    $reader = mock(LedgerReader::class);

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
        prePipeline: mock(EntryPipeline::class),
        reader: $reader,
        drivers: app(DriverResolver::class),
        extensions: app(EntryExtensionRegistry::class),
    );

    expect($manager->reader())->toBe($reader);
});

it('registers entry extensions', function () {
    $resolver = mock(ReferenceResolver::class);
    $pipeline = mock(EntryPipeline::class);
    $reader = mock(LedgerReader::class);
    $registry = new EntryExtensionRegistry(app());

    $manager = new ChronicleManager(
        resolver: $resolver,
        pipeline: $pipeline,
        prePipeline: mock(EntryPipeline::class),
        reader: $reader,
        drivers: app(DriverResolver::class),
        extensions: $registry,
    );

    $extension = new class implements EntryExtension
    {
        public function stage(): ExtensionStage
        {
            return ExtensionStage::PROCESS;
        }

        public function process(PendingEntry $entry): PendingEntry
        {
            return $entry;
        }
    };

    $manager->extendEntry($extension);

    expect($registry->ordered())->toHaveCount(1);
});
