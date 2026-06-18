<?php

declare(strict_types=1);

namespace Chronicle\Exceptions;

/**
 * Base exception for all Chronicle policy rejections.
 *
 * Catch this to handle any policy violation.
 * Catch the subclasses for specific violation types.
 */
class PolicyViolationException extends ChronicleException {}
