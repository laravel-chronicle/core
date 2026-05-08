<?php

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
    }
}
