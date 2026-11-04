<x-app-layout title="{{ $vehicle->display_name }}">

    <div class="haven-breadcrumb">
        <a href="{{ route('vehicles.index') }}" class="haven-breadcrumb-link">My Vehicles</a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <span style="color:var(--h-text2);">{{ $vehicle->display_name }}</span>
    </div>

    @if(session('success'))
        <div class="h-flash h-flash-success">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Hero --}}
    <div class="haven-vehicle-hero">
        <div class="haven-vehicle-hero-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
        </div>
        <div class="haven-vehicle-hero-info">
            <h1 class="haven-vehicle-hero-name">{{ $vehicle->display_name }}</h1>
            <div style="display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;">
                @if($vehicle->plate_number)
                    <span class="h-badge h-badge-teal">{{ $vehicle->plate_number }}</span>
                @endif
                @if($vehicle->color)
                    <span class="h-badge h-badge-gray">{{ $vehicle->color }}</span>
                @endif
                @if($vehicle->transmission)
                    <span class="h-badge h-badge-gray">{{ $vehicle->transmission }}</span>
                @endif
                <span class="h-badge {{ $vehicle->scope_meta['badge_css'] }}">{{ $vehicle->scope_meta['label'] }}</span>
                <span style="font-size:0.72rem;color:var(--h-text3);">Registered {{ $vehicle->created_at->format('M j, Y') }}</span>
            </div>
        </div>
        <div style="display:flex;gap:0.5rem;flex-shrink:0;flex-wrap:wrap;align-items:center;">
            <a href="{{ route('vehicles.edit', $vehicle) }}" class="haven-action-btn haven-action-btn-secondary" style="text-decoration:none;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
            </a>
            <button type="button" onclick="document.getElementById('delete-modal').classList.add('show')" class="haven-action-btn haven-action-btn-danger">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                Delete
            </button>
        </div>
    </div>

    {{-- Run Diagnostic CTA --}}
    @if(!$vehicle->isScopeCompliant())
        <div class="h-notice" style="margin-bottom:1.25rem;background:var(--h-amber-bg);border:1px solid rgba(245,158,11,0.25);">
            <svg class="h-notice-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span style="font-size:0.75rem;color:var(--h-text);">
                <strong>Thesis Scope Advisory:</strong> {{ $vehicle->scope_meta['notice'] }}
            </span>
        </div>
    @endif
    <div class="h-action-panel" style="margin-bottom:1.25rem;">
        <div class="h-action-panel-info">
            <p class="h-action-panel-title">Run Engine Diagnostic</p>
            <p class="h-action-panel-desc">Record or upload engine audio, vibration, and multi-sensor telemetry to get an ML-assisted condition report for this vehicle.</p>
        </div>
        <div style="flex-shrink:0;">
            <a href="{{ route('diagnostics.create', ['vehicle_id' => $vehicle->id]) }}" class="haven-btn-primary" style="width:auto;text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                    <line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>
                </svg>
                Start Diagnostic
            </a>
        </div>
    </div>

    {{-- Detail grid --}}
    <div class="haven-detail-grid">

        <div class="haven-detail-card">
            <div class="haven-detail-card-title">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                Vehicle Details
            </div>
            <div class="haven-detail-rows">
                <div class="haven-detail-row"><span class="haven-detail-label">Make</span><span class="haven-detail-value">{{ $vehicle->make }}</span></div>
                <div class="haven-detail-row"><span class="haven-detail-label">Model</span><span class="haven-detail-value">{{ $vehicle->model }}</span></div>
                <div class="haven-detail-row"><span class="haven-detail-label">Year</span><span class="haven-detail-value">{{ $vehicle->year }}</span></div>
                @if($vehicle->color)
                    <div class="haven-detail-row"><span class="haven-detail-label">Color</span><span class="haven-detail-value">{{ $vehicle->color }}</span></div>
                @endif
                @if($vehicle->plate_number)
                    <div class="haven-detail-row"><span class="haven-detail-label">Plate</span><span class="haven-detail-value">{{ $vehicle->plate_number }}</span></div>
                @endif
            </div>
        </div>

        <div class="haven-detail-card">
            <div class="haven-detail-card-title">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Engine & Drivetrain
            </div>
            <div class="haven-detail-rows">
                @if($vehicle->engine_type)
                    <div class="haven-detail-row"><span class="haven-detail-label">Engine</span><span class="haven-detail-value">{{ $vehicle->engine_type }}</span></div>
                @endif
                @if($vehicle->transmission)
                    <div class="haven-detail-row"><span class="haven-detail-label">Transmission</span><span class="haven-detail-value">{{ $vehicle->transmission }}</span></div>
                @endif
                @if($vehicle->mileage_km !== null)
                    <div class="haven-detail-row"><span class="haven-detail-label">Mileage</span><span class="haven-detail-value">{{ number_format($vehicle->mileage_km) }} km</span></div>
                @endif
                @if(!$vehicle->engine_type && !$vehicle->transmission && $vehicle->mileage_km === null)
                    <p style="padding:0.75rem 1rem;font-size:0.8rem;color:var(--h-text3);margin:0;">No engine data recorded.</p>
                @endif
            </div>
        </div>

        <div class="haven-detail-card">
            <div class="haven-detail-card-title" style="justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:0.4rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M1.42 9a16 16 0 0 1 21.16 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>
                    ESP32 Hardware Ingest Key
                </div>
                <span class="h-badge h-badge-teal" style="font-size:0.6rem;">ISO/SAE 21434</span>
            </div>
            <div style="padding:0.75rem 1rem;">
                @php
                    $rawKey = (string) ($vehicle->device_key ?? '');
                    $maskedKey = strlen($rawKey) > 12
                        ? substr($rawKey, 0, 12) . str_repeat('•', 16)
                        : ($rawKey ?: 'Generating...');
                @endphp
                <p style="font-size:0.75rem;color:var(--h-text3);margin:0 0 0.5rem;line-height:1.5;">
                    Flash this key into <code style="color:var(--h-teal);font-size:0.7rem;">HAVEN_ESP32_FIRMWARE.ino</code> or include as <code style="color:var(--h-teal);font-size:0.7rem;">X-Haven-Device-Key</code> in telemetry requests.
                </p>
                <div style="display:flex;align-items:center;gap:0.5rem;background:var(--h-surface2);padding:0.4rem 0.6rem;border-radius:var(--h-radius);border:1px solid var(--h-border);">
                    <code id="veh-device-key"
                          data-raw="{{ $rawKey }}"
                          data-masked="{{ $maskedKey }}"
                          data-visible="false"
                          style="font-size:0.74rem;color:var(--h-teal);word-break:break-all;flex:1;font-family:monospace;">{{ $maskedKey }}</code>
                    <button type="button"
                            onclick="const el=document.getElementById('veh-device-key');const vis=el.dataset.visible==='true';el.textContent=vis?el.dataset.masked:el.dataset.raw;el.dataset.visible=vis?'false':'true';this.textContent=vis?'Show':'Hide';"
                            class="haven-action-btn haven-action-btn-secondary"
                            style="font-size:0.7rem;padding:0.2rem 0.5rem;flex-shrink:0;">
                        Show
                    </button>
                    <button type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('veh-device-key').dataset.raw);const b=this;b.innerText='Copied!';setTimeout(()=>b.innerText='Copy Key',2000);"
                            class="haven-action-btn haven-action-btn-secondary"
                            style="font-size:0.7rem;padding:0.2rem 0.5rem;flex-shrink:0;">
                        Copy Key
                    </button>
                </div>
            </div>
        </div>

        @if($vehicle->notes)
            <div class="haven-detail-card" style="grid-column:1/-1;">
                <div class="haven-detail-card-title">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Notes
                </div>
                <p style="padding:0.875rem 1rem;font-size:0.82rem;color:var(--h-text2);line-height:1.65;margin:0;">{{ $vehicle->notes }}</p>
            </div>
        @endif

        {{-- ── Vehicle Health & Telemetry Trend Chart (Last 10 Sessions) ── --}}
        @php
            $trendSessions = $vehicle->diagnostics()
                ->where('status', 'completed')
                ->latest('id')
                ->take(10)
                ->get()
                ->reverse()
                ->values();
        @endphp

        @if($trendSessions->count() >= 2)
            <div class="haven-detail-card" style="grid-column:1/-1;padding:1.25rem 1.45rem;">
                <div class="haven-detail-card-title" style="justify-content:space-between;margin-bottom:0.75rem;padding:0;border:none;">
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2.2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        <span>Health &amp; Telemetry Trend</span>
                        <span class="h-badge h-badge-teal" style="font-size:0.62rem;">Last {{ $trendSessions->count() }} Sessions</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.75rem;font-size:0.72rem;color:var(--h-text3);">
                        <span style="display:flex;align-items:center;gap:0.3rem;"><strong style="color:#0284c7;">●</strong> Vibration Peak (g)</span>
                        <span style="display:flex;align-items:center;gap:0.3rem;"><strong style="color:#16a34a;">●</strong> Health Score (%)</span>
                    </div>
                </div>

                {{-- Trend Chart SVG --}}
                @php
                    $pointsCount = $trendSessions->count();
                    $width = 720;
                    $height = 140;
                    $padX = 35;
                    $padY = 20;
                    $chartW = $width - ($padX * 2);
                    $chartH = $height - ($padY * 2);

                    $vibPoints = [];
                    $healthPoints = [];
                    $maxVib = max(3.5, (float)($trendSessions->max('vibration_peak_g') ?: 2.0));

                    foreach ($trendSessions as $idx => $ts) {
                        $x = $padX + ($idx * ($chartW / max(1, $pointsCount - 1)));
                        // Peak G scale [0 .. maxVib]
                        $vVal = (float)($ts->vibration_peak_g ?: 0.8);
                        $yVib = ($height - $padY) - min($chartH, ($vVal / $maxVib) * $chartH);
                        $vibPoints[] = ['x' => $x, 'y' => $yVib, 'val' => $vVal, 'id' => $ts->id, 'cond' => $ts->ml_condition];

                        // Health score: 100% if normal, 65% if loose/misfire, 40% if valve
                        $hPct = match($ts->ml_condition) {
                            'normal' => 96,
                            'misfire' => 64,
                            'loose_component' => 58,
                            'valve_clearance' => 38,
                            default => 80,
                        };
                        $yHealth = ($height - $padY) - (($hPct / 100.0) * $chartH);
                        $healthPoints[] = ['x' => $x, 'y' => $yHealth, 'pct' => $hPct, 'date' => $ts->created_at->format('M j')];
                    }

                    $polyVib = implode(' ', array_map(fn($p) => "{$p['x']},{$p['y']}", $vibPoints));
                    $polyHealth = implode(' ', array_map(fn($p) => "{$p['x']},{$p['y']}", $healthPoints));
                    $areaVib = "{$vibPoints[0]['x']}," . ($height - $padY) . " {$polyVib} " . end($vibPoints)['x'] . "," . ($height - $padY);
                @endphp

                <div style="width:100%;overflow-x:auto;">
                    <svg viewBox="0 0 {{ $width }} {{ $height }}" style="width:100%;height:auto;display:block;overflow:visible;" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="vibTrendGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#0284c7" stop-opacity="0.28"/>
                                <stop offset="100%" stop-color="#0284c7" stop-opacity="0.0"/>
                            </linearGradient>
                        </defs>

                        {{-- Horizontal reference grids --}}
                        <line x1="{{ $padX }}" y1="{{ $padY }}" x2="{{ $width - $padX }}" y2="{{ $padY }}" stroke="var(--h-border)" stroke-dasharray="3 3"/>
                        <line x1="{{ $padX }}" y1="{{ $padY + ($chartH/2) }}" x2="{{ $width - $padX }}" y2="{{ $padY + ($chartH/2) }}" stroke="var(--h-border)" stroke-dasharray="3 3"/>
                        <line x1="{{ $padX }}" y1="{{ $height - $padY }}" x2="{{ $width - $padX }}" y2="{{ $height - $padY }}" stroke="var(--h-border)"/>

                        {{-- Shaded Vibration Area --}}
                        <polygon points="{{ $areaVib }}" fill="url(#vibTrendGrad)"/>

                        {{-- Vibration Polyline --}}
                        <polyline points="{{ $polyVib }}" fill="none" stroke="#0284c7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>

                        {{-- Health Score Polyline --}}
                        <polyline points="{{ $polyHealth }}" fill="none" stroke="#16a34a" stroke-width="2" stroke-dasharray="4 3" stroke-linecap="round"/>

                        {{-- Session Marker Points --}}
                        @foreach($vibPoints as $idx => $p)
                            @php
                                $dotColor = match($p['cond']) {
                                    'normal'          => '#16a34a',
                                    'misfire'         => '#f59e0b',
                                    'loose_component' => '#ea580c',
                                    'valve_clearance' => '#ef4444',
                                    default           => '#0284c7',
                                };
                            @endphp
                            <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4.5" fill="{{ $dotColor }}" stroke="#ffffff" stroke-width="2">
                                <title>Session #{{ $p['id'] }}: {{ $p['val'] }}g ({{ ucwords(str_replace('_',' ',$p['cond'] ?? 'Unknown')) }})</title>
                            </circle>
                            {{-- Date label --}}
                            <text x="{{ $p['x'] }}" y="{{ $height - 4 }}" font-size="9" font-family="sans-serif" fill="var(--h-text3)" text-anchor="middle">
                                {{ $healthPoints[$idx]['date'] }}
                            </text>
                        @endforeach
                    </svg>
                </div>

                {{-- Trend Summary Footer --}}
                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--h-border);font-size:0.75rem;color:var(--h-text2);flex-wrap:wrap;gap:0.5rem;">
                    <div>
                        Latest Condition: 
                        <strong style="color:var(--h-text);text-transform:capitalize;">
                            {{ str_replace('_', ' ', $trendSessions->last()->ml_condition ?? 'Healthy') }}
                        </strong>
                        ({{ round(($trendSessions->last()->ml_confidence ?? 0.85) * 100) }}% confidence)
                    </div>
                    <div>
                        Average Peak Vibration: 
                        <strong class="h-mono" style="color:var(--h-teal);">
                            {{ round($trendSessions->avg('vibration_peak_g'), 2) }}g
                        </strong>
                    </div>
                </div>
            </div>
        @endif

        {{-- Diagnostic History --}}
        <div class="haven-detail-card" style="grid-column:1/-1;">
            <div class="haven-detail-card-title" style="justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Diagnostic History
                </div>
                <a href="{{ route('diagnostics.create', ['vehicle_id' => $vehicle->id]) }}"
                   class="h-btn-sm h-btn-sm-teal" style="text-decoration:none;text-transform:none;letter-spacing:0;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    New Session
                </a>
            </div>
            @php $vDiags = $vehicle->diagnostics()->with('vehicle')->latest()->take(8)->get(); @endphp
            @if($vDiags->isEmpty())
                <div class="h-list-empty" style="padding:2rem 1.25rem;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 0.6rem;display:block;color:var(--h-text3);"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    No diagnostic sessions yet for this vehicle.
                </div>
            @else
                @foreach($vDiags as $d)
                    <a href="{{ route('diagnostics.show', $d) }}" class="h-list-item">
                        <div class="h-list-item-icon"
                             style="{{ $d->status==='completed' && $d->ml_condition==='normal' ? 'background:var(--h-green-bg);color:var(--h-green);' : ($d->status==='completed' ? 'background:var(--h-amber-bg);color:var(--h-amber);' : ($d->status==='submitted' ? 'background:var(--h-teal-bg);color:var(--h-teal);' : '')) }}">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <div class="h-list-item-main">
                            <div class="h-list-item-title">
                                Session #{{ $d->id }}
                                @if($d->ml_condition)
                                    — <span style="color:var(--h-teal);font-weight:600;">{{ ucwords(str_replace('_',' ',$d->ml_condition)) }}</span>
                                @endif
                            </div>
                            <div class="h-list-item-sub">
                                {{ $d->created_at->format('M j, Y · g:i A') }}
                                @if($d->audio_duration_sec) · {{ floor($d->audio_duration_sec/60) }}:{{ str_pad($d->audio_duration_sec%60,2,'0',STR_PAD_LEFT) }} audio @endif
                                @if($d->vibration_sample_count) · {{ number_format($d->vibration_sample_count) }} samples @endif
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:0.5rem;flex-shrink:0;">
                            @if($d->ml_confidence)
                                <span style="font-size:0.72rem;font-weight:600;color:var(--h-text3);">{{ round($d->ml_confidence*100) }}%</span>
                            @endif
                            <span class="h-badge {{ $d->status_badge }}">{{ $d->status_label }}</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--h-text3);"><polyline points="9 18 15 12 9 6"/></svg>
                        </div>
                    </a>
                @endforeach
                @if($vehicle->diagnostics()->count() > 8)
                    <a href="{{ route('diagnostics.index', ['vehicle_id' => $vehicle->id]) }}"
                       class="h-list-item" style="color:var(--h-teal);justify-content:center;font-size:0.8rem;">
                        View all {{ $vehicle->diagnostics()->count() }} sessions →
                    </a>
                @endif
            @endif
        </div>

    </div>

</x-app-layout>

{{-- Delete confirmation modal --}}
<div class="h-delete-confirm" id="delete-modal" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="h-delete-confirm-card">
        <p class="h-delete-confirm-title">Remove this vehicle?</p>
        <p class="h-delete-confirm-desc">
            You are about to permanently remove <strong style="color:var(--h-text);">{{ $vehicle->display_name }}</strong>.
            Any diagnostic history associated with this vehicle will also be deleted. This cannot be undone.
        </p>
        <div class="h-delete-confirm-actions">
            <button type="button" class="haven-btn-ghost" style="width:auto;" onclick="document.getElementById('delete-modal').classList.remove('show')">
                Cancel
            </button>
            <form method="POST" action="{{ route('vehicles.destroy', $vehicle) }}" style="display:inline;">
                @csrf @method('DELETE')
                <button type="submit" class="haven-btn-danger" style="width:auto;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                        <path d="M10 11v6"/><path d="M14 11v6"/>
                    </svg>
                    Yes, Delete Vehicle
                </button>
            </form>
        </div>
    </div>
</div>
