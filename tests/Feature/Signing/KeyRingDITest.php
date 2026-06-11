<?php

use Chronicle\Contracts\SigningProvider;
use Chronicle\Signing\ConfigKeyRing;
use Chronicle\Signing\Ed25519SigningProvider;
use Chronicle\Signing\KeyRing;
use Chronicle\Signing\NullSigningProvider;

// ---------------------------------------------------------------------------
// Legacy flat config resolves a working single-key ring
// ---------------------------------------------------------------------------

it('legacy flat config synthesises a one-entry ring and signs/verifies', function () {
    // Simulate an app that published the old config and hasn't changed it
    config()->set('chronicle.signing', [
        'provider' => Ed25519SigningProvider::class,
        'key_id' => 'legacy-key',
        'private_key' => 'RcSfC2MuYTPnkrL/MIA4/l/sAjirGXXIFXZEPokdwh1Lcz+SvNE7bjvgCsDotjnlHfJyZ4XW/kUXemtoyaa92Q==',
        'public_key' => 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
        'enforce_on_boot' => false,
    ]);
    app()->forgetInstance(KeyRing::class);
    app()->forgetInstance(SigningProvider::class);

    $ring = app(KeyRing::class);

    expect($ring)->toBeInstanceOf(ConfigKeyRing::class);

    $provider = $ring->active();

    expect($provider)->toBeInstanceOf(Ed25519SigningProvider::class)
        ->and($provider->keyId())->toBe('legacy-key');

    $sig = $provider->sign('legacy-payload');
    expect($provider->verify('legacy-payload', $sig))->toBeTrue();
});

it('legacy config resolve() works by algorithm + key id', function () {
    config()->set('chronicle.signing', [
        'provider' => Ed25519SigningProvider::class,
        'key_id' => 'old',
        'private_key' => 'RcSfC2MuYTPnkrL/MIA4/l/sAjirGXXIFXZEPokdwh1Lcz+SvNE7bjvgCsDotjnlHfJyZ4XW/kUXemtoyaa92Q==',
        'public_key' => 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
    ]);
    app()->forgetInstance(KeyRing::class);
    app()->forgetInstance(SigningProvider::class);

    $provider = app(KeyRing::class)->resolve('ed25519', 'old');

    expect($provider->keyId())->toBe('old');
});

// ---------------------------------------------------------------------------
// New-artifact signing output is byte-identical to direct Ed25519 construction
// ---------------------------------------------------------------------------

it('signing via KeyRing produces byte-identical output to direct Ed25519 construction', function () {
    // Direct 1.9.1 construction path
    $legacy = new Ed25519SigningProvider(
        privateKey: 'RcSfC2MuYTPnkrL/MIA4/l/sAjirGXXIFXZEPokdwh1Lcz+SvNE7bjvgCsDotjnlHfJyZ4XW/kUXemtoyaa92Q==',
        publicKey: 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
        keyId: 'chronicle-dev-key',
    );

    // New 1.10 path through the container binding
    app()->forgetInstance(KeyRing::class);
    app()->forgetInstance(SigningProvider::class);
    $active = app(SigningProvider::class);

    $payload = 'chronicle-test-payload-'.str_repeat('x', 100);

    // Both should produce the same deterministic Ed25519 signature for the same key
    expect($active->sign($payload))->toBe($legacy->sign($payload));
});

it('new-shape config resolves active provider correctly', function () {
    config()->set('chronicle.signing', [
        'enforce_on_boot' => false,
        'active' => 'key-a',
        'keys' => [
            'key-a' => [
                'provider' => Ed25519SigningProvider::class,
                'algorithm' => 'ed25519',
                'private_key' => 'RcSfC2MuYTPnkrL/MIA4/l/sAjirGXXIFXZEPokdwh1Lcz+SvNE7bjvgCsDotjnlHfJyZ4XW/kUXemtoyaa92Q==',
                'public_key' => 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
            ],
        ],
    ]);
    app()->forgetInstance(KeyRing::class);
    app()->forgetInstance(SigningProvider::class);

    $provider = app(SigningProvider::class);

    expect($provider)->toBeInstanceOf(Ed25519SigningProvider::class)
        ->and($provider->keyId())->toBe('key-a');
});

it('returns NullSigningProvider when enforce_on_boot is false and active key is broken', function () {
    config()->set('chronicle.signing', [
        'enforce_on_boot' => false,
        'active' => 'bad-key',
        'keys' => [
            'bad-key' => [
                'provider' => Ed25519SigningProvider::class,
                'algorithm' => 'ed25519',
                'public_key' => null,  // missing - triggers InvalidArgumentException
            ],
        ],
    ]);
    app()->forgetInstance(KeyRing::class);
    app()->forgetInstance(SigningProvider::class);

    expect(app(SigningProvider::class))->toBeInstanceOf(NullSigningProvider::class);
});
