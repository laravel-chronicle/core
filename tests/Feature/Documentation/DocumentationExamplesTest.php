<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;
use Illuminate\Support\Str;

it('records an entry using documented fluent API', function () {
    Chronicle::record()
        ->actor('docs-user')
        ->action('invoice.created')
        ->subject('invoice:1001')
        ->metadata(['total' => 1000])
        ->context(['request_id' => (string) Str::uuid()])
        ->tags(['billing'])
        ->commit();

    $entry = Entry::first();

    expect($entry)->not->toBeNull()
        ->and($entry->action)->toBe('invoice.created')
        ->and($entry->tags)->toContain('billing');
});

it('records a diff using documented API', function () {
    Chronicle::record()
        ->actor('docs-admin')
        ->action('invoice.amount_changed')
        ->subject('invoice:1002')
        ->diff([
            'amount' => ['old' => 1000, 'new' => 500],
        ])
        ->commit();

    $entry = Entry::first();

    expect($entry)->not->toBeNull()
        ->and($entry->diff)->toHaveKey('amount')
        ->and($entry->diff['amount']['old'])->toBe(1000)
        ->and($entry->diff['amount']['new'])->toBe(500);
});

it('supports documented transaction closure example', function () {
    Chronicle::transaction(function () {
        Chronicle::record()
            ->actor('system')
            ->action('batch.started')
            ->subject('ledger')
            ->commit();

        Chronicle::record()
            ->actor('system')
            ->action('batch.finished')
            ->subject('ledger')
            ->commit();
    });

    $entries = Entry::orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->correlation_id)->toBe($entries[1]->correlation_id);
});

it('supports documented transaction object example', function () {
    $tx = Chronicle::transaction();

    $tx->entry()
        ->actor('system')
        ->action('import.started')
        ->subject('ledger')
        ->commit();

    $tx->entry()
        ->actor('system')
        ->action('import.finished')
        ->subject('ledger')
        ->commit();

    $entries = Entry::orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->correlation_id)->toBe($tx->id())
        ->and($entries[1]->correlation_id)->toBe($tx->id());
});

it('supports documented export and verify-export commands', function () {
    Chronicle::record()
        ->actor('system')
        ->action('docs.export')
        ->subject('ledger')
        ->commit();

    $path = storage_path('chronicle-docs-export-'.Str::uuid());

    $this->artisan('chronicle:export', [
        'path' => $path,
    ])->assertExitCode(0);

    $this->artisan('chronicle:verify-export', [
        'path' => $path,
    ])->assertExitCode(0);
});
