<?php

use Chronicle\Signing\Ed25519SigningProvider;

it('throws when private key is missing', function () {
    new Ed25519SigningProvider(
        privateKey: null,
        publicKey: 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
    );
})->throws(InvalidArgumentException::class, 'Missing CHRONICLE_PRIVATE_KEY');

it('throws when private key is invalid base64', function () {
    new Ed25519SigningProvider(
        privateKey: 'not-base64',
        publicKey: 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
    );
})->throws(InvalidArgumentException::class, 'Invalid CHRONICLE_PRIVATE_KEY');

it('throws when public key has invalid length', function () {
    new Ed25519SigningProvider(
        privateKey: 'RcSfC2MuYTPnkrL/MIA4/l/sAjirGXXIFXZEPokdwh1Lcz+SvNE7bjvgCsDotjnlHfJyZ4XW/kUXemtoyaa92Q==',
        publicKey: base64_encode('short'),
    );
})->throws(InvalidArgumentException::class, 'Invalid CHRONICLE_PUBLIC_KEY');

it('signs and verifies payloads', function () {
    $provider = new Ed25519SigningProvider(
        privateKey: 'RcSfC2MuYTPnkrL/MIA4/l/sAjirGXXIFXZEPokdwh1Lcz+SvNE7bjvgCsDotjnlHfJyZ4XW/kUXemtoyaa92Q==',
        publicKey: 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
    );

    $payload = 'chronicle-payload';
    $signature = $provider->sign($payload);

    expect($provider->verify($payload, $signature))->toBeTrue()
        ->and($provider->verify($payload, 'not-base64'))->toBeFalse();
});

it('destructor does not throw when privateKey is null', function () {
    // Simulate construction failure leaving $privateKey null.
    // We do this by constructing with a bad public key, catching the exception,
    // then verifying the destructor does not throw when the object is destroyed.
    $threw = false;
    try {
        $provider = new Ed25519SigningProvider(
            privateKey: 'RcSfC2MuYTPnkrL/MIA4/l/sAjirGXXIFXZEPokdwh1Lcz+SvNE7bjvgCsDotjnlHfJyZ4XW/kUXemtoyaa92Q==',
            publicKey: base64_encode('tooshort'),
        );
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    expect($threw)->toBeTrue();
    // If destructor throws on null, the test process itself would fatal-error here.
    gc_collect_cycles();
});
