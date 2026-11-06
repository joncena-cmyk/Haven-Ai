<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 shadow sm:rounded-lg" style="background:var(--h-surface);border:1px solid var(--h-border);border-radius:var(--h-radius-lg);">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 shadow sm:rounded-lg" style="background:var(--h-surface);border:1px solid var(--h-border);border-radius:var(--h-radius-lg);">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 shadow sm:rounded-lg" style="background:var(--h-surface);border:1px solid var(--h-border);border-radius:var(--h-radius-lg);">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>

            {{-- ════ ISO/SAE 21434 IoT Hardware Device Keys ════ --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg" style="background:var(--h-surface);border:1px solid var(--h-border);border-radius:var(--h-radius-lg);">

                {{-- Section Header --}}
                <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.35rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                        <rect x="2" y="7" width="3" height="10" rx="1"/><rect x="19" y="7" width="3" height="10" rx="1"/>
                        <rect x="7" y="2" width="10" height="3" rx="1"/><rect x="7" y="19" width="10" height="3" rx="1"/>
                        <circle cx="12" cy="12" r="3"/><line x1="5" y1="12" x2="9" y2="12"/><line x1="15" y1="12" x2="19" y2="12"/>
                        <line x1="12" y1="5" x2="12" y2="9"/><line x1="12" y1="15" x2="12" y2="19"/>
                    </svg>
                    <h3 style="font-size:0.95rem;font-weight:700;color:var(--h-text);margin:0;">ESP32 IoT Hardware &amp; Device Keys</h3>
                    <span class="h-badge h-badge-teal" style="font-size:0.62rem;">ISO/SAE 21434 §9</span>
                </div>
                <p style="font-size:0.75rem;color:var(--h-text3);margin-bottom:1.1rem;">
                    Each vehicle uses a unique 32-character hardware key to authenticate ESP32 wireless telemetry. Copy it into your firmware or regenerate if compromised.
                </p>

                {{-- Flash: key regenerated --}}
                @if(session('key_regenerated'))
                    <div style="background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);border-radius:var(--h-radius);padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.78rem;color:var(--h-teal);display:flex;align-items:center;gap:0.5rem;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        {{ session('key_regenerated') }}
                    </div>
                @endif
                @if(session('thresholds_saved'))
                    <div style="background:var(--h-green-bg);border:1px solid var(--h-green);border-radius:var(--h-radius);padding:0.65rem 1rem;margin-bottom:1rem;font-size:0.78rem;color:var(--h-green);display:flex;align-items:center;gap:0.5rem;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        {{ session('thresholds_saved') }}
                    </div>
                @endif

                @if($vehicles->isEmpty())
                    <p style="font-size:0.8rem;color:var(--h-text3);">No vehicles registered yet. <a href="{{ route('vehicles.create') }}" style="color:var(--h-teal);">Register a vehicle</a> to generate its IoT device key.</p>
                @else
                    <div style="display:flex;flex-direction:column;gap:1.1rem;">
                        @foreach($vehicles as $veh)
                            @php
                                $thresholds = $veh->alert_thresholds ?? [];
                                $isHighlighted = session('key_vehicle_id') == $veh->id;
                            @endphp
                            <div data-vehicle-id="{{ $veh->id }}"
                                 style="background:var(--h-surface2);border:1px solid {{ $isHighlighted ? 'rgba(2,132,199,0.5)' : 'var(--h-border)' }};border-radius:var(--h-radius-lg);overflow:hidden;">

                                {{-- Vehicle Header Bar --}}
                                <div style="padding:0.7rem 1rem;border-bottom:1px solid var(--h-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
                                    <div style="display:flex;align-items:center;gap:0.6rem;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v9a2 2 0 0 1-2 2h-3"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>
                                        </svg>
                                        <span style="font-size:0.85rem;font-weight:700;color:var(--h-text);">{{ $veh->display_name }}</span>
                                        <span style="font-size:0.7rem;color:var(--h-text3);">ID #{{ $veh->id }}</span>
                                        @if($veh->plate_number)
                                            <span class="h-badge h-badge-gray" style="font-size:0.6rem;">{{ $veh->plate_number }}</span>
                                        @endif
                                    </div>
                                    {{-- Live ESP32 Status dot --}}
                                    @php
                                        $vehTelemetry = \Illuminate\Support\Facades\Cache::get('telemetry:live:' . $veh->id)
                                            ?? ((($latestTelemetry['vehicle_id'] ?? null) == $veh->id) ? $latestTelemetry : null);
                                        $vehTs = $vehTelemetry['timestamp'] ?? $vehTelemetry['received_at'] ?? null;
                                        $isOnline = !empty($vehTelemetry['online'])
                                            && !empty($vehTs)
                                            && abs(\Illuminate\Support\Carbon::parse($vehTs)->diffInSeconds(now())) <= 20;
                                        $rawKey = (string) ($veh->device_key ?? '');
                                        $maskedKey = strlen($rawKey) > 12
                                            ? substr($rawKey, 0, 12) . str_repeat('•', 16)
                                            : ($rawKey ?: '(none)');
                                    @endphp
                                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.15rem;">
                                        <div style="display:flex;align-items:center;gap:0.4rem;">
                                            <span class="vehicle-status-dot" style="width:8px;height:8px;border-radius:50%;background:{{ $isOnline ? 'var(--h-green)' : 'var(--h-text3)' }};box-shadow:{{ $isOnline ? '0 0 6px var(--h-green)' : 'none' }};display:inline-block;flex-shrink:0;transition:background 0.3s;"></span>
                                            <span class="vehicle-status-label" style="font-size:0.7rem;color:{{ $isOnline ? 'var(--h-green)' : 'var(--h-text3)' }};font-family:monospace;">{{ $isOnline ? 'Live (Streaming)' : ($vehTelemetry['last_seen_human'] ?? 'Standby / Offline') }}</span>
                                        </div>
                                        @if($isOnline && !empty($vehTelemetry['device_id']))
                                            <div style="font-size:0.65rem;color:var(--h-text3);display:flex;align-items:center;gap:0.3rem;">
                                                <span style="color:var(--h-teal);font-family:monospace;">{{ $vehTelemetry['device_id'] }}</span>
                                                <span>•</span>
                                                <span>5 Sensors Active</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div style="padding:0.85rem 1rem;display:flex;flex-direction:column;gap:0.85rem;">

                                    {{-- ── Device Key Row ── --}}
                                    <div>
                                        <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.35rem;font-weight:600;">ESP32 Device Key</div>
                                        <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                                            <code id="dkey-{{ $veh->id }}"
                                                  data-raw="{{ $rawKey }}"
                                                  data-masked="{{ $maskedKey }}"
                                                  data-visible="false"
                                                  style="background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);color:var(--h-teal);padding:0.3rem 0.7rem;border-radius:4px;font-size:0.74rem;font-family:monospace;letter-spacing:0.03em;word-break:break-all;">{{ $maskedKey }}</code>
                                            <button type="button"
                                                    onclick="havenToggleKey('{{ $veh->id }}')"
                                                    id="toggle-btn-{{ $veh->id }}"
                                                    class="haven-action-btn haven-action-btn-secondary"
                                                    style="font-size:0.72rem;padding:0.28rem 0.65rem;cursor:pointer;display:flex;align-items:center;gap:0.3rem;">
                                                Show
                                            </button>
                                            <button type="button"
                                                    onclick="havenCopyKey('{{ $veh->id }}')"
                                                    id="copy-btn-{{ $veh->id }}"
                                                    class="haven-action-btn haven-action-btn-secondary"
                                                    style="font-size:0.72rem;padding:0.28rem 0.65rem;cursor:pointer;display:flex;align-items:center;gap:0.3rem;">
                                                <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                Copy Key
                                            </button>
                                            {{-- Regenerate Key --}}
                                            <button type="button"
                                                    onclick="havenConfirmRegen({{ $veh->id }},'{{ addslashes($veh->display_name) }}')"
                                                    class="haven-action-btn haven-action-btn-danger"
                                                    style="font-size:0.72rem;padding:0.28rem 0.65rem;cursor:pointer;display:flex;align-items:center;gap:0.3rem;">
                                                <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.5 15a9 9 0 1 1-2.7-6.4L23 10"/></svg>
                                                Regenerate
                                            </button>
                                            {{-- Hidden form for regenerate POST --}}
                                            <form id="regen-form-{{ $veh->id }}" method="POST" action="{{ route('vehicles.regenerate-key', $veh) }}" style="display:none;">
                                                @csrf
                                            </form>
                                        </div>
                                        <p style="font-size:0.68rem;color:var(--h-text3);margin-top:0.3rem;">
                                            ⚠ Regenerating will immediately disconnect your ESP32 until you flash the new key into its firmware.
                                        </p>
                                    </div>

                                    {{-- ── Sensor Alert Thresholds ── --}}
                                    <div>
                                        <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.5rem;font-weight:600;display:flex;align-items:center;gap:0.4rem;">
                                            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                            Sensor Alert Thresholds
                                        </div>
                                        <form method="POST" action="{{ route('vehicles.thresholds', $veh) }}">
                                            @csrf
                                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.5rem;margin-bottom:0.6rem;">
                                                <label style="display:flex;flex-direction:column;gap:0.2rem;">
                                                    <span style="font-size:0.68rem;color:var(--h-text3);">Max Temp (°C)</span>
                                                    <input type="number" name="temp_max_c" min="50" max="150" step="1"
                                                           value="{{ $thresholds['temp_max_c'] ?? '' }}"
                                                           placeholder="e.g. 95"
                                                           style="background:var(--h-surface);border:1px solid var(--h-border);color:var(--h-text);border-radius:var(--h-radius-sm);padding:0.28rem 0.5rem;font-size:0.75rem;width:100%;outline:none;">
                                                </label>
                                                <label style="display:flex;flex-direction:column;gap:0.2rem;">
                                                    <span style="font-size:0.68rem;color:var(--h-text3);">Max RPM</span>
                                                    <input type="number" name="rpm_max" min="500" max="10000" step="100"
                                                           value="{{ $thresholds['rpm_max'] ?? '' }}"
                                                           placeholder="e.g. 5000"
                                                           style="background:var(--h-surface);border:1px solid var(--h-border);color:var(--h-text);border-radius:var(--h-radius-sm);padding:0.28rem 0.5rem;font-size:0.75rem;width:100%;outline:none;">
                                                </label>
                                                <label style="display:flex;flex-direction:column;gap:0.2rem;">
                                                    <span style="font-size:0.68rem;color:var(--h-text3);">Min Voltage (V)</span>
                                                    <input type="number" name="voltage_min_v" min="8" max="16" step="0.1"
                                                           value="{{ $thresholds['voltage_min_v'] ?? '' }}"
                                                           placeholder="e.g. 11.5"
                                                           style="background:var(--h-surface);border:1px solid var(--h-border);color:var(--h-text);border-radius:var(--h-radius-sm);padding:0.28rem 0.5rem;font-size:0.75rem;width:100%;outline:none;">
                                                </label>
                                                <label style="display:flex;flex-direction:column;gap:0.2rem;">
                                                    <span style="font-size:0.68rem;color:var(--h-text3);">Max Vibration (g)</span>
                                                    <input type="number" name="vibration_max" min="0.1" max="50" step="0.1"
                                                           value="{{ $thresholds['vibration_max'] ?? '' }}"
                                                           placeholder="e.g. 1.5"
                                                           style="background:var(--h-surface);border:1px solid var(--h-border);color:var(--h-text);border-radius:var(--h-radius-sm);padding:0.28rem 0.5rem;font-size:0.75rem;width:100%;outline:none;">
                                                </label>
                                            </div>
                                            <button type="submit" class="haven-action-btn haven-action-btn-secondary" style="font-size:0.72rem;padding:0.28rem 0.75rem;cursor:pointer;display:inline-flex;align-items:center;gap:0.3rem;">
                                                <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                                Save Thresholds
                                            </button>
                                        </form>
                                    </div>

                                </div>{{-- /body --}}
                            </div>{{-- /vehicle card --}}
                        @endforeach
                    </div>
                @endif
            </div>{{-- /IoT section --}}

            {{-- ════ ISO/IEC 27001 Security Audit Log ════ --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg" style="background:var(--h-surface);border:1px solid var(--h-border);border-radius:var(--h-radius-lg);">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:0.75rem;">
                    <div>
                        <div style="display:flex;align-items:center;gap:0.45rem;">
                            <h3 style="font-size:0.95rem;font-weight:700;color:var(--h-text);margin:0;">Security & Activity Audit Trail</h3>
                            <span class="h-badge h-badge-gray" style="font-size:0.62rem;">ISO/IEC 27001 A.8.15</span>
                        </div>
                        <p style="font-size:0.75rem;color:var(--h-text3);margin-top:0.25rem;">
                            Immutable forensic log of recent authentication, vehicle management, and IoT sensor transmissions.
                        </p>
                    </div>
                </div>

                @if($auditLogs->isEmpty())
                    <p style="font-size:0.8rem;color:var(--h-text3);">No audit events recorded yet.</p>
                @else
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:0.78rem;text-align:left;">
                            <thead>
                                <tr style="border-bottom:1px solid var(--h-border);color:var(--h-text3);font-size:0.7rem;text-transform:uppercase;">
                                    <th style="padding:0.5rem 0.75rem;">Event</th>
                                    <th style="padding:0.5rem 0.75rem;">Status</th>
                                    <th style="padding:0.5rem 0.75rem;">IP Address</th>
                                    <th style="padding:0.5rem 0.75rem;">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($auditLogs as $log)
                                    <tr style="border-bottom:1px solid var(--h-border);">
                                        <td style="padding:0.6rem 0.75rem;font-weight:600;color:var(--h-text);">
                                            <code style="color:var(--h-teal);font-size:0.74rem;">{{ $log->event }}</code>
                                        </td>
                                        <td style="padding:0.6rem 0.75rem;">
                                            @if($log->status === 'success')
                                                <span class="h-badge h-badge-green" style="font-size:0.6rem;padding:0.12rem 0.45rem;">Success</span>
                                            @elseif($log->status === 'failure')
                                                <span class="h-badge h-badge-red" style="font-size:0.6rem;padding:0.12rem 0.45rem;">Failed</span>
                                            @else
                                                <span class="h-badge h-badge-amber" style="font-size:0.6rem;padding:0.12rem 0.45rem;">{{ ucfirst($log->status) }}</span>
                                            @endif
                                        </td>
                                        <td style="padding:0.6rem 0.75rem;color:var(--h-text3);font-family:monospace;font-size:0.72rem;">
                                            {{ $log->ip_address ?? '—' }}
                                        </td>
                                        <td style="padding:0.6rem 0.75rem;color:var(--h-text3);white-space:nowrap;">
                                            {{ $log->created_at->diffForHumans() }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ════ Regen Confirm Modal ════ --}}
    <div id="haven-regen-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.7);backdrop-filter:blur(2px);align-items:center;justify-content:center;">
        <div style="background:var(--h-surface2);border:1px solid rgba(239,68,68,0.4);border-radius:var(--h-radius-xl);padding:1.5rem 2rem;max-width:420px;width:90%;box-shadow:0 8px 40px rgba(0,0,0,0.6);">
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                <svg width="18" height="18" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span style="font-size:0.9rem;font-weight:700;color:var(--h-text);">Regenerate Device Key?</span>
            </div>
            <p id="haven-regen-msg" style="font-size:0.8rem;color:var(--h-text3);line-height:1.5;margin-bottom:1rem;"></p>
            <div style="background:var(--h-red-bg);border:1px solid var(--h-red);border-radius:var(--h-radius-sm);padding:0.5rem 0.75rem;font-size:0.74rem;color:var(--h-red);margin-bottom:1.1rem;">
                ⚠ Your ESP32 will immediately lose connection. You must flash the new key into the firmware before it can reconnect.
            </div>
            <div style="display:flex;gap:0.6rem;justify-content:flex-end;">
                <button onclick="havenCloseModal()" class="haven-action-btn haven-action-btn-secondary" style="font-size:0.78rem;cursor:pointer;">Cancel</button>
                <button id="haven-regen-confirm-btn" class="haven-action-btn haven-action-btn-danger" style="font-size:0.78rem;cursor:pointer;">Yes, Regenerate Key</button>
            </div>
        </div>
    </div>

    <script>
    // ── Toggle device key visibility ─────────────────────────────────────────
    function havenToggleKey(vid) {
        const el  = document.getElementById('dkey-' + vid);
        const btn = document.getElementById('toggle-btn-' + vid);
        if (!el || !btn) return;
        const vis = el.dataset.visible === 'true';
        el.textContent    = vis ? el.dataset.masked : el.dataset.raw;
        el.dataset.visible = vis ? 'false' : 'true';
        btn.textContent   = vis ? 'Show' : 'Hide';
    }

    // ── Copy device key to clipboard ──────────────────────────────────────────
    function havenCopyKey(vid) {
        const el  = document.getElementById('dkey-' + vid);
        const key = el ? el.dataset.raw : '';
        if (!key) return;
        navigator.clipboard.writeText(key).then(() => {
            const btn = document.getElementById('copy-btn-' + vid);
            if (btn) {
                btn.innerHTML = '<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
                setTimeout(() => {
                    btn.innerHTML = '<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy Key';
                }, 2000);
            }
        });
    }

    // ── Regenerate key confirmation modal ────────────────────────────────────
    function havenConfirmRegen(vehicleId, vehicleName) {
        const modal = document.getElementById('haven-regen-modal');
        const msg   = document.getElementById('haven-regen-msg');
        const btn   = document.getElementById('haven-regen-confirm-btn');
        msg.textContent = 'You are about to regenerate the ESP32 device key for "' + vehicleName + '". This action cannot be undone.';
        btn.onclick = () => {
            document.getElementById('regen-form-' + vehicleId).submit();
        };
        modal.style.display = 'flex';
    }
    function havenCloseModal() {
        document.getElementById('haven-regen-modal').style.display = 'none';
    }
    // Close on backdrop click
    document.getElementById('haven-regen-modal').addEventListener('click', function(e) {
        if (e.target === this) havenCloseModal();
    });

    // ── Live ESP32 status per vehicle card (one-shot on page load) ───────────
    document.querySelectorAll('[data-vehicle-id]').forEach(card => {
        const vid   = card.dataset.vehicleId;
        const dot   = card.querySelector('.vehicle-status-dot');
        const label = card.querySelector('.vehicle-status-label');
        fetch('/api/v1/telemetry/latest?vehicle_id=' + encodeURIComponent(vid), { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                if (data.online) {
                    dot.style.background  = 'var(--h-green)';
                    dot.style.boxShadow   = '0 0 6px var(--h-green)';
                    label.style.color     = 'var(--h-green)';
                    label.textContent     = 'Live (Streaming)';
                } else {
                    dot.style.background  = 'var(--h-text3)';
                    dot.style.boxShadow   = 'none';
                    label.style.color     = 'var(--h-text3)';
                    label.textContent     = data.last_seen_human ?? 'Standby / Offline';
                }
            })
            .catch(() => {
                label.textContent = 'Unable to check';
            });
    });
    </script>
</x-app-layout>
