<?php

namespace Chronicle\Query;

use Carbon\CarbonInterface;
use Chronicle\Entry\Entry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Throwable;

class LedgerQuery
{
    protected bool $orderApplied = false;

    /**
     * @param  Builder<Entry>  $query
     */
    public function __construct(
        protected Builder $query,
    ) {
        //
    }

    /**
     * Filter by actor (type and id).
     */
    public function forActor(Model $actor): static
    {
        $this->query->forActor($actor);

        return $this;
    }

    /**
     * Filter by subject (type and id).
     */
    public function forSubject(Model $subject): static
    {
        $this->query->forSubject($subject);

        return $this;
    }

    /**
     * Filter by the exact action string.
     */
    public function action(string $action): static
    {
        $this->query->action($action);

        return $this;
    }

    /**
     * Filter by any of the given action strings.
     *
     * @param  list<string>  $actions
     */
    public function actions(array $actions): static
    {
        $this->query->whereIn('action', $actions);

        return $this;
    }

    /**
     * Filter by correlation ID (exact match).
     */
    public function correlation(string $id): static
    {
        $this->query->correlation($id);

        return $this;
    }

    /**
     * Filter entries belonging to a workflow tree (prefix match).
     */
    public function workflow(string $rootCorrelation): static
    {
        $this->query->workflow($rootCorrelation);

        return $this;
    }

    /**
     * Filter entries whose action starts with the given prefix.
     *
     * Uses a LIKE query: actionPrefix('invoice.') matches
     * 'invoice.created', 'invoice.sent', 'invoice.voided', etc.
     *
     * The prefix is escaped to prevent LIKE injection.
     */
    public function actionPrefix(string $prefix): static
    {
        $escaped = str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $prefix
        );

        $this->query->whereRaw("action LIKE ? ESCAPE '!'", [$escaped.'%']);

        return $this;
    }

    /**
     * Filter entries that carry the given tag (AND with any other tag filters).
     */
    public function withTag(string $tag): static
    {
        $this->query->withTag($tag);

        return $this;
    }

    /**
     * Filter entries that carry ALL the given tags (AND semantics).
     *
     * @param  list<string>  $tags
     */
    public function withTags(array $tags): static
    {
        $this->query->withTags($tags);

        return $this;
    }

    /**
     * Filter entries that carry ANY of the given tags (OR semantics).
     *
     * @param  list<string>  $tags
     */
    public function withAnyTag(array $tags): static
    {
        $this->query->where(function (Builder $q) use ($tags): void {
            foreach ($tags as $tag) {
                $q->orWhere(function (Builder $q) use ($tag): void {
                    $q->whereJsonContains('tags', $tag);
                });
            }
        });

        return $this;
    }

    /**
     * Filter entries created at or after the given date.
     */
    public function since(CarbonInterface|string $date): static
    {
        $this->query->where('created_at', '>=', $this->parseDate($date));

        return $this;
    }

    /**
     * Filter entries created at or before the given date.
     */
    public function until(CarbonInterface|string $date): static
    {
        $this->query->where('created_at', '<=', $this->parseDate($date));

        return $this;
    }

    /**
     * Filter entries within a date range (inclusive).
     */
    public function between(CarbonInterface $start, CarbonInterface $end): static
    {
        $this->query->between($start, $end);

        return $this;
    }

    /**
     * Order results newest-first (descending by id).
     */
    public function latest(): static
    {
        $this->query->orderByDesc('id');
        $this->orderApplied = true;

        return $this;
    }

    /**
     * Order results oldest-first / ledger order (ascending by id).
     */
    public function oldest(): static
    {
        $this->query->orderBy('id');
        $this->orderApplied = true;

        return $this;
    }

    /**
     * Execute and return all matching entries.
     *
     * Defaults to ledger order (oldest-first) if no ordering was applied.
     *
     * @return Collection<int, Entry>
     */
    public function get(): Collection
    {
        $this->applyDefaultOrder();

        return $this->query->get();
    }

    /**
     * Return the first matching entry, or null.
     */
    public function first(): ?Entry
    {
        return $this->query->first();
    }

    /**
     * Cursor-paginate the results.
     *
     * Defaults to ledger order (oldest-first) if no ordering was applied.
     *
     * @return CursorPaginator<int, Entry>
     */
    public function paginate(int $perPage = 50, ?string $cursor = null): CursorPaginator
    {
        $this->applyDefaultOrder();

        return $this->query->cursorPaginate(perPage: $perPage, cursor: $cursor);
    }

    /**
     * Stream results lazily using a database cursor.
     *
     * @return LazyCollection<int, Entry>
     */
    public function stream(): LazyCollection
    {
        return $this->query->cursor();
    }

    /**
     * Count matching entries.
     */
    public function count(): int
    {
        return $this->query->count();
    }

    /**
     * Determine whether any matching entries exist.
     */
    public function exists(): bool
    {
        return $this->query->exists();
    }

    protected function applyDefaultOrder(): void
    {
        if (! $this->orderApplied) {
            $this->query->orderBy('id');
        }
    }

    protected function parseDate(CarbonInterface|string $date): Carbon
    {
        if ($date instanceof CarbonInterface) {
            return Carbon::instance($date);
        }

        try {
            return Carbon::parse($date);
        } catch (Throwable) {
            throw new InvalidArgumentException('Chronicle LedgerQuery: invalid date string.');
        }
    }
}
