<?php

declare(strict_types=1);

namespace Chronicle\Entry;

use Chronicle\Contracts\LedgerReader as LedgerReaderContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Default Chronicle ledger reader implementation.
 */
class EloquentLedgerReader implements LedgerReaderContract
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

    /**
     * Fetch entries by workflow.
     *
     * @return Collection<int, Entry>
     */
    public function workflow(string $rootCorrelation): Collection
    {
        return Entry::workflow($rootCorrelation)
            ->latestFirst()
            ->get();
    }

    /**
     * Fetch entries by a single tag.
     *
     * @return Collection<int, Entry>
     */
    public function withTag(string $tag): Collection
    {
        return Entry::withTag($tag)
            ->latestFirst()
            ->get();
    }

    /**
     * Fetch entries by multiple tags.
     *
     * @param  list<string>  $tags
     * @return Collection<int, Entry>
     */
    public function withTags(array $tags): Collection
    {
        return Entry::withTags($tags)
            ->latestFirst()
            ->get();
    }
}
