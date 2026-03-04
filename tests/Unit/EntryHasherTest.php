<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Hashing\EntryHasher;
use Chronicle\Serialization\CanonicalPayloadSerializer;

it('generates deterministic payload hash', function () {
    $serializer = new CanonicalPayloadSerializer;
    $hasher = new EntryHasher($serializer);

    $entry = new PendingEntry([
        'action' => 'invoice.created',
    ]);

    $entry->setPayload([
        'action' => 'invoice.created',
    ]);

    $hash1 = $hasher->hash($entry);
    $hash2 = $hasher->hash($entry);

    expect($hash1)->toBe($hash2);

});
