<?php

declare(strict_types=1);

namespace Chronicle\Console\Commands;

use Chronicle\Encryption\SubjectKey;
use Chronicle\Entry\Entry;
use Illuminate\Console\Command;

/**
 * Artisan command that lists subject key state and entry counts without printing key material.
 */
final class SubjectKeysCommand extends Command
{
    protected $signature = 'chronicle:subject:keys
        {--subject= : Filter by subject id}
        {--status= : Filter by status (active|erased)}
        {--json : Output as JSON}';

    protected $description = 'List subject key state (active/erased) and entry counts - never prints key material';

    public function handle(): int
    {
        $query = SubjectKey::query()->orderBy('subject_type')->orderBy('subject_id');

        /** @var string|null $subject */
        $subject = $this->option('subject');
        if ($subject !== null && $subject !== '') {
            $query->where('subject_id', $subject);
        }

        /** @var string|null $status */
        $status = $this->option('status');
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $rows = $query->get()->map(function (SubjectKey $key): array {
            return [
                'subject_type' => $key->subject_type,
                'subject_id' => $key->subject_id,
                'status' => $key->status,
                'created_at' => $key->created_at->toIso8601String(),
                'erased_at' => $key->erased_at?->toIso8601String(),
                'entry_count' => Entry::query()
                    ->where('subject_type', $key->subject_type)
                    ->where('subject_id', $key->subject_id)
                    ->count(),
            ];
        })->all();

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $this->warn('No subject keys found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Subject Type', 'Subject ID', 'Status', 'Created', 'Erased', 'Entries'],
            array_map(static fn (array $r): array => [
                $r['subject_type'], $r['subject_id'], $r['status'],
                $r['created_at'], $r['erased_at'] ?? '-', (string) $r['entry_count'],
            ], $rows),
        );

        return self::SUCCESS;
    }
}
