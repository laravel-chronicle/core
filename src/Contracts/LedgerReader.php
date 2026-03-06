<?php

namespace Chronicle\Contracts;

use Chronicle\Models\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Provides read access to the Chronicle ledger.
 */
interface LedgerReader
{
    /**
     * Fetch entries using cursor pagination.
     *
     * @return CursorPaginator<int, Entry>
     */
    public function paginate(
        int $perPage = 50,
        ?string $cursor = null,
    ): CursorPaginator;

    /**
     * Stream entries lazily.
     *
     * @return LazyCollection<int, Entry>
     */
    public function stream(): LazyCollection;

    /**
     * Get entries for an actor.
     *
     * @return Collection<int, Entry>
     */
    public function forActor(Model $actor): Collection;

    /**
     * Get entries for a subject.
     *
     * @return Collection<int, Entry>
     */
    public function forSubject(Model $subject): Collection;

    /**
     * Get entries by action.
     *
     * @return Collection<int, Entry>
     */
    public function action(string $action): Collection;

    /**
     * Get entries by correlation id.
     *
     * @return Collection<int, Entry>
     */
    public function correlation(string $id): Collection;
}
