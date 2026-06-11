<?php

use Chronicle\Contracts\SigningProvider;
use Chronicle\Exports\ExportManager;
use Chronicle\Facades\Chronicle;
use Chronicle\Signing\EcdsaSigningProvider;
use Chronicle\Signing\KeyRing;
use Chronicle\Verification\ExportVerifier;
use Illuminate\Support\Str;

if (! function_exists('ecdsaP256PrivatePem')) {
    function ecdsaP256PrivatePem(): string
    {
        return "-----BEGIN PRIVATE KEY-----\n".
            "MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgW+bV8zg4mNoXrTXz\n".
            "vy3jy0tYCc/+V1Zm2hiVlhYQxwShRANCAASjdo2NyADb51tx8N3G7nTYVno6nufj\n".
            "BUcJq4gYuc2zBjb3DQFGO8ph2flJYxAQMuFw69NAbDnaDlj1MQjUTvyW\n".
            "-----END PRIVATE KEY-----\n";
    }
}

if (! function_exists('ecdsaP256PublicPem')) {
    function ecdsaP256PublicPem(): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".
            "MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEo3aNjcgA2+dbcfDdxu502FZ6Op7n\n".
            "4wVHCauIGLnNswY29w0BRjvKYdn5SWMQEDLhcOvTQGw52g5Y9TEI1E78lg==\n".
            "-----END PUBLIC KEY-----\n";
    }
}

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

    // Confirm signature.json records 'ecdsa-p256' - proving KeyRing resolved the right provider
    $sig = json_decode((string) file_get_contents($exportPath.'/signature.json'), true);
    expect($sig['algorithm'])->toBe('ecdsa-p256')
        ->and($sig['key_id'])->toBe('ecdsa-key');
});
