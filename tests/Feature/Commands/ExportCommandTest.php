<?php

use Chronicle\Export\ExportManager;
use Chronicle\Facades\Chronicle;

it('exports chronicle dataset via artisan command', function () {
    Chronicle::record()
        ->actor('system')
        ->action('test')
        ->subject('ledger')
        ->commit();

    $path = storage_path('chronicle-test-export');

    $this->artisan('chronicle:export', [
        'path' => $path,
    ])
        ->assertExitCode(0);

    expect(file_exists($path.'/entries.ndjson'))->toBeTrue()
        ->and(file_exists($path.'/manifest.json'))->toBeTrue()
        ->and(file_exists($path.'/signature.json'))->toBeTrue();
});

it('handles export manager exceptions at command level', function () {
    $exports = Mockery::mock(ExportManager::class);
    $exports->shouldReceive('export')
        ->once()
        ->andThrow(new \RuntimeException('simulated export failure'));

    app()->instance(ExportManager::class, $exports);

    $this->artisan('chronicle:export', [
        'path' => storage_path('chronicle-test-export-failing'),
    ])
        ->expectsOutput('Export failed.')
        ->expectsOutputToContain('simulated export failure')
        ->assertExitCode(1);
});
