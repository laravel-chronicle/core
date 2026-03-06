<?php

namespace Chronicle\Storage;

use Chronicle\Contracts\StorageDriver;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class DriverResolver
{
    /**
     * @var array<int, string>
     */
    private const RESERVED_DRIVERS = [
        'eloquent',
        'array',
        'null',
    ];

    /**
     * @var array<string, callable>
     */
    protected array $extensions = [];

    public function __construct(
        protected Container $container
    ) {}

    public function extend(string $driver, callable $factory): void
    {
        if (in_array($driver, self::RESERVED_DRIVERS, true)) {
            throw new InvalidArgumentException(
                sprintf('Chronicle driver [%s] is reserved and cannot be overridden.', $driver)
            );
        }

        if (array_key_exists($driver, $this->extensions)) {
            throw new InvalidArgumentException(
                sprintf('Chronicle driver [%s] is already registered.', $driver)
            );
        }

        $this->extensions[$driver] = $factory;
    }

    public function resolve(string $driver): StorageDriver
    {
        if (array_key_exists($driver, $this->extensions)) {
            $resolved = $this->container->call($this->extensions[$driver]);

            if (! $resolved instanceof StorageDriver) {
                throw new InvalidArgumentException(
                    sprintf('Chronicle driver [%s] must resolve to %s.', $driver, StorageDriver::class)
                );
            }

            return $resolved;
        }

        return match ($driver) {
            'eloquent' => $this->container->make(EloquentDriver::class),
            'array' => $this->container->make(ArrayDriver::class),
            'null' => $this->container->make(NullDriver::class),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported Chronicle driver [%s].', $driver)
            ),
        };
    }
}
