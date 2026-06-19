<?php

declare(strict_types=1);

namespace Chronicle\Entry;

use Chronicle\Contracts\LedgerReader as LedgerReaderContract;
use Chronicle\Facades\Chronicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Default Chronicle ledger reader implementation.
 */
final class EloquentLedgerReader implements LedgerReaderContract
{
    /**
     * Cursor paginate entries.
     */
    public function paginate(
        int $perPage = 50,
        ?string $cursor = null,
    ): CursorPaginator {
        return Chronicle::newEntryQuery()
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
        return Chronicle::newEntryQuery()
            ->orderBy('id')
            ->cursor();
    }

    /**
     * Fetch entries for an actor.
     */
    public function forActor(Model $actor): Collection
    {
        return Chronicle::newEntryQuery()
            ->forActor($actor)
            ->latestFirst()
            ->get();
    }

    /**
     * Fetch entries for a subject.
     */
    public function forSubject(Model $subject): Collection
    {
        return Chronicle::newEntryQuery()
            ->forSubject($subject)
            ->latestFirst()
            ->get();
    }

    /**
     * Fetch entries by action.
     */
    public function action(string $action): Collection
    {
        return Chronicle::newEntryQuery()
            ->action($action)
            ->latestFirst()
            ->get();
    }

    /**
     * Fetch entries by correlation id.
     */
    public function correlation(string $id): Collection
    {
        return Chronicle::newEntryQuery()
            ->correlation($id)
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
        return Chronicle::newEntryQuery()
            ->workflow($rootCorrelation)
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
        return Chronicle::newEntryQuery()
            ->withTag($tag)
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
        return Chronicle::newEntryQuery()
            ->withTags($tags)
            ->latestFirst()
            ->get();
    }
}
