<x-admin-layout title="Admin Overview">
    <div style="margin-bottom:1rem;">
        <span class="h-badge h-badge-teal" style="font-size:0.65rem;">ADMIN CONSOLE</span>
        <h1 style="font-size:1.35rem;font-weight:800;color:var(--h-text);margin:0.3rem 0 0;">System Overview</h1>
        <p style="font-size:0.8rem;color:var(--h-text3);margin:0.25rem 0 0;">Users, fleet activity and security signals at a glance.</p>
    </div>

    @include('admin.partials.tabs')

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0.85rem;margin-bottom:1.25rem;">
        @foreach([
            ['Users', $stats['users'], $stats['suspended'].' suspended · '.$stats['admins'].' '.Str::plural('administrator', $stats['admins'])],
            ['Vehicles', $stats['vehicles'], 'registered fleet'],
            ['Diagnostics', $stats['diagnostics'], $stats['diagnostics_7d'].' in last 7 days'],
            ['Failed Logins (24h)', $stats['failed_logins_24h'], $stats['events_24h'].' total events (24h)'],
        ] as [$label, $value, $sub])
            <div class="h-stat-cell">
                <div class="h-stat-value">{{ number_format($value) }}</div>
                <div class="h-stat-label">{{ $label }}</div>
                <div class="h-stat-sub">{{ $sub }}</div>
            </div>
        @endforeach
    </div>

    {{-- ML & IoT Infrastructure Status Banner --}}
    <div class="h-card" style="margin-bottom:1.25rem;padding:1rem 1.25rem;background:linear-gradient(135deg,var(--h-surface) 0%,rgba(2,132,199,0.06) 100%);border:1px solid rgba(56,189,248,0.25);">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:rgba(56,189,248,0.12);border:1px solid rgba(56,189,248,0.3);display:flex;align-items:center;justify-content:center;color:#38bdf8;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                </div>
                <div>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <span style="font-size:0.875rem;font-weight:800;color:var(--h-text);">FastAPI ML Classifier &amp; Telemetry Queue</span>
                        <span class="h-badge {{ !empty($mlInfo['online']) ? 'h-badge-teal' : 'h-badge-green' }}" style="font-size:0.6rem;">
                            {{ !empty($mlInfo['online']) ? 'FASTAPI ONLINE' : 'HEURISTIC ENGINE READY' }}
                        </span>
                    </div>
                    <div style="font-size:0.74rem;color:var(--h-text3);margin-top:0.15rem;">
                        4-Class Supervised Model &middot; {{ $stats['pending_jobs'] }} queue jobs &middot; {{ $stats['failed_jobs'] }} failed
                    </div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:0.6rem;">
                <a href="{{ route('admin.ml.index') }}" class="haven-btn-secondary" style="font-size:0.75rem;padding:0.4rem 0.85rem;">
                    <span>Inspect Model &amp; Datasets &rarr;</span>
                </a>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.25rem;">
        <div class="h-card">
            <h3 style="margin:0 0 0.75rem;font-size:0.9rem;color:var(--h-text);">Security Alerts (24h)</h3>
            @forelse($alerts as $log)
                <div style="display:flex;justify-content:space-between;gap:0.5rem;padding:0.45rem 0;border-bottom:1px solid var(--h-border);font-size:0.78rem;">
                    <span style="color:var(--h-red);font-weight:600;">{{ $log->event }}</span>
                    <span style="color:var(--h-text3);">{{ $log->actor_email ?? $log->ip_address }} · {{ $log->created_at?->diffForHumans() }}</span>
                </div>
            @empty
                <p style="font-size:0.8rem;color:var(--h-text3);margin:0;">No failures in the last 24 hours.</p>
            @endforelse
        </div>

        <div class="h-card">
            <h3 style="margin:0 0 0.75rem;font-size:0.9rem;color:var(--h-text);">Recent Activity</h3>
            @foreach($recentEvents as $log)
                <div style="display:flex;justify-content:space-between;gap:0.5rem;padding:0.45rem 0;border-bottom:1px solid var(--h-border);font-size:0.78rem;">
                    <span style="color:var(--h-text);">{{ $log->event }}</span>
                    <span style="color:var(--h-text3);">{{ $log->actor_name ?? 'guest' }} · {{ $log->created_at?->diffForHumans() }}</span>
                </div>
            @endforeach
            <a href="{{ route('admin.audit.index') }}" style="display:inline-block;margin-top:0.6rem;font-size:0.78rem;color:var(--h-teal);">View full audit log →</a>
        </div>

        <div class="h-card">
            <h3 style="margin:0 0 0.75rem;font-size:0.9rem;color:var(--h-text);">Newest Users</h3>
            @foreach($newUsers as $u)
                <a href="{{ route('admin.users.show', $u) }}" style="display:flex;justify-content:space-between;gap:0.5rem;padding:0.45rem 0;border-bottom:1px solid var(--h-border);font-size:0.78rem;text-decoration:none;">
                    <span style="color:var(--h-text);">{{ $u->name }}</span>
                    <span style="color:var(--h-text3);">{{ $u->email }}</span>
                </a>
            @endforeach
            <a href="{{ route('admin.users.index') }}" style="display:inline-block;margin-top:0.6rem;font-size:0.78rem;color:var(--h-teal);">Manage users →</a>
        </div>
    </div>
</x-admin-layout>

