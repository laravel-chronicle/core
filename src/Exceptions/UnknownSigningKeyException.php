<?php

declare(strict_types=1);

namespace Chronicle\Exceptions;

/**
 * Thrown when a referenced signing key cannot be resolved from the key ring.
 */
final class UnknownSigningKeyException extends ChronicleException {}
