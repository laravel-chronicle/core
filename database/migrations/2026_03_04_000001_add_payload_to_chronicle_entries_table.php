<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add payload column to chronicle_entries table.
 *
 * The payload column stores the canonical serialized
 * entry payload used for hashing and export operations.
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
            $table->json('payload')
                ->nullable()
                ->after('subject_id');
        });
    }

    public function down(): void
    {
        $table = config('chronicle.tables.entries', 'chronicle_entries');

        Schema::connection($this->getConnection())->table($table, function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
