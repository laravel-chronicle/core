<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the chronicle_checkpoints table.
 *
 * Checkpoints anchor the Chronicle ledger by signing
 * a specific chain hash using a cryptographic signature.
 *
 * This prevents attacks with database access from
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
        $configured = Config::get('chronicle.connection');

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = Config::string('chronicle.tables.checkpoints', 'chronicle_checkpoints');

        Schema::connection($this->getConnection())->create($tableName, function (Blueprint $table) use ($tableName) {
            $table->ulid('id')->primary();
            $table->string('chain_hash', 64);
            $table->text('signature');
            $table->string('algorithm')->default('ed25519');
            $table->string('key_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index('chain_hash', "{$tableName}_chain_hash_index");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = Config::string('chronicle.tables.checkpoints', 'chronicle_checkpoints');

        Schema::connection($this->getConnection())->dropIfExists($table);
    }
};
