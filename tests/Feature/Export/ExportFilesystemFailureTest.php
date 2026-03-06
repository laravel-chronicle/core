<?php

use Chronicle\Exceptions\ExportWriteException;
use Chronicle\Export\ExportManager;
use Chronicle\Export\ExportManifestBuilder;
use Chronicle\Export\ExportSigner;
use Chronicle\Facades\Chronicle;
use Illuminate\Support\Str;

it('fails export when target path collides with an existing file', function () {
    Chronicle::record()
        ->actor('system')
        ->action('fs.collision')
        ->subject('ledger')
        ->commit();

    $path = storage_path('chronicle-export-collision-'.Str::uuid());
    file_put_contents($path, 'not a directory');

    expect(fn () => app(ExportManager::class)->export($path))
        ->toThrow(ExportWriteException::class, 'Unable to create export directory');
});

it('fails manifest write when output path is a directory', function () {
    $dir = storage_path('chronicle-manifest-dir-'.Str::uuid());
    mkdir($dir, 0755, true);

    expect(fn () => app(ExportManifestBuilder::class)->write($dir, ['ok' => true]))
        ->toThrow(ExportWriteException::class, 'Unable to write export file');
});

it('fails signature write when output path is a directory', function () {
    $dir = storage_path('chronicle-signature-dir-'.Str::uuid());
    mkdir($dir, 0755, true);

    expect(fn () => app(ExportSigner::class)->write($dir, ['ok' => true]))
        ->toThrow(ExportWriteException::class, 'Unable to write export file');
});

it('surfaces filesystem failure from chronicle export command', function () {
    Chronicle::record()
        ->actor('system')
        ->action('fs.command')
        ->subject('ledger')
        ->commit();

    $path = storage_path('chronicle-export-command-collision-'.Str::uuid());
    file_put_contents($path, 'not a directory');

    $this->artisan('chronicle:export', ['path' => $path])
        ->expectsOutput('Export failed.')
        ->expectsOutputToContain('Unable to create export directory')
        ->assertExitCode(1);
});
