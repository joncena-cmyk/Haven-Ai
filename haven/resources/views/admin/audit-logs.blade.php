<x-admin-layout title="Admin Security & Audit Console">
    {{-- Page Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
        <div>
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.3rem;">
                <span class="h-badge h-badge-teal" style="font-size:0.65rem;">ISO/IEC 27001 A.8.15</span>
                <span class="h-badge h-badge-gray" style="font-size:0.65rem;">ADMIN FORENSIC CONSOLE</span>
            </div>
            <h1 style="font-size:1.35rem;font-weight:800;color:var(--h-text);margin:0;">Security & Activity Audit Console</h1>
            <p style="font-size:0.8rem;color:var(--h-text3);margin:0.25rem 0 0;">
                System-wide forensic event log covering authentication, failed logins, vehicle provisioning, and ESP32 IoT telemetry keys.
            </p>
        </div>
        <div style="display:flex;align-items:center;gap:0.6rem;">
            <a href="{{ route('admin.audit.export') }}" class="haven-action-btn haven-action-btn-secondary" style="text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;font-size:0.78rem;padding:0.45rem 0.85rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export Forensic CSV
            </a>
        </div>
    </div>

    @include('admin.partials.tabs')

    {{-- KPI Summary Strip --}}
    <div class="h-stat-row" style="margin-bottom:1.25rem;">
        <div class="h-stat-cell">
            <div class="h-stat-value">{{ number_format($stats['total_events']) }}</div>
            <div class="h-stat-label">Total Audit Events</div>
            <div class="h-stat-sub h-stat-sub-ok">Immutable forensic records</div>
        </div>
        <div class="h-stat-cell">
            <div class="h-stat-value" style="color:{{ $stats['failed_events'] > 0 ? 'var(--h-red)' : 'var(--h-text)' }};">
                {{ number_format($stats['failed_events']) }}
            </div>
            <div class="h-stat-label">Security Alerts / Failed Auth</div>
            <div class="h-stat-sub">
                @if($stats['failed_events'] > 0)
                    <a href="{{ route('admin.audit.index', ['status' => 'failure']) }}" style="color:var(--h-red);text-decoration:none;">Filter failed events →</a>
                @else
                    Zero failed attempts recorded
                @endif
            </div>
        </div>
        <div class="h-stat-cell">
            <div class="h-stat-value">{{ number_format($stats['total_users']) }}</div>
            <div class="h-stat-label">Driver Accounts</div>
            <div class="h-stat-sub" style="color:var(--h-teal);">{{ $stats['admin_users'] }} {{ Str::plural('administrator', $stats['admin_users']) }}</div>
        </div>
        <div class="h-stat-cell">
            <div class="h-stat-value">{{ number_format($stats['total_vehicles']) }}</div>
            <div class="h-stat-label">Fleet Vehicles</div>
            <div class="h-stat-sub">Provisioned with ESP32 keys</div>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div style="background:var(--h-surface);border:1px solid var(--h-border);border-radius:var(--h-radius-lg);padding:1rem;margin-bottom:1.25rem;">
        <form method="GET" action="{{ route('admin.audit.index') }}" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:flex-end;">
            <div style="flex:1;min-width:220px;">
                <label style="display:block;font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.25rem;font-weight:600;">Search User, Event, or IP</label>
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="e.g. auth.login, 127.0.0.1, user@email.com"
                       style="width:100%;background:var(--h-surface2);border:1px solid var(--h-border);color:var(--h-text);border-radius:var(--h-radius);padding:0.42rem 0.7rem;font-size:0.8rem;outline:none;">
            </div>
            <div style="min-width:170px;">
                <label style="display:block;font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.25rem;font-weight:600;">Event Type</label>
                <select name="event" style="width:100%;background:var(--h-surface2);border:1px solid var(--h-border);color:var(--h-text);border-radius:var(--h-radius);padding:0.42rem 0.7rem;font-size:0.8rem;outline:none;">
                    <option value="">All Events</option>
                    @foreach($eventTypes as $evt)
                        <option value="{{ $evt }}" @selected($filters['event'] === $evt)>{{ $evt }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:140px;">
                <label style="display:block;font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.25rem;font-weight:600;">Status</label>
                <select name="status" style="width:100%;background:var(--h-surface2);border:1px solid var(--h-border);color:var(--h-text);border-radius:var(--h-radius);padding:0.42rem 0.7rem;font-size:0.8rem;outline:none;">
                    <option value="">All Statuses</option>
                    <option value="success" @selected($filters['status'] === 'success')>Success</option>
                    <option value="failure" @selected($filters['status'] === 'failure')>Failed / Alert</option>
                </select>
            </div>
            <div style="display:flex;gap:0.5rem;">
                <button type="submit" class="haven-btn-primary" style="width:auto;padding:0.42rem 0.9rem;font-size:0.78rem;">Filter Logs</button>
                @if($filters['q'] !== '' || $filters['event'] !== '' || $filters['status'] !== '')
                    <a href="{{ route('admin.audit.index') }}" class="haven-action-btn haven-action-btn-secondary" style="text-decoration:none;padding:0.42rem 0.75rem;font-size:0.78rem;">Reset</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Forensic Audit Log Table --}}
    <div style="background:var(--h-surface);border:1px solid var(--h-border);border-radius:var(--h-radius-lg);padding:1.25rem;margin-bottom:1.5rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.85rem;">
            <h2 style="font-size:0.95rem;font-weight:700;color:var(--h-text);margin:0;">Forensic Event Stream</h2>
            <span style="font-size:0.74rem;color:var(--h-text3);">Showing {{ $auditLogs->firstItem() ?? 0 }}–{{ $auditLogs->lastItem() ?? 0 }} of {{ $auditLogs->total() }}</span>
        </div>

        @if($auditLogs->isEmpty())
            <p style="font-size:0.82rem;color:var(--h-text3);padding:1rem 0;margin:0;">No audit events match your current filters.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.78rem;text-align:left;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--h-border);color:var(--h-text3);font-size:0.7rem;text-transform:uppercase;">
                            <th style="padding:0.55rem 0.75rem;">Timestamp</th>
                            <th style="padding:0.55rem 0.75rem;">User</th>
                            <th style="padding:0.55rem 0.75rem;">Event</th>
                            <th style="padding:0.55rem 0.75rem;">Status</th>
                            <th style="padding:0.55rem 0.75rem;">IP Address</th>
                            <th style="padding:0.55rem 0.75rem;">Context / Metadata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($auditLogs as $log)
                            <tr style="border-bottom:1px solid var(--h-border);">
                                <td style="padding:0.6rem 0.75rem;white-space:nowrap;color:var(--h-text3);font-size:0.73rem;">
                                    <div>{{ $log->created_at?->format('Y-m-d H:i:s') }}</div>
                                    <div style="font-size:0.68rem;opacity:0.75;">{{ $log->created_at?->diffForHumans() }}</div>
                                </td>
                                <td style="padding:0.6rem 0.75rem;">
                                    @if($log->actor_name)
                                        <div style="font-weight:600;color:var(--h-text);">
                                            {{ $log->actor_name }}
                                            @if($log->actor_type === 'admin')
                                                <span class="h-badge h-badge-teal" style="font-size:0.58rem;margin-left:0.3rem;">ADMIN</span>
                                            @endif
                                        </div>
                                        <div style="font-size:0.7rem;color:var(--h-text3);">{{ $log->actor_email }}</div>
                                    @else
                                        <span style="color:var(--h-text3);font-style:italic;">Unauthenticated / System</span>
                                    @endif
                                </td>
                                <td style="padding:0.6rem 0.75rem;">
                                    <code style="color:var(--h-teal);font-size:0.74rem;font-weight:600;">{{ $log->event }}</code>
                                </td>
                                <td style="padding:0.6rem 0.75rem;">
                                    @if($log->status === 'success')
                                        <span class="h-badge h-badge-green" style="font-size:0.62rem;">Success</span>
                                    @elseif($log->status === 'failure')
                                        <span class="h-badge h-badge-red" style="font-size:0.62rem;">Failed</span>
                                    @else
                                        <span class="h-badge h-badge-amber" style="font-size:0.62rem;">{{ ucfirst($log->status) }}</span>
                                    @endif
                                </td>
                                <td style="padding:0.6rem 0.75rem;font-family:monospace;font-size:0.73rem;color:var(--h-text2);">
                                    {{ $log->ip_address ?? 'N/A' }}
                                </td>
                                <td style="padding:0.6rem 0.75rem;font-family:monospace;font-size:0.71rem;color:var(--h-text3);max-width:340px;word-break:break-word;">
                                    @if(!empty($log->details))
                                        {{ json_encode($log->details, JSON_UNESCAPED_SLASHES) }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:1rem;">
                {{ $auditLogs->links() }}
            </div>
        @endif
    </div>

    {{-- Dedicated Administrator Accounts (read-only; provisioned via `php artisan admin:create`) --}}
    <div style="background:var(--h-surface);border:1px solid var(--h-border);border-radius:var(--h-radius-lg);padding:1.25rem;">
        <div style="margin-bottom:0.85rem;">
            <h2 style="font-size:0.95rem;font-weight:700;color:var(--h-text);margin:0;">Administrator Accounts</h2>
            <p style="font-size:0.75rem;color:var(--h-text3);margin:0.2rem 0 0;">ISO/IEC 27001 A.5.15 Access Control — administrators are dedicated identities, separate from driver accounts. Provision with <code>php artisan admin:create</code>.</p>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.78rem;text-align:left;">
                <thead>
                    <tr style="border-bottom:1px solid var(--h-border);color:var(--h-text3);font-size:0.7rem;text-transform:uppercase;">
                        <th style="padding:0.55rem 0.75rem;">Administrator</th>
                        <th style="padding:0.55rem 0.75rem;">Last Sign-in</th>
                        <th style="padding:0.55rem 0.75rem;">Created</th>
                        <th style="padding:0.55rem 0.75rem;">Audit Events</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($admins as $a)
                        <tr style="border-bottom:1px solid var(--h-border);">
                            <td style="padding:0.6rem 0.75rem;">
                                <div style="font-weight:600;color:var(--h-text);">
                                    {{ $a->name }}
                                    @if($a->id === Auth::guard('admin')->id())
                                        <span class="h-badge h-badge-teal" style="font-size:0.58rem;margin-left:0.3rem;">YOU</span>
                                    @endif
                                </div>
                                <div style="font-size:0.7rem;color:var(--h-text3);">{{ $a->email }}</div>
                            </td>
                            <td style="padding:0.6rem 0.75rem;color:var(--h-text2);">{{ $a->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td style="padding:0.6rem 0.75rem;color:var(--h-text2);">{{ $a->created_at?->format('M d, Y') }}</td>
                            <td style="padding:0.6rem 0.75rem;color:var(--h-text2);">{{ $a->audit_logs_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>