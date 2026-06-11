<?php

use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Contracts\SigningProvider;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Signing\Ed25519SigningProvider;
use Chronicle\Signing\KeyRing;
use Chronicle\Verification\IntegrityVerifier;
use Chronicle\Verification\VerificationFailure;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------------
// Rotation roundtrip: checkpoints under two keys must verify end-to-end
// ---------------------------------------------------------------------------

it('verifies a ledger whose checkpoints span two different keys', function () {
    $kp2 = sodium_crypto_sign_keypair();
    $key2Private = base64_encode(sodium_crypto_sign_secretkey($kp2));
    $key2Public = base64_encode(sodium_crypto_sign_publickey($kp2));

    $twoKeyConfig = [
        'enforce_on_boot' => false,
        'active' => 'chronicle-dev-key',
        'keys' => [
            'chronicle-dev-key' => [
                'provider' => Ed25519SigningProvider::class,
                'algorithm' => 'ed25519',
                'private_key' => 'RcSfC2MuYTPnkrL/MIA4/l/sAjirGXXIFXZEPokdwh1Lcz+SvNE7bjvgCsDotjnlHfJyZ4XW/kUXemtoyaa92Q==',
                'public_key' => 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=',
            ],
            'key-2' => [
                'provider' => Ed25519SigningProvider::class,
                'algorithm' => 'ed25519',
                'private_key' => $key2Private,
                'public_key' => $key2Public,
            ],
        ],
    ];

    // Phase 1: chronicle-dev-key active - write entry + checkpoint
    config()->set('chronicle.signing', $twoKeyConfig);
    app()->forgetInstance(KeyRing::class);
    app()->forgetInstance(SigningProvider::class);
    Chronicle::record()->actor('system')->action('before.rotation')->subject(ref('ledger'))->commit();
    $checkpoint1 = app(CheckpointCreator::class)->create();
    // checkpoint_id is not set by the pipeline - anchor the entry manually
    $entry1 = Entry::query()->latest('id')->first();
    $entry1->newQuery()->whereKey($entry1->id)->update(['checkpoint_id' => $checkpoint1->id]);

    // Phase 2: switch active to key-2 - write entry + checkpoint
    config()->set('chronicle.signing.active', 'key-2');
    app()->forgetInstance(KeyRing::class);
    app()->forgetInstance(SigningProvider::class);
    Chronicle::record()->actor('system')->action('after.rotation')->subject(ref('ledger'))->commit();
    $checkpoint2 = app(CheckpointCreator::class)->create();
    $entry2 = Entry::query()->latest('id')->first();
    $entry2->newQuery()->whereKey($entry2->id)->update(['checkpoint_id' => $checkpoint2->id]);

    // Phase 3: ring has both keys; verify the full ledger
    // (KeyRing was already forgotten in phase 2; fresh ring resolves from current config)
    $result = app(IntegrityVerifier::class)->verify();

    expect($result->isValid())->toBeTrue();
});

// ---------------------------------------------------------------------------
// A tampered checkpoint signature must yield CheckpointSignatureInvalid (not UnknownKey)
// ---------------------------------------------------------------------------

it('returns checkpoint_signature_invalid when a checkpoint signature is tampered', function () {
    Chronicle::record()->actor('system')->action('tamper.sig')->subject(ref('ledger'))->commit();
    $checkpoint = app(CheckpointCreator::class)->create();
    $entry = Entry::query()->latest('id')->first();
    $entry->newQuery()->whereKey($entry->id)->update(['checkpoint_id' => $checkpoint->id]);

    DB::table('chronicle_checkpoints')
        ->where('id', $checkpoint->id)
        ->update(['signature' => base64_encode(str_repeat('x', 64))]);

    $result = app(IntegrityVerifier::class)->verify();

    expect($result->isValid())->toBeFalse()
        ->and($result->failureType())->toBe(VerificationFailure::CheckpointSignatureInvalid->value);
});

// ---------------------------------------------------------------------------
// A checkpoint signed by a now-verify-only key (private key removed) still verifies
// ---------------------------------------------------------------------------

it('verifies a ledger whose checkpoint was signed by a key that is now verify-only', function () {
    // Create entry + checkpoint while chronicle-dev-key has both private and public keys
    Chronicle::record()->actor('system')->action('verify.only')->subject(ref('ledger'))->commit();
    $checkpoint = app(CheckpointCreator::class)->create();
    $entry = Entry::query()->latest('id')->first();
    $entry->newQuery()->whereKey($entry->id)->update(['checkpoint_id' => $checkpoint->id]);

    // Remove the private key - retain only the public key (verify-only scenario)
    config()->set('chronicle.signing.keys.chronicle-dev-key.private_key');
    app()->forgetInstance(KeyRing::class);
    app()->forgetInstance(SigningProvider::class);

    $result = app(IntegrityVerifier::class)->verify();

    expect($result->isValid())->toBeTrue();
});

// ---------------------------------------------------------------------------
// A checkpoint signed by a key no longer in the ring must yield UnknownKey
// ---------------------------------------------------------------------------

it('returns unknown_key when the checkpoint algorithm+key_id has no ring entry', function () {
    Chronicle::record()->actor('system')->action('orphan.sig')->subject(ref('ledger'))->commit();
    $checkpoint = app(CheckpointCreator::class)->create(); // signed by chronicle-dev-key
    $entry = Entry::query()->latest('id')->first();
    $entry->newQuery()->whereKey($entry->id)->update(['checkpoint_id' => $checkpoint->id]);

    // Reconfigure ring without chronicle-dev-key
    $kp = sodium_crypto_sign_keypair();
    config()->set('chronicle.signing', [
        'enforce_on_boot' => false,
        'active' => 'unrelated-key',
        'keys' => [
            'unrelated-key' => [
                'provider' => Ed25519SigningProvider::class,
                'algorithm' => 'ed25519',
                'private_key' => base64_encode(sodium_crypto_sign_secretkey($kp)),
                'public_key' => base64_encode(sodium_crypto_sign_publickey($kp)),
            ],
        ],
    ]);
    app()->forgetInstance(KeyRing::class);
    app()->forgetInstance(SigningProvider::class);

    $result = app(IntegrityVerifier::class)->verify();

    expect($result->isValid())->toBeFalse()
        ->and($result->failureType())->toBe(VerificationFailure::UnknownKey->value);
});
