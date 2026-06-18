<?php

declare(strict_types=1);

namespace Chronicle;

use Chronicle\Anchoring\AnchorManager;
use Chronicle\Anchoring\CheckpointAnchorer;
use Chronicle\Console\Commands\AnchorRetryCommand;
use Chronicle\Console\Commands\AnchorVerifyCommand;
use Chronicle\Console\Commands\CheckpointsBackfillCommand;
use Chronicle\Console\Commands\CreateCheckpointCommand;
use Chronicle\Console\Commands\EncryptBackfillCommand;
use Chronicle\Console\Commands\ExportCommand;
use Chronicle\Console\Commands\InstallCommand;
use Chronicle\Console\Commands\KeyGenerateCommand;
use Chronicle\Console\Commands\KeyListCommand;
use Chronicle\Console\Commands\KeyRotateCommand;
use Chronicle\Console\Commands\LegalHoldCommand;
use Chronicle\Console\Commands\PruneCommand;
use Chronicle\Console\Commands\ReportCommand;
use Chronicle\Console\Commands\RotateKekCommand;
use Chronicle\Console\Commands\ShowEntryCommand;
use Chronicle\Console\Commands\StatsCommand;
use Chronicle\Console\Commands\SubjectEraseCommand;
use Chronicle\Console\Commands\SubjectKeysCommand;
use Chronicle\Console\Commands\VerifyEntryCommand;
use Chronicle\Console\Commands\VerifyExportCommand;
use Chronicle\Context\QueueJobContext;
use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\LedgerReader as LedgerReaderContract;
use Chronicle\Contracts\ReferenceResolver;
use Chronicle\Contracts\SigningProvider;
use Chronicle\Contracts\StorageDriver;
use Chronicle\Encryption\KeyEncryptionManager;
use Chronicle\Encryption\PayloadCipher;
use Chronicle\Encryption\SubjectKeyManager;
use Chronicle\Entry\EloquentLedgerReader;
use Chronicle\Exceptions\ChronicleException;
use Chronicle\Exports\EntryExporter;
use Chronicle\Exports\ExportManager;
use Chronicle\Exports\ExportManifestBuilder;
use Chronicle\Exports\ExportSigner;
use Chronicle\Pipeline\CanonicalizePayload;
use Chronicle\Pipeline\ChainHashEntry;
use Chronicle\Pipeline\EncryptPayload;
use Chronicle\Pipeline\EntryExtensionRegistry;
use Chronicle\Pipeline\EntryPipeline;
use Chronicle\Pipeline\HashPayload;
use Chronicle\Pipeline\PersistEntry;
use Chronicle\Pipeline\RunExtensions;
use Chronicle\Reports\ComplianceReport;
use Chronicle\Signing\ConfigKeyRing;
use Chronicle\Signing\KeyRing;
use Chronicle\Signing\LegacySigningConfigAdapter;
use Chronicle\Signing\NullSigningProvider;
use Chronicle\Signing\SigningProviderFactory;
use Chronicle\Storage\DriverResolver;
use Chronicle\Storage\QueuedDriver;
use Chronicle\Support\CanonicalPayloadSerializer;
use Chronicle\Support\DefaultReferenceResolver;
use Chronicle\Verification\ExportChainVerifier;
use Chronicle\Verification\ExportVerifier;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Laravel service provider that registers Chronicle's services, drivers, commands, and queue listeners.
 */
final class ChronicleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chronicle.php', 'chronicle');

        $this->registerCore();
        $this->registerContracts();
        $this->registerManager();
        $this->registerSigning();
        $this->registerEncryption();
        $this->registerAnchoring();
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
                KeyGenerateCommand::class,
                KeyListCommand::class,
                KeyRotateCommand::class,
                CheckpointsBackfillCommand::class,
                AnchorRetryCommand::class,
                AnchorVerifyCommand::class,
                LegalHoldCommand::class,
                SubjectEraseCommand::class,
                SubjectKeysCommand::class,
                RotateKekCommand::class,
                EncryptBackfillCommand::class,
            ]);
        }
    }

    protected function registerCore(): void
    {
        $this->app->singleton(CanonicalPayloadSerializer::class);
        $this->app->singleton(EntryExtensionRegistry::class, function (Application $app) {
            $registry = new EntryExtensionRegistry($app);
            $configured = Config::array('chronicle.extensions', []);

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
                $app->make(EncryptPayload::class),
                $app->make(HashPayload::class),
                $app->make(ChainHashEntry::class),
                $app->make(PersistEntry::class),
            ]);
        });

        $this->app->singleton('chronicle.pipeline.pre', function (Application $app) {
            return new EntryPipeline([
                $app->make(RunExtensions::class),
                $app->make(CanonicalizePayload::class),
                $app->make(EncryptPayload::class),
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
            $driver = Config::string('chronicle.driver', 'eloquent');

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
        $this->app->singleton(SigningProviderFactory::class);

        $this->app->singleton(KeyRing::class, function (Application $app) {
            /** @var array{active: string, enforce_on_boot?: bool, keys: array<string, array<string, mixed>>} $config */
            $config = (array) $app['config']->get('chronicle.signing', []);

            if (LegacySigningConfigAdapter::isLegacy($config)) {
                $config = LegacySigningConfigAdapter::adapt($config);
            }

            return new ConfigKeyRing(
                config: $config,
                factory: $app->make(SigningProviderFactory::class),
            );
        });

        $this->app->singleton(SigningProvider::class, function (Application $app) {
            $enforce = (bool) $app['config']->get('chronicle.signing.enforce_on_boot', false);

            try {
                return $app->make(KeyRing::class)->active();
            } catch (Throwable $e) {
                if ($e instanceof RuntimeException && ! ($e instanceof ChronicleException)) {
                    throw $e;
                }

                if ($enforce && ! $app->environment('testing')) {
                    throw new RuntimeException(
                        'Invalid Chronicle signing configuration. Configure CHRONICLE_PRIVATE_KEY and CHRONICLE_PUBLIC_KEY (or a valid custom signing provider).',
                        0,
                        $e,
                    );
                }

                return new NullSigningProvider($e);
            }
        });
    }

    protected function registerEncryption(): void
    {
        $this->app->singleton(KeyEncryptionManager::class);
        $this->app->singleton(SubjectKeyManager::class);
        $this->app->singleton(PayloadCipher::class);
    }

    protected function registerAnchoring(): void
    {
        $this->app->bind(AnchorManager::class, function (Application $app) {
            /** @var array<string, mixed> $config */
            $config = (array) $app['config']->get('chronicle.anchoring', []);

            return new AnchorManager($app, $config);
        });
        $this->app->singleton(CheckpointAnchorer::class);
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

        if (Config::boolean('chronicle.ui.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/ui.php');
        }
    }
}
