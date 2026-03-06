<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the chronicle_checkpoints table.
 *
 * Checkpoints anchor the Chronicle ledger by signing
 * a specific chain hash using a cryptographic signature.
 *
 * This prevents attackes with database access from
 * recomputing the entire chain after tampering.
 */
return new class extends Migration
{
    /**
     * The database connection to use.
     *
     * Reads from config so Chronicle can use a dedicated connection.
     */
    public function getConnection(): ?string
    {
        return config('chronicle.connection');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('chronicle.tables.checkpoints', 'chronicle_checkpoints');

        Schema::connection($this->getConnection())->create($tableName, function (Blueprint $table) use ($tableName) {
            $table->ulid('id')->primary();

            /**
             * Chain hash anchored by this checkpoint.
             */
            $table->string('chain_hash', 64);

            /**
             * Signature generated from the chain hash.
             */
            $table->text('signature');

            /**
             * Cryptographic algorithm used.
             *
             * Example:
             *  - ed25519
             *  - rsa
             *  - kms
             */
            $table->string('algorithm')->default('ed25519');

            /**
             * Optional key identifier used for signing.
             *
             * Useful when rotating signing keys.
             */
            $table->string('key_id')->nullable();

            /**
             * Optional metadata.
             *
             * Can include external anchor references
             * or cloud verification data.
             */
            $table->json('metadata')->nullable();

            /**
             * Timestamp of checkpoint creation.
             */
            $table->timestamp('created_at');

            $table->index('chain_hash', "{$tableName}_chain_hash_index");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('chronicle.tables.checkpoints', 'chronicle_checkpoints');

        Schema::connection($this->getConnection())->dropIfExists($table);
    }
};
