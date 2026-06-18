<?php

declare(strict_types=1);

namespace Chronicle\Exceptions;

/**
 * Thrown when an entry's action is not on the configured allowed-actions list.
 */
final class ActionNotAllowedException extends PolicyViolationException
{
    public static function notInAllowlist(string $action): self
    {
        return new self(sprintf(
            'Chronicle entry rejected: action [%s] is not in the allowed actions list.',
            $action
        ));
    }
}
