<?php

use Chronicle\Encryption\SubjectKey;
use Chronicle\Encryption\SubjectKeyManager;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\IntegrityVerifier;

it('re-wraps DEKs under a new KEK while keeping decryptability and verification', function () {
    $this->useEloquentDriver();

    $oldKey = base64_encode(random_bytes(32));
    config([
        'chronicle.encryption.enabled' => true,
        'chronicle.encryption.kek.key' => $oldKey,
        'chronicle.encryption.kek.id' => 'k1',
    ]);

    Chronicle::record()->actor(ref('a'))->action('order.placed')->subject(ref('s2'))
        ->metadata(['email' => 'client@example.com'])->commit();

    expect(SubjectKey::query()->firstOrFail()->kek_id)->toBe('k1');

    // New KEK becomes the configured one; old key passed to the command.
    $newKey = base64_encode(random_bytes(32));
    config(['chronicle.encryption.kek.key' => $newKey, 'chronicle.encryption.kek.id' => 'k2']);

    $this->artisan('chronicle:encryption:rotate-kek', ['--old-key' => $oldKey, '--old-kek-id' => 'k1'])
        ->assertExitCode(0);

    // Row re-wrapped under k2; entries untouched.
    expect(SubjectKey::query()->firstOrFail()->kek_id)->toBe('k2');

    // Fresh manager (empty DEK cache) must unwrap under the NEW KEK and decrypt.
    app()->forgetInstance(SubjectKeyManager::class);
    $entry = Entry::query()->where('action', 'order.placed')->firstOrFail();
    expect($entry->decryptedMetadata())->toBe(['email' => 'client@example.com'])
        ->and(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue();
});

it('is idempotent: a second run skips already-rotated rows', function () {
    $this->useEloquentDriver();

    $oldKey = base64_encode(random_bytes(32));
    config([
        'chronicle.encryption.enabled' => true,
        'chronicle.encryption.kek.key' => $oldKey,
        'chronicle.encryption.kek.id' => 'k1',
    ]);
    Chronicle::record()->actor(ref('a'))->action('order.placed')->subject(ref('s2'))
        ->metadata(['email' => 'client@example.com'])->commit();

    $newKey = base64_encode(random_bytes(32));
    config(['chronicle.encryption.kek.key' => $newKey, 'chronicle.encryption.kek.id' => 'k2']);

    $this->artisan('chronicle:encryption:rotate-kek', ['--old-key' => $oldKey, '--old-kek-id' => 'k1'])->assertExitCode(0);
    // Second run: rows are already k2 and are skipped (no unwrap attempted with the stale old key).
    $this->artisan('chronicle:encryption:rotate-kek', ['--old-key' => $oldKey, '--old-kek-id' => 'k1'])->assertExitCode(0);

    expect(SubjectKey::query()->firstOrFail()->kek_id)->toBe('k2');
});
