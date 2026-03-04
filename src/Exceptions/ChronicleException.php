<?php

namespace Chronicle\Exceptions;

use RuntimeException;

/**
 * Base exception for all Chronicle errors.
 *
 * Catch this to handle any Chronicle failure.
 * Catch the subclasses when you need to handle
 * specific failure modes.
 */
class ChronicleException extends RuntimeException {}
