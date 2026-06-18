<?php

declare(strict_types=1);

namespace Chronicle\Exceptions;

class UnauthenticatedActorException extends PolicyViolationException
{
    public static function notAuthenticated(): self
    {
        return new self('Chronicle entry rejected: actor is not authenticated.');
    }
}
