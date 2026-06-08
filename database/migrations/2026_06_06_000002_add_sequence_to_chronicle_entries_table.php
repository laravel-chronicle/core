<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the monotonic `sequence` column to an EXISTING chronicle_entries table
 * and backfills it in current chain order (ascending id). Fresh installs already
 * receive `sequence` from the create migration, so this migration no-ops there.
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
        $connection = $this->getConnection();
        $schema = Schema::connection($connection);

        if ($schema->hasColumn($table, 'sequence')) {
            return; // fresh install - create migration already handled it
        }

        $schema->table($table, function (Blueprint $t) {
            $t->unsignedBigInteger('sequence')->nullable()->after('chain_hash');
        });

        $seq = 0;

        DB::connection($connection)
            ->table($table)
            ->orderBy('id')
            ->select('id')
            ->each(function (object $row) use (&$seq, $connection, $table) {
                $seq++;
                DB::connection($connection)
                    ->table($table)
                    ->where('id', $row->id)
                    ->update(['sequence' => $seq]);
            });

        $schema->table($table, function (Blueprint $t) use ($table) {
            $t->unique('sequence', "{$table}_sequence_unique");
            $t->unique('chain_hash', "{$table}_chain_hash_unique");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('chronicle.tables.entries', 'chronicle_entries');
        $schema = Schema::connection($this->getConnection());

        if (! $schema->hasColumn($table, 'sequence')) {
            return;
        }

        $schema->table($table, function (Blueprint $t) use ($table) {
            $t->dropUnique("{$table}_sequence_unique");
            $t->dropUnique("{$table}_chain_hash_unique");
            $t->dropColumn('sequence');
        });
    }
};
