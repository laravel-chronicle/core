<?php

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Facades\Chronicle;
use Chronicle\Signing\Ed25519SigningProvider;

it('refuses with an actionable message when the target key is not in the ring', function () {
    $this->artisan('chronicle:key:rotate', ['newKeyId' => 'nonexistent-key'])
        ->expectsOutputToContain('not configured')
        ->assertFailed();
});

it('refuses when the target key is already the active key', function () {
    $this->artisan('chronicle:key:rotate', ['newKeyId' => 'chronicle-dev-key'])
        ->expectsOutputToContain('already the active key')
        ->assertFailed();
});

it('refuses when the target key is verify-only', function () {
    config([
        'chronicle.signing.keys.verify-only-key' => [
            'provider' => Ed25519SigningProvider::class,
            'algorithm' => 'ed25519',
            'public_key' => config('chronicle.signing.keys.chronicle-dev-key.public_key'),
            // no private_key → verify-only
        ],
    ]);

    $this->artisan('chronicle:key:rotate', ['newKeyId' => 'verify-only-key'])
        ->expectsOutputToContain('verify-only')
        ->assertFailed();
});

it('fails with an actionable message when the ledger is empty', function () {
    // Valid second key with signing material — passes config validation, fails at checkpoint
    config([
        'chronicle.signing.keys.next-key' => [
            'provider' => Ed25519SigningProvider::class,
            'algorithm' => 'ed25519',
            'private_key' => config('chronicle.signing.keys.chronicle-dev-key.private_key'),
            'public_key' => config('chronicle.signing.keys.chronicle-dev-key.public_key'),
        ],
    ]);

    $this->artisan('chronicle:key:rotate', ['newKeyId' => 'next-key'])
        ->expectsOutputToContain('ledger is empty')
        ->assertFailed();
});

it('creates exactly one boundary checkpoint on success', function () {
    $this->useEloquentDriver();

    config([
        'chronicle.signing.keys.next-key' => [
            'provider' => Ed25519SigningProvider::class,
            'algorithm' => 'ed25519',
            'private_key' => config('chronicle.signing.keys.chronicle-dev-key.private_key'),
            'public_key' => config('chronicle.signing.keys.chronicle-dev-key.public_key'),
        ],
    ]);

    Chronicle::record()
        ->actor('system')
        ->action('rotation.test')
        ->subject(ref('ledger'))
        ->commit();

    $this->artisan('chronicle:key:rotate', ['newKeyId' => 'next-key'])
        ->assertSuccessful();

    expect(Checkpoint::count())->toBe(1);
});

it('prints the CHRONICLE_ACTIVE_KEY instruction on success', function () {
    $this->useEloquentDriver();

    config([
        'chronicle.signing.keys.next-key' => [
            'provider' => Ed25519SigningProvider::class,
            'algorithm' => 'ed25519',
            'private_key' => config('chronicle.signing.keys.chronicle-dev-key.private_key'),
            'public_key' => config('chronicle.signing.keys.chronicle-dev-key.public_key'),
        ],
    ]);

    Chronicle::record()
        ->actor('system')
        ->action('rotation.test')
        ->subject(ref('ledger'))
        ->commit();

    $this->artisan('chronicle:key:rotate', ['newKeyId' => 'next-key'])
        ->expectsOutputToContain('CHRONICLE_ACTIVE_KEY=next-key')
        ->assertSuccessful();
});
