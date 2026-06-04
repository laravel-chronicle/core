<?php

namespace Chronicle\Console\Commands;

use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Signing\LegacySigningConfigAdapter;
use Illuminate\Console\Command;
use RuntimeException;

class KeyRotateCommand extends Command
{
    protected $signature = 'chronicle:key:rotate
        {newKeyId : ID of the signing key to rotate to (must exist in signing.keys)}';

    protected $description = 'Create a boundary checkpoint and print instructions for activating a new signing key';

    public function handle(CheckpointCreator $creator): int
    {
        /** @var string $newKeyId */
        $newKeyId = $this->argument('newKeyId');

        /** @var array<string, mixed> $rawConfig */
        $rawConfig = (array) config('chronicle.signing', []);

        if (LegacySigningConfigAdapter::isLegacy($rawConfig)) {
            $rawConfig = LegacySigningConfigAdapter::adapt($rawConfig);
        }

        /** @var string $activeId */
        $activeId = $rawConfig['active'] ?? '';

        /** @var array<string, array<string, mixed>> $keys */
        $keys = (array) ($rawConfig['keys'] ?? []);

        // Validate key exists in the ring
        if (! array_key_exists($newKeyId, $keys)) {
            $this->error("Key '$newKeyId' is not configured in signing.keys.");
            $available = implode(', ', array_keys($keys));
            $this->line('Available keys: '.$available);

            return self::FAILURE;
        }

        // Validate key is not already active
        if ($activeId === $newKeyId) {
            $this->error("Key '$newKeyId' is already the active key. No rotation needed.");

            return self::FAILURE;
        }

        // Validate key has signing material (not verify-only)
        /** @var array<string, mixed> $targetConfig */
        $targetConfig = $keys[$newKeyId];
        $hasPrivateKey = is_string($targetConfig['private_key'] ?? null) && $targetConfig['private_key'] !== '';
        $hasKeyArn = is_string($targetConfig['key_arn'] ?? null) && $targetConfig['key_arn'] !== '';

        if (! $hasPrivateKey && ! $hasKeyArn) {
            $this->error("Key '$newKeyId' is configured as verify-only (no private_key or key_arn).");
            $this->line("Add signing material to this key's config entry before rotating to it.");

            return self::FAILURE;
        }

        // Create boundary checkpoint at current ledger head (signed with the CURRENT active key)
        $this->line('Creating boundary checkpoint before rotation...');

        try {
            $checkpoint = $creator->create();
        } catch (RuntimeException $e) {
            $this->error('Failed to create boundary checkpoint: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('✓ Boundary checkpoint created');
        $this->line('  ID:        '.$checkpoint->id);
        $this->line('  Algorithm: '.$checkpoint->algorithm);
        $this->line('  Key:       '.$checkpoint->key_id);
        $this->newLine();
        $this->info('Rotation ready. To activate '.$newKeyId.', update your environment:');
        $this->newLine();
        $this->line('  CHRONICLE_ACTIVE_KEY='.$newKeyId);
        $this->newLine();
        $this->line('After deploying the updated environment:');
        $this->line('  php artisan chronicle:key:list');
        $this->line('  php artisan chronicle:verify');

        return self::SUCCESS;
    }
}
