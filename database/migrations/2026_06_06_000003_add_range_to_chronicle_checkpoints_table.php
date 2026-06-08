<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds range-awareness to chronicle_checkpoints: a head pointer, a cumulative
 * entry count, and a self-link to the previous checkpoint. Also ensures an
 * index exists on chronicle_entries.checkpoint_id (the FK does not create one
 * on every driver).
 *
 * Columns are added without ALTER-added foreign keys: SQLite cannot add FK
 * constraints to an existing table. Linkage is enforced at the model/verifier
 * layer. No artifact format or hash changes.
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
        $checkpoints = config('chronicle.tables.checkpoints', 'chronicle_checkpoints');
        $entries = config('chronicle.tables.entries', 'chronicle_entries');
        $schema = Schema::connection($this->getConnection());

        $schema->table($checkpoints, function (Blueprint $table) use ($checkpoints) {
            $table->ulid('head_id')->nullable()->after('chain_hash');
            $table->unsignedBigInteger('entry_count')->nullable()->after('head_id');
            $table->ulid('previous_checkpoint_id')->nullable()->after('entry_count');

            $table->index('previous_checkpoint_id', "{$checkpoints}_previous_checkpoint_id_index");
            $table->index('head_id', "{$checkpoints}_head_id_index");
        });

        if (! $this->hasIndex($entries, "{$entries}_checkpoint_id_index")) {
            $schema->table($entries, function (Blueprint $table) use ($entries) {
                $table->index('checkpoint_id', "{$entries}_checkpoint_id_index");
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $checkpoints = config('chronicle.tables.checkpoints', 'chronicle_checkpoints');
        $entries = config('chronicle.tables.entries', 'chronicle_entries');
        $schema = Schema::connection($this->getConnection());

        $schema->table($checkpoints, function (Blueprint $table) use ($checkpoints) {
            $table->dropIndex("{$checkpoints}_previous_checkpoint_id_index");
            $table->dropIndex("{$checkpoints}_head_id_index");
            $table->dropColumn(['head_id', 'entry_count', 'previous_checkpoint_id']);
        });

        if ($this->hasIndex($entries, "{$entries}_checkpoint_id_index")) {
            $schema->table($entries, function (Blueprint $table) use ($entries) {
                $table->dropIndex("{$entries}_checkpoint_id_index");
            });
        }
    }

    protected function hasIndex(string $table, string $index): bool
    {
        $schema = Schema::connection($this->getConnection());

        foreach ($schema->getIndexes($table) as $existing) {
            if (($existing['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
