<?php

declare(strict_types=1);

namespace Chronicle\Exceptions;

class InvalidPayloadSizeException extends ChronicleException
{
    /**
     * Create an InvalidPayloadSizeException when the serialized payload exceeds the configured limit.
     *
     * @param  int  $actualBytes  The measured byte length of the JSON-encoded payload.
     * @param  int  $maxBytes  The configured maximum allowed byte length.
     * @return self An exception reporting the actual and maximum sizes.
     */
    public static function exceedsMaxSize(int $actualBytes, int $maxBytes): self
    {
        return new self(sprintf(
            'Chronicle entry payload size [%d bytes] exceeds the maximum allowed size of [%d bytes].',
            $actualBytes,
            $maxBytes,
        ));
    }
}
