<?php

namespace Chronicle\Anchoring;

use Chronicle\Contracts\AnchorProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves anchor providers from chronicle.anchoring config, mirroring the
 * signing KeyRing/factory. Anchoring is opt-in (enabled defaults to false).
 */
class AnchorManager
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $config,
    ) {
        //
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    /**
     * @return list<string>
     */
    public function providerNames(): array
    {
        /** @var array<string, mixed> $providers */
        $providers = $this->config['providers'] ?? [];

        return array_keys($providers);
    }

    public function provider(string $name): AnchorProvider
    {
        /** @var array<string, mixed> $providers */
        $providers = $this->config['providers'] ?? [];

        if (! isset($providers[$name]) || ! is_array($providers[$name])) {
            throw new InvalidArgumentException("Unknown Chronicle anchor provider [$name].");
        }

        /** @var array<string, mixed> $providerConfig */
        $providerConfig = $providers[$name];
        $class = $providerConfig['provider'] ?? null;

        if (! is_string($class) || ! is_a($class, AnchorProvider::class, true)) {
            throw new InvalidArgumentException(
                "Chronicle anchor provider [$name] must implement ".AnchorProvider::class.'.'
            );
        }

        /** @var AnchorProvider */
        return $this->container->makeWith($class, ['config' => $providerConfig]);
    }

    /**
     * @return array<string, AnchorProvider>
     */
    public function providers(): array
    {
        $resolved = [];

        foreach ($this->providerNames() as $name) {
            $resolved[$name] = $this->provider($name);
        }

        return $resolved;
    }
}
