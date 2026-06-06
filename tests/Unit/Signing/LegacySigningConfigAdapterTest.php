<?php

use Chronicle\Signing\Ed25519SigningProvider;
use Chronicle\Signing\LegacySigningConfigAdapter;

// --- isLegacy() ---

it('detects legacy config when keys key is absent', function () {
    $config = [
        'provider' => Ed25519SigningProvider::class,
        'key_id' => 'old-key',
        'private_key' => 'abc',
        'public_key' => 'def',
        'enforce_on_boot' => false,
    ];

    expect(LegacySigningConfigAdapter::isLegacy($config))->toBeTrue();
});

it('does not detect legacy when keys key is present', function () {
    $config = [
        'active' => 'my-key',
        'enforce_on_boot' => false,
        'keys' => [],
    ];

    expect(LegacySigningConfigAdapter::isLegacy($config))->toBeFalse();
});

// --- adapt() ---

it('adapts legacy config to plural shape', function () {
    $config = [
        'provider' => Ed25519SigningProvider::class,
        'key_id' => 'old-key',
        'private_key' => 'RcSfC2MuYTPnkrL/MIA4/l/sAjirGXXIFXZEPokdwh1Lcz+SvNE7bjvgCsDotjnlHfJyZ4XW/kUXemtoyaa92Q==',
        'public_key' => 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
        'enforce_on_boot' => true,
    ];

    $adapted = LegacySigningConfigAdapter::adapt($config);

    expect($adapted['active'])->toBe('old-key')
        ->and($adapted['enforce_on_boot'])->toBeTrue()
        ->and($adapted['keys'])->toHaveKey('old-key')
        ->and($adapted['keys']['old-key']['provider'])->toBe(Ed25519SigningProvider::class)
        ->and($adapted['keys']['old-key']['algorithm'])->toBe('ed25519')
        ->and($adapted['keys']['old-key']['private_key'])->toBe('RcSfC2MuYTPnkrL/MIA4/l/sAjirGXXIFXZEPokdwh1Lcz+SvNE7bjvgCsDotjnlHfJyZ4XW/kUXemtoyaa92Q==')
        ->and($adapted['keys']['old-key']['public_key'])->toBe('S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=');
});

it('adapt() uses chronicle-dev-key when key_id is absent', function () {
    $config = [
        'provider' => Ed25519SigningProvider::class,
        'private_key' => 'abc',
        'public_key' => 'def',
    ];

    $adapted = LegacySigningConfigAdapter::adapt($config);

    expect($adapted['active'])->toBe('chronicle-dev-key')
        ->and($adapted['keys'])->toHaveKey('chronicle-dev-key');
});
