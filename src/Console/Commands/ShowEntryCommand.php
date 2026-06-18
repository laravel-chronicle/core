<?php

declare(strict_types=1);

namespace Chronicle\Console\Commands;

use Chronicle\Entry\Entry;
use Illuminate\Console\Command;

class ShowEntryCommand extends Command
{
    protected $signature = 'chronicle:show {id : The ULID of the entry to display}';

    protected $description = 'Display the full detail of a single Chronicle entry';

    public function handle(): int
    {
        /** @var string $id */
        $id = $this->argument('id');

        $entry = Entry::find($id);

        if ($entry === null) {
            $this->line("Entry [$id] not found.");

            return self::FAILURE;
        }

        $this->renderEntry($entry);

        return self::SUCCESS;
    }

    protected function renderEntry(Entry $entry): void
    {
        $this->line('Chronicle Entry');
        $this->line('===============');
        $this->newLine();

        $this->line("  ID:               $entry->id");
        $this->line("  Created at:       {$entry->created_at->format('Y-m-d H:i:s T')}");
        $this->newLine();

        $this->line("  Actor type:       $entry->actor_type");
        $this->line("  Actor ID:         $entry->actor_id");
        $this->line("  Action:           $entry->action");
        $this->line("  Subject type:     $entry->subject_type");
        $this->line("  Subject ID:       $entry->subject_id");
        $this->newLine();

        $tags = ! empty($entry->tags) ? implode(', ', $entry->tags) : '(none)';
        $this->line("  Tags:             $tags");
        $this->line('  Correlation ID:   '.($entry->correlation_id ?: '(none)'));
        $this->line('  Checkpoint ID:    '.($entry->checkpoint_id ?: '(none)'));
        $this->newLine();

        $this->line("  Payload hash:     $entry->payload_hash");
        $this->line("  Chain hash:       $entry->chain_hash");
        $this->newLine();

        $this->renderKeyValueSection('Metadata', $entry->metadata ?? []);
        $this->renderContextSection($entry->context ?? []);
        $this->renderDiffSection($entry->diff ?? []);
    }

    /** @param array<string,mixed> $data */
    protected function renderKeyValueSection(string $label, array $data): void
    {
        $this->line("  $label:");

        if (empty($data)) {
            $this->line('    (none)');
        } else {
            foreach ($data as $key => $value) {
                /** @var scalar|null $value */
                $this->line("    $key:  ".$value);
            }
        }

        $this->newLine();
    }

    /** @param array<string,mixed> $context */
    protected function renderContextSection(array $context): void
    {
        $this->line('  Context:');

        if (empty($context)) {
            $this->line('    (none)');
        } else {
            foreach ($this->flattenContext($context) as $key => $value) {
                /** @var scalar|null $value */
                $this->line("    $key:  ".$value);
            }
        }

        $this->newLine();
    }

    /** @param array<string,mixed> $diff */
    protected function renderDiffSection(array $diff): void
    {
        $this->line('  Diff:');

        if (empty($diff)) {
            $this->line('    (none)');
        } else {
            /** @var array<string, int|string>|null $change */
            foreach ($diff as $field => $change) {
                $this->line("    $field:");

                if (is_array($change) && array_key_exists('old', $change) && array_key_exists('new', $change)) {
                    $this->line('      old:  '.$change['old']);
                    $this->line('      new:  '.$change['new']);
                }
            }
        }

        $this->newLine();
    }

    /**
     * Flatten context one level deep using dot notation.
     *
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    protected function flattenContext(array $context): array
    {
        $flat = [];

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $flat[$key.'.'.$subKey] = $subValue;
                }
            } else {
                $flat[$key] = $value;
            }
        }

        return $flat;
    }
}
