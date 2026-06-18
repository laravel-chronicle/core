<?php

declare(strict_types=1);

namespace Chronicle\Exceptions;

/**
 * Thrown when an entry's action matches the configured forbidden-actions denylist.
 */
final class ActionForbiddenException extends PolicyViolationException
{
    public static function matchesDenylist(string $action): self
    {
        return new self(sprintf(
            'Chronicle entry rejected: action [%s] matches the forbidden actions list.',
            $action
        ));
    }
}
