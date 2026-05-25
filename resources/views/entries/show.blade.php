@extends('chronicle::layout', ['title' => 'Entry ' . Str::limit($entry->id, 8, '')])

@section('content')
    <div class="chr-container">
        <div style="margin-bottom:1rem;">
            <a href="{{ route('chronicle.entries.index') }}" style="font-size:0.8rem;">← Back to Ledger</a>
        </div>

        <h1 style="font-size:1.25rem; font-weight:700; margin-bottom:1.5rem;">Entry Detail</h1>

        <div class="chr-section">
            <div class="chr-section-title">Identity</div>
            <div class="chr-card">
                <dl class="chr-kv">
                    <dt>ID</dt>             <dd class="chr-hash">{{ $entry->id }}</dd>
                    <dt>Action</dt>         <dd><span class="chr-badge">{{ $entry->action }}</span></dd>
                    <dt>Actor</dt>          <dd>{{ $entry->actor_type }} <strong>#{{ $entry->actor_id }}</strong></dd>
                    <dt>Subject</dt>        <dd>{{ $entry->subject_type }} <strong>#{{ $entry->subject_id }}</strong></dd>
                    <dt>Created at</dt>     <dd>{{ $entry->created_at->format('Y-m-d H:i:s T') }}</dd>
                    <dt>Correlation ID</dt> <dd class="chr-hash">{{ $entry->correlation_id ?: '—' }}</dd>
                    @if($entry->tags)
                        <dt>Tags</dt>
                        <dd>
                            @foreach($entry->tags as $tag)
                                <span class="chr-badge">{{ $tag }}</span>
                            @endforeach
                        </dd>
                    @endif
                </dl>
            </div>
        </div>

        <div class="chr-section">
            <div class="chr-section-title">Chain Integrity</div>
            <div class="chr-card">
                <dl class="chr-kv">
                    <dt>Payload hash</dt> <dd class="chr-hash">{{ $entry->payload_hash }}</dd>
                    <dt>Chain hash</dt>   <dd class="chr-hash">{{ $entry->chain_hash }}</dd>
                    @if($checkpoint)
                        <dt>Checkpoint</dt>   <dd class="chr-hash">{{ $checkpoint->id }}</dd>
                        <dt>Signed</dt>       <dd>{{ $checkpoint->algorithm }} · key {{ $checkpoint->key_id }}</dd>
                    @else
                        <dt>Checkpoint</dt>   <dd style="color:var(--chr-text-muted);">None</dd>
                    @endif
                </dl>
            </div>
        </div>

        @if(!empty($entry->diff))
            <div class="chr-section">
                <div class="chr-section-title">Changes</div>
                <div class="chr-card" style="padding:0; overflow:hidden;">
                    <table class="chr-table">
                        <thead>
                            <tr><th>Field</th><th>Before</th><th>After</th></tr>
                        </thead>
                        <tbody>
                            @foreach($entry->diff as $field => $change)
                                <tr>
                                    <td style="font-weight:600;">{{ $field }}</td>
                                    <td style="color:var(--chr-danger);">{{ is_array($change['old'] ?? null) ? json_encode($change['old']) : ($change['old'] ?? '—') }}</td>
                                    <td style="color:var(--chr-success);">{{ is_array($change['new'] ?? null) ? json_encode($change['new']) : ($change['new'] ?? '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="chr-section">
            <div class="chr-section-title">Payload</div>
            <div class="chr-card">
                <pre class="chr-hash" style="white-space:pre-wrap; word-break:break-all;">{{ json_encode($entry->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>

        @if(!empty($entry->metadata))
            <div class="chr-section">
                <div class="chr-section-title">Metadata</div>
                <div class="chr-card">
                    <dl class="chr-kv">
                        @foreach($entry->metadata as $key => $value)
                            <dt>{{ $key }}</dt>
                            <dd>{{ is_array($value) ? json_encode($value) : $value }}</dd>
                        @endforeach
                    </dl>
                </div>
            </div>
        @endif

        @if(!empty($entry->context))
            <div class="chr-section">
                <div class="chr-section-title">Context</div>
                <div class="chr-card">
                    <dl class="chr-kv">
                        @foreach($entry->context as $resolver => $values)
                            @if(is_array($values))
                                @foreach($values as $key => $value)
                                    <dt>{{ $resolver }}.{{ $key }}</dt>
                                    <dd>{{ is_array($value) ? json_encode($value) : ($value ?? '—') }}</dd>
                                @endforeach
                            @else
                                <dt>{{ $resolver }}</dt>
                                <dd>{{ $values ?? '—' }}</dd>
                            @endif
                        @endforeach
                    </dl>
                </div>
            </div>
        @endif
    </div>
@endsection
