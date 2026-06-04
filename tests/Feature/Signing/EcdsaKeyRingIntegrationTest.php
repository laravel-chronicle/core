<?php

use Chronicle\Contracts\SigningProvider;
use Chronicle\Exports\ExportManager;
use Chronicle\Facades\Chronicle;
use Chronicle\Signing\EcdsaSigningProvider;
use Chronicle\Signing\KeyRing;
use Chronicle\Verification\ExportVerifier;
use Illuminate\Support\Str;

it('an ECDSA P-256 signed export verifies offline through the KeyRing', function () {
    // Configure the ring with an ECDSA key as the active signer
    config()->set('chronicle.signing', [
        'enforce_on_boot' => false,
        'active' => 'ecdsa-key',
        'keys' => [
            'ecdsa-key' => [
                'provider' => EcdsaSigningProvider::class,
                'algorithm' => 'ecdsa-p256',
                'private_key' => ecdsaP256PrivatePem(),
                'public_key' => ecdsaP256PublicPem(),
            ],
        ],
    ]);
    app()->forgetInstance(KeyRing::class);
    app()->forgetInstance(SigningProvider::class);
    app()->forgetInstance(ExportVerifier::class);

    Chronicle::record()->actor('system')->action('ecdsa.export')->subject(ref('ledger'))->commit();

    $exportPath = storage_path('chronicle-ecdsa-export-'.Str::uuid());
    app(ExportManager::class)->export($exportPath);

    $result = app(ExportVerifier::class)->verify($exportPath);

    expect($result->isValid())->toBeTrue();

    // Confirm signature.json records 'ecdsa-p256' — proving KeyRing resolved the right provider
    $sig = json_decode((string) file_get_contents($exportPath.'/signature.json'), true);
    expect($sig['algorithm'])->toBe('ecdsa-p256')
        ->and($sig['key_id'])->toBe('ecdsa-key');
});
