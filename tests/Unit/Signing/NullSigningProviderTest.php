<?php

declare(strict_types=1);

use Chronicle\Signing\NullSigningProvider;

it('throws on sign()', function () {
    $original = new RuntimeException('original');
    $provider = new NullSigningProvider($original);

    $provider->sign('data');
})->throws(RuntimeException::class, 'not configured');

it('throws on verify()', function () {
    $original = new RuntimeException('original');
    $provider = new NullSigningProvider($original);

    $provider->verify('data', 'sig');
})->throws(RuntimeException::class, 'not configured');

it('returns none for algorithm()', function () {
    $provider = new NullSigningProvider(new RuntimeException);
    expect($provider->algorithm())->toBe('none');
});

it('returns null for keyId()', function () {
    $provider = new NullSigningProvider(new RuntimeException);
    expect($provider->keyId())->toBeNull();
});
