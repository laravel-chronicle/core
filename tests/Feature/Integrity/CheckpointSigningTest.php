<?php

use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\IntegrityVerifier;
use Chronicle\Verification\VerificationFailure;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->useEloquentDriver());

it('signs checkpoint metadata so a tampered key_id is detected', function () {
    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();
    $checkpoint = app(CheckpointCreator::class)->create();

    Entry::query()->update(['checkpoint_id' => $checkpoint->id]);

    // Baseline: intact checkpoint verifies.
    expect(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue();

    // Tamper a field that is now part of the signed payload.
    DB::table('chronicle_checkpoints')->where('id', $checkpoint->id)->update(['key_id' => 'attacker-key']);

    $result = app(IntegrityVerifier::class)->verify();

    // Resolves to a different/missing key OR fails signature verification - either is a hard failure.
    expect($result->isValid())->toBeFalse()
        ->and($result->failureType())->toBeIn([
            VerificationFailure::CheckpointSignatureInvalid->value,
            VerificationFailure::UnknownKey->value,
        ]);
});
