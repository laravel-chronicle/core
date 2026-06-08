<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-anchor receipts for checkpoints (one row per provider per checkpoint).
 * Created fresh, so an inline FK to chronicle_checkpoints is safe on every driver
 * (mirrors the chronicle_entries -> chronicle_checkpoints FK). Anchoring behavior
 * is added in Phase C; this is storage only.
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
        $table = config('chronicle.tables.checkpoint_anchors', 'chronicle_checkpoint_anchors');
        $checkpoints = config('chronicle.tables.checkpoints', 'chronicle_checkpoints');

        Schema::connection($this->getConnection())->create($table, function (Blueprint $t) use ($table, $checkpoints) {
            $t->ulid('id')->primary();
            $t->ulid('checkpoint_id');
            $t->string('provider');
            $t->string('reference')->nullable();
            $t->text('proof')->nullable();
            $t->string('status')->default('pending'); // pending|anchored|failed
            $t->timestamp('anchored_at')->nullable();
            $t->timestamp('created_at');

            $t->index('checkpoint_id', "{$table}_checkpoint_id_index");
            $t->index(['provider', 'status'], "{$table}_provider_status_index");

            $t->foreign('checkpoint_id')
                ->references('id')
                ->on($checkpoints)
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('chronicle.tables.checkpoint_anchors', 'chronicle_checkpoint_anchors');

        Schema::connection($this->getConnection())->dropIfExists($table);
    }
};
