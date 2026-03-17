<?php

namespace Chronicle\Validation;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\PrioritizedEntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidTagsException;
use Chronicle\Pipeline\ExtensionStage;

class TagsValidator implements EntryExtension, PrioritizedEntryExtension
{
    public function stage(): ExtensionStage
    {
        return ExtensionStage::VALIDATE;
    }

    public function priority(): int
    {
        return -75;
    }

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

    protected function maxLength(): int
    {
        /** @var int $length */
        $length = config('chronicle.validation.tag_max_length', 50);

        return $length;
    }
}
