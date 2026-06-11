<?php

namespace Chronicle\Exceptions;

class InvalidDiffException extends ChronicleException
{
    /**
     * Create an InvalidDiffException for a diff value that is not an array.
     *
     * @param  mixed  $diff  The received value that was expected to be an array or null.
     * @return self An exception stating the diff must be an array and reporting the given value's type.
     */
    public static function mustBeArray(mixed $diff): self
    {
        return new self(sprintf(
            'Chronicle entry diff must be an array or null, %s given.',
            get_debug_type($diff),
        ));
    }

    /**
     * Create an InvalidDiffException for a diff entry whose value is not an array.
     *
     * @param  string  $key  The diff key whose value is invalid.
     * @param  mixed  $value  The received value that was expected to be an array.
     * @return self An exception stating the entry must be an array and reporting the actual type.
     */
    public static function entryMustBeArray(string $key, mixed $value): self
    {
        return new self(sprintf(
            'Chronicle entry diff[%s] must be an array, %s given.',
            $key,
            get_debug_type($value),
        ));
    }

    /**
     * Create an InvalidDiffException for a diff entry missing a required key.
     *
     * @param  string  $key  The diff key whose entry is incomplete.
     * @param  string  $side  The missing key name - either 'old' or 'new'.
     * @return self An exception naming the diff key and the missing side.
     */
    public static function missingKey(string $key, string $side): self
    {
        return new self(sprintf(
            'Chronicle entry diff[%s] is missing the required key [%s].',
            $key,
            $side,
        ));
    }

    /**
     * Create an InvalidDiffException for a diff entry containing keys beyond old and new.
     *
     * @param  string  $key  The diff key whose entry contains unexpected keys.
     * @param  string[]  $extra  The unexpected key names found in the entry.
     * @return self An exception naming the diff key and listing the extra keys.
     */
    public static function extraKeys(string $key, array $extra): self
    {
        return new self(sprintf(
            'Chronicle entry diff[%s] contains unexpected keys: [%s]. Only [old] and [new] are allowed.',
            $key,
            implode(', ', $extra),
        ));
    }

    /**
     * Create an InvalidDiffException when an old or new value contains a Closure.
     *
     * @param  string  $key  The diff key.
     * @param  string  $side  The side ('old' or 'new') that contains the Closure.
     * @return self An exception naming the diff key and side.
     */
    public static function valueContainsClosure(string $key, string $side): self
    {
        return new self(sprintf(
            'Chronicle entry diff[%s][%s] must not contain closures.',
            $key,
            $side,
        ));
    }

    /**
     * Create an InvalidDiffException when an old or new value contains a resource.
     *
     * @param  string  $key  The diff key.
     * @param  string  $side  The side ('old' or 'new') that contains the resource.
     * @return self An exception naming the diff key and side.
     */
    public static function valueContainsResource(string $key, string $side): self
    {
        return new self(sprintf(
            'Chronicle entry diff[%s][%s] must not contain resources.',
            $key,
            $side,
        ));
    }

    /**
     * Create an InvalidDiffException when an old or new value contains an object.
     *
     * @param  string  $key  The diff key.
     * @param  string  $side  The side ('old' or 'new') that contains the object.
     * @param  string  $class  The class name of the object found.
     * @return self An exception naming the diff key, side, and object class.
     */
    public static function valueContainsObject(string $key, string $side, string $class): self
    {
        return new self(sprintf(
            'Chronicle entry diff[%s][%s] must not contain objects, got [%s].',
            $key,
            $side,
            $class,
        ));
    }
}
