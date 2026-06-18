<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('exits successfully', function () {
    $this->artisan('chronicle:key:generate')->assertSuccessful();
});

it('outputs a private key whose base64 decodes to SODIUM_CRYPTO_SIGN_SECRETKEYBYTES bytes', function () {
    Artisan::call('chronicle:key:generate');
    $output = Artisan::output();

    preg_match('/Private key \(base64\):\s+(\S+)/', $output, $matches);
    expect($matches)->toHaveKey(1);

    $decoded = base64_decode($matches[1], true);
    expect($decoded)->toBeString()
        ->and(strlen((string) $decoded))->toBe(SODIUM_CRYPTO_SIGN_SECRETKEYBYTES); // 64
});

it('outputs a public key whose base64 decodes to SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES bytes', function () {
    Artisan::call('chronicle:key:generate');
    $output = Artisan::output();

    preg_match('/Public key  \(base64\):\s+(\S+)/', $output, $matches);
    expect($matches)->toHaveKey(1);

    $decoded = base64_decode($matches[1], true);
    expect($decoded)->toBeString()
        ->and(strlen((string) $decoded))->toBe(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES); // 32
});

it('includes the --id value in the output', function () {
    $this->artisan('chronicle:key:generate', ['--id' => 'my-prod-key'])
        ->expectsOutputToContain('my-prod-key')
        ->assertSuccessful();
});

it('includes a SECURITY warning about storing the private key', function () {
    $this->artisan('chronicle:key:generate')
        ->expectsOutputToContain('SECURITY')
        ->assertSuccessful();
});

it('includes a paste-ready signing.keys config snippet when --id is given', function () {
    Artisan::call('chronicle:key:generate', ['--id' => 'test-key']);
    $output = Artisan::output();

    expect($output)
        ->toContain("'test-key'")
        ->toContain("'provider'")
        ->toContain("'algorithm'")
        ->toContain("'ed25519'")
        ->toContain("'private_key'")
        ->toContain("'public_key'");
});
