<?php

declare(strict_types=1);

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Encryption\CipherEnvelope;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\IntegrityVerifier;

beforeEach(function () {
    $this->useEloquentDriver();
    config([
        'chronicle.encryption.enabled' => true,
        'chronicle.encryption.kek.key' => base64_encode(random_bytes(32)),
        'chronicle.encryption.kek.id' => 'local',
    ]);
});

function recordCleartextForCommand(string $action, string $subject): void
{
    config(['chronicle.encryption.enabled' => false]);
    Chronicle::record()->actor(ref('a'))->action($action)->subject(ref($subject))
        ->metadata(['email' => "{$subject}@x.test"])->commit();
    config(['chronicle.encryption.enabled' => true]);
}

it('encrypts historical entries and creates a signed checkpoint (--force)', function () {
    recordCleartextForCommand('order.one', 's1');
    recordCleartextForCommand('order.two', 's2');

    expect(Checkpoint::query()->count())->toBe(0);

    $this->artisan('chronicle:encrypt-backfill', ['--force' => true])
        ->assertExitCode(0);

    $entry = Entry::query()->where('action', 'order.one')->firstOrFail();

    expect(CipherEnvelope::isEnvelope($entry->metadata))->toBeTrue()
        ->and($entry->decryptedMetadata())->toBe(['email' => 's1@x.test'])
        ->and(Checkpoint::query()->count())->toBe(1)
        ->and(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue();
});

it('dry-run writes nothing and creates no checkpoint', function () {
    recordCleartextForCommand('order.one', 's1');
    $before = Entry::query()->where('action', 'order.one')->firstOrFail();

    $this->artisan('chronicle:encrypt-backfill', ['--dry-run' => true, '--force' => true])
        ->assertExitCode(0);

    $after = Entry::query()->where('action', 'order.one')->firstOrFail();

    expect($after->metadata)->toBe(['email' => 's1@x.test'])     // unchanged
        ->and($after->payload_hash)->toBe($before->payload_hash)
        ->and(Checkpoint::query()->count())->toBe(0);
});

it('is a no-op on an already-encrypted ledger (no new checkpoint)', function () {
    recordCleartextForCommand('order.one', 's1');
    $this->artisan('chronicle:encrypt-backfill', ['--force' => true])->assertExitCode(0);

    expect(Checkpoint::query()->count())->toBe(1);

    // Second run: nothing to encrypt, so no rewrite and no new checkpoint.
    $this->artisan('chronicle:encrypt-backfill', ['--force' => true])->assertExitCode(0);

    expect(Checkpoint::query()->count())->toBe(1);
});

it('fails fast when encryption is disabled', function () {
    config(['chronicle.encryption.enabled' => false]);

    $this->artisan('chronicle:encrypt-backfill', ['--force' => true])
        ->assertExitCode(1);
});
