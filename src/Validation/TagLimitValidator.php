<?php

namespace Chronicle\Validation;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\PrioritizedEntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidTagsException;
use Chronicle\Pipeline\ExtensionStage;

class TagLimitValidator implements EntryExtension, PrioritizedEntryExtension
{
    /**
     * Indicates the extension pipeline stage where this validator executes.
     *
     * @return ExtensionStage The validation stage (ExtensionStage::VALIDATE).
     */
    public function stage(): ExtensionStage
    {
        return ExtensionStage::VALIDATE;
    }

    /**
     * Provides the extension's execution priority within the pipeline.
     *
     * @return int The priority value used to order extensions; lower values run earlier (returns -80).
     */
    public function priority(): int
    {
        return -80;
    }

    /**
     * Validates an entry's tags against the configured maximum tag limit.
     *
     * If the entry's "tags" attribute is not an array, validation is skipped and the entry is returned unchanged.
     *
     * @param  PendingEntry  $entry  The pending entry to validate.
     * @return PendingEntry The input entry (returned unchanged when validation passes or is skipped).
     *
     * @throws InvalidTagsException If the number of tags exceeds the configured limit.
     */
    public function process(PendingEntry $entry): PendingEntry
    {
        $tags = $entry->attribute('tags');

        // Non-array values are TagsValidator's concern; skip silently.
        if (! is_array($tags)) {
            return $entry;
        }

        $count = count($tags);
        $limit = $this->maxCount();

        if ($count > $limit) {
            throw InvalidTagsException::exceedsTagLimit($count, $limit);
        }

        return $entry;
    }

    /**
     * Resolve the configured maximum number of tags allowed for an entry.
     *
     * Reads the `chronicle.validation.tag_limit` configuration value and returns it as an integer.
     *
     * @return int The maximum allowed number of tags (defaults to 10).
     */
    protected function maxCount(): int
    {
        /** @var int $limit */
        $limit = config('chronicle.validation.tag_limit', 10);

        return $limit;
    }
}
