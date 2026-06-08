<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional progress storage for resumable verification (Phase B, --resume).
 * No behavior reads this table in Phase A; verification falls back gracefully
 * when it is absent.
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
        $table = config('chronicle.tables.verification_runs', 'chronicle_verification_runs');

        Schema::connection($this->getConnection())->create($table, function (Blueprint $t) use ($table) {
            $t->ulid('id')->primary();
            $t->string('mode');
            $t->ulid('last_checkpoint_id')->nullable();
            $t->unsignedBigInteger('verified_count')->default(0);
            $t->string('status')->default('completed');
            $t->timestamps();

            $t->index('last_checkpoint_id', "{$table}_last_checkpoint_id_index");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('chronicle.tables.verification_runs', 'chronicle_verification_runs');

        Schema::connection($this->getConnection())->dropIfExists($table);
    }
};
