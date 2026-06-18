<?php

declare(strict_types=1);

namespace Chronicle\Exceptions;

/**
 * Thrown when an entry is recorded outside the configured allowed time window.
 */
final class OutsideTimeWindowException extends PolicyViolationException
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
