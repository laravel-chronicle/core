<?php

namespace Chronicle\Console;

use Chronicle\Integrity\IntegrityVerifier;
use Chronicle\Models\Entry;
use Illuminate\Console\Command;

/**
 * Command used to verify Chronicle entry integrity.
 */
class VerifyEntryCommand extends Command
{
    protected $signature = 'chronicle:verify';

    protected $description = 'Verify Chronicle entry integrity';

    protected IntegrityVerifier $verifier;

    public function __construct(IntegrityVerifier $verifier)
    {
        parent::__construct();

        $this->verifier = $verifier;
    }

    public function handle(): int
    {
        $this->info('Verifying Chronicle entries...');

        $result = $this->verifier->verify();

        if (! $result->isValid()) {
            $this->error("Integrity violation ({$result->failureType()}) at entry {$result->entryId()}");

            return self::FAILURE;
        }

        $this->info("Chronicle entries verified successfully ({$result->checked()} entries checked)");

        return self::SUCCESS;
    }
}
