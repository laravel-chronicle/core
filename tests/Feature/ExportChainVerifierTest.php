<?php

use Chronicle\Export\ExportManager;
use Chronicle\Export\ExportVerifier;
use Chronicle\Facades\Chronicle;

it('verifies chain integrity of exported dataset', function () {
    Chronicle::record()
        ->actor('system')
        ->action('a')
        ->subject('ledger')
        ->commit();

    Chronicle::record()
        ->actor('system')
        ->action('b')
        ->subject('ledger')
        ->commit();

    $path = storage_path('chronicle-chain-test');

    app(ExportManager::class)->export($path);

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeTrue();
});
