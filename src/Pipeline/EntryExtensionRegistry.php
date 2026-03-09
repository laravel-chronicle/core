<?php

namespace Chronicle\Pipeline;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\PrioritizedEntryExtension;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class EntryExtensionRegistry
{
    /** @var array<int, EntryExtension> */
    protected array $extensions = [];

    public function __construct(
        protected Container $container
    ) {}

    /**
     * @param  EntryExtension|class-string<EntryExtension>  $extension
     *
     * @throws BindingResolutionException
     */
    public function register(EntryExtension|string $extension): void
    {
        if (is_string($extension)) {
            $resolved = $this->container->make($extension);

            if (! $resolved instanceof EntryExtension) {
                throw new InvalidArgumentException(sprintf(
                    'Chronicle extension [%s] must implement %s.',
                    $extension,
                    EntryExtension::class
                ));
            }

            $extension = $resolved;
        }

        $this->extensions[] = $extension;
    }

    public function isEmpty(): bool
    {
        return $this->extensions === [];
    }

    /**
     * @return array<int, EntryExtension>
     */
    public function ordered(): array
    {
        if ($this->extensions === []) {
            return [];
        }

        $indexed = [];

        foreach ($this->extensions as $index => $extension) {
            $priority = $extension instanceof PrioritizedEntryExtension
                ? $extension->priority()
                : 0;

            $indexed[] = [
                'extension' => $extension,
                'stage' => $extension->stage()->value,
                'priority' => $priority,
                'name' => $extension::class,
                'index' => $index,
            ];
        }

        usort($indexed, function (array $left, array $right): int {
            return [$left['stage'], $left['priority'], $left['name'], $left['index']]
                <=> [$right['stage'], $right['priority'], $right['name'], $right['index']];
        });

        return array_map(
            fn (array $item): EntryExtension => $item['extension'],
            $indexed
        );
    }
}
