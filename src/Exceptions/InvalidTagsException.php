<?php

declare(strict_types=1);

namespace Chronicle\Exceptions;

/**
 * Thrown when an entry's tags are malformed (wrong type, too long, or duplicated).
 */
final class InvalidTagsException extends ChronicleException
{
    /**
     * Create an InvalidTagsException for a value that is not an array.
     *
     * The exception message includes the actual type of the provided value.
     *
     * @param  mixed  $value  The received value that was expected to be an array.
     * @return self An InvalidTagsException stating tags must be an array and reporting the given value's type.
     */
    public static function mustBeArray(mixed $value): self
    {
        return new self(sprintf(
            'Chronicle entry tags must be an array, %s given.',
            get_debug_type($value),
        ));
    }

    /**
     * Create an InvalidTagsException indicating a non-string tag was found at the given index.
     *
     * @param  int  $index  The index of the tag that is not a string.
     * @param  mixed  $value  The value found at that index.
     * @return self An InvalidTagsException describing the index and the found value's type.
     */
    public static function mustContainOnlyStrings(int $index, mixed $value): self
    {
        return new self(sprintf(
            'Chronicle entry tags must contain only strings, %s found at index [%d].',
            get_debug_type($value),
            $index,
        ));
    }

    /**
     * Create an exception indicating a blank tag was found at the given index.
     *
     * @param  int  $index  The index of the blank tag.
     * @return self An InvalidTagsException describing the empty tag at the specified index.
     */
    public static function mustNotBeEmpty(int $index): self
    {
        return new self(sprintf(
            'Chronicle entry tags must not be empty, blank tag found at index [%d].',
            $index,
        ));
    }

    /**
     * Create an exception indicating a tag was provided more than once.
     *
     * @param  string  $tag  The duplicated tag.
     * @return self The exception describing the duplicated tag.
     */
    public static function mustBeUnique(string $tag): self
    {
        return new self(sprintf(
            'Chronicle entry tags must be unique, [%s] appears more than once.',
            $tag,
        ));
    }

    /**
     * Create an InvalidTagsException for a tag that is longer than the allowed length.
     *
     * The exception message includes the offending tag and the maximum permitted length in characters.
     *
     * @param  string  $tag  The tag that exceeds the maximum length.
     * @param  int  $maxLength  The maximum allowed length in characters.
     * @return self An exception instance with a message describing the tag and the maximum length.
     */
    public static function tagExceedsMaxLength(string $tag, int $maxLength): self
    {
        return new self(sprintf(
            'Chronicle entry tag [%s] exceeds the maximum length of %d characters.',
            $tag,
            $maxLength,
        ));
    }

    /**
     * Create an InvalidTagsException for when the number of tags exceeds the allowed limit.
     *
     * @param  int  $count  The actual number of tags provided.
     * @param  int  $limit  The maximum allowed number of tags.
     * @return self An InvalidTagsException whose message states the allowed limit, the provided count, and uses correct pluralization ("tag was" / "tags were").
     */
    public static function exceedsTagLimit(int $count, int $limit): self
    {
        return new self(sprintf(
            'Chronicle entry exceeds the tag limit of %d, %d %s provided.',
            $limit,
            $count,
            $count === 1 ? 'tag was' : 'tags were',
        ));
    }

    /**
     * Create an exception indicating a tag contains non-ASCII or non-printable characters.
     *
     * @param  string  $tag  The offending tag.
     * @return self An exception stating the tag must contain only printable ASCII characters.
     */
    public static function mustContainOnlyAscii(string $tag): self
    {
        return new self(sprintf(
            'Chronicle entry tags must contain only printable ASCII characters, [%s] contains non-ASCII or control characters.',
            $tag,
        ));
    }
}
