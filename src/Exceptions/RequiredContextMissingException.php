<?php

declare(strict_types=1);

namespace Chronicle\Exceptions;

/**
 * Thrown when an entry is missing a context key required by policy.
 */
final class RequiredContextMissingException extends PolicyViolationException
{
    public static function missingKey(string $key): self
    {
        return new self(sprintf(
            'Chronicle entry rejected: required context key [%s] is missing.',
            $key
        ));
    }
}
