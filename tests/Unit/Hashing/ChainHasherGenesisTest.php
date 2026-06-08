<?php

use Chronicle\Hashing\ChainHasher;

it('exposes the genesis seed as a shared constant', function () {
    expect(ChainHasher::GENESIS)->toBe('0');
});
