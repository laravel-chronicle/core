<?php

namespace Chronicle\Validation;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\PrioritizedEntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidTagsException;
use Chronicle\Pipeline\ExtensionStage;

class TagLimitValidator implements EntryExtension, PrioritizedEntryExtension
{
    public function stage(): ExtensionStage
    {
        return ExtensionStage::VALIDATE;
    }

    public function priority(): int
    {
        return -80;
    }

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

    protected function maxCount(): int
    {
        /** @var int $limit */
        $limit = config('chronicle.validation.tag_limit', 10);

        return $limit;
    }
}
