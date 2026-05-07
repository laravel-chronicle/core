<?php

use Chronicle\Exports\ExportManager;
use Chronicle\Facades\Chronicle;
use Illuminate\Support\Str;

it('verifies an exported chronicle dataset', function () {
    Chronicle::record()
        ->actor('system')
        ->action('export.verify')
        ->subject(ref('ledger'))
        ->commit();

    $path = storage_path('chronicle-test-export-'.Str::uuid());

    app(ExportManager::class)->export($path);

    $this->artisan('chronicle:verify-export', [
        'path' => $path,
    ])->assertExitCode(0);
});

it('fails verify-export command when dataset is invalid', function () {
    Chronicle::record()
        ->actor('system')
        ->action('test.invalid')
        ->subject(ref('ledger'))
        ->commit();

    $path = storage_path('chronicle-test-export-invalid-'.Str::uuid());

    app(ExportManager::class)->export($path);

    file_put_contents($path.'/manifest.json', '{not-json');

    $this->artisan('chronicle:verify-export', [
        'path' => $path,
    ])
        ->expectsOutput('Verification failed.')
        ->assertExitCode(1);
});
