<?php

use Chronicle\Exceptions\ChronicleException;
use Chronicle\Exceptions\UnknownSigningKeyException;

it('is a ChronicleException', function () {
    $e = new UnknownSigningKeyException('No key for ecdsa:retired-key');
    expect($e)->toBeInstanceOf(ChronicleException::class);
});
