<?php

declare(strict_types=1);

use Chronicle\Contracts\SigningProvider;
use Chronicle\Exceptions\ChronicleException;
use Chronicle\Exceptions\UnknownSigningKeyException;
use Chronicle\Signing\ConfigKeyRing;
use Chronicle\Signing\Ed25519SigningProvider;
use Chronicle\Signing\SigningProviderFactory;

// --- UnknownSigningKeyException ---

it('UnknownSigningKeyException is a ChronicleException', function () {
    $e = new UnknownSigningKeyException('No key for ecdsa:retired-key');
    expect($e)->toBeInstanceOf(ChronicleException::class);
});

// --- ConfigKeyRing ---

function makeTestKeyRing(array $extraKeys = []): ConfigKeyRing
{
    $keys = array_merge([
        'key-1' => [
            'provider' => Ed25519SigningProvider::class,
            'algorithm' => 'ed25519',
            'private_key' => 'RcSfC2MuYTPnkrL/MIA4/l/sAjirGXXIFXZEPokdwh1Lcz+SvNE7bjvgCsDotjnlHfJyZ4XW/kUXemtoyaa92Q==',
            'public_key' => 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
        ],
    ], $extraKeys);

    $config = [
        'active' => 'key-1',
        'enforce_on_boot' => false,
        'keys' => $keys,
    ];

    return new ConfigKeyRing($config, new SigningProviderFactory(app()));
}

it('active() returns the provider for the configured active key id', function () {
    $ring = makeTestKeyRing();

    expect($ring->active())->toBeInstanceOf(Ed25519SigningProvider::class)
        ->and($ring->active()->keyId())->toBe('key-1');
});

it('resolve() returns provider matching algorithm and key id', function () {
    $ring = makeTestKeyRing();

    $provider = $ring->resolve('ed25519', 'key-1');

    expect($provider)->toBeInstanceOf(Ed25519SigningProvider::class)
        ->and($provider->keyId())->toBe('key-1');
});

it('resolve() throws UnknownSigningKeyException on miss', function () {
    $ring = makeTestKeyRing();

    $ring->resolve('ed25519', 'missing-key');
})->throws(UnknownSigningKeyException::class);

it('resolve() throws on wrong algorithm', function () {
    $ring = makeTestKeyRing();

    $ring->resolve('ecdsa-p256', 'key-1');
})->throws(UnknownSigningKeyException::class);

it('all() returns providers keyed by {algorithm}:{keyId}', function () {
    $ring = makeTestKeyRing([
        'key-2' => [
            'provider' => Ed25519SigningProvider::class,
            'algorithm' => 'ed25519',
            'public_key' => 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
        ],
    ]);

    $all = $ring->all();

    expect($all)->toHaveKey('ed25519:key-1')
        ->and($all)->toHaveKey('ed25519:key-2')
        ->and($all['ed25519:key-1'])->toBeInstanceOf(SigningProvider::class);
});

it('providers are built lazily and cached', function () {
    $ring = makeTestKeyRing();

    $a = $ring->active();
    $b = $ring->active();

    expect($a)->toBe($b);
});
