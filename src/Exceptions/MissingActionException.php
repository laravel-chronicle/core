<?php

namespace Chronicle\Exceptions;

/**
 * Class MissingActionException
 *
 * Thrown when an EntryBuilder attempts to build a Chronicle entry
 * without specifying an action.
 *
 * Actions represent the semantic event that occurred within the
 * application (e.g. "invoice.created", "user.invited").
 *
 * Chronicle requires actions to be explicit in order to maintain
 * a meaningful and searchable audit trail.
 */
class MissingActionException extends ChronicleException
{
    /**
     * Create a new exception instance.
     */
    public function __construct()
    {
        parent::__construct('Chronicle entry requires an action. Use EntryBuilder::action() before building.');
    }
}
