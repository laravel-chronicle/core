<?php

declare(strict_types=1);

use Chronicle\Contracts\SigningProvider;
use Chronicle\Exports\ExportManager;
use Chronicle\Facades\Chronicle;
use Chronicle\Signing\Ed25519SigningProvider;
use Chronicle\Signing\KeyRing;
use Chronicle\Verification\ExportVerifier;
use Chronicle\Verification\VerificationFailure;
use Illuminate\Support\Str;

// ---------------------------------------------------------------------------
// An export signed by a retired key (public key kept) still verifies
// ---------------------------------------------------------------------------

it('verifies an export signed by a retired key when the public key remains in the ring', function () {
    Chronicle::record()->actor('system')->action('export.key1')->subject(ref('ledger'))->commit();

    $exportPath = storage_path('chronicle-multikey-export-'.Str::uuid());
    app(ExportManager::class)->export($exportPath);

    // Rotate: key-2 active, chronicle-dev-key kept as verify-only (no private key)
    $kp2 = sodium_crypto_sign_keypair();
    config()->set('chronicle.signing', [
        'enforce_on_boot' => false,
        'active' => 'key-2',
        'keys' => [
            'chronicle-dev-key' => [
                'provider' => Ed25519SigningProvider::class,
                'algorithm' => 'ed25519',
                'private_key' => null,
                'public_key' => 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
            ],
            'key-2' => [
                'provider' => Ed25519SigningProvider::class,
                'algorithm' => 'ed25519',
                'private_key' => base64_encode(sodium_crypto_sign_secretkey($kp2)),
                'public_key' => base64_encode(sodium_crypto_sign_publickey($kp2)),
            ],
        ],
    ]);
    app()->forgetInstance(KeyRing::class);
    app()->forgetInstance(SigningProvider::class);
    app()->forgetInstance(ExportVerifier::class);

    $result = app(ExportVerifier::class)->verify($exportPath);

    expect($result->isValid())->toBeTrue();
});

// ---------------------------------------------------------------------------
// A forged signature with a known key returns signature_invalid (not unknown_key)
// ---------------------------------------------------------------------------

it('returns signature_invalid when the signature bytes are forged but the key is known', function () {
    Chronicle::record()->actor('system')->action('export.forge')->subject(ref('ledger'))->commit();

    $exportPath = storage_path('chronicle-forged-export-'.Str::uuid());
    app(ExportManager::class)->export($exportPath);

    // Corrupt the signature bytes; keep algorithm + key_id intact
    $sigFile = $exportPath.'/signature.json';
    $sig = json_decode((string) file_get_contents($sigFile), true);
    $sig['signature'] = base64_encode(str_repeat('x', 64));
    file_put_contents($sigFile, json_encode($sig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $result = app(ExportVerifier::class)->verify($exportPath);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe(VerificationFailure::SignatureInvalid->value);
});

// ---------------------------------------------------------------------------
// A signature.json referencing an unknown key returns unknown_key
// ---------------------------------------------------------------------------

it('returns unknown_key when signature.json references a key not present in the ring', function () {
    Chronicle::record()->actor('system')->action('export.unknown')->subject(ref('ledger'))->commit();

    $exportPath = storage_path('chronicle-unknownkey-export-'.Str::uuid());
    app(ExportManager::class)->export($exportPath);

    // Overwrite algorithm/key_id to reference a key that does not exist in the ring
    $sigFile = $exportPath.'/signature.json';
    $sig = json_decode((string) file_get_contents($sigFile), true);
    $sig['algorithm'] = 'ed25519';
    $sig['key_id'] = 'no-such-key';
    file_put_contents($sigFile, json_encode($sig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $result = app(ExportVerifier::class)->verify($exportPath);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe(VerificationFailure::UnknownKey->value);
});
