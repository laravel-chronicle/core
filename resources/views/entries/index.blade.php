@extends('chronicle::layout', ['title' => 'Ledger'])

@section('content')
    <div class="chr-container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h1 style="font-size:1.25rem; font-weight:700;">Ledger</h1>
            <a href="{{ route('chronicle.stats') }}" class="chr-btn">Stats →</a>
        </div>

        <form method="GET" action="{{ route('chronicle.entries.index') }}" class="chr-filter-bar chr-card" style="padding:0.75rem;">
            <input type="text" name="action" placeholder="Action (e.g. invoice.sent)"
                   value="{{ $filters['action'] ?? '' }}" style="min-width:200px;">
            <input type="text" name="actor_id" placeholder="Actor ID"
                   value="{{ $filters['actor_id'] ?? '' }}">
            <input type="text" name="subject_type" placeholder="Subject type"
                   value="{{ $filters['subject_type'] ?? '' }}">
            <input type="text" name="subject_id" placeholder="Subject ID"
                   value="{{ $filters['subject_id'] ?? '' }}">
            <input type="text" name="tag" placeholder="Tag"
                   value="{{ $filters['tag'] ?? '' }}">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" title="From date">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" title="To date">
            <select name="sort">
                <option value="desc" @selected(($filters['sort'] ?? 'desc') === 'desc')>Newest first</option>
                <option value="asc" @selected(($filters['sort'] ?? 'desc') === 'asc')>Oldest first</option>
            </select>
            <button type="submit" class="chr-btn chr-btn-primary">Filter</button>
            @if(array_filter($filters))
                <a href="{{ route('chronicle.entries.index') }}" class="chr-btn">Clear</a>
            @endif
        </form>

        <p style="color:var(--chr-text-muted); margin-bottom:0.75rem; font-size:0.8rem;">
            {{ number_format($entries->total()) }} {{ Str::plural('entry', $entries->total()) }}
        </p>

        <div class="chr-card" style="padding:0; overflow:hidden;">
            @if($entries->isEmpty())
                <p style="padding:2rem; text-align:center; color:var(--chr-text-muted);">No entries found.</p>
            @else
                <table class="chr-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Actor</th>
                            <th>Subject</th>
                            <th>Tags</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $entry)
                            <tr>
                                <td><span class="chr-badge">{{ $entry->action }}</span></td>
                                <td style="color:var(--chr-text-muted);">
                            <span title="{{ $entry->actor_type }}">
                                {{ class_basename($entry->actor_type) }}
                                <span style="font-weight:600; color:var(--chr-text);">#{{ $entry->actor_id }}</span>
                            </span>
                                </td>
                                <td style="color:var(--chr-text-muted);">
                            <span title="{{ $entry->subject_type }}">
                                {{ class_basename($entry->subject_type) }}
                                <span style="font-weight:600; color:var(--chr-text);">#{{ $entry->subject_id }}</span>
                            </span>
                                </td>
                                <td>
                                    @foreach(($entry->tags ?? []) as $tag)
                                        <span class="chr-badge">{{ $tag }}</span>
                                    @endforeach
                                </td>
                                <td class="chr-hash" title="{{ $entry->created_at->toIso8601String() }}">
                                    {{ $entry->created_at->format('Y-m-d H:i:s') }}
                                </td>
                                <td>
                                    <a href="{{ route('chronicle.entries.show', $entry->id) }}"
                                       style="font-size:0.75rem;">View →</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if($entries->hasPages())
            <div style="margin-top:1rem;">
                {{ $entries->links() }}
            </div>
        @endif
    </div>
@endsection
