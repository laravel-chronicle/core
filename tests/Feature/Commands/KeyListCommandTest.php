<?php

use Chronicle\Signing\Ed25519SigningProvider;

it('exits successfully', function () {
    $this->artisan('chronicle:key:list')->assertSuccessful();
});

it('shows the active key ID in the output', function () {
    $this->artisan('chronicle:key:list')
        ->expectsOutputToContain('chronicle-dev-key')
        ->assertSuccessful();
});

it('marks the active key with ACTIVE', function () {
    $this->artisan('chronicle:key:list')
        ->expectsOutputToContain('ACTIVE')
        ->assertSuccessful();
});

it('shows the algorithm column', function () {
    $this->artisan('chronicle:key:list')
        ->expectsOutputToContain('ed25519')
        ->assertSuccessful();
});

it('marks a verify-only key as verify-only', function () {
    config([
        'chronicle.signing.keys.retired-key' => [
            'provider' => Ed25519SigningProvider::class,
            'algorithm' => 'ed25519',
            'public_key' => config('chronicle.signing.keys.chronicle-dev-key.public_key'),
            // no private_key → verify-only
        ],
    ]);

    $this->artisan('chronicle:key:list')
        ->expectsOutputToContain('verify-only')
        ->assertSuccessful();
});

it('shows Checkpoints column header with --with-counts', function () {
    $this->artisan('chronicle:key:list', ['--with-counts' => true])
        ->expectsOutputToContain('Checkpoints')
        ->assertSuccessful();
});
