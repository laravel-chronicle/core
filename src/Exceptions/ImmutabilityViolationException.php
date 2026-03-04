<?php

namespace Chronicle\Exceptions;

/**
 * Thrown when code attempts to update
 * or delete a Chronicle entry.
 *
 * Chronicle entries are append-only. They cannot be
 * modified or removed after creation.
 * This exception is thrown by the Entry model on any
 * attempted mutation.
 */
class ImmutabilityViolationException extends ChronicleException
{
    public static function onUpdate(): self
    {
        return new self('Chronicle entries are immutable. Updates are not permitted.');
    }

    public static function onDelete(): self
    {
        return new self('Chronicle entries are immutable. Deletion is not permitted.');
    }
}
