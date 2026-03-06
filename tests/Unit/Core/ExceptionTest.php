<?php

use Chronicle\Exceptions\ChronicleException;
use Chronicle\Exceptions\ImmutabilityViolationException;

it('extends chronicle exception', function () {
    expect(ImmutabilityViolationException::onUpdate())
        ->toBeInstanceOf(ChronicleException::class)
        ->and(ImmutabilityViolationException::onDelete())
        ->toBeInstanceOf(ChronicleException::class);
});
