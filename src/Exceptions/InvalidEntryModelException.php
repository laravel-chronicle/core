<?php

declare(strict_types=1);

namespace Chronicle\Exceptions;

use Chronicle\Entry\Entry;

/**
 * Thrown when chronicle.models.entry is set to a class that does not exist
 * or does not extend Chronicle\Entry\Entry. Guards the immutability and
 * chain contract carried by the base Entry model.
 */
final class InvalidEntryModelException extends ChronicleException
{
    public static function for(string $class): self
    {
        return new self(sprintf(
            'Configured chronicle.models.entry [%s] must be a class that extends %s.',
            $class,
            Entry::class,
        ));
    }
}
