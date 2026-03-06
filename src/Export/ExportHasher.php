<?php

namespace Chronicle\Export;

use RuntimeException;

/**
 * Computes the cryptographic hash of an exported Chronicle dataset.
 */
class ExportHasher
{
    /**
     * Hash a file using SHA-256.
     *
     * The file is streamed to avoid loading large exports into memory.
     */
    public function hashFile(string $path): string
    {
        $context = hash_init('sha256');

        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw new RuntimeException("Unable to open export file: $path");
        }

        while (! feof($handle)) {
            $chunk = fread($handle, 8192);

            if ($chunk !== false) {
                hash_update($context, $chunk);
            }
        }

        fclose($handle);

        return hash_final($context);
    }
}
