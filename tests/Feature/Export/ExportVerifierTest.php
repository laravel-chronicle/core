<?php

use Chronicle\Exports\ExportManager;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\ExportVerifier;
use Illuminate\Support\Str;

function minimalExportManifestForEmptyDataset(): array
{
    return [
        'version' => '1.0',
        'generated_at' => now()->toIso8601String(),
        'entry_count' => 0,
        'first_entry_id' => null,
        'last_entry_id' => null,
        'chain_head' => null,
        'dataset_hash' => hash('sha256', ''),
        'algorithm' => 'ed25519',
    ];
}

function minimalExportSignature(): array
{
    return [
        'signature' => 'non-empty-signature',
        'algorithm' => 'ed25519',
        'key_id' => 'chronicle-dev-key',
    ];
}

it('verifies a valid export dataset', function () {
    $manager = app(ExportManager::class);

    $path = storage_path('chronicle-test-export-'.Str::uuid());

    $manager->export($path);

    $verifier = app(ExportVerifier::class);

    $result = $verifier->verify($path);

    expect($result->isValid())->toBeTrue();
});

it('fails when manifest json is invalid', function () {
    $manager = app(ExportManager::class);
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    $manager->export($path);

    file_put_contents($path.'/manifest.json', '{not-json');

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('manifest_invalid_json');
});

it('fails when manifest shape is invalid', function () {
    $manager = app(ExportManager::class);
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    $manager->export($path);

    file_put_contents($path.'/manifest.json', json_encode(['dataset_hash' => 'abc']));

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('manifest_invalid');
});

it('fails when signature shape is invalid', function () {
    $manager = app(ExportManager::class);
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    $manager->export($path);

    file_put_contents($path.'/signature.json', json_encode(['algorithm' => 'ed25519']));

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('signature_invalid_format');
});

it('fails when entries ndjson is malformed', function () {
    $manager = app(ExportManager::class);
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    $manager->export($path);

    file_put_contents($path.'/entries.ndjson', "{bad-line\n");

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('entries_invalid_json');
});

it('fails when entries ndjson has invalid entry shape', function () {
    $manager = app(ExportManager::class);
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    $manager->export($path);

    file_put_contents($path.'/entries.ndjson', json_encode(['payload_hash' => 'x'])."\n");

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('entries_invalid_format');
});

it('fails when chain in entries ndjson is invalid', function () {
    Chronicle::record()->actor('system')->action('chain.check')->subject(ref('ledger'))->commit();

    $manager = app(ExportManager::class);
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    $manager->export($path);

    $lines = file($path.'/entries.ndjson', FILE_IGNORE_NEW_LINES);
    expect($lines)->toBeArray()->not->toBeEmpty();

    $first = json_decode((string) $lines[0], true);
    expect($first)->toBeArray();
    $first['payload_hash'] = str_repeat('0', 64);
    $lines[0] = (string) json_encode($first, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    file_put_contents($path.'/entries.ndjson', implode("\n", $lines)."\n");

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('chain_invalid');
});

it('fails when manifest chain head does not match entries', function () {
    Chronicle::record()->actor('system')->action('head.check')->subject(ref('ledger'))->commit();

    $manager = app(ExportManager::class);
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    $manager->export($path);

    $manifest = json_decode((string) file_get_contents($path.'/manifest.json'), true);
    expect($manifest)->toBeArray();
    $manifest['chain_head'] = str_repeat('f', 64);
    file_put_contents($path.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('chain_head_mismatch');
});

it('fails when entries file is missing', function () {
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    mkdir($path, 0755, true);
    file_put_contents($path.'/manifest.json', '{}');
    file_put_contents($path.'/signature.json', '{}');

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('entries_missing');
});

it('fails when manifest file is missing', function () {
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    mkdir($path, 0755, true);
    file_put_contents($path.'/entries.ndjson', '');
    file_put_contents($path.'/signature.json', '{}');

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('manifest_missing');
});

it('fails when signature file is missing', function () {
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    mkdir($path, 0755, true);
    file_put_contents($path.'/entries.ndjson', '');
    file_put_contents($path.'/manifest.json', '{}');

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('signature_missing');
});

it('fails when manifest file is unreadable', function () {
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    mkdir($path, 0755, true);
    file_put_contents($path.'/entries.ndjson', '');
    mkdir($path.'/manifest.json', 0755, true);
    file_put_contents($path.'/signature.json', json_encode(minimalExportSignature()));

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('manifest_unreadable');
});

it('fails when signature file is unreadable', function () {
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    mkdir($path, 0755, true);
    file_put_contents($path.'/entries.ndjson', '');
    file_put_contents($path.'/manifest.json', json_encode(minimalExportManifestForEmptyDataset()));
    mkdir($path.'/signature.json', 0755, true);

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('signature_unreadable');
});

it('fails when entries file is unreadable', function () {
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    mkdir($path, 0755, true);
    mkdir($path.'/entries.ndjson', 0755, true);
    file_put_contents($path.'/manifest.json', json_encode(minimalExportManifestForEmptyDataset()));
    file_put_contents($path.'/signature.json', json_encode(minimalExportSignature()));

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('entries_unreadable');
});

it('fails when dataset hash mismatches manifest', function () {
    Chronicle::record()->actor('system')->action('hash.check')->subject(ref('ledger'))->commit();

    $manager = app(ExportManager::class);
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    $manager->export($path);

    $lines = file($path.'/entries.ndjson', FILE_IGNORE_NEW_LINES);
    expect($lines)->toBeArray()->not->toBeEmpty();

    $first = json_decode((string) $lines[0], true);
    expect($first)->toBeArray();
    $first['action'] = 'tampered.action';
    $lines[0] = (string) json_encode($first, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    file_put_contents($path.'/entries.ndjson', implode("\n", $lines)."\n");

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('dataset_hash_mismatch');
});

it('fails when signature verification is invalid', function () {
    Chronicle::record()->actor('system')->action('signature.check')->subject(ref('ledger'))->commit();

    $manager = app(ExportManager::class);
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    $manager->export($path);

    $signature = json_decode((string) file_get_contents($path.'/signature.json'), true);
    expect($signature)->toBeArray();
    $signature['signature'] = base64_encode(str_repeat('x', 64));
    file_put_contents($path.'/signature.json', json_encode($signature, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('signature_invalid');
});

it('fails when manifest entry count mismatches dataset', function () {
    Chronicle::record()->actor('system')->action('count.check')->subject(ref('ledger'))->commit();

    $manager = app(ExportManager::class);
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    $manager->export($path);

    $manifest = json_decode((string) file_get_contents($path.'/manifest.json'), true);
    expect($manifest)->toBeArray();
    $manifest['entry_count'] = ((int) $manifest['entry_count']) + 1;
    file_put_contents($path.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('entry_count_mismatch');
});

it('fails when manifest first entry id mismatches dataset', function () {
    Chronicle::record()->actor('system')->action('first.check')->subject(ref('ledger'))->commit();

    $manager = app(ExportManager::class);
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    $manager->export($path);

    $manifest = json_decode((string) file_get_contents($path.'/manifest.json'), true);
    expect($manifest)->toBeArray();
    $manifest['first_entry_id'] = '01AAAAAAAAAAAAAAAAAAAAAAAA';
    file_put_contents($path.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('first_entry_mismatch');
});

it('fails when manifest last entry id mismatches dataset', function () {
    Chronicle::record()->actor('system')->action('last.check')->subject(ref('ledger'))->commit();

    $manager = app(ExportManager::class);
    $path = storage_path('chronicle-test-export-'.Str::uuid());
    $manager->export($path);

    $manifest = json_decode((string) file_get_contents($path.'/manifest.json'), true);
    expect($manifest)->toBeArray();
    $manifest['last_entry_id'] = '01BBBBBBBBBBBBBBBBBBBBBBBB';
    file_put_contents($path.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('last_entry_mismatch');
});

it('fails when an exported entry payload has been tampered with', function () {
    $manager = app(ExportManager::class);
    $path = storage_path('chronicle-test-export-'.Str::uuid());

    Chronicle::record()
        ->actor('system')
        ->action('test.tamper')
        ->subject(ref('system'))
        ->commit();

    $manager->export($path);

    // Read the entry file and tamper with the payload field only,
    // leaving payload_hash unchanged - the old verifier would miss this.
    $lines = file($path.'/entries.ndjson', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $entry = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
    $entry['payload']['action'] = 'tampered.action';  // mutate payload
    // payload_hash is left unchanged -> integrity gap
    $lines[0] = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    file_put_contents($path.'/entries.ndjson', implode("\n", $lines)."\n");

    $result = app(ExportVerifier::class)->verify($path);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('payload_hash_mismatch');
});

it('verifies an export whose entries file has a trailing blank line', function () {
    // Create a normal export, then append a trailing newline
    Chronicle::record()->actor('system')->action('export.blank')->subject(ref('ledger'))->commit();

    $dir = storage_path('chronicle-blank-test-'.Str::uuid());
    app(ExportManager::class)->export($dir);

    file_put_contents($dir.'/entries.ndjson', "\n", FILE_APPEND);

    $result = app(ExportVerifier::class)->verify($dir);

    expect($result->isValid())->toBeTrue();
});

it('rejects an export where entry_count in the manifest was tampered after signing', function () {
    $path = storage_path('chronicle-test-export-sig-'.Str::uuid());
    app(ExportManager::class)->export($path);

    // Tamper entry_count - keep dataset_hash and entries.ndjson untouched
    $manifestRaw = file_get_contents($path.'/manifest.json');
    $manifest = json_decode($manifestRaw, true);
    $manifest['entry_count'] = 999;
    file_put_contents($path.'/manifest.json', json_encode($manifest));

    $result = app(ExportVerifier::class)->verify($path);

    // With old code: signature still valid (only dataset_hash is signed)
    // With new code: signature fails (entry_count is part of signed payload)
    expect($result->isValid())->toBeFalse();
});
