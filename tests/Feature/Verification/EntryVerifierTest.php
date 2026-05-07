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
