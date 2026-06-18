<?php

declare(strict_types=1);

use Chronicle\Facades\Chronicle;
use Illuminate\Support\Facades\Artisan;

it('outputs empty message and exits SUCCESS on an empty ledger', function () {
    $this->artisan('chronicle:stats')
        ->expectsOutputToContain('Chronicle ledger is empty')
        ->assertExitCode(0);
});

it('outputs the header and key sections for a non-empty ledger', function () {
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('invoice.sent')->subject(ref('ledger'))->commit();

    $this->artisan('chronicle:stats')
        ->expectsOutputToContain('Chronicle Ledger Stats')
        ->expectsOutputToContain('Total entries:')
        ->expectsOutputToContain('Top Actions')
        ->expectsOutputToContain('Activity (last 30 days)')
        ->assertExitCode(0);
});

it('output includes the top action name', function () {
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('invoice.sent')->subject(ref('ledger'))->commit();

    $this->artisan('chronicle:stats')
        ->expectsOutputToContain('order.created')
        ->assertExitCode(0);
});

it('--json flag outputs valid JSON containing all expected keys', function () {
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();

    // PendingCommand's Mockery mock only triggers one expectation per doWrite call,
    // so multi-key checks on a single $this->line() output require Artisan::call().
    Artisan::call('chronicle:stats', ['--json' => true]);
    $output = Artisan::output();

    expect($output)
        ->toContain('"total_entries"')
        ->toContain('"checkpoint_count"')
        ->toContain('"top_actions"')
        ->toContain('"daily_activity"');
});

it('--json total_entries matches the actual entry count', function () {
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('order.created')->subject(ref('ledger'))->commit();

    $this->artisan('chronicle:stats', ['--json' => true])
        ->expectsOutputToContain('"total_entries": 3')
        ->assertExitCode(0);
});

it('exits SUCCESS in all normal cases', function () {
    $this->artisan('chronicle:stats')->assertExitCode(0);

    Chronicle::record()->actor('system')->action('test.entry')->subject(ref('ledger'))->commit();

    $this->artisan('chronicle:stats')->assertExitCode(0);
    $this->artisan('chronicle:stats', ['--json' => true])->assertExitCode(0);
});
