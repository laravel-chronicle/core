<?php

namespace Chronicle\Exceptions;

class InvalidTagsException extends ChronicleException
{
    public static function mustBeArray(mixed $value): self
    {
        return new self(sprintf(
            'Chronicle entry tags must be an array, %s given.',
            get_debug_type($value),
        ));
    }

    public static function mustContainOnlyStrings(int $index, mixed $value): self
    {
        return new self(sprintf(
            'Chronicle entry tags must contain only strings, %s found at index [%d].',
            get_debug_type($value),
            $index,
        ));
    }

    public static function mustNotBeEmpty(int $index): self
    {
        return new self(sprintf(
            'Chronicle entry tags must not be empty, blank tag found at index [%d].',
            $index,
        ));
    }

    public static function mustBeUnique(string $tag): self
    {
        return new self(sprintf(
            'Chronicle entry tags must be unique, [%s] appears more than once.',
            $tag,
        ));
    }

    public static function tagExceedsMaxLength(string $tag, int $maxLength): self
    {
        return new self(sprintf(
            'Chronicle entry tag [%s] exceeds the maximum length of %d characters.',
            $tag,
            $maxLength,
        ));
    }

    public static function exceedsTagLimit(int $count, int $limit): self
    {
        return new self(sprintf(
            'Chronicle entry exceeds the tag limit of %d, %d %s provided.',
            $limit,
            $count,
            $count === 1 ? 'tag was' : 'tags were',
        ));
    }
}
