<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds checkpoint reference to Chronicle entries.
 *
 * Entries after a checkpoint reference the checkpoint
 * that anchors the chain up to that point.
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
        $table = config('chronicle.tables.entries', 'chronicle_entries');

        Schema::connection($this->getConnection())->table($table, function (Blueprint $table) {
            $table->ulid('checkpoint_id')
                ->nullable()
                ->after('chain_hash');

            $table->foreign('checkpoint_id')
                ->references('id')
                ->on(config('chronicle.tables.checkpoints', 'chronicle_checkpoints'))
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $table = config('chronicle.tables.entries', 'chronicle_entries');

        Schema::connection($this->getConnection())->table($table, function (Blueprint $table) {
            $table->dropForeign(['checkpoint_id']);
            $table->dropColumn('checkpoint_id');
        });
    }
};
