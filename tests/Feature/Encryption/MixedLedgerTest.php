<?php

declare(strict_types=1);

use Chronicle\Encryption\CipherEnvelope;
use Chronicle\Encryption\SubjectKeyManager;
use Chronicle\Entry\Entry;
use Chronicle\Exports\ExportFormat;
use Chronicle\Exports\ExportManager;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\ExportVerifier;
use Chronicle\Verification\IntegrityVerifier;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->useEloquentDriver();
    config([
        'chronicle.encryption.fields' => ['metadata', 'context', 'diff'],
        'chronicle.encryption.kek.key' => base64_encode(random_bytes(32)),
        'chronicle.encryption.kek.id' => 'local',
    ]);
});

it('verifies a ledger mixing cleartext and encrypted entries', function () {
    // First entry cleartext (encryption off).
    config(['chronicle.encryption.enabled' => false]);
    Chronicle::record()->actor(ref('a'))->action('a.clear')->subject(ref('s1'))
        ->metadata(['k' => 'plain'])->commit();

    // Second entry encrypted (encryption on) - chain links across the toggle.
    config(['chronicle.encryption.enabled' => true]);
    Chronicle::record()->actor(ref('a'))->action('a.enc')->subject(ref('s2'))
        ->metadata(['email' => 'client@example.com'])->commit();

    expect(Entry::query()->count())->toBe(2)
        ->and(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue();
});

it('exports the cipher envelope and the export verifies', function () {
    config(['chronicle.encryption.enabled' => true]);
    Chronicle::record()->actor(ref('a'))->action('a.enc')->subject(ref('s2'))
        ->metadata(['email' => 'client@example.com'])->commit();

    $path = storage_path('chronicle-mixed-export-'.Str::uuid());

    app(ExportManager::class)->export($path);

    // Export verifies (hashes/columns over the envelope, never decrypting).
    expect(app(ExportVerifier::class)->verify($path)->isValid())->toBeTrue();

    // The exported field carries the envelope, not plaintext.
    $entries = file_get_contents($path.'/'.ExportFormat::ENTRIES);
    expect($entries)->toContain(CipherEnvelope::MARKER)
        ->and($entries)->not->toContain('client@example.com');
});

it('an exported erased subject still verifies but is unreadable', function () {
    config(['chronicle.encryption.enabled' => true]);
    Chronicle::record()->actor(ref('a'))->action('a.enc')->subject(ref('s2'))
        ->metadata(['email' => 'client@example.com'])->commit();

    $entry = Entry::query()->firstOrFail();
    app(SubjectKeyManager::class)->destroy($entry->subject_type, (string) $entry->subject_id);

    $path = storage_path('chronicle-erased-export-'.Str::uuid());
    app(ExportManager::class)->export($path);

    expect(app(ExportVerifier::class)->verify($path)->isValid())->toBeTrue()
        ->and(file_get_contents($path.'/'.ExportFormat::ENTRIES))->not->toContain('client@example.com');
});
