<?php

declare(strict_types=1);

namespace Chronicle\Validation;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\PrioritizedEntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\MissingActorException;
use Chronicle\Pipeline\ExtensionStage;

/**
 * Entry extension that rejects entries recorded without an actor.
 */
final class ActorPresenceValidator implements EntryExtension, PrioritizedEntryExtension
{
    public function stage(): ExtensionStage
    {
        return ExtensionStage::VALIDATE;
    }

    public function priority(): int
    {
        return -200;
    }

    public function process(PendingEntry $entry): PendingEntry
    {
        $actorType = $entry->attribute('actor_type');
        $actorId = $entry->attribute('actor_id');

        if ($this->isPresent($actorType) && $this->isPresent($actorId)) {
            return $entry;
        }

        throw new MissingActorException;
    }

    protected function isPresent(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
