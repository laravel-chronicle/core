<?php

namespace Chronicle\Exceptions;

class InvalidActionException extends ChronicleException
{
    public static function mustBeString(mixed $action): self
    {
        return new self(sprintf(
            'Chronicle action must be a string, %s given.',
            get_debug_type($action)
        ));
    }

    public static function mustUseDotNotation(string $action): self
    {
        return new self(sprintf(
            'Chronicle action [%s] must use dot notation such as domain.event.',
            $action
        ));
    }

    public static function exceedsMaxLength(string $action, int $maxLength): self
    {
        return new self(sprintf(
            'Chronicle action [%s] exceeds the maximum length of %d characters.',
            $action,
            $maxLength
        ));
    }
}
