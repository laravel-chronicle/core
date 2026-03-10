<?php

use Chronicle\Exceptions\ChronicleException;
use Chronicle\Exceptions\ImmutabilityViolationException;
use Chronicle\Exceptions\InvalidActionException;

it('extends chronicle exception', function () {
    expect(ImmutabilityViolationException::onUpdate())
        ->toBeInstanceOf(ChronicleException::class)
        ->and(ImmutabilityViolationException::onDelete())
        ->toBeInstanceOf(ChronicleException::class);

    expect(InvalidActionException::mustUseDotNotation('orders'))
        ->toBeInstanceOf(ChronicleException::class);
});
