<?php

use Chronicle\Storage\DatabaseDriver;
use Chronicle\Storage\DriverResolver;
use Chronicle\Storage\QueuedDriver;

it('resolves queued driver', function () {
    $resolver = app(DriverResolver::class);
    expect($resolver->resolve('queued'))->toBeInstanceOf(QueuedDriver::class);
});

it('resolves database as an alias for eloquent', function () {
    $resolver = app(DriverResolver::class);
    expect($resolver->resolve('database'))->toBeInstanceOf(DatabaseDriver::class);
});

it('prevents overriding queued driver via extend()', function () {
    $resolver = app(DriverResolver::class);
    expect(fn () => $resolver->extend('queued', fn () => null))
        ->toThrow(InvalidArgumentException::class);
});

it('prevents overriding database driver via extend()', function () {
    $resolver = app(DriverResolver::class);
    expect(fn () => $resolver->extend('database', fn () => null))
        ->toThrow(InvalidArgumentException::class);
});
