<?php

declare(strict_types=1);

namespace Chronicle\Signing;

use Chronicle\Contracts\SigningProvider;
use Chronicle\Exceptions\UnknownSigningKeyException;

/**
 * KeyRing implementation backed by the chronicle.signing config (keys plus the active key id).
 */
final class ConfigKeyRing implements KeyRing
{
    /** @var array<string, SigningProvider> */
    protected array $resolved = [];

    /**
     * @param  array{active: string, enforce_on_boot?: bool, keys: array<string, array<string, mixed>>}  $config
     */
    public function __construct(
        protected readonly array $config,
        protected readonly SigningProviderFactory $factory,
    ) {
        //
    }

    public function active(): SigningProvider
    {
        return $this->resolveById((string) $this->config['active']);
    }

    public function resolve(string $algorithm, ?string $keyId): SigningProvider
    {
        foreach ($this->config['keys'] as $id => $keyConfig) {
            if (($keyConfig['algorithm'] ?? null) === $algorithm && (string) $id === (string) $keyId) {
                return $this->resolveById((string) $id);
            }
        }

        throw new UnknownSigningKeyException(sprintf(
            'No signing key configured for algorithm "%s" and key ID "%s".',
            $algorithm,
            $keyId ?? 'null',
        ));
    }

    /**
     * @return array<string, SigningProvider>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->config['keys'] as $id => $keyConfig) {
            $rawAlgorithm = $keyConfig['algorithm'] ?? null;
            $algorithm = is_string($rawAlgorithm) ? $rawAlgorithm : 'unknown';
            $result["$algorithm:$id"] = $this->resolveById((string) $id);
        }

        return $result;
    }

    protected function resolveById(string $id): SigningProvider
    {
        if (! isset($this->resolved[$id])) {
            $keyConfig = $this->config['keys'][$id] ?? null;

            if ($keyConfig === null) {
                throw new UnknownSigningKeyException(
                    "No signing key configured with ID \"$id\"."
                );
            }

            $this->resolved[$id] = $this->factory->make($id, (array) $keyConfig);
        }

        return $this->resolved[$id];
    }
}
