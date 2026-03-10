<?php

namespace Chronicle\Extensions;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\PrioritizedEntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\InvalidActionException;

class ActionValidator implements EntryExtension, PrioritizedEntryExtension
{
    public function stage(): ExtensionStage
    {
        return ExtensionStage::VALIDATE;
    }

    public function priority(): int
    {
        return -100;
    }

    public function process(PendingEntry $entry): PendingEntry
    {
        $action = $entry->attribute('action');

        if (! is_string($action)) {
            throw InvalidActionException::mustBeString($action);
        }

        if (strlen($action) > $this->maxLength()) {
            throw InvalidActionException::exceedsMaxLength($action, $this->maxLength());
        }

        if (! preg_match('/^[^\s.]+\.[^\s.]+$/', $action)) {
            throw InvalidActionException::mustUseDotNotation($action);
        }

        return $entry;
    }

    protected function maxLength(): int
    {
        /** @var int $length */
        $length = config('chronicle.validation.action_max_length', 255);

        return $length;
    }
}
