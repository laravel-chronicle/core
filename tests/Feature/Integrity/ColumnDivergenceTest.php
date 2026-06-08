<?php

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\EntryVerifier;
use Chronicle\Verification\IntegrityVerifier;
use Chronicle\Verification\VerificationFailure;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->useEloquentDriver());

it('detects a tampered action column even when payload and hashes are intact', function () {
    Chronicle::record()->actor(ref('a'))->action('invoice.created')->subject(ref('s'))->commit();

    $entry = Entry::query()->orderBy('sequence')->first();

    // Tamper ONLY the denormalized column; payload + hashes untouched.
    DB::table('chronicle_entries')->where('id', $entry->id)->update(['action' => 'invoice.deleted']);

    $result = app(EntryVerifier::class)->verify($entry->id);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe(VerificationFailure::ColumnPayloadDivergence->value);
});

it('flags column divergence during full-ledger verification', function () {
    Chronicle::record()->actor(ref('a'))->action('invoice.created')->subject(ref('s'))->commit();
    $entry = Entry::query()->orderBy('sequence')->first();

    DB::table('chronicle_entries')->where('id', $entry->id)->update(['actor_id' => 'someone-else']);

    $result = app(IntegrityVerifier::class)->verify();

    expect($result->isValid())->toBeFalse()
        ->and($result->failureType())->toBe(VerificationFailure::ColumnPayloadDivergence->value);
});
