<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Checkpoint;
use Chronicle\Models\Entry;
use Illuminate\Support\Str;

it('fails verify command when payload hash is tampered', function () {
    Chronicle::record()
        ->actor('system')
        ->action('verify.tamper.payload')
        ->subject('ledger')
        ->commit();

    $entry = Entry::query()->firstOrFail();
    $entry->newQuery()->whereKey($entry->id)->update([
        'payload_hash' => str_repeat('0', 64),
    ]);

    $this->artisan('chronicle:verify')
        ->expectsOutput('Verifying Chronicle ledger...')
        ->expectsOutput('Integrity violation detected.')
        ->expectsOutputToContain('payload_hash_mismatch')
        ->assertExitCode(1);
});

it('fails verify command when chain hash is tampered', function () {
    Chronicle::record()
        ->actor('system')
        ->action('verify.tamper.chain')
        ->subject('ledger')
        ->commit();

    $entry = Entry::query()->firstOrFail();
    $entry->newQuery()->whereKey($entry->id)->update([
        'chain_hash' => str_repeat('f', 64),
    ]);

    $this->artisan('chronicle:verify')
        ->expectsOutput('Verifying Chronicle ledger...')
        ->expectsOutput('Integrity violation detected.')
        ->expectsOutputToContain('chain_hash_mismatch')
        ->assertExitCode(1);
});

it('fails verify command when checkpoint signature is invalid', function () {
    Chronicle::record()
        ->actor('system')
        ->action('verify.checkpoint.invalid-signature')
        ->subject('ledger')
        ->commit();

    $entry = Entry::query()->firstOrFail();

    $checkpoint = Checkpoint::query()->create([
        'id' => (string) Str::ulid(),
        'chain_hash' => $entry->chain_hash,
        'signature' => base64_encode(str_repeat('x', 64)),
        'algorithm' => 'ed25519',
        'key_id' => 'bad-key',
        'created_at' => now(),
    ]);

    $entry->newQuery()->whereKey($entry->id)->update([
        'checkpoint_id' => $checkpoint->id,
    ]);

    $this->artisan('chronicle:verify')
        ->expectsOutput('Verifying Chronicle ledger...')
        ->expectsOutput('Integrity violation detected.')
        ->expectsOutputToContain('checkpoint_signature_invalid')
        ->assertExitCode(1);
});
