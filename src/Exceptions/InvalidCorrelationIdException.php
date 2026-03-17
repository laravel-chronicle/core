<?php

namespace Chronicle\Exceptions;

class InvalidCorrelationIdException extends ChronicleException
{
    /**
     * Create an InvalidCorrelationIdException for a value that is not a string.
     *
     * The exception message includes the actual type of the provided value.
     *
     * @param  mixed  $value  The received value that was expected to be a string or null.
     * @return self An InvalidCorrelationIdException stating the correlation_id must be a string and reporting the given value's type.
     */
    public static function mustBeString(mixed $value): self
    {
        return new self(sprintf(
            'Chronicle entry correlation_id must be a string or null, %s given.',
            get_debug_type($value),
        ));
    }

    /**
     * Create an InvalidCorrelationIdException indicating the correlation_id is blank.
     *
     * @return self An InvalidCorrelationIdException stating the correlation_id must not be blank.
     */
    public static function mustNotBeBlank(): self
    {
        return new self('Chronicle entry correlation_id must not be blank.');
    }

    /**
     * Create an InvalidCorrelationIdException for a correlation_id that exceeds the maximum allowed length.
     *
     * The exception message includes the offending value and the maximum permitted length in characters.
     *
     * @param  string  $correlationId  The correlation_id that exceeds the maximum length.
     * @param  int  $maxLength  The maximum allowed length in characters.
     * @return self An exception instance with a message describing the value and the maximum length.
     */
    public static function exceedsMaxLength(string $correlationId, int $maxLength): self
    {
        return new self(sprintf(
            'Chronicle entry correlation_id [%s] exceeds the maximum length of %d characters.',
            $correlationId,
            $maxLength,
        ));
    }
}
