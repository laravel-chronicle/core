<?php

namespace Chronicle\Exceptions;

/**
 * Class MissingSubjectException
 *
 * Thrown when an EntryBuilder attempts to build a Chronicle entry
 * without defining the subject of the action.
 *
 * The subject represents the entity affected by the action
 * (e.g. Invoice, User, Order).
 *
 * Chronicle requires a subject to ensure the audit trail
 * clearly records what was acted upon.
 */
class MissingSubjectException extends ChronicleException
{
    /**
     * Create a new exception instance.
     */
    public function __construct()
    {
        parent::__construct('Chronicle entry requires a subject. Use EntryBuilder::subject() before building.');
    }
}
