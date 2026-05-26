<?php

namespace Chronicle\Testing;

use Chronicle\ChronicleManager;
use Chronicle\Contracts\StorageDriver;
use Chronicle\Storage\ArrayDriver;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;

class ChronicleAssertions
{
    public function __construct(
        protected ArrayDriver $driver,
    ) {
        //
    }

    /**
     * Return all entries recorded since fake() was called.
     *
     * @return Collection<int|string, mixed>
     */
    public function entries(): Collection
    {
        return $this->driver->allEntries();
    }

    /**
     * Assert that at least one entry was recorded.
     * If a filter callable is given, at least one entry must match it.
     */
    public function assertRecorded(?callable $filter = null): static
    {
        $entries = $this->driver->allEntries();

        if ($filter !== null) {
            $entries = $entries->filter($filter);
        }

        Assert::assertTrue(
            $entries->isNotEmpty(),
            $filter !== null
                ? 'Failed asserting that a Chronicle entry matching the filter was recorded.'
                : 'Failed asserting that any Chronicle entry was recorded.'
        );

        return $this;
    }

    /**
     * Assert an exact number of entries were recorded.
     * If a filter is given, count only matching entries.
     */
    public function assertRecordedCount(int $count, ?callable $filter = null): static
    {
        $entries = $this->driver->allEntries();

        if ($filter !== null) {
            $entries = $entries->filter($filter);
        }

        Assert::assertCount(
            $count,
            $entries->all(),
            "Failed asserting that exactly $count Chronicle entries were recorded."
        );

        return $this;
    }

    /**
     * Assert that no entries were recorded.
     */
    public function assertNothingRecorded(): static
    {
        Assert::assertCount(
            0,
            $this->driver->allEntries()->all(),
            'Failed asserting that no Chronicle entries were recorded.'
        );

        return $this;
    }

    /**
     * Assert that no entry matching the filter was recorded.
     */
    public function assertNotRecorded(callable $filter): static
    {
        $matching = $this->driver->allEntries()->filter($filter);

        Assert::assertTrue(
            $matching->isEmpty(),
            'Failed asserting that no Chronicle entry matching the filter was recorded.'
        );

        return $this;
    }

    /**
     * Restore the real StorageDriver after a fake() call.
     *
     * Clears the ArrayDriver container binding and resets the ChronicleManager
     * so the next Chronicle call resolves the driver from config.
     *
     * Call in afterEach() when test ordering matters:
     *
     *   afterEach(fn () => $fake->restore());
     */
    public function restore(): void
    {
        app()->forgetInstance(StorageDriver::class);

        /** @var ChronicleManager $manager */
        $manager = app('chronicle');
        $manager->resetDriver();
    }
}
