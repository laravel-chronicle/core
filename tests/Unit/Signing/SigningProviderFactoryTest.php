<?php

use Chronicle\Signing\Ed25519SigningProvider;
use Chronicle\Signing\SigningProviderFactory;

it('make() constructs an Ed25519 provider via the container', function () {
    $factory = new SigningProviderFactory(app());

    $provider = $factory->make('test-key', [
        'provider' => Ed25519SigningProvider::class,
        'algorithm' => 'ed25519',
        'private_key' => 'RcSfC2MuYTPnkrL/MIA4/l/sAjirGXXIFXZEPokdwh1Lcz+SvNE7bjvgCsDotjnlHfJyZ4XW/kUXemtoyaa92Q==',
        'public_key' => 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
    ]);

    expect($provider)->toBeInstanceOf(Ed25519SigningProvider::class)
        ->and($provider->keyId())->toBe('test-key')
        ->and($provider->algorithm())->toBe('ed25519');
});

it('make() injects the key id from the ring id into config', function () {
    $factory = new SigningProviderFactory(app());

    $provider = $factory->make('ring-id-123', [
        'provider' => Ed25519SigningProvider::class,
        'public_key' => 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
    ]);

    expect($provider->keyId())->toBe('ring-id-123');
});

it('make() throws when provider class does not implement SigningProvider', function () {
    $factory = new SigningProviderFactory(app());

    $factory->make('bad', [
        'provider' => stdClass::class,
    ]);
})->throws(RuntimeException::class, 'must implement');

it('make() throws when provider key is missing', function () {
    $factory = new SigningProviderFactory(app());

    $factory->make('bad', []);
})->throws(RuntimeException::class, 'must implement');
