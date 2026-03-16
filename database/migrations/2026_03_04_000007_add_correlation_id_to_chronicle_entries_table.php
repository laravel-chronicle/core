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
            $table->string('correlation_id')
                ->nullable()
                ->after('tags');
        });
    }

    public function down(): void
    {
        $table = config('chronicle.tables.entries', 'chronicle_entries');

        Schema::connection($this->getConnection())->table($table, function (Blueprint $table) {
            $table->dropColumn('correlation_id');
        });
    }
};
