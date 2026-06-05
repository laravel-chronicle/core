<?php

namespace Chronicle;

use Chronicle\Console\Commands\CreateCheckpointCommand;
use Chronicle\Console\Commands\ExportCommand;
use Chronicle\Console\Commands\InstallCommand;
use Chronicle\Console\Commands\PruneCommand;
use Chronicle\Console\Commands\ReportCommand;
use Chronicle\Console\Commands\ShowEntryCommand;
use Chronicle\Console\Commands\StatsCommand;
use Chronicle\Console\Commands\VerifyEntryCommand;
use Chronicle\Console\Commands\VerifyExportCommand;
use Chronicle\Context\QueueJobContext;
use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\LedgerReader as LedgerReaderContract;
use Chronicle\Contracts\ReferenceResolver;
use Chronicle\Contracts\SigningProvider;
use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\EloquentLedgerReader;
use Chronicle\Exports\EntryExporter;
use Chronicle\Exports\ExportManager;
use Chronicle\Exports\ExportManifestBuilder;
use Chronicle\Exports\ExportSigner;
use Chronicle\Pipeline\CanonicalizePayload;
use Chronicle\Pipeline\ChainHashEntry;
use Chronicle\Pipeline\EntryExtensionRegistry;
use Chronicle\Pipeline\EntryPipeline;
use Chronicle\Pipeline\HashPayload;
use Chronicle\Pipeline\PersistEntry;
use Chronicle\Pipeline\RunExtensions;
use Chronicle\Reports\ComplianceReport;
use Chronicle\Signing\NullSigningProvider;
use Chronicle\Storage\DriverResolver;
use Chronicle\Storage\QueuedDriver;
use Chronicle\Support\CanonicalPayloadSerializer;
use Chronicle\Support\DefaultReferenceResolver;
use Chronicle\Verification\ExportChainVerifier;
use Chronicle\Verification\ExportVerifier;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Application;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ChronicleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chronicle.php', 'chronicle');

        $this->registerCore();
        $this->registerContracts();
        $this->registerManager();
        $this->registerSigning();
        $this->registerLedgerReader();
        $this->registerExports();
        $this->registerQueueContext();
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->publishConfiguration();
        $this->publishMigrations();
        $this->bootUi();

        $this->registerQueueListeners();

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyEntryCommand::class,
                CreateCheckpointCommand::class,
                ExportCommand::class,
                VerifyExportCommand::class,
                InstallCommand::class,
                ReportCommand::class,
                StatsCommand::class,
                ShowEntryCommand::class,
                PruneCommand::class,
            ]);
        }
    }

    protected function registerCore(): void
    {
        $this->app->singleton(CanonicalPayloadSerializer::class);
        $this->app->singleton(EntryExtensionRegistry::class, function (Application $app) {
            $registry = new EntryExtensionRegistry($app);
            $configured = config('chronicle.extensions', []);

            if (! is_array($configured)) {
                throw new InvalidArgumentException('Chronicle extensions configuration must be an array.');
            }

            foreach ($configured as $extension) {
                if (! is_string($extension) && ! $extension instanceof EntryExtension) {
                    throw new InvalidArgumentException('Chronicle extensions must be class names implementing '.EntryExtension::class.'.');
                }

                /** @var EntryExtension|class-string<EntryExtension> $extension */
                $registry->register($extension);
            }

            return $registry;
        });

        $this->app->singleton(EntryPipeline::class, function (Application $app) {
            return new EntryPipeline([
                $app->make(RunExtensions::class),
                $app->make(CanonicalizePayload::class),
                $app->make(HashPayload::class),
                $app->make(ChainHashEntry::class),
                $app->make(PersistEntry::class),
            ]);
        });

        $this->app->singleton('chronicle.pipeline.pre', function (Application $app) {
            return new EntryPipeline([
                $app->make(RunExtensions::class),
                $app->make(CanonicalizePayload::class),
                $app->make(HashPayload::class),
            ]);
        });

        $this->app->singleton(QueuedDriver::class);
    }

    protected function registerContracts(): void
    {
        $this->app->singleton(DriverResolver::class);
        $this->app->singleton(ReferenceResolver::class, DefaultReferenceResolver::class);

        $this->app->singleton(StorageDriver::class, function (Application $app) {
            $configured = config('chronicle.driver', 'eloquent');
            $driver = is_string($configured) ? $configured : 'eloquent';

            return $app->make(DriverResolver::class)->resolve($driver);
        });
    }

    protected function registerManager(): void
    {
        $this->app->singleton('chronicle', function (Application $app) {
            $prePipeline = $app->make('chronicle.pipeline.pre');
            assert($prePipeline instanceof EntryPipeline);

            return new ChronicleManager(
                resolver: $app->make(ReferenceResolver::class),
                pipeline: $app->make(EntryPipeline::class),
                prePipeline: $prePipeline,
                reader: $app->make(LedgerReaderContract::class),
                drivers: $app->make(DriverResolver::class),
                extensions: $app->make(EntryExtensionRegistry::class),
            );
        });
    }

    protected function registerSigning(): void
    {
        $this->app->singleton(SigningProvider::class, function (Application $app) {
            /** @var array{provider: class-string, private_key: ?string, public_key: ?string, key_id: string} $config */
            $config = $app['config']->get('chronicle.signing', []);

            $providerClass = $config['provider'];

            if (! is_a($providerClass, SigningProvider::class, true)) {
                throw new RuntimeException(
                    "Chronicle signing provider [$providerClass] must implement ".SigningProvider::class.'.'
                );
            }

            try {
                return new $config['provider'](
                    privateKey: $config['private_key'],
                    publicKey: $config['public_key'],
                    keyId: $config['key_id'],
                );
            } catch (Throwable $e) {
                if ($app['config']->get('chronicle.signing.enforce_on_boot', false)
                    && ! $app->environment('testing')
                ) {
                    throw new RuntimeException(
                        'Invalid Chronicle signing configuration. Configure CHRONICLE_PRIVATE_KEY and CHRONICLE_PUBLIC_KEY (or a valid custom signing provider).',
                        0,
                        $e
                    );
                }

                return new NullSigningProvider($e);
            }
        });
    }

    protected function registerLedgerReader(): void
    {
        $this->app->singleton(LedgerReaderContract::class, EloquentLedgerReader::class);
    }

    protected function registerExports(): void
    {
        $this->app->singleton(EntryExporter::class);
        $this->app->singleton(ExportManifestBuilder::class);
        $this->app->singleton(ExportSigner::class);
        $this->app->singleton(ExportVerifier::class);
        $this->app->singleton(ExportChainVerifier::class);
        $this->app->singleton(ComplianceReport::class);

        $this->app->singleton(ExportManager::class, function (Application $app) {
            return new ExportManager(
                $app->make(EntryExporter::class),
                $app->make(ExportManifestBuilder::class),
                $app->make(ExportSigner::class),
            );
        });
    }

    /**
     * @throws BindingResolutionException
     */
    protected function registerQueueListeners(): void
    {
        /** @var Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);

        $events->listen(
            JobProcessing::class,
            function (JobProcessing $event): void {
                /** @var Job $job */
                $job = $event->job;
                $this->app->make(QueueJobContext::class)->set($job);
            }
        );

        foreach ([
            JobProcessed::class,
            JobFailed::class,
            JobExceptionOccurred::class,
        ] as $event) {
            $events->listen(
                $event,
                function (): void {
                    $this->app->make(QueueJobContext::class)->clear();
                }
            );
        }
    }

    protected function registerQueueContext(): void
    {
        $this->app->singleton(QueueJobContext::class);
    }

    protected function publishConfiguration(): void
    {
        $this->publishes([
            __DIR__.'/../config/chronicle.php' => config_path('chronicle.php'),
        ], 'chronicle-config');
    }

    protected function publishMigrations(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'chronicle-migrations');
    }

    protected function bootUi(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'chronicle');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/chronicle'),
        ], 'chronicle-views');

        if (config('chronicle.ui.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/ui.php');
        }
    }
}
