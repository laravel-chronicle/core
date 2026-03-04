<?php

use Chronicle\Contracts\ReferenceResolver;
use Chronicle\Contracts\StorageDriver;
use Chronicle\Storage\ArrayDriver;
use Chronicle\Storage\EloquentDriver;
use Chronicle\Storage\NullDriver;

it('binds entry store', function () {
    expect(app(StorageDriver::class))->not->toBeNull();
});

it('binds reference resolver', function () {
    expect(app(ReferenceResolver::class))->not->toBeNull();
});

it('resolves eloquent driver store', function () {
    config()->set('chronicle.driver', 'eloquent');

    expect(app(StorageDriver::class))->toBeInstanceOf(EloquentDriver::class);
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
})->throws(\InvalidArgumentException::class);
