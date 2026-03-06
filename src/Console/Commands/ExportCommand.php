<?php

namespace Chronicle\Console\Commands;

use Chronicle\Export\ExportManager;
use Illuminate\Console\Command;
use Throwable;

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

        try {
            $result = $exports->export($path);
        } catch (Throwable $e) {
            $this->error('Export failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Export completed successfully.');
        $this->newLine();

        $this->line("Entries exported: <info>$result->entryCount</info>");
        $this->line("Dataset hash: <comment>$result->datasetHash</comment>");
        $this->line("Chain head: <comment>$result->chainHead</comment>");

        return self::SUCCESS;
    }
}
