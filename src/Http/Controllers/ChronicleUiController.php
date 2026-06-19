<?php

declare(strict_types=1);

namespace Chronicle\Http\Controllers;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Facades\Chronicle;
use Chronicle\Query\LedgerStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\View\View;
use Throwable;

/**
 * Read-only controller backing the Chronicle Blade UI for browsing entries and checkpoints.
 */
final class ChronicleUiController
{
    public function index(Request $request): View
    {
        $query = Chronicle::newEntryQuery();

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->string('actor_id')->toString());
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->string('subject_type')->toString());
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->string('subject_id')->toString());
        }

        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->string('tag')->toString());
        }

        if ($request->filled('from')) {
            try {
                $from = Carbon::parse($request->string('from')->toString(), 'UTC')->startOfDay();

                $query->where('created_at', '>=', $from);
            } catch (Throwable) {
                // discard unparseable date
            }
        }

        if ($request->filled('to')) {
            try {
                $to = Carbon::parse($request->string('to')->toString(), 'UTC')->endOfDay();

                $query->where('created_at', '<=', $to);
            } catch (Throwable) {
                // discard unparseable date
            }
        }

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['asc', 'desc'], true) ? $sort : 'desc';

        $query->orderBy('id', $sort);

        $perPage = Config::integer('chronicle.ui.per_page', 25);

        $entries = $query->paginate($perPage)->withQueryString();

        return view('chronicle::entries.index', [
            'entries' => $entries,
            'filters' => $request->only(['action', 'actor_id', 'subject_type', 'subject_id', 'tag', 'from', 'to', 'sort']),
        ]);
    }

    public function show(string $id): View|RedirectResponse
    {
        if (! preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i', $id)) {
            abort(404);
        }

        $entry = Chronicle::newEntryQuery()->find($id);

        if ($entry === null) {
            return redirect()
                ->route('chronicle.entries.index')
                ->with('error', "Entry [$id] not found.");
        }

        $checkpoint = $entry->checkpoint_id
            ? Checkpoint::find($entry->checkpoint_id)
            : null;

        return view('chronicle::entries.show', [
            'entry' => $entry,
            'checkpoint' => $checkpoint,
        ]);
    }

    public function stats(): View
    {
        $stats = LedgerStats::compute();

        $activityIndexed = collect($stats->dailyActivity())->keyBy('date');
        $activityByDay = collect();

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');

            $activityByDay->push([
                'date' => $date,
                'count' => $activityIndexed->get($date)['count'] ?? 0,
            ]);
        }

        return view('chronicle::stats.index', [
            'total' => $stats->totalEntries(),
            'oldest' => $stats->oldestEntryAt(),
            'newest' => $stats->newestEntryAt(),
            'checkpointCount' => $stats->checkpointCount(),
            'topActions' => collect($stats->topActions()),
            'activityByDay' => $activityByDay,
        ]);
    }
}
