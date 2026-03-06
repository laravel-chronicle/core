<?php

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
