<?php

namespace Chronicle\Tests\Feature\Infrastructure;

use Chronicle\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class MigrationRollbackSemanticsTest extends TestCase
{
    public function test_chronicle_migrations_rollback_and_remigrate_cleanly(): void
    {
        $connection = (string) config('database.default');
        $entriesTable = (string) config('chronicle.tables.entries', 'chronicle_entries');
        $checkpointsTable = (string) config('chronicle.tables.checkpoints', 'chronicle_checkpoints');
        $migrationsPath = realpath(__DIR__.'/../../../database/migrations');
        $this->assertNotFalse($migrationsPath);

        $freshExitCode = Artisan::call('migrate:fresh', [
            '--database' => $connection,
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--force' => true,
        ]);
        $this->assertSame(0, $freshExitCode);

        $this->assertTrue(Schema::connection($connection)->hasTable($entriesTable));
        $this->assertTrue(Schema::connection($connection)->hasTable($checkpointsTable));

        $rollbackExitCode = Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--force' => true,
        ]);
        $this->assertSame(0, $rollbackExitCode);

        $this->assertFalse(Schema::connection($connection)->hasTable($entriesTable));
        $this->assertFalse(Schema::connection($connection)->hasTable($checkpointsTable));

        $migrateExitCode = Artisan::call('migrate', [
            '--database' => $connection,
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--force' => true,
        ]);
        $this->assertSame(0, $migrateExitCode);

        $this->assertTrue(Schema::connection($connection)->hasTable($entriesTable));
        $this->assertTrue(Schema::connection($connection)->hasTable($checkpointsTable));
    }
}
