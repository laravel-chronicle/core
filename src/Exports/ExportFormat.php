<?php

declare(strict_types=1);

namespace Chronicle\Exports;

/**
 * Defines the canonical filenames that make up an audit export bundle.
 */
final class ExportFormat
{
    public const ENTRIES = 'entries.ndjson';

    public const MANIFEST = 'manifest.json';

    public const SIGNATURE = 'signature.json';
}
