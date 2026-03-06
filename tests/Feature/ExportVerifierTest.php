<?php

use Chronicle\Export\ExportManager;
use Chronicle\Export\ExportVerifier;

it('verifies a valid export dataset', function () {
    $manager = app(ExportManager::class);

    $path = storage_path('chronicle-test-export');

    $manager->export($path);

    $verifier = app(ExportVerifier::class);

    $result = $verifier->verify($path);

    expect($result->isValid())->toBeTrue();
});
