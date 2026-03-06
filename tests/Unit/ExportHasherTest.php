<?php

use Chronicle\Export\ExportHasher;

it('computes a sha256 hash for a file', function () {
    $path = storage_path('test-hash.txt');

    file_put_contents($path, 'chronicle');

    $hasher = new ExportHasher;

    $hash = $hasher->hashFile($path);

    expect($hash)->toBe(hash('sha256', 'chronicle'));
});
