<?php

declare(strict_types=1);

use Chronicle\Signing\EcdsaSigningProvider;

// ---------------------------------------------------------------------------
// Test helpers - fixed P-256 keypair for all tests
// ---------------------------------------------------------------------------

function ecdsaP256PrivatePem(): string
{
    return "-----BEGIN PRIVATE KEY-----\n".
        "MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgW+bV8zg4mNoXrTXz\n".
        "vy3jy0tYCc/+V1Zm2hiVlhYQxwShRANCAASjdo2NyADb51tx8N3G7nTYVno6nufj\n".
        "BUcJq4gYuc2zBjb3DQFGO8ph2flJYxAQMuFw69NAbDnaDlj1MQjUTvyW\n".
        "-----END PRIVATE KEY-----\n";
}

function ecdsaP256PublicPem(): string
{
    return "-----BEGIN PUBLIC KEY-----\n".
        "MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEo3aNjcgA2+dbcfDdxu502FZ6Op7n\n".
        "4wVHCauIGLnNswY29w0BRjvKYdn5SWMQEDLhcOvTQGw52g5Y9TEI1E78lg==\n".
        "-----END PUBLIC KEY-----\n";
}

// Precomputed signature of 'chronicle-ecdsa-test-vector' under ecdsaP256PrivatePem().
// Non-deterministic to produce but deterministic to verify: this specific base64 string
// was signed with the above private key and verified to be correct.
function ecdsaP256KnownSigB64(): string
{
    return 'MEQCIB1M5BTqcSncv6bg1X4VOVmCP/UInHXuIj3Qz1r4RFvfAiB8yU4TBcDsugVcHf5YycEodkJzGON4WFTFQX94i2Ew/w==';
}

// ---------------------------------------------------------------------------

it('algorithm() returns ecdsa-p256', function () {
    $provider = new EcdsaSigningProvider([
        'public_key' => ecdsaP256PublicPem(),
        'key_id' => 'test',
    ]);

    expect($provider->algorithm())->toBe('ecdsa-p256');
});

it('keyId() returns the configured key id', function () {
    $provider = new EcdsaSigningProvider([
        'public_key' => ecdsaP256PublicPem(),
        'key_id' => 'my-ecdsa-key',
    ]);

    expect($provider->keyId())->toBe('my-ecdsa-key');
});

it('sign() and verify() round-trip with a local private key', function () {
    $provider = new EcdsaSigningProvider([
        'private_key' => ecdsaP256PrivatePem(),
        'public_key' => ecdsaP256PublicPem(),
        'key_id' => 'test',
    ]);

    $payload = 'chronicle-roundtrip-payload';
    $signature = $provider->sign($payload);

    expect($provider->verify($payload, $signature))->toBeTrue();
});

it('verify() validates a precomputed known-vector signature', function () {
    $provider = new EcdsaSigningProvider([
        'public_key' => ecdsaP256PublicPem(),
        'key_id' => 'test',
    ]);

    expect($provider->verify('chronicle-ecdsa-test-vector', ecdsaP256KnownSigB64()))->toBeTrue();
});

it('verify() returns false for the wrong payload', function () {
    $provider = new EcdsaSigningProvider([
        'public_key' => ecdsaP256PublicPem(),
        'key_id' => 'test',
    ]);

    expect($provider->verify('wrong-payload', ecdsaP256KnownSigB64()))->toBeFalse();
});

it('verify() returns false for a different public key', function () {
    // Generate a fresh key pair - unrelated to the test vector
    $freshKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $freshDetails = openssl_pkey_get_details($freshKey);
    $freshPublicPem = $freshDetails['key'];

    $provider = new EcdsaSigningProvider([
        'public_key' => $freshPublicPem,
        'key_id' => 'test',
    ]);

    expect($provider->verify('chronicle-ecdsa-test-vector', ecdsaP256KnownSigB64()))->toBeFalse();
});

it('verify() returns false for invalid base64 signature', function () {
    $provider = new EcdsaSigningProvider([
        'public_key' => ecdsaP256PublicPem(),
        'key_id' => 'test',
    ]);

    expect($provider->verify('any-payload', 'not-valid-base64!!!'))->toBeFalse();
});

it('sign() throws when constructed without a private key (verify-only)', function () {
    $provider = new EcdsaSigningProvider([
        'public_key' => ecdsaP256PublicPem(),
        'key_id' => 'test',
    ]);

    $provider->sign('payload');
})->throws(RuntimeException::class, 'no private key');

it('throws when public_key is missing from config', function () {
    new EcdsaSigningProvider(['key_id' => 'test']);
})->throws(InvalidArgumentException::class, 'public_key');

it('verify() works with public key only - no private key required', function () {
    // Full provider to sign
    $signer = new EcdsaSigningProvider([
        'private_key' => ecdsaP256PrivatePem(),
        'public_key' => ecdsaP256PublicPem(),
        'key_id' => 'signer',
    ]);

    // Verify-only provider with just the public key
    $verifier = new EcdsaSigningProvider([
        'public_key' => ecdsaP256PublicPem(),
        'key_id' => 'verifier',
    ]);

    $payload = 'verify-only-test';
    $sig = $signer->sign($payload);

    expect($verifier->verify($payload, $sig))->toBeTrue();
});
