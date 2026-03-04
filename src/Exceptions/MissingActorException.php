<?php

namespace Chronicle\Exceptions;

/**
 * Class MissingActorException
 *
 * Thrown when an EntryBuilder attempts to build a Chronicle entry
 * without an actor being defined.
 *
 * Chronicle requires explicit intent for every entry, meaning
 * that the actor responsible for the action must always be provided.
 *
 * This exception enforces Chronicle's core design principle:
 * audit events must always identify who initiated them.
 */
class MissingActorException extends ChronicleException
{
    /**
     * Create a new exception instance.
     */
    public function __construct()
    {
        parent::__construct('Chronicle entry requires an actor. Use EntryBuilder::actor() before building.');
    }
}
