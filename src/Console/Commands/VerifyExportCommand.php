<?php

namespace Chronicle\Console\Commands;

use Chronicle\Export\ExportVerifier;
use Illuminate\Console\Command;

/**
 * Verify a Chronicle export dataset.
 */
class VerifyExportCommand extends Command
{
    /**
     * Command signature.
     */
    protected $signature = 'chronicle:verify-export
        {path : Path to the Chronicle export directory}';

    /**
     * Command description.
     */
    protected $description = 'Verify the integrity and signature of a Chronicle export dataset';

    /**
     * Execute the console command.
     */
    public function handle(ExportVerifier $verifier): int
    {
        /** @var string $path */
        $path = $this->argument('path');

        $this->info('Verifying Chronicle export...');
        $this->newLine();

        $result = $verifier->verify($path);

        if (! $result->isValid()) {
            $this->error('Verification failed.');

            $this->line("Reason: <comment>$result->failure</comment>");

            return self::FAILURE;
        }

        $this->info('Export verified successfully.');
        $this->newLine();

        $this->line("Entries: <info>$result->entryCount</info>");
        $this->line("Dataset hash: <comment>$result->datasetHash</comment>");
        $this->line("Chain head: <comment>$result->chainHead</comment>");

        return self::SUCCESS;
    }
}
