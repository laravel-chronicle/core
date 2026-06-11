<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Chronicle - {{ $title ?? 'Ledger' }}</title>
        <style>
            :root {
                --chr-bg:         #f8fafc;
                --chr-surface:    #ffffff;
                --chr-border:     #e2e8f0;
                --chr-text:       #1e293b;
                --chr-text-muted: #64748b;
                --chr-accent:     #6366f1;
                --chr-accent-fg:  #ffffff;
                --chr-danger:     #ef4444;
                --chr-success:    #22c55e;
                --chr-radius:     0.375rem;
                --chr-font:       system-ui, -apple-system, sans-serif;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: var(--chr-font); background: var(--chr-bg); color: var(--chr-text); font-size: 0.875rem; }
            a { color: var(--chr-accent); text-decoration: none; }
            a:hover { text-decoration: underline; }
            .chr-nav { background: var(--chr-surface); border-bottom: 1px solid var(--chr-border); padding: 0.75rem 1.5rem; display: flex; gap: 1.5rem; align-items: center; }
            .chr-nav strong { color: var(--chr-text); margin-right: auto; }
            .chr-container { max-width: 1280px; margin: 0 auto; padding: 1.5rem; }
            .chr-card { background: var(--chr-surface); border: 1px solid var(--chr-border); border-radius: var(--chr-radius); padding: 1rem; }
            .chr-table { width: 100%; border-collapse: collapse; }
            .chr-table th { text-align: left; font-weight: 600; color: var(--chr-text-muted); padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--chr-border); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
            .chr-table td { padding: 0.625rem 0.75rem; border-bottom: 1px solid var(--chr-border); vertical-align: top; }
            .chr-table tr:last-child td { border-bottom: none; }
            .chr-table tbody tr:hover { background: var(--chr-bg); }
            .chr-badge { display: inline-block; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; background: var(--chr-bg); border: 1px solid var(--chr-border); color: var(--chr-text-muted); }
            .chr-hash { font-family: ui-monospace, monospace; font-size: 0.75rem; color: var(--chr-text-muted); }
            .chr-filter-bar { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; align-items: flex-end; }
            .chr-filter-bar input, .chr-filter-bar select { padding: 0.375rem 0.625rem; border: 1px solid var(--chr-border); border-radius: var(--chr-radius); font-size: 0.8rem; background: var(--chr-surface); color: var(--chr-text); }
            .chr-btn { padding: 0.375rem 0.875rem; border-radius: var(--chr-radius); font-size: 0.8rem; cursor: pointer; border: 1px solid var(--chr-border); background: var(--chr-surface); color: var(--chr-text); }
            .chr-btn-primary { background: var(--chr-accent); color: var(--chr-accent-fg); border-color: var(--chr-accent); }
            .chr-flash-error { background: #fef2f2; border: 1px solid #fecaca; color: var(--chr-danger); padding: 0.75rem 1rem; border-radius: var(--chr-radius); margin-bottom: 1rem; }
            .chr-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
            .chr-stat-card { background: var(--chr-surface); border: 1px solid var(--chr-border); border-radius: var(--chr-radius); padding: 1rem; }
            .chr-stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--chr-text-muted); margin-bottom: 0.25rem; }
            .chr-stat-value { font-size: 1.5rem; font-weight: 700; color: var(--chr-text); }
            .chr-sparkline { display: flex; align-items: flex-end; gap: 2px; height: 48px; }
            .chr-sparkline-bar { flex: 1; background: var(--chr-accent); opacity: 0.7; border-radius: 2px 2px 0 0; min-height: 2px; }
            .chr-kv { display: grid; grid-template-columns: max-content 1fr; gap: 0.25rem 1rem; font-size: 0.8rem; }
            .chr-kv dt { color: var(--chr-text-muted); font-weight: 500; }
            .chr-kv dd { color: var(--chr-text); word-break: break-all; }
            .chr-section { margin-bottom: 1.5rem; }
            .chr-section-title { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--chr-text-muted); margin-bottom: 0.5rem; }
        </style>
    </head>
    <body>
        <nav class="chr-nav">
            <strong>Chronicle</strong>
            <a href="{{ route('chronicle.entries.index') }}">Ledger</a>
            <a href="{{ route('chronicle.stats') }}">Stats</a>
        </nav>
        <div>
            @if(session('error'))
                <div class="chr-container">
                    <div class="chr-flash-error">{{ session('error') }}</div>
                </div>
            @endif
            @yield('content')
        </div>
    </body>
</html>
