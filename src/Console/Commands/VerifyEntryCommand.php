<?php

namespace Chronicle\Console\Commands;

use Chronicle\Integrity\IntegrityVerifier;
use Chronicle\Models\Entry;
use Illuminate\Console\Command;

/**
 * Verifies the integrity of the Chronicle ledger.
 *
 * This command checks:
 *  - payload hashes
 *  - chain hashes
 *  - checkpoint signatures
 */
class VerifyEntryCommand extends Command
{
    protected $signature = 'chronicle:verify';

    protected $description = 'Verify the integrity of the Chronicle ledger';

    public function handle(IntegrityVerifier $verifier): int
    {
        $this->info('Verifying Chronicle ledger...');
        $this->newLine();
        $this->line('Verifying entries');

        $total = Entry::query()->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $result = $verifier->verify(function (int $processed) use ($bar): void {
            $bar->setProgress($processed);
        });

        $bar->finish();
        $this->newLine();

        if ($result->hasFailed()) {
            $this->newLine();

            $this->error('Integrity violation detected.');

            $this->line('Type: '.$result->failureType());
            $this->line('Entry: '.$result->entryId());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('✓ Chain integrity verified');
        $this->line('✓ Entry count validated');
        $this->line('✓ Dataset boundaries verified');
        $this->newLine();
        $this->line("Entries checked: {$result->checked()}");
        $this->info('Ledger integrity OK');

        return self::SUCCESS;
    }
}
