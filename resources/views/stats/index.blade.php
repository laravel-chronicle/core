@extends('chronicle::layout', ['title' => 'Stats'])

@section('content')
    <div class="chr-container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h1 style="font-size:1.25rem; font-weight:700;">Stats</h1>
            <a href="{{ route('chronicle.entries.index') }}" class="chr-btn">← Ledger</a>
        </div>

        <div class="chr-stats-grid">
            <div class="chr-stat-card">
                <div class="chr-stat-label">Total Entries</div>
                <div class="chr-stat-value">{{ number_format($total) }}</div>
            </div>
            <div class="chr-stat-card">
                <div class="chr-stat-label">Checkpoints</div>
                <div class="chr-stat-value">{{ number_format($checkpointCount) }}</div>
            </div>
            <div class="chr-stat-card">
                <div class="chr-stat-label">First Entry</div>
                <div class="chr-stat-value" style="font-size:0.9rem;">
                    {{ $oldest?->format('Y-m-d') ?? '—' }}
                </div>
            </div>
            <div class="chr-stat-card">
                <div class="chr-stat-label">Latest Entry</div>
                <div class="chr-stat-value" style="font-size:0.9rem;">
                    {{ $newest?->format('Y-m-d') ?? '—' }}
                </div>
            </div>
        </div>

        <div class="chr-section">
            <div class="chr-section-title">Activity — last 30 days</div>
            <div class="chr-card">
                @php $maxCount = $activityByDay->max('count') ?: 1; @endphp
                <div class="chr-sparkline" title="Daily entry count over last 30 days">
                    @foreach($activityByDay as $day)
                        @php $height = max(4, (int) round(($day['count'] / $maxCount) * 48)); @endphp
                        <div class="chr-sparkline-bar"
                             style="height:{{ $height }}px;"
                             title="{{ $day['date'] }}: {{ number_format($day['count']) }}"></div>
                    @endforeach
                </div>
                <p style="font-size:0.7rem; color:var(--chr-text-muted); margin-top:0.5rem;">
                    {{ $activityByDay->sum('count') }} entries in the last 30 days
                </p>
            </div>
        </div>

        @if($topActions->isNotEmpty())
            <div class="chr-section">
                <div class="chr-section-title">Top Actions</div>
                <div class="chr-card" style="padding:0; overflow:hidden;">
                    <table class="chr-table">
                        <thead>
                            <tr><th>Action</th><th style="text-align:right;">Count</th><th style="width:40%;">Share</th></tr>
                        </thead>
                        <tbody>
                            @php $topTotal = $topActions->sum('count') ?: 1; @endphp
                            @foreach($topActions as $row)
                                @php $pct = round(($row->count / $topTotal) * 100); @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('chronicle.entries.index', ['action' => $row->action]) }}"
                                           class="chr-badge">{{ $row->action }}</a>
                                    </td>
                                    <td style="text-align:right; font-weight:600;">{{ number_format($row->count) }}</td>
                                    <td>
                                        <div style="background:var(--chr-border); border-radius:9999px; height:6px; overflow:hidden;">
                                            <div style="width:{{ $pct }}%; background:var(--chr-accent); height:100%;"></div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
