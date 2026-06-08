<?php

namespace Chronicle\Checkpoints;

use Chronicle\Contracts\SigningProvider;
use Chronicle\Entry\Entry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
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
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('chain_hash');

            if (! is_string($chainHash)) {
                throw new RuntimeException(
                    'Cannot create checkpoint: ledger is empty.'
                );
            }

            $existing = Checkpoint::where('chain_hash', $chainHash)->first();

            if ($existing) {
                return $existing;
            }

            $id = (string) Str::ulid();
            $createdAt = now();

            $signaturePayload = $this->signaturePayload(
                id: $id,
                chainHash: $chainHash,
                algorithm: $this->signer->algorithm(),
                keyId: $this->signer->keyId(),
                createdAt: $createdAt->getTimestamp(),
            );

            $signature = $this->signer->sign($signaturePayload);

            return Checkpoint::create([
                'id' => $id,
                'chain_hash' => $chainHash,
                'signature' => $signature,
                'algorithm' => $this->signer->algorithm(),
                'key_id' => $this->signer->keyId(),
                'created_at' => $createdAt,
            ]);
        });
    }

    /**
     * The exact bytes a checkpoint signature covers. Shared with IntegrityVerifier
     * so signing and verification never drift.
     *
     * @throws JsonException
     */
    public static function signaturePayload(
        string $id,
        string $chainHash,
        string $algorithm,
        ?string $keyId,
        int $createdAt,
    ): string {
        return json_encode([
            'id' => $id,
            'chain_hash' => $chainHash,
            'algorithm' => $algorithm,
            'key_id' => $keyId,
            'created_at' => $createdAt,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
