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

    public function up(): void
    {
        $table = config('chronicle.tables.entries', 'chronicle_entries');

        Schema::connection($this->getConnection())->create($table, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->timestamp('recorded_at');
            $table->string('actor_type');
            $table->string('actor_id');
            $table->string('action');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->json('metadata')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        $table = config('chronicle.tables.entries', 'chronicle_entries');

        Schema::connection($this->getConnection())->dropIfExists($table);
    }
};
