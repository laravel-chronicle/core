<?php

namespace Chronicle\Checkpoint;

use Chronicle\Contracts\SigningProvider;
use Chronicle\Models\Checkpoint;
use Chronicle\Models\Entry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Class CheckpointCreator
 *
 * Responsible for creating cryptographic checkpoints
 * that anchor the Chronicle ledger.
 */
class CheckpointCreator
{
    protected SigningProvider $signer;

    public function __construct(SigningProvider $signer)
    {
        $this->signer = $signer;
    }

    /**
     * Create a checkpoint for the current ledger head.
     *
     * @throws Throwable
     */
    public function create(): Checkpoint
    {
        /** @var string|null $connection */
        $connection = config('chronicle.connection');

        return DB::connection($connection)->transaction(function () {
            $chainHash = Entry::query()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('chain_hash');

            if (! $chainHash) {
                throw new RuntimeException(
                    'Cannot create checkpoint: ledger is empty.'
                );
            }

            $existing = Checkpoint::where('chain_hash', $chainHash)->first();

            if ($existing) {
                return $existing;
            }

            /** @var string $payload */
            $payload = $chainHash;

            $signature = $this->signer->sign($payload);

            return Checkpoint::create([
                'id' => (string) Str::ulid(),
                'chain_hash' => $chainHash,
                'signature' => $signature,
                'algorithm' => $this->signer->algorithm(),
                'key_id' => $this->signer->keyId(),
                'created_at' => now(),
            ]);
        });
    }
}
