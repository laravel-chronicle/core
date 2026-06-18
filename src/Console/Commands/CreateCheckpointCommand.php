<?php

declare(strict_types=1);

namespace Chronicle\Console\Commands;

use Chronicle\Anchoring\AnchorManager;
use Chronicle\Anchoring\CheckpointAnchorer;
use Chronicle\Checkpoints\CheckpointCreator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Class CreateCheckpointCommand
 *
 * Creates a cryptographic checkpoint anchoring the current
 * Chronicle ledger head.
 */
class CreateCheckpointCommand extends Command
{
    /**
     * Command signature.
     */
    protected $signature = 'chronicle:checkpoint
        {--anchor : Anchor the new checkpoint synchronously}';

    /**
     * Command description.
     */
    protected $description = 'Create a cryptographic checkpoint for the Chronicle ledger';

    /**
     * Execute the command.
     */
    public function handle(CheckpointCreator $creator): int
    {
        $this->info('Creating Chronicle checkpoint...');

        try {
            $checkpoint = $creator->create();

            $this->info('Checkpoint created successfully.');

            $this->line('ID: '.$checkpoint->id);
            $this->line('Chain Hash: '.$checkpoint->chain_hash);
            $this->line('Algorithm: '.$checkpoint->algorithm);
            $this->line('Key ID: '.$checkpoint->key_id);
            $this->line('Created At: '.$checkpoint->created_at);

            if ($this->option('anchor')) {
                $manager = app(AnchorManager::class);
                $anchorer = app(CheckpointAnchorer::class);

                foreach ($manager->providerNames() as $providerName) {
                    try {
                        $anchorer->anchor($checkpoint, $providerName);
                        $this->line("Anchored via [$providerName].");
                    } catch (Throwable $e) {
                        $this->error("Anchor via [$providerName] failed: {$e->getMessage()}");
                    }
                }
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Checkpoint creation failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
