<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the chronicle_subject_keys table.
 *
 * Stores one wrapped per-subject Data Encryption Key (DEK) per
 * (subject_type, subject_id) - the GDPR erasure unit. Destroying a row's
 * wrapped DEK renders that subject's encrypted payloads permanently
 * unreadable while the ledger still verifies (crypto-shredding).
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
        $configured = Config::get('chronicle.connection');

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = Config::string('chronicle.tables.subject_keys', 'chronicle_subject_keys');

        Schema::connection($this->getConnection())->create($tableName, function (Blueprint $table) use ($tableName) {
            $table->ulid('id')->primary();
            $table->string('subject_type');
            $table->string('subject_id');
            $table->text('wrapped_dek')->nullable();
            $table->string('kek_id');
            $table->string('status')->default('active');
            $table->timestamp('created_at');
            $table->timestamp('erased_at')->nullable();

            $table->unique(['subject_type', 'subject_id'], "{$tableName}_subject_unique");
            $table->index('status', "{$tableName}_status_index");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = Config::string('chronicle.tables.subject_keys', 'chronicle_subject_keys');

        Schema::connection($this->getConnection())->dropIfExists($table);
    }
};
