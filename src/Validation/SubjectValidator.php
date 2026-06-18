<?php

declare(strict_types=1);

namespace Chronicle\Validation;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\PrioritizedEntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\MissingSubjectException;
use Chronicle\Pipeline\ExtensionStage;

/**
 * Entry extension that rejects entries recorded without a subject.
 */
final class SubjectValidator implements EntryExtension, PrioritizedEntryExtension
{
    public function stage(): ExtensionStage
    {
        return ExtensionStage::VALIDATE;
    }

    public function priority(): int
    {
        return -150;
    }

    public function process(PendingEntry $entry): PendingEntry
    {
        // System actors are permitted to omit a subject.
        if ($entry->attribute('actor_type') === 'system') {
            return $entry;
        }

        $subjectType = $entry->attribute('subject_type');
        $subjectId = $entry->attribute('subject_id');

        if ($this->isPresent($subjectType) && $this->isPresent($subjectId)) {
            return $entry;
        }

        throw new MissingSubjectException;
    }

    protected function isPresent(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
