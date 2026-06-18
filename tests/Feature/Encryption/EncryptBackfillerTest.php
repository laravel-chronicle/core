<?php

declare(strict_types=1);

use Chronicle\Encryption\CipherEnvelope;
use Chronicle\Encryption\EncryptBackfiller;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Lifecycle\LegalHold;
use Chronicle\Verification\IntegrityVerifier;

beforeEach(function () {
    $this->useEloquentDriver();
    config([
        'chronicle.encryption.enabled' => true,
        'chronicle.encryption.fields' => ['metadata', 'context', 'diff'],
        'chronicle.encryption.kek.key' => base64_encode(random_bytes(32)),
        'chronicle.encryption.kek.id' => 'local',
    ]);
});

/** Record entries while encryption is OFF, so they land cleartext for backfill. */
function recordCleartext(string $action, string $subject, array $metadata): void
{
    config(['chronicle.encryption.enabled' => false]);
    Chronicle::record()->actor(ref('a'))->action($action)->subject(ref($subject))
        ->metadata($metadata)->commit();
    config(['chronicle.encryption.enabled' => true]);
}

it('encrypts historical cleartext entries; ledger still verifies and reads decrypt', function () {
    recordCleartext('order.one', 's1', ['email' => 'a@x.test']);
    recordCleartext('order.two', 's2', ['email' => 'b@x.test']);
    recordCleartext('order.three', 's1', ['email' => 'c@x.test']);

    $report = app(EncryptBackfiller::class)->run(null, 500, false);

    expect($report->encrypted)->toBe(3)
        ->and($report->changed)->toBeTrue();

    // Column is now an envelope, not the cleartext email.
    $first = Entry::query()->where('action', 'order.one')->firstOrFail();
    expect(CipherEnvelope::isEnvelope($first->metadata))->toBeTrue()
        ->and($first->decryptedMetadata())->toBe(['email' => 'a@x.test'])
        ->and(json_encode($first->getAttributes()))->not->toContain('a@x.test')
        ->and(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue();
});

it('is idempotent: a second run writes nothing and creates no change', function () {
    recordCleartext('order.one', 's1', ['email' => 'a@x.test']);
    app(EncryptBackfiller::class)->run(null, 500, false);

    $before = Entry::query()->where('action', 'order.one')->firstOrFail();

    $report = app(EncryptBackfiller::class)->run(null, 500, false);

    $after = Entry::query()->where('action', 'order.one')->firstOrFail();

    expect($report->changed)->toBeFalse()
        ->and($report->encrypted)->toBe(0)
        ->and($report->relinked)->toBe(0)
        ->and($after->payload_hash)->toBe($before->payload_hash)
        ->and($after->chain_hash)->toBe($before->chain_hash);
});

it('dry-run reports scope but writes nothing', function () {
    recordCleartext('order.one', 's1', ['email' => 'a@x.test']);
    $before = Entry::query()->where('action', 'order.one')->firstOrFail();

    $report = app(EncryptBackfiller::class)->run(null, 500, true);

    $after = Entry::query()->where('action', 'order.one')->firstOrFail();

    expect($report->dryRun)->toBeTrue()
        ->and($report->changed)->toBeTrue()
        ->and($after->payload_hash)->toBe($before->payload_hash)
        ->and($after->metadata)->toBe(['email' => 'a@x.test']); // still cleartext
});

it('recomputes the identical payload_hash for an entry with nothing to encrypt', function () {
    // Subject present but no encryptable fields => nothing changes => no-op.
    config(['chronicle.encryption.enabled' => false]);
    Chronicle::record()->actor(ref('a'))->action('noop.one')->subject(ref('s1'))->commit();
    config(['chronicle.encryption.enabled' => true]);

    $before = Entry::query()->where('action', 'noop.one')->firstOrFail();

    $report = app(EncryptBackfiller::class)->run(null, 500, false);

    $after = Entry::query()->where('action', 'noop.one')->firstOrFail();

    expect($report->changed)->toBeFalse()
        ->and($after->payload_hash)->toBe($before->payload_hash);
});

it('respects legal holds: a held subject stays cleartext while others encrypt', function () {
    recordCleartext('order.held', 'held', ['email' => 'held@x.test']);
    recordCleartext('order.free', 'free', ['email' => 'free@x.test']);

    LegalHold::place('stdClass', 'held', 'litigation');

    $report = app(EncryptBackfiller::class)->run(null, 500, false);

    $held = Entry::query()->where('action', 'order.held')->firstOrFail();
    $free = Entry::query()->where('action', 'order.free')->firstOrFail();

    expect($held->metadata)->toBe(['email' => 'held@x.test'])      // untouched
        ->and(CipherEnvelope::isEnvelope($free->metadata))->toBeTrue()
        ->and($report->encrypted)->toBe(1)
        ->and(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue();
});

it('throws when --from references an unknown entry id', function () {
    app(EncryptBackfiller::class)->run('does-not-exist', 500, false);
})->throws(InvalidArgumentException::class);
