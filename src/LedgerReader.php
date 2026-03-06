<?php

namespace Chronicle;

use Chronicle\Contracts\LedgerReader as LedgerReaderContract;
use Chronicle\Models\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Default Chronicle ledger reader implementation.
 */
class LedgerReader implements LedgerReaderContract
{
    /**
     * Cursor paginate entries.
     */
    public function paginate(
        int $perPage = 50,
        ?string $cursor = null,
    ): CursorPaginator {
        return Entry::query()
            ->orderBy('id')
            ->cursorPaginate(
                perPage: $perPage,
                cursor: $cursor);
    }

    /**
     * Stream entries lazily.
     */
    public function stream(): LazyCollection
    {
        return Entry::query()
            ->orderBy('id')
            ->cursor();
    }

    /**
     * Fetch entries for an actor.
     */
    public function forActor(Model $actor): Collection
    {
        return Entry::forActor($actor)
            ->latestFirst()
            ->get();
    }

    /**
     * Fetch entries for a subject.
     */
    public function forSubject(Model $subject): Collection
    {
        return Entry::forSubject($subject)
            ->latestFirst()
            ->get();
    }

    /**
     * Fetch entries by action.
     */
    public function action(string $action): Collection
    {
        return Entry::action($action)
            ->latestFirst()
            ->get();
    }

    /**
     * Fetch entries by correlation id.
     */
    public function correlation(string $id): Collection
    {
        return Entry::correlation($id)
            ->latestFirst()
            ->get();
    }
}
