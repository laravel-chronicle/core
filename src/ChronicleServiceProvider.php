<?php

namespace Chronicle;

use Chronicle\Console\CreateCheckpointCommand;
use Chronicle\Console\VerifyEntryCommand;
use Chronicle\Contracts\ReferenceResolver;
use Chronicle\Contracts\SigningProvider;
use Chronicle\Contracts\StorageDriver;
use Chronicle\Pipeline\CanonicalizePayload;
use Chronicle\Pipeline\ChainHashEntry;
use Chronicle\Pipeline\EntryPipeline;
use Chronicle\Pipeline\HashPayload;
use Chronicle\Pipeline\PersistEntry;
use Chronicle\Serialization\CanonicalPayloadSerializer;
use Chronicle\Storage\ArrayDriver;
use Chronicle\Storage\EloquentDriver;
use Chronicle\Storage\NullDriver;
use Chronicle\Support\DefaultReferenceResolver;
use Chronicle\Tests\Fakes\FakeSigningProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Class ChronicleServiceProvider
 *
 * Registers Chronicle services within Laravel service container.
 *
 * Responsibilities:
 *
 *  - Bind Chronicle core services
 *  - Register default implementations for contracts
 *  - Publish configuration and migrations
 *  - Register Artisan commands
 *
 * The provider is automatically discovered via Composer.
 */
class ChronicleServiceProvider extends ServiceProvider
{
    /**
     * Register Chronicle services in the container.
     *
     * This method binds Chronicle's core components
     * so they can be resolved via dependency injection.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chronicle.php', 'chronicle');

        $this->app->singleton(CanonicalPayloadSerializer::class);

        $this->app->singleton(EntryPipeline::class, function ($app) {
            return new EntryPipeline([
                $app->make(CanonicalizePayload::class),
                $app->make(HashPayload::class),
                $app->make(ChainHashEntry::class),
                $app->make(PersistEntry::class),
            ]);
        });

        $this->registerContracts();

        $this->registerChronicleManager();

        $this->app->singleton(SigningProvider::class, function ($app) {
            if ($app->environment('testing')) {
                return new FakeSigningProvider;
            }
            $config = $app['config']['chronicle.signing'];

            return new $config['provider'](
                privateKey: $config['private_key'],
                publicKey: $config['public_key'],
                keyId: $config['key_id'],
            );
        });
    }

    /**
     * Bootstrap Chronicle services
     *
     * This method handles tasks that require the application
     * to be fully booted, such as publishing configuration
     * and migrations.
     */
    public function boot(): void
    {
        $this->publishConfiguration();

        $this->publishMigrations();

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyEntryCommand::class,
                CreateCheckpointCommand::class,
            ]);
        }
    }

    /**
     * Register Chronicle contract implementations.
     *
     * These bindings define the default behavior of Chronicle
     * while still allowing users to override implementations.
     */
    protected function registerContracts(): void
    {
        $this->app->singleton(StorageDriver::class, function ($app) {
            $configuredDriver = config('chronicle.driver');
            $driver = is_string($configuredDriver) ? $configuredDriver : 'eloquent';

            return match ($driver) {
                'eloquent' => $app->make(EloquentDriver::class),
                'array' => $app->make(ArrayDriver::class),
                'null' => $app->make(NullDriver::class),
                default => throw new InvalidArgumentException(
                    sprintf('Unsupported Chronicle driver [%s].', $driver)
                ),
            };
        });

        $this->app->singleton(ReferenceResolver::class, DefaultReferenceResolver::class);
    }

    /**
     * Register the Chronicle manager.
     *
     * The manager is the primary entry point used by
     * the Chronicle facade and application code.
     */
    protected function registerChronicleManager(): void
    {
        $this->app->singleton('chronicle', function ($app) {
            return new ChronicleManager(
                resolver: $app->make(ReferenceResolver::class),
                pipeline: $app->make(EntryPipeline::class),
            );
        });
    }

    /**
     * Publish Chronicle configuration file.
     *
     * Allows developers to customize Chronicle behavior.
     */
    protected function publishConfiguration(): void
    {
        $this->publishes([
            __DIR__.'/../config/chronicle.php' => config_path('chronicle.php'),
        ], 'chronicle-config');
    }

    /**
     * Publish Chronicle database migrations.
     *
     * The migrations create the Chronicle ledger tables.
     */
    protected function publishMigrations(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'chronicle-migrations');
    }
}
