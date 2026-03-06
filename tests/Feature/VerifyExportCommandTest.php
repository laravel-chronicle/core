<?php

use Chronicle\Export\ExportManager;
use Chronicle\Facades\Chronicle;

it('verifies an exported chronicle dataset', function () {
    Chronicle::record()
        ->actor('system')
        ->action('test')
        ->subject('ledger')
        ->commit();

    $path = storage_path('chronicle-test-export');

    app(ExportManager::class)->export($path);

    $this->artisan('chronicle:verify-export', [
        'path' => $path,
    ])->assertExitCode(0);
});
