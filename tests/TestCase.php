<?php

namespace Chronicle\Tests;

use Chronicle\ChronicleServiceProvider;
use Chronicle\Storage\EloquentDriver;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ChronicleServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function getEnvironmentSetUp($app): void
    {
        $connection = 'testing';
        $aliasConnection = 'chronicle_testing';
        $driver = (string) env('CHRONICLE_TEST_DB_CONNECTION', 'sqlite');

        $databaseConfig = match ($driver) {
            'mysql' => [
                'driver' => 'mysql',
                'host' => (string) env('CHRONICLE_TEST_DB_HOST', '127.0.0.1'),
                'port' => (int) env('CHRONICLE_TEST_DB_PORT', 3306),
                'database' => (string) env('CHRONICLE_TEST_DB_DATABASE', 'chronicle_test'),
                'username' => (string) env('CHRONICLE_TEST_DB_USERNAME', 'root'),
                'password' => (string) env('CHRONICLE_TEST_DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => (string) env('CHRONICLE_TEST_DB_HOST', '127.0.0.1'),
                'port' => (int) env('CHRONICLE_TEST_DB_PORT', 5432),
                'database' => (string) env('CHRONICLE_TEST_DB_DATABASE', 'chronicle_test'),
                'username' => (string) env('CHRONICLE_TEST_DB_USERNAME', 'postgres'),
                'password' => (string) env('CHRONICLE_TEST_DB_PASSWORD', ''),
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
                'sslmode' => 'prefer',
            ],
            default => [
                'driver' => 'sqlite',
                'database' => (string) env('CHRONICLE_TEST_DB_DATABASE', ':memory:'),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        };

        config()->set("database.connections.{$connection}", $databaseConfig);
        config()->set("database.connections.{$aliasConnection}", $databaseConfig);
        config()->set('database.default', $connection);
        config()->set('chronicle.connection', $connection);

        config()->set('chronicle.signing.private_key', 'RcSfC2MuYTPnkrL/MIA4/l/sAjirGXXIFXZEPokdwh1Lcz+SvNE7bjvgCsDotjnlHfJyZ4XW/kUXemtoyaa92Q==');
        config()->set('chronicle.signing.public_key', 'S3M/krzRO2474ArA6LY55R3ycmeF1v5FF3praMmmvdk=');
    }

    /**
     * Switch the active driver to EloquentDriver for the current test.
     */
    protected function useEloquentDriver(): void
    {
        config(['chronicle.driver' => 'eloquent']);
        app('chronicle')->swapDriver(
            new EloquentDriver
        );
    }
}
