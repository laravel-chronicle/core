<?php

declare(strict_types=1);

namespace Chronicle\Validation;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\PrioritizedEntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidDiffException;
use Chronicle\Pipeline\ExtensionStage;
use Closure;

class DiffStructureValidator implements EntryExtension, PrioritizedEntryExtension
{
    /**
     * Indicates that this extension runs during the validation stage.
     *
     * @return ExtensionStage The validation stage (ExtensionStage::VALIDATE).
     */
    public function stage(): ExtensionStage
    {
        return ExtensionStage::VALIDATE;
    }

    /**
     * Specifies this extension's processing priority (lower values run earlier).
     *
     * @return int The priority value; lower numbers are executed earlier.
     */
    public function priority(): int
    {
        return -60;
    }

    /**
     * Validate the `diff` attribute of a PendingEntry for structure and serializability.
     *
     * A null diff is accepted - diffs are optional. When present, the diff must be an array
     * where each entry has exactly the keys `old` and `new` (no extras, no missing), and
     * neither value may be a Closure, resource, or object.
     *
     * @param  PendingEntry  $entry  The pending entry whose `diff` attribute will be validated.
     * @return PendingEntry The original PendingEntry if validation succeeds.
     *
     * @throws InvalidDiffException If the diff is not an array (provides actual type).
     * @throws InvalidDiffException If a diff entry's value is not an array (provides key and actual type).
     * @throws InvalidDiffException If a diff entry is missing the `old` or `new` key (provides key and side).
     * @throws InvalidDiffException If a diff entry contains keys beyond `old` and `new` (provides key and extra keys).
     * @throws InvalidDiffException If an `old` or `new` value contains a Closure (provides key and side).
     * @throws InvalidDiffException If an `old` or `new` value contains a resource (provides key and side).
     * @throws InvalidDiffException If an `old` or `new` value contains an object (provides key, side, and class).
     */
    public function process(PendingEntry $entry): PendingEntry
    {
        $diff = $entry->attribute('diff');

        if ($diff === null) {
            return $entry;
        }

        if (! is_array($diff)) {
            throw InvalidDiffException::mustBeArray($diff);
        }

        foreach ($diff as $key => $value) {
            $key = (string) $key;

            if (! is_array($value)) {
                throw InvalidDiffException::entryMustBeArray($key, $value);
            }

            if (! array_key_exists('old', $value)) {
                throw InvalidDiffException::missingKey($key, 'old');
            }

            if (! array_key_exists('new', $value)) {
                throw InvalidDiffException::missingKey($key, 'new');
            }

            $extras = array_values(array_diff(array_keys($value), ['old', 'new']));

            if ($extras !== []) {
                throw InvalidDiffException::extraKeys($key, $extras);
            }

            $this->assertSerializable($value['old'], $key, 'old');
            $this->assertSerializable($value['new'], $key, 'new');
        }

        return $entry;
    }

    /**
     * Recursively assert that a diff value contains no Closure, resource, or object.
     *
     * @param  mixed  $value  The value to inspect.
     * @param  string  $key  The diff key (used in exception messages).
     * @param  string  $side  The side being checked: 'old' or 'new'.
     *
     * @throws InvalidDiffException If a Closure, resource, or object is found.
     */
    private function assertSerializable(mixed $value, string $key, string $side): void
    {
        if ($value instanceof Closure) {
            throw InvalidDiffException::valueContainsClosure($key, $side);
        }

        if (is_resource($value)) {
            throw InvalidDiffException::valueContainsResource($key, $side);
        }

        if (is_object($value)) {
            throw InvalidDiffException::valueContainsObject($key, $side, get_debug_type($value));
        }

        if (is_array($value)) {
            foreach ($value as $element) {
                $this->assertSerializable($element, $key, $side);
            }
        }
    }
}
