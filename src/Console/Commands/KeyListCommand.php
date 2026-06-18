<?php

declare(strict_types=1);

namespace Chronicle\Console\Commands;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Signing\LegacySigningConfigAdapter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Artisan command that lists the signing keys configured in the key ring.
 */
final class KeyListCommand extends Command
{
    protected $signature = 'chronicle:key:list
        {--with-counts : Show per-key checkpoint counts}';

    protected $description = 'List all signing keys in the Chronicle key ring';

    public function handle(): int
    {
        /** @var array<string, mixed> $rawConfig */
        $rawConfig = Config::array('chronicle.signing', []);

        if (LegacySigningConfigAdapter::isLegacy($rawConfig)) {
            $rawConfig = LegacySigningConfigAdapter::adapt($rawConfig);
        }

        /** @var string $activeId */
        $activeId = $rawConfig['active'] ?? '';

        /** @var array<string, array<string, mixed>> $keys */
        $keys = (array) ($rawConfig['keys'] ?? []);

        if ($keys === []) {
            $this->warn('No signing keys configured in chronicle.signing.keys.');

            return self::SUCCESS;
        }

        /** @var bool $withCounts */
        $withCounts = $this->option('with-counts');

        $headers = ['Key ID', 'Algorithm', 'Provider', 'Status'];
        if ($withCounts) {
            $headers[] = 'Checkpoints';
        }

        $rows = [];

        foreach ($keys as $id => $keyConfig) {
            $rawAlgo = $keyConfig['algorithm'] ?? 'unknown';
            $algo = is_string($rawAlgo) ? $rawAlgo : 'unknown';

            $rawProvider = $keyConfig['provider'] ?? 'unknown';
            $providerClass = is_string($rawProvider) ? $rawProvider : 'unknown';
            $provider = class_basename($providerClass);

            $isActive = $id === $activeId;
            $isVerifyOnly = empty($keyConfig['private_key']) && empty($keyConfig['key_arn']);

            if ($isActive) {
                $status = '● ACTIVE';
            } elseif ($isVerifyOnly) {
                $status = 'verify-only';
            } else {
                $status = 'inactive';
            }

            $row = [$id, $algo, $provider, $status];

            if ($withCounts) {
                $row[] = (string) Checkpoint::where('key_id', $id)->count();
            }

            $rows[] = $row;
        }

        $this->newLine();
        $this->info('Chronicle Key Ring');
        $this->table($headers, $rows);

        return self::SUCCESS;
    }
}
