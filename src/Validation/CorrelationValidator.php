<?php

declare(strict_types=1);

namespace Chronicle\Validation;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\PrioritizedEntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidCorrelationIdException;
use Chronicle\Pipeline\ExtensionStage;

class CorrelationValidator implements EntryExtension, PrioritizedEntryExtension
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
        return -95;
    }

    /**
     * Validate the `correlation_id` attribute of a PendingEntry for type, content, and length.
     *
     * A null correlation_id is accepted - correlation is optional. When a value is present,
     * it must be a non-blank string within the configured maximum length.
     *
     * @param  PendingEntry  $entry  The pending entry whose `correlation_id` attribute will be validated.
     * @return PendingEntry The original PendingEntry if validation succeeds.
     *
     * @throws InvalidCorrelationIdException If the `correlation_id` is not a string (provides the actual type).
     * @throws InvalidCorrelationIdException If the `correlation_id` is empty or contains only whitespace.
     * @throws InvalidCorrelationIdException If the `correlation_id` length exceeds the configured maximum (provides the offending value and max length; default max is 255).
     */
    public function process(PendingEntry $entry): PendingEntry
    {
        $correlationId = $entry->attribute('correlation_id');

        if ($correlationId === null) {
            return $entry;
        }

        if (! is_string($correlationId)) {
            throw InvalidCorrelationIdException::mustBeString($correlationId);
        }

        if (trim($correlationId) === '') {
            throw InvalidCorrelationIdException::mustNotBeBlank();
        }

        if (mb_strlen($correlationId, 'UTF-8') > $this->maxLength()) {
            throw InvalidCorrelationIdException::exceedsMaxLength($correlationId, $this->maxLength());
        }

        return $entry;
    }

    /**
     * Determine the maximum allowed length for a correlation_id from configuration.
     *
     * Reads the `chronicle.validation.correlation_id_max_length` setting and falls back to 255 if not set.
     *
     * @return int The maximum number of characters allowed for a correlation_id.
     */
    protected function maxLength(): int
    {
        /** @var int $length */
        $length = config('chronicle.validation.correlation_id_max_length', 255);

        return $length;
    }
}
