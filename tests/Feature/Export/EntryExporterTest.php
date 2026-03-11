<?php

use Chronicle\Exports\EntryExporter;
use Chronicle\Facades\Chronicle;

it('exports entries to ndjson', function () {
    Chronicle::record()
        ->actor('system')
        ->action('export.test')
        ->subject('ledger')
        ->commit();

    $path = storage_path('chronicle-test.ndjson');

    $exporter = app(EntryExporter::class);

    $result = $exporter->export($path);

    expect($result->entryCount)->toBe(1)
        ->and(file_exists($path))->toBeTrue();
});
