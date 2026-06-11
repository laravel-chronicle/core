<?php

use Chronicle\Contracts\SigningProvider;
use Chronicle\Facades\Chronicle;
use Chronicle\Reports\ComplianceReport;
use Chronicle\Signing\Ed25519SigningProvider;
use Chronicle\Signing\KeyRing;
use Illuminate\Support\Str;

// ---------------------------------------------------------------------------
// A report signed under key-1 verifies after rotating to key-2
// ---------------------------------------------------------------------------

it('verifies a compliance report signed under key-1 after rotating to key-2', function () {
    Chronicle::record()->actor('system')->action('report.rotation')->subject(ref('ledger'))->commit();

    $reportPath = storage_path('chronicle-report-rotation-'.Str::uuid().'.html');

    // Phase 1: generate report with chronicle-dev-key active
    $result = app(ComplianceReport::class)->generate($reportPath);

    expect($result->algorithm)->toBe('ed25519')
        ->and($result->keyId)->toBe('chronicle-dev-key');

    // Phase 2: rotate - key-2 active, chronicle-dev-key kept as verify-only
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
    app()->forgetInstance(ComplianceReport::class);

    // Phase 3: verify the old report - must succeed using chronicle-dev-key public key
    $valid = app(ComplianceReport::class)->verify(
        $result->reportHash,
        $result->signature,
        $result->algorithm,
        $result->keyId,
    );

    expect($valid)->toBeTrue();
});

// ---------------------------------------------------------------------------
// verify() returns false when the report hash has been tampered
// ---------------------------------------------------------------------------

it('returns false when the report hash does not match the signature', function () {
    Chronicle::record()->actor('system')->action('report.tamper')->subject(ref('ledger'))->commit();

    $reportPath = storage_path('chronicle-report-tamper-'.Str::uuid().'.html');
    $result = app(ComplianceReport::class)->generate($reportPath);

    $valid = app(ComplianceReport::class)->verify(
        str_repeat('f', 64), // wrong hash
        $result->signature,
        $result->algorithm,
        $result->keyId,
    );

    expect($valid)->toBeFalse();
});

// ---------------------------------------------------------------------------
// verify() returns false for an unknown key
// ---------------------------------------------------------------------------

it('returns false when the algorithm+key_id is not in the ring', function () {
    Chronicle::record()->actor('system')->action('report.unknownkey')->subject(ref('ledger'))->commit();

    $reportPath = storage_path('chronicle-report-unknownkey-'.Str::uuid().'.html');
    $result = app(ComplianceReport::class)->generate($reportPath);

    $valid = app(ComplianceReport::class)->verify(
        $result->reportHash,
        $result->signature,
        'ed25519',
        'no-such-key',
    );

    expect($valid)->toBeFalse();
});
