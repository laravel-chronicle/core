<?php

namespace Chronicle\Exceptions;

class ExportWriteException extends ChronicleException
{
    public static function directoryCreationFailed(string $path, ?string $reason = null): self
    {
        $message = "Unable to create export directory: {$path}";

        if ($reason !== null) {
            $message .= " ({$reason})";
        }

        return new self($message);
    }

    public static function encodeFailed(string $target): self
    {
        return new self("Unable to encode export {$target} JSON.");
    }

    public static function writeFailed(string $path): self
    {
        return new self("Unable to write export file: {$path}");
    }
}
