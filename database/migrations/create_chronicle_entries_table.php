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

        Schema::connection($this->getConnection())->create($tableName, function (Blueprint $table) use ($tableName) {
            $table->ulid('id')->primary();
            $table->string('actor_type');
            $table->string('actor_id');
            $table->string('action');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->json('payload')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->string('chain_hash', 64)->nullable();
            $table->ulid('checkpoint_id')->nullable();
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            $table->string('correlation_id')->nullable();
            $table->json('diff')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('checkpoint_id')
                ->references('id')
                ->on(config('chronicle.tables.checkpoints', 'chronicle_checkpoints'))
                ->nullOnDelete();

            $table->index(['actor_type', 'actor_id'], "{$tableName}_actor_type_actor_id_index");
            $table->index(['subject_type', 'subject_id'], "{$tableName}_subject_type_subject_id_index");
            $table->index('correlation_id', "{$tableName}_correlation_id_index");
            $table->index('action', "{$tableName}_action_index");
            $table->index('created_at', "{$tableName}_created_at_index");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('chronicle.tables.entries', 'chronicle_entries');

        Schema::connection($this->getConnection())->dropIfExists($table);
    }
};
