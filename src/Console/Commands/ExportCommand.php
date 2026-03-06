<?php

namespace Chronicle\Console\Commands;

use Chronicle\Export\ExportManager;
use Illuminate\Console\Command;

/**
 * Export Chronicle entries to a verifiable dataset.
 */
class ExportCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'chronicle:export
        {path : Directory where the export will be written}';

    /**
     * The command description.
     */
    protected $description = 'Export Chronicle entries as a verifiable dataset';

    /**
     * Execute the console command.
     */
    public function handle(ExportManager $exports): int
    {
        /** @var string $path */
        $path = $this->argument('path');

        $this->info('Exporting Chronicle dataset...');
        $this->newLine();

        $result = $exports->export($path);

        $this->info('Export completed successfully.');
        $this->newLine();

        $this->line("Entries exported: <info>$result->entryCount</info>");
        $this->line("Dataset hash: <commenct>$result->datasetHash</commenct>");
        $this->line("Chain head: <commenct>$result->chainHead</commenct>");

        return self::SUCCESS;
    }
}
