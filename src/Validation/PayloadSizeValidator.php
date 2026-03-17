<?php

namespace Chronicle\Validation;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\PrioritizedEntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidPayloadSizeException;
use Chronicle\Pipeline\ExtensionStage;

class PayloadSizeValidator implements EntryExtension, PrioritizedEntryExtension
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
     * Runs at -40, after PayloadSerializableValidator (-50), so the payload is
     * guaranteed to be JSON-serializable before size is measured.
     *
     * @return int The priority value; lower numbers are executed earlier.
     */
    public function priority(): int
    {
        return -40;
    }

    /**
     * Validate that the combined JSON size of metadata, context, and diff does not exceed
     * the configured maximum payload size.
     *
     * Size is measured in bytes as strlen(json_encode($combined)). Because this validator
     * runs after PayloadSerializableValidator, the payload is guaranteed to be serializable.
     *
     * @param  PendingEntry  $entry  The pending entry whose payload fields will be measured.
     * @return PendingEntry The original PendingEntry if validation succeeds.
     *
     * @throws InvalidPayloadSizeException If the serialized byte length exceeds the configured maximum.
     */
    public function process(PendingEntry $entry): PendingEntry
    {
        $encoded = json_encode([
            'metadata' => $entry->attribute('metadata'),
            'context' => $entry->attribute('context'),
            'diff' => $entry->attribute('diff'),
        ], JSON_THROW_ON_ERROR);

        $size = strlen($encoded);
        $max = $this->maxPayloadSize();

        if ($size > $max) {
            throw InvalidPayloadSizeException::exceedsMaxSize($size, $max);
        }

        return $entry;
    }

    /**
     * Determine the maximum allowed payload size in bytes from configuration.
     *
     * Reads `chronicle.validation.max_payload_size` and falls back to 65536 (64 KB) if not set.
     *
     * @return int The maximum number of bytes allowed for the serialized payload.
     */
    protected function maxPayloadSize(): int
    {
        /** @var int $size */
        $size = config('chronicle.validation.max_payload_size', 65536);

        return $size;
    }
}
