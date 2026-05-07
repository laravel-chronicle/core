<?php

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\EntryVerificationResult;
use Chronicle\Verification\EntryVerifier;

it('constructs an ok result and exposes its properties', function () {
    Chronicle::record()->actor('system')->action('ver.test')->subject(ref('ledger'))->commit();
    $entry = Entry::first();

    $result = EntryVerificationResult::ok($entry);

    expect($result->isValid())->toBeTrue()
        ->and($result->failureCode())->toBeNull()
        ->and($result->entry)->toBe($entry);
});

it('constructs a not_found result', function () {
    $result = EntryVerificationResult::notFound('01FAKEID');

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('not_found')
        ->and($result->entry)->toBeNull()
        ->and($result->missingId)->toBe('01FAKEID');
});

it('constructs a failure result with a failure code', function () {
    Chronicle::record()->actor('system')->action('ver.test')->subject(ref('ledger'))->commit();
    $entry = Entry::first();

    $result = EntryVerificationResult::failure($entry, 'payload_hash_mismatch');

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('payload_hash_mismatch')
        ->and($result->entry)->toBe($entry);
});

it('returns ok for a valid entry', function () {
    Chronicle::record()->actor('system')->action('ver.ok')->subject(ref('ledger'))->commit();

    $entry = Entry::first();
    $verifier = app(EntryVerifier::class);

    $result = $verifier->verify($entry->id);

    expect($result->isValid())->toBeTrue()
        ->and($result->failureCode())->toBeNull()
        ->and($result->entry?->id)->toBe($entry->id);
});

it('returns not_found for an unknown entry id', function () {
    $verifier = app(EntryVerifier::class);

    $result = $verifier->verify('01FAKEULIDXXXXXXXXX');

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('not_found');
});

it('returns payload_hash_mismatch when payload is tampered', function () {
    Chronicle::record()->actor('system')->action('ver.tamper')->subject(ref('ledger'))->commit();

    $entry = Entry::first();
    $entry->newQuery()->whereKey($entry->id)->update([
        'payload' => json_encode(['tampered' => true]),
    ]);

    $verifier = app(EntryVerifier::class);
    $result = $verifier->verify($entry->id);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('payload_hash_mismatch');
});

it('returns chain_hash_mismatch when chain hash is tampered', function () {
    Chronicle::record()->actor('system')->action('ver.chain')->subject(ref('ledger'))->commit();

    $entry = Entry::first();
    $entry->newQuery()->whereKey($entry->id)->update([
        'chain_hash' => str_repeat('f', 64),
    ]);

    $verifier = app(EntryVerifier::class);
    $result = $verifier->verify($entry->id);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureCode())->toBe('chain_hash_mismatch');
});
