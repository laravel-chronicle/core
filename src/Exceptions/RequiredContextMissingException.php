<?php

namespace Chronicle\Exceptions;

class RequiredContextMissingException extends PolicyViolationException
{
    public static function missingKey(string $key): self
    {
        return new self(sprintf(
            'Chronicle entry rejected: required context key [%s] is missing.',
            $key
        ));
    }
}
