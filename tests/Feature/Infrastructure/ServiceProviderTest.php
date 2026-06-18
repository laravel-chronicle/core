<?php

declare(strict_types=1);

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\ReferenceResolver;
use Chronicle\Contracts\SigningProvider;
use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidActionException;
use Chronicle\Facades\Chronicle;
use Chronicle\Pipeline\EntryExtensionRegistry;
use Chronicle\Pipeline\EntryPipeline;
use Chronicle\Pipeline\ExtensionStage;
use Chronicle\Signing\ConfigKeyRing;
use Chronicle\Signing\KeyRing;
use Chronicle\Signing\NullSigningProvider;
use Chronicle\Storage\ArrayDriver;
use Chronicle\Storage\DatabaseDriver;
use Chronicle\Storage\DriverResolver;
use Chronicle\Storage\NullDriver;
use Chronicle\Validation\ActionValidator;
use Chronicle\Validation\ActorPresenceValidator;

it('binds entry store', function () {
    expect(app(StorageDriver::class))->not->toBeNull();
});

it('binds reference resolver', function () {
    expect(app(ReferenceResolver::class))->not->toBeNull();
});

it('resolves eloquent driver store', function () {
    config()->set('chronicle.driver', 'eloquent');

    expect(app(StorageDriver::class))->toBeInstanceOf(DatabaseDriver::class);
});

it('resolves array driver store', function () {
    config()->set('chronicle.driver', 'array');

    expect(app(StorageDriver::class))->toBeInstanceOf(ArrayDriver::class);
});

it('resolves null driver store', function () {
    config()->set('chronicle.driver', 'null');

    expect(app(StorageDriver::class))->toBeInstanceOf(NullDriver::class);
});

it('throws for unsupported driver', function () {
    config()->set('chronicle.driver', 'unsupported-driver');

    app(StorageDriver::class);
})->throws(InvalidArgumentException::class);

it('resolves custom extended driver', function () {
    app(DriverResolver::class)->extend('custom', fn (): NullDriver => new NullDriver);
    config()->set('chronicle.driver', 'custom');
    app()->forgetInstance(StorageDriver::class);

    expect(app(StorageDriver::class))->toBeInstanceOf(NullDriver::class);
});

it('allows extending drivers through the facade', function () {
    Chronicle::extendDriver('custom-facade', fn (): NullDriver => new NullDriver);

    expect(app('chronicle')->driver('custom-facade'))->toBeInstanceOf(NullDriver::class);
});

it('allows registering entry extensions through the facade', function () {
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

    Chronicle::extendEntry($extension);

    expect(app(EntryExtensionRegistry::class)->ordered())
        ->toContain($extension);
});

it('registers the action validator from config', function () {
    config()->set('chronicle.extensions', [ActorPresenceValidator::class, ActionValidator::class]);
    app()->forgetInstance(EntryExtensionRegistry::class);

    expect(collect(app(EntryExtensionRegistry::class)->ordered())
        ->contains(fn ($extension): bool => $extension instanceof ActorPresenceValidator))
        ->toBeTrue()
        ->and(collect(app(EntryExtensionRegistry::class)->ordered())
            ->contains(fn ($extension): bool => $extension instanceof ActionValidator))
        ->toBeTrue();
});

it('rejects persisted actions without dot notation', function () {
    config()->set('chronicle.extensions', [ActionValidator::class]);
    app()->forgetInstance(EntryExtensionRegistry::class);
    app()->forgetInstance(EntryPipeline::class);
    app()->forgetInstance('chronicle');

    Chronicle::record()
        ->actor('system')
        ->action('invalid')
        ->subject(ref('ledger'))
        ->commit();
})->throws(InvalidActionException::class);

it('throws when signing provider does not implement SigningProvider', function () {
    config()->set('chronicle.signing.keys.chronicle-dev-key.provider', stdClass::class);
    app()->forgetInstance(SigningProvider::class);
    app()->forgetInstance(KeyRing::class);

    app(SigningProvider::class);
})->throws(RuntimeException::class, 'must implement');

it('boots successfully when enforce_on_boot is false and keys are missing', function () {
    config()->set('chronicle.signing.enforce_on_boot', false);
    config()->set('chronicle.signing.keys.chronicle-dev-key.private_key');
    config()->set('chronicle.signing.keys.chronicle-dev-key.public_key');
    app()->forgetInstance(SigningProvider::class);
    app()->forgetInstance(KeyRing::class);

    $provider = app(SigningProvider::class);

    expect($provider)->toBeInstanceOf(NullSigningProvider::class);
});

it('resolves KeyRing from container', function () {
    expect(app(KeyRing::class))
        ->toBeInstanceOf(ConfigKeyRing::class);
});
