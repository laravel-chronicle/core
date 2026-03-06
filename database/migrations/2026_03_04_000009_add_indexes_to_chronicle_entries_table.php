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
        $tableName = config('chronicle.tables.entries', 'chronicle_entries');

        Schema::connection($this->getConnection())->table($tableName, function (Blueprint $table) use ($tableName) {
            $table->index(['actor_type', 'actor_id'], "{$tableName}_actor_type_actor_id_index");
            $table->index(['subject_type', 'subject_id'], "{$tableName}_subject_type_subject_id_index");
            $table->index('correlation_id', "{$tableName}_correlation_id_index");
            $table->index('action', "{$tableName}_action_index");
            $table->index('created_at', "{$tableName}_created_at_index");
        });
    }

    public function down(): void
    {
        $tableName = config('chronicle.tables.entries', 'chronicle_entries');

        Schema::connection($this->getConnection())->table($tableName, function (Blueprint $table) use ($tableName) {
            $table->dropIndex("{$tableName}_actor_type_actor_id_index");
            $table->dropIndex("{$tableName}_subject_type_subject_id_index");
            $table->dropIndex("{$tableName}_correlation_id_index");
            $table->dropIndex("{$tableName}_action_index");
            $table->dropIndex("{$tableName}_created_at_index");
        });
    }
};
