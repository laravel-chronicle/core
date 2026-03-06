<?php

use Chronicle\Export\ExportChainVerifier;
use Chronicle\Export\ExportManager;
use Chronicle\Export\ExportVerifier;
use Chronicle\Facades\Chronicle;
use Illuminate\Support\Str;

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

it('returns false for missing entries file path', function () {
    $path = storage_path('missing-export-'.Str::uuid().'/entries.ndjson');

    $result = app(ExportChainVerifier::class)->verify($path);

    expect($result)->toBeFalse();
});

it('returns false for unreadable entries file path', function () {
    $path = storage_path('chronicle-chain-unreadable-'.Str::uuid());
    mkdir($path, 0755, true);

    $file = $path.'/entries.ndjson';
    file_put_contents($file, "{}\n");

    chmod($file, 0000);

    if (is_readable($file)) {
        expect(app(ExportChainVerifier::class)->verify($path))
            ->toBeFalse();

        return;
    }

    try {
        $result = app(ExportChainVerifier::class)->verify($file);
        expect($result)->toBeFalse();
    } finally {
        chmod($file, 0644);
    }
});

it('returns false for malformed ndjson line', function () {
    $path = storage_path('chronicle-chain-test-'.Str::uuid());
    mkdir($path, 0755, true);
    file_put_contents($path.'/entries.ndjson', "{not-json\n");

    $result = app(ExportChainVerifier::class)->verify($path.'/entries.ndjson');

    expect($result)->toBeFalse();
});

it('returns false for invalid chain hash sequence', function () {
    $path = storage_path('chronicle-chain-test-'.Str::uuid());
    mkdir($path, 0755, true);

    $line1 = json_encode([
        'id' => '01A',
        'payload_hash' => str_repeat('a', 64),
        'chain_hash' => str_repeat('b', 64),
    ], JSON_UNESCAPED_SLASHES);

    file_put_contents($path.'/entries.ndjson', $line1."\n");

    $result = app(ExportChainVerifier::class)->verify($path.'/entries.ndjson');

    expect($result)->toBeFalse();
});
