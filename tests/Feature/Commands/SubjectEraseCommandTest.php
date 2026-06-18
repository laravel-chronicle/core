<?php

declare(strict_types=1);

use Chronicle\Encryption\SubjectKeyManager;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Lifecycle\LegalHold;

beforeEach(function () {
    $this->useEloquentDriver();
    config([
        'chronicle.encryption.enabled' => true,
        'chronicle.encryption.kek.key' => base64_encode(random_bytes(32)),
        'chronicle.encryption.kek.id' => 'local',
    ]);
});

it('erases a subject via the command', function () {
    Chronicle::record()->actor(ref('a'))->action('order.placed')->subject(ref('s2'))
        ->metadata(['email' => 'client@example.com'])->commit();

    $this->artisan('chronicle:subject:erase', ['type' => 'stdClass', 'id' => 's2', '--reason' => 'gdpr'])
        ->assertExitCode(0);

    expect(app(SubjectKeyManager::class)->stateFor('stdClass', 's2')->erased)->toBeTrue();
});

it('refuses a held subject without --force', function () {
    Chronicle::record()->actor(ref('a'))->action('order.placed')->subject(ref('s2'))
        ->metadata(['email' => 'client@example.com'])->commit();
    LegalHold::place('stdClass', 's2', 'litigation');

    $this->artisan('chronicle:subject:erase', ['type' => 'stdClass', 'id' => 's2'])
        ->assertExitCode(1);

    expect(app(SubjectKeyManager::class)->stateFor('stdClass', 's2')->erased)->toBeFalse();
});

it('erases a held subject with --force and audits the override in the proof', function () {
    Chronicle::record()->actor(ref('a'))->action('order.placed')->subject(ref('s2'))
        ->metadata(['email' => 'client@example.com'])->commit();
    LegalHold::place('stdClass', 's2', 'litigation');

    $this->artisan('chronicle:subject:erase', ['type' => 'stdClass', 'id' => 's2', '--reason' => 'gdpr', '--force' => true])
        ->assertExitCode(0);

    $proof = Entry::query()->where('action', 'subject.erased')->firstOrFail();
    expect($proof->decryptedMetadata())->toMatchArray(['legal_hold_override' => true]);
});
