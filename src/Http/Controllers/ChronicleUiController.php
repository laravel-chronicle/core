<?php

namespace Chronicle\Http\Controllers;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Entry\Entry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class ChronicleUiController extends Controller
{
    public function __construct()
    {
        /** @var list<string> $middleware */
        $middleware = config('chronicle.ui.middleware', ['web', 'auth']);

        $this->middleware($middleware);
    }

    public function index(Request $request): View
    {
        $query = Entry::query();

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

        /** @var int $perPage */
        $perPage = config('chronicle.ui.per_page', 25);

        $entries = $query->paginate($perPage)->withQueryString();

        /** @var view-string $view */
        $view = 'chronicle::entries.index';

        return view($view, [
            'entries' => $entries,
            'filters' => $request->only(['action', 'actor_id', 'subject_type', 'subject_id', 'tag', 'from', 'to', 'sort']),
        ]);
    }

    public function show(string $id): View|RedirectResponse
    {
        $entry = Entry::find($id);

        if ($entry === null) {
            return redirect()
                ->route('chronicle.entries.index')
                ->with('error', "Entry [$id] not found.");
        }

        $checkpoint = $entry->checkpoint_id
            ? Checkpoint::find($entry->checkpoint_id)
            : null;

        /** @var view-string $view */
        $view = 'chronicle::entries.show';

        return view($view, [
            'entry' => $entry,
            'checkpoint' => $checkpoint,
        ]);
    }

    public function stats(): View
    {
        /** @var string|null $connection */
        $connection = config('chronicle.connection');

        /** @var string $entriesTable */
        $entriesTable = config('chronicle.tables.entries', 'chronicle_entries');

        /** @var string $checkpointsTable */
        $checkpointsTable = config('chronicle.tables.checkpoints', 'chronicle_checkpoints');

        $db = DB::connection($connection);

        /** @var object{total: int, oldest: string|null, newest: string|null}|null $aggregate */
        $aggregate = $db->table($entriesTable)
            ->selectRaw('COUNT(*) as total, MIN(created_at) as oldest, MAX(created_at) as newest')
            ->first();

        $topActions = $db->table($entriesTable)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $checkpointCount = $db->table($checkpointsTable)->count();

        $dailyActivity = $db->table($entriesTable)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $activityByDay = collect();

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');

            $activityByDay->push([
                'date' => $date,
                'count' => $dailyActivity->get($date)->count ?? 0,
            ]);
        }

        /** @var view-string $view */
        $view = 'chronicle::stats.index';

        return view($view, [
            'total' => $aggregate->total ?? 0,
            'oldest' => $aggregate?->oldest ? Carbon::parse($aggregate->oldest) : null,
            'newest' => $aggregate?->newest ? Carbon::parse($aggregate->newest) : null,
            'checkpointCount' => $checkpointCount,
            'topActions' => $topActions,
            'activityByDay' => $activityByDay,
        ]);
    }
}
