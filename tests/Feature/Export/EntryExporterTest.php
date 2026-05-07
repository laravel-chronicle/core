<?php

use Chronicle\Exports\EntryExporter;
use Chronicle\Facades\Chronicle;

it('exports entries to ndjson', function () {
    Chronicle::record()
        ->actor('system')
        ->action('export.test')
        ->subject(ref('ledger'))
        ->commit();

    $path = storage_path('chronicle-test.ndjson');

    $exporter = app(EntryExporter::class);

    $result = $exporter->export($path);

    expect($result->entryCount)->toBe(1)
        ->and(file_exists($path))->toBeTrue();
});

it('includes metadata and context columns in the exported entry', function () {
    Chronicle::record()
        ->actor('system')
        ->action('export.tested')
        ->subject(ref('system'))
        ->metadata(['key' => 'value'])
        ->commit();

    $path = storage_path('chronicle-export-meta-'.Str::uuid().'.ndjson');

    app(EntryExporter::class)->export($path);

    $line = trim(file_get_contents($path));
    $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

    expect($entry)->toHaveKey('metadata')
        ->and($entry)->toHaveKey('context')
        ->and($entry['metadata'])->toBe(['key' => 'value']);
});
