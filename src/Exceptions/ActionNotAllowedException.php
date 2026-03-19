<?php

namespace Chronicle\Exceptions;

class ActionNotAllowedException extends PolicyViolationException
{
    public static function notInAllowlist(string $action): self
    {
        return new self(sprintf(
            'Chronicle entry rejected: action [%s] is not in the allowed actions list.',
            $action
        ));
    }
}
