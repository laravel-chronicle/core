<?php

declare(strict_types=1);

use Chronicle\Contracts\StorageDriver;
use Chronicle\Storage\DriverResolver;
use Illuminate\Container\Container;

it('has() returns false for an unregistered driver', function () {
    $resolver = new DriverResolver(new Container);

    expect($resolver->has('custom-driver'))->toBeFalse();
});

it('has() returns true after a driver is registered via extend()', function () {
    $resolver = new DriverResolver(new Container);

    $resolver->extend('custom-driver', fn () => mock(StorageDriver::class));

    expect($resolver->has('custom-driver'))->toBeTrue();
});

it('has() returns false for reserved driver names', function () {
    $resolver = new DriverResolver(new Container);

    // Reserved drivers are handled by the built-in resolve() match, not extensions.
    expect($resolver->has('eloquent'))->toBeFalse()
        ->and($resolver->has('array'))->toBeFalse()
        ->and($resolver->has('null'))->toBeFalse();
});
