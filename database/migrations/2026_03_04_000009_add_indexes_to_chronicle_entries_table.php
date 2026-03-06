<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->index(['actor_type', 'actor_id']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('correlation_id');
            $table->index('action');
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        $table = config('chronicle.tables.entries', 'chronicle_entries');

        Schema::connection($this->getConnection())->table($table, function (Blueprint $table) {
            $table->dropIndex(['actor_type', 'actor_id']);
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex('correlation_id');
            $table->dropIndex('action');
            $table->dropIndex('created_at');
        });
    }
};
