<?php

namespace Chronicle\Exceptions;

class UnserializablePayloadException extends ChronicleException
{
    public static function containsClosure(): self
    {
        return new self('Chronicle entry payload must not contain closures.');
    }

    public static function containsResource(): self
    {
        return new self('Chronicle entry payload must not contain resources.');
    }

    public static function containsObject(string $class): self
    {
        return new self(sprintf(
            'Chronicle entry payload must not contain objects, got [%s].',
            $class,
        ));
    }

    public static function notJsonSerializable(string $reason): self
    {
        return new self(sprintf(
            'Chronicle entry payload is not JSON-serialisable: %s.',
            $reason,
        ));
    }
}
