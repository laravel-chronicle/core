<?php

namespace Chronicle\Exceptions;

class RateLimitExceededException extends PolicyViolationException
{
    public static function exceededLimit(int $retryAfter): self
    {
        return new self(sprintf(
            'Chronicle entry rejected: rate limit exceeded. Retry after %d second(s).',
            $retryAfter
        ));
    }
}
