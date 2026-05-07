<?php

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;

it('verifies a single entry successfully via --entry option', function () {
    Chronicle::record()->actor('system')->action('cmd.test')->subject(ref('ledger'))->commit();

    $entry = Entry::first();

    $this->artisan('chronicle:verify', ['--entry' => $entry->id])
        ->expectsOutputToContain($entry->id)
        ->expectsOutputToContain('cmd.test')
        ->expectsOutputToContain('Payload hash OK')
        ->expectsOutputToContain('Chain hash OK')
        ->expectsOutputToContain('Entry integrity verified')
        ->assertExitCode(0);
});

it('returns exit code 1 when entry is not found', function () {
    $this->artisan('chronicle:verify', ['--entry' => '01FAKEULIDXXXXXXXXX'])
        ->expectsOutputToContain('not found')
        ->assertExitCode(1);
});

it('detects a tampered payload via --entry', function () {
    Chronicle::record()->actor('system')->action('cmd.tamper')->subject(ref('ledger'))->commit();

    $entry = Entry::first();
    $entry->newQuery()->whereKey($entry->id)->update([
        'payload' => json_encode(['tampered' => true]),
    ]);

    $this->artisan('chronicle:verify', ['--entry' => $entry->id])
        ->expectsOutputToContain('payload_hash_mismatch')
        ->expectsOutputToContain('Integrity violation detected')
        ->assertExitCode(1);
});

it('detects a tampered chain hash via --entry', function () {
    Chronicle::record()->actor('system')->action('cmd.chain')->subject(ref('ledger'))->commit();

    $entry = Entry::first();
    $entry->newQuery()->whereKey($entry->id)->update([
        'chain_hash' => str_repeat('f', 64),
    ]);

    $this->artisan('chronicle:verify', ['--entry' => $entry->id])
        ->expectsOutputToContain('chain_hash_mismatch')
        ->expectsOutputToContain('Integrity violation detected')
        ->assertExitCode(1);
});

it('runs full ledger verification when --entry is not provided', function () {
    Chronicle::record()->actor('system')->action('cmd.full')->subject(ref('ledger'))->commit();

    $this->artisan('chronicle:verify')
        ->expectsOutput('Verifying Chronicle ledger...')
        ->expectsOutput('Ledger integrity OK')
        ->assertExitCode(0);
});
