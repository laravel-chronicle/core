<?php

namespace Chronicle\Exceptions;

class OutsideTimeWindowException extends PolicyViolationException
{
    public static function outsideWindow(string $start, string $end): self
    {
        return new self(sprintf(
            'Chronicle entry rejected: current time is outside the allowed window (%s – %s).',
            $start,
            $end
        ));
    }
}
