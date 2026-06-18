<?php

declare(strict_types=1);

namespace Chronicle\Console\Commands;

use Chronicle\Encryption\KeyEncryptionManager;
use Chronicle\Encryption\SubjectKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Artisan command that re-wraps every subject DEK under the newly configured KEK without touching entries.
 */
final class RotateKekCommand extends Command
{
    protected $signature = 'chronicle:encryption:rotate-kek
        {--old-key= : Previous base64 KEK that wrapped existing DEKs}
        {--old-kek-id=local : kek_id the previous KEK was recorded under}
        {--chunk=500 : Rows per chunk}';

    protected $description = 'Re-wrap every subject DEK under the new (configured) KEK; entries untouched';

    public function handle(KeyEncryptionManager $manager): int
    {
        /** @var string|null $oldKey */
        $oldKey = $this->option('old-key');

        if ($oldKey === null || $oldKey === '') {
            $this->error('--old-key is required (the previous base64 KEK).');

            return self::FAILURE;
        }

        /** @var string $oldKekId */
        $oldKekId = $this->option('old-kek-id');

        $newProvider = $manager->provider();
        $newKekId = $newProvider->kekId();

        if ($oldKekId === $newKekId) {
            $this->error("--old-kek-id ($oldKekId) equals the new kek_id. Set a distinct CHRONICLE_ENCRYPTION_KEK_ID before rotating.");

            return self::FAILURE;
        }

        /** @var class-string|string $providerClass */
        $providerClass = Config::get('chronicle.encryption.kek.provider');

        $oldProvider = $manager->providerFor([
            'provider' => $providerClass,
            'key' => $oldKey,
            'id' => $oldKekId,
        ]);

        $chunk = (int) $this->option('chunk');
        $rotated = 0;
        $skipped = 0;

        SubjectKey::query()
            ->where('status', 'active')
            ->whereNotNull('wrapped_dek')
            ->orderBy('id')
            ->chunkById($chunk, function ($keys) use ($oldProvider, $newProvider, $newKekId, &$rotated, &$skipped) {
                foreach ($keys as $key) {
                    // Idempotent: rows already on the new KEK are skipped.
                    if ($key->kek_id === $newKekId) {
                        $skipped++;

                        continue;
                    }

                    /** @var string $wrapped */
                    $wrapped = $key->wrapped_dek;
                    $dek = $oldProvider->unwrap($wrapped);

                    $key->wrapped_dek = $newProvider->wrap($dek);
                    $key->kek_id = $newKekId;
                    $key->save();

                    sodium_memzero($dek);
                    $rotated++;
                }
            });

        $this->info("KEK rotation complete: $rotated re-wrapped, $skipped already current. Entries untouched.");

        return self::SUCCESS;
    }
}
