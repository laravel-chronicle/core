<?php

use Chronicle\Hashing\ChainHasher;

it('computes chain hash deterministically', function () {
    $hasher = new ChainHasher;

    $hash = $hasher->hash('0', 'abc123');

    expect($hash)->toBe(hash('sha256', '0abc123'));
});
