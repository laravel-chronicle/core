<?php

namespace Chronicle\Validation;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\PrioritizedEntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidTagsException;
use Chronicle\Pipeline\ExtensionStage;

class TagsValidator implements EntryExtension, PrioritizedEntryExtension
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
        return -75;
    }

    /**
     * Validate the `tags` attribute of a PendingEntry for type, content, uniqueness, and length.
     *
     * @param  PendingEntry  $entry  The pending entry whose `tags` attribute will be validated.
     * @return PendingEntry The original PendingEntry if validation succeeds.
     *
     * @throws InvalidTagsException If the `tags` attribute is not an array.
     * @throws InvalidTagsException If any tag is not a string (provides the offending index and value).
     * @throws InvalidTagsException If any tag is empty or contains only whitespace (provides the offending index).
     * @throws InvalidTagsException If duplicate tags are detected (provides the duplicate tag).
     * @throws InvalidTagsException If a tag's length exceeds the configured maximum (provides the offending tag and max length; default max is 50).
     */
    public function process(PendingEntry $entry): PendingEntry
    {
        $tags = $entry->attribute('tags');

        if (! is_array($tags)) {
            throw InvalidTagsException::mustBeArray($tags);
        }

        $maxLength = $this->maxLength();
        $seen = [];

        foreach ($tags as $index => $tag) {
            if (! is_string($tag)) {
                throw InvalidTagsException::mustContainOnlyStrings((int) $index, $tag);
            }

            if (trim($tag) === '') {
                throw InvalidTagsException::mustNotBeEmpty((int) $index);
            }

            if (preg_match('/[^\x20-\x7E]/', $tag) === 1) {
                throw InvalidTagsException::mustContainOnlyAscii($tag);
            }

            if (array_key_exists($tag, $seen)) {
                throw InvalidTagsException::mustBeUnique($tag);
            }

            $seen[$tag] = true;

            if (mb_strlen($tag, 'UTF-8') > $maxLength) {
                throw InvalidTagsException::tagExceedsMaxLength($tag, $maxLength);
            }
        }

        return $entry;
    }

    /**
     * Determine the maximum allowed length for a single tag from configuration.
     *
     * Reads the `chronicle.validation.tag_max_length` setting and falls back to 50 if not set.
     *
     * @return int The maximum number of characters allowed for a tag.
     */
    protected function maxLength(): int
    {
        /** @var int $length */
        $length = config('chronicle.validation.tag_max_length', 50);

        return $length;
    }
}
