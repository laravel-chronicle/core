<?php

use Chronicle\Exceptions\ExportWriteException;
use Chronicle\Export\EntryExporter;
use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;
use Illuminate\Support\Str;

if (! class_exists('ChronicleFailingWriteStream')) {
    class ChronicleFailingWriteStream
    {
        public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
        {
            return true;
        }

        public function stream_write(string $data): int
        {
            return 0;
        }

        public function stream_close(): void {}
    }
}

it('fails entry export when output file cannot be opened', function () {
    Chronicle::record()
        ->actor('system')
        ->action('export.open-failure')
        ->subject('ledger')
        ->commit();

    $dirPath = storage_path('chronicle-entry-export-dir-'.Str::uuid());
    mkdir($dirPath, 0755, true);

    expect(fn () => app(EntryExporter::class)->export($dirPath))
        ->toThrow(ExportWriteException::class, 'Unable to write export file');
});

it('fails entry serialization when json encoding fails', function () {
    $exporter = new class extends EntryExporter
    {
        public function exposeSerializeEntry(Entry $entry): string
        {
            return $this->serializeEntry($entry);
        }
    };

    $entry = new class extends Entry
    {
        /**
         * @var array<string, mixed>
         */
        private array $values;

        public function __construct()
        {
            parent::__construct();

            $this->values = [
                'id' => (string) Str::ulid(),
                'actor_type' => 'system',
                'actor_id' => 'system',
                'action' => 'export.encode-failure',
                'subject_type' => 'ledger',
                'subject_id' => 'ledger',
                'payload' => ['bad' => "\xB1\x31"],
                'payload_hash' => str_repeat('a', 64),
                'chain_hash' => str_repeat('b', 64),
                'checkpoint_id' => null,
                'tags' => [],
                'diff' => null,
                'correlation_id' => null,
                'created_at' => now('UTC'),
            ];
        }

        public function getAttribute($key): mixed
        {
            return $this->values[$key] ?? null;
        }
    };

    expect(fn () => $exporter->exposeSerializeEntry($entry))
        ->toThrow(ExportWriteException::class, 'Unable to encode export entries JSON.');
});

it('fails entry export when write operation cannot persist bytes', function () {
    Chronicle::record()
        ->actor('system')
        ->action('export.write-failure')
        ->subject('ledger')
        ->commit();

    $scheme = 'chroniclefailwrite';

    if (in_array($scheme, stream_get_wrappers(), true)) {
        stream_wrapper_unregister($scheme);
    }

    stream_wrapper_register($scheme, ChronicleFailingWriteStream::class);

    try {
        expect(fn () => app(EntryExporter::class)->export($scheme.'://entries.ndjson'))
            ->toThrow(ExportWriteException::class, 'Unable to write export file');
    } finally {
        stream_wrapper_unregister($scheme);
    }
});
