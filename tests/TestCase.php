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
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
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
