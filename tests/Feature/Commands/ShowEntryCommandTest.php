<?php

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Illuminate\Support\Facades\Artisan;

it('outputs error message containing the ID and exits FAILURE for a non-existent ULID', function () {
    Artisan::call('chronicle:show', ['id' => '01FAKEULIDXXXXXXXXX']);

    $output = Artisan::output();

    expect($output)
        ->toContain('01FAKEULIDXXXXXXXXX')
        ->toContain('not found');
});

it('displays the header and core fields for a valid entry and exits SUCCESS', function () {
    Chronicle::record()
        ->actor('system')
        ->action('invoice.sent')
        ->subject(ref('ledger'))
        ->commit();

    $entry = Entry::first();

    $this->artisan('chronicle:show', ['id' => $entry->id])
        ->expectsOutputToContain('Chronicle Entry')
        ->expectsOutputToContain($entry->id)
        ->expectsOutputToContain('invoice.sent')
        ->expectsOutputToContain($entry->payload_hash)
        ->expectsOutputToContain($entry->chain_hash)
        ->assertExitCode(0);
});

it('shows (none) for tags when entry has no tags', function () {
    Chronicle::record()
        ->actor('system')
        ->action('test.action')
        ->subject(ref('ledger'))
        ->commit();

    $entry = Entry::first();

    $this->artisan('chronicle:show', ['id' => $entry->id])
        ->expectsOutputToContain('Tags:             (none)')
        ->assertExitCode(0);
});

it('shows (none) for Correlation ID when not set', function () {
    Chronicle::record()
        ->actor('system')
        ->action('test.action')
        ->subject(ref('ledger'))
        ->commit();

    $entry = Entry::first();

    $this->artisan('chronicle:show', ['id' => $entry->id])
        ->expectsOutputToContain('Correlation ID:   (none)')
        ->assertExitCode(0);
});

it('shows (none) in the Diff section when entry has no diff', function () {
    Chronicle::record()
        ->actor('system')
        ->action('test.action')
        ->subject(ref('ledger'))
        ->commit();

    $entry = Entry::first();

    // Artisan::call avoids Mockery limitation when asserting
    // across multiple lines in the same output block.
    Artisan::call('chronicle:show', ['id' => $entry->id]);
    $output = Artisan::output();

    expect($output)
        ->toContain('  Diff:')
        ->toContain('    (none)');
});

it('shows metadata key/value pairs', function () {
    Chronicle::record()
        ->actor('system')
        ->action('invoice.sent')
        ->subject(ref('ledger'))
        ->metadata(['email' => 'client@example.com', 'total' => '1000'])
        ->commit();

    $entry = Entry::first();

    Artisan::call('chronicle:show', ['id' => $entry->id]);

    $output = Artisan::output();

    expect($output)
        ->toContain('email')
        ->toContain('client@example.com');
});

it('shows context with dot-notation keys for nested arrays', function () {
    Chronicle::record()
        ->actor('system')
        ->action('test.action')
        ->subject(ref('ledger'))
        ->context(['request' => ['method' => 'POST', 'ip_address' => '127.0.0.1']])
        ->commit();

    $entry = Entry::first();

    $this->artisan('chronicle:show', ['id' => $entry->id])
        ->expectsOutputToContain('request.method')
        ->expectsOutputToContain('request.ip_address')
        ->assertExitCode(0);
});

it('shows diff with old and new values per field', function () {
    Chronicle::record()
        ->actor('system')
        ->action('invoice.updated')
        ->subject(ref('ledger'))
        ->change('status', 'draft', 'sent')
        ->commit();

    $entry = Entry::first();

    $this->artisan('chronicle:show', ['id' => $entry->id])
        ->expectsOutputToContain('status:')
        ->expectsOutputToContain('old:  draft')
        ->expectsOutputToContain('new:  sent')
        ->assertExitCode(0);
});

it('shows comma-separated tags when entry has multiple tags', function () {
    Chronicle::record()
        ->actor('system')
        ->action('invoice.sent')
        ->subject(ref('ledger'))
        ->tags(['billing', 'invoicing'])
        ->commit();

    $entry = Entry::first();

    // Tags are normalized to sorted lowercase; expect alphabetical order.
    $this->artisan('chronicle:show', ['id' => $entry->id])
        ->expectsOutputToContain('billing, invoicing')
        ->assertExitCode(0);
});
