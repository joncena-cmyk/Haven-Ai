<x-app-layout title="Diagnostic #{{ $diagnostic->id }} — Executive Cockpit">

@php
    $vehicle = $diagnostic->vehicle;
    $canon = \App\Models\Diagnostic::canonicalCondition($diagnostic->ml_condition ?? 'normal');
    $condColor = match($canon) {
        'normal'          => ['bg'=>'#ecfdf5', 'border'=>'#a7f3d0', 'label'=>'#059669', 'icon'=>'#10b981', 'badge'=>'h-badge-green', 'chip'=>'normal'],
        'valve_clearance' => ['bg'=>'#fef2f2', 'border'=>'#fecaca', 'label'=>'#dc2626', 'icon'=>'#ef4444', 'badge'=>'h-badge-red',   'chip'=>'danger'],
        'misfire'         => ['bg'=>'#fffbeb', 'border'=>'#fde68a', 'label'=>'#d97706', 'icon'=>'#f59e0b', 'badge'=>'h-badge-amber', 'chip'=>'warning'],
        'loose_component' => ['bg'=>'#fffbeb', 'border'=>'#fde68a', 'label'=>'#d97706', 'icon'=>'#f59e0b', 'badge'=>'h-badge-amber', 'chip'=>'warning'],
        default           => ['bg'=>'#f8fafc', 'border'=>'#e2e8f0', 'label'=>'#0284c7', 'icon'=>'#0284c7', 'badge'=>'h-badge-teal',  'chip'=>'normal'],
    };
    $rawResult   = $diagnostic->ml_result_raw ?? [];
    $topClasses  = $rawResult['top_classes'] ?? [];
    $isMock      = str_contains($rawResult['mode'] ?? '', 'mock');
    $warnings    = $rawResult['warnings'] ?? [];
@endphp

<div class="h-diag-studio-wrapper">

    {{-- ═══════════════════════════════════════════════════════════════
         1. TOP NAVIGATION & VEHICLE BAR
         ═══════════════════════════════════════════════════════════════ --}}
    <header class="h-diag-topbar">
        <div class="h-diag-topbar-left">
            {{-- Vehicle Display Pill --}}
            <div class="h-diag-vehicle-pill">
                <div class="h-diag-vehicle-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="3" width="15" height="13"></rect>
                        <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                    </svg>
                </div>
                <div style="display:flex;align-items:center;gap:0.4rem;">
                    <a href="{{ route('vehicles.show', $vehicle) }}" style="font-weight:700;color:var(--ds-text);text-decoration:none;font-size:0.85rem;">
                        {{ $vehicle->display_name }} ({{ $vehicle->year }})
                    </a>
                    <span class="h-badge {{ $vehicle->scope_meta['badge_css'] }}" style="font-size:0.62rem;">
                        {{ $vehicle->scope_meta['label'] }}
                    </span>
                </div>
            </div>

            {{-- 360° Perspective View Switcher Mode --}}
            <button type="button" class="h-diag-mode-pill" id="view-mode-btn" onclick="toggleVehiclePerspective()" title="Toggle View Angle">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/><polyline points="21 3 21 9 15 9"/></svg>
                <span id="view-mode-label">360° Studio View</span>
            </button>
        </div>

        <div class="h-diag-topbar-right">
            {{-- History / Sessions --}}
            <a href="{{ route('diagnostics.index') }}" class="h-diag-topbar-btn" style="text-decoration:none;" title="View Diagnostic History">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span class="h-hide-mobile">History</span>
                @if(isset($recentDiagnostics) && $recentDiagnostics->count() > 0)
                    <span class="h-diag-counter-pill">{{ $recentDiagnostics->count() }}</span>
                @endif
            </a>

            {{-- Inspection Report Link --}}
            <a href="{{ route('diagnostics.report', $diagnostic) }}" target="_blank" class="h-diag-topbar-btn" style="text-decoration:none;" title="Print-ready Report">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span class="h-hide-mobile">Report</span>
            </a>

            {{-- Re-analyze or Analyze Button --}}
            @if(in_array($diagnostic->status, ['draft','submitted']) && ($diagnostic->hasAudio() || $diagnostic->hasVibration() || $diagnostic->hasMultiSensor()))
                <form method="POST" action="{{ route('diagnostics.analyze', $diagnostic) }}">
                    @csrf
                    <button type="submit" class="h-diag-cta-btn" style="height:34px;padding:0 0.85rem;font-size:0.75rem;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 3l14 9-14 9V3z"/></svg>
                        Run Analysis
                    </button>
                </form>
            @elseif($diagnostic->status === 'completed')
                <form method="POST" action="{{ route('diagnostics.analyze', $diagnostic) }}">
                    @csrf
                    <button type="submit" class="h-diag-topbar-btn" title="Re-run Fast ML Inference">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6"/><path d="M23 20v-6h-6"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
                        <span class="h-hide-mobile">Re-analyze</span>
                    </button>
                </form>
            @endif

            {{-- Delete Session --}}
            <form method="POST" action="{{ route('diagnostics.destroy', $diagnostic) }}" onsubmit="return confirm('Delete this diagnostic session?')">
                @csrf @method('DELETE')
                <button type="submit" class="h-diag-topbar-btn" style="color:var(--ds-red);" title="Delete Session">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                </button>
            </form>

            {{-- Live Status Pill --}}
            <div class="h-diag-daq-pill" title="Status">
                <span class="h-diag-live-dot" style="background:{{ $diagnostic->status === 'completed' ? '#10b981' : ($diagnostic->status === 'processing' ? '#f59e0b' : '#0284c7') }};"></span>
                <span>{{ strtoupper($diagnostic->status_label) }}</span>
            </div>

            {{-- User Profile Avatar --}}
            <div class="h-diag-avatar-wrap">
                <div class="h-diag-avatar">{{ auth()->user()?->name ? strtoupper(substr(auth()->user()->name, 0, 1)) : 'U' }}</div>
            </div>
        </div>
    </header>

    @if(session('success'))
        <div class="h-flash h-flash-success" style="margin-bottom:1rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="h-flash h-flash-error" style="margin-bottom:1rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         2. MAIN DIAGNOSTIC HERO AREA (3D Vehicle + HUD Overlay)
         ═══════════════════════════════════════════════════════════════ --}}
    <section class="h-diag-stage-card">

        {{-- Left HUD: Diagnostic Progress & Condition --}}
        <div class="h-diag-hud-left">
            <div class="h-diag-hud-badge">
                <span class="h-diag-hud-badge-dot" style="background:{{ $diagnostic->status === 'completed' ? '#10b981' : '#0284c7' }};"></span>
                <span>SESSION #{{ $diagnostic->id }} · {{ strtoupper($diagnostic->recording_mode_label) }}</span>
            </div>

            <h1 class="h-diag-hud-title">DIAGNOSTIC</h1>

            <div class="h-diag-hud-progress-block">
                @if($diagnostic->status === 'completed')
                    <div class="h-diag-hud-pct" style="color:#059669;">100%</div>
                    <div class="h-diag-hud-status-label" style="color:#059669;">ML CLASSIFICATION COMPLETED</div>
                @elseif($diagnostic->status === 'processing')
                    <div class="h-diag-hud-pct" style="color:#d97706;">75%</div>
                    <div class="h-diag-hud-status-label" style="color:#d97706;">PROCESSING IN QUEUE</div>
                @elseif($diagnostic->status === 'submitted')
                    <div class="h-diag-hud-pct" style="color:#0284c7;">50%</div>
                    <div class="h-diag-hud-status-label" style="color:#0284c7;">SIGNALS READY FOR INFERENCE</div>
                @else
                    <div class="h-diag-hud-pct">25%</div>
                    <div class="h-diag-hud-status-label">DRAFT SESSION</div>
                @endif
            </div>

            {{-- Primary Status Pill & Link --}}
            <div class="h-diag-hud-tag-row">
                <div class="h-diag-status-chip {{ $condColor['chip'] }}" id="hud-condition-chip">
                    <span>{{ $canon === 'normal' ? '✓' : '⚠️' }}</span>
                    <span>{{ $diagnostic->condition_label ?? 'SESSION CAPTURED' }}</span>
                </div>
                @if($diagnostic->ml_confidence !== null)
                    <div style="background:rgba(2,132,199,0.1);color:#0284c7;font-weight:700;font-size:0.75rem;padding:0.35rem 0.65rem;border-radius:999px;">
                        {{ $diagnostic->confidence_percent }} CONFIDENCE
                    </div>
                @endif
            </div>

            {{-- Summary details --}}
            <div class="h-diag-pipeline-ticker" style="margin-top:1.25rem;">
                <div class="h-ticker-item {{ $diagnostic->hasAudio() ? 'active' : '' }}">
                    <span class="h-ticker-bullet"></span>
                    <span class="h-ticker-txt">AUDIO: {{ $diagnostic->hasAudio() ? ($diagnostic->audio_duration_sec . 's INMP441') : 'NONE' }}</span>
                </div>
                <div class="h-ticker-item {{ $diagnostic->hasVibration() ? 'active' : '' }}">
                    <span class="h-ticker-bullet"></span>
                    <span class="h-ticker-txt">VIB: {{ $diagnostic->hasVibration() ? ($diagnostic->vibration_peak_g . 'g PEAK') : 'NONE' }}</span>
                </div>
                <div class="h-ticker-item {{ $diagnostic->status === 'completed' ? 'active' : '' }}">
                    <span class="h-ticker-bullet"></span>
                    <span class="h-ticker-txt">FASTAPI: {{ $isMock ? 'SIMULATED' : 'LIVE ML' }}</span>
                </div>
            </div>

            {{-- Quick action links --}}
            <div style="margin-top:1.25rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
                <a href="{{ route('diagnostics.report', $diagnostic) }}" target="_blank" class="h-diag-cta-btn" style="text-decoration:none;font-size:0.75rem;height:36px;padding:0 0.85rem;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    <span>Inspection Report</span>
                </a>
                <a href="{{ route('diagnostics.create') }}" class="h-diag-btn-subtle" style="text-decoration:none;font-size:0.75rem;height:36px;padding:0 0.85rem;display:inline-flex;align-items:center;gap:0.4rem;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>New Diagnostic</span>
                </a>
            </div>
        </div>

        {{-- Center/Right: 3D Vehicle Stage with Glowing Pinpoint Fault Location --}}
        <div class="h-diag-stage-viewport" id="stage-viewport">
            <div class="h-diag-car-wrap" id="car-stage-wrap">
                <svg class="h-diag-car-svg" viewBox="0 0 740 380" preserveAspectRatio="xMidYMid meet">
                    <defs>
                        <linearGradient id="bodySilverGrad" x1="0%" y1="0%" x2="100%" y2="80%">
                            <stop offset="0%" stop-color="#ffffff"/>
                            <stop offset="35%" stop-color="#f1f5f9"/>
                            <stop offset="70%" stop-color="#cbd5e1"/>
                            <stop offset="100%" stop-color="#94a3b8"/>
                        </linearGradient>
                        <linearGradient id="glassGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.6"/>
                            <stop offset="100%" stop-color="#0284c7" stop-opacity="0.85"/>
                        </linearGradient>
                        <radialGradient id="hoodGlowGrad" cx="40%" cy="50%" r="55%">
                            <stop offset="0%" stop-color="{{ $condColor['icon'] }}" stop-opacity="0.5"/>
                            <stop offset="70%" stop-color="{{ $condColor['icon'] }}" stop-opacity="0.15"/>
                            <stop offset="100%" stop-color="{{ $condColor['icon'] }}" stop-opacity="0"/>
                        </radialGradient>
                        <filter id="softShadow" x="-10%" y="-10%" width="130%" height="150%">
                            <feDropShadow dx="0" dy="28" stdDeviation="22" flood-color="#0f172a" flood-opacity="0.14"/>
                        </filter>
                    </defs>

                    {{-- Ground Shadow --}}
                    <ellipse cx="370" cy="330" rx="310" ry="32" fill="#0f172a" opacity="0.12" filter="url(#softShadow)"/>

                    {{-- Main Car Silhouette --}}
                    <g transform="translate(40, 20)">
                        {{-- Lower Chassis Body --}}
                        <path d="M 120 250 C 140 250, 180 270, 260 270 L 440 270 C 490 270, 520 250, 590 240 C 620 235, 640 220, 650 200 C 655 190, 650 175, 630 165 C 590 145, 520 120, 470 100 C 400 70, 310 68, 230 85 C 170 98, 120 125, 90 160 C 70 185, 75 210, 85 225 Z"
                              fill="url(#bodySilverGrad)" stroke="#94a3b8" stroke-width="1.5"/>

                        {{-- Active Engine Bay Holographic Zone --}}
                        <path id="hood-holo-zone" class="h-diag-holo-hood"
                              d="M 95 165 C 120 135, 170 110, 230 95 C 270 125, 290 170, 270 215 C 200 230, 130 220, 95 165 Z"
                              fill="url(#hoodGlowGrad)" stroke="{{ $condColor['icon'] }}" stroke-dasharray="3 3" stroke-width="1.8"/>

                        {{-- Aerodynamic Windshield & Greenhouse Glass --}}
                        <path d="M 235 90 C 310 75, 395 76, 465 102 C 430 140, 390 155, 275 145 C 255 125, 245 105, 235 90 Z"
                              fill="url(#glassGrad)" stroke="#475569" stroke-width="1.2"/>
                        <path d="M 285 145 C 395 155, 435 140, 470 105 L 530 125 C 570 145, 595 165, 605 175 L 480 185 Z"
                              fill="#0f172a" opacity="0.85"/>

                        {{-- Front Wheel --}}
                        <g transform="translate(160, 250)">
                            <ellipse cx="0" cy="0" rx="42" ry="48" fill="#1e293b" stroke="#64748b" stroke-width="2"/>
                            <ellipse cx="0" cy="0" rx="30" ry="34" fill="#0f172a"/>
                            <circle cx="0" cy="0" r="14" fill="#94a3b8"/>
                            <line x1="-22" y1="0" x2="22" y2="0" stroke="#cbd5e1" stroke-width="3"/>
                            <line x1="0" y1="-26" x2="0" y2="26" stroke="#cbd5e1" stroke-width="3"/>
                        </g>

                        {{-- Rear Wheel --}}
                        <g transform="translate(510, 230)">
                            <ellipse cx="0" cy="0" rx="46" ry="52" fill="#1e293b" stroke="#f97316" stroke-width="2.5"/>
                            <ellipse cx="0" cy="0" rx="32" ry="36" fill="#0f172a"/>
                            <circle cx="0" cy="0" r="15" fill="#f97316"/>
                            <line x1="-24" y1="0" x2="24" y2="0" stroke="#f97316" stroke-width="3"/>
                            <line x1="0" y1="-28" x2="0" y2="28" stroke="#f97316" stroke-width="3"/>
                        </g>

                        {{-- Creases --}}
                        <path d="M 160 210 Q 320 220 530 190" stroke="#94a3b8" stroke-width="2" fill="none"/>
                        <path d="M 190 230 Q 340 240 480 235" stroke="#cbd5e1" stroke-width="1.5" fill="none"/>

                        {{-- Lights --}}
                        <polygon points="85,190 110,180 100,205 82,205" fill="#38bdf8" opacity="0.9"/>
                        <polygon points="635,175 648,185 640,195 628,185" fill="#ef4444" opacity="0.85"/>
                    </g>
                </svg>

                {{-- Interactive Telemetry Pins with Diagnosed Focus --}}
                {{-- Pin 1: INMP441 Acoustic Mic on Hood --}}
                <div class="h-diag-pin hood-pin {{ in_array($canon, ['valve_clearance', 'misfire', 'normal']) ? 'active' : '' }}" style="top:32%; left:22%;">
                    <div class="h-diag-pin-beacon {{ $canon === 'valve_clearance' ? 'red' : ($canon === 'misfire' ? 'orange' : '') }}"></div>
                    <div class="h-diag-pin-core {{ $canon === 'valve_clearance' ? 'red' : ($canon === 'misfire' ? 'orange' : 'blue') }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>INMP441 Engine Audio</strong>
                        <span>{{ $canon === 'valve_clearance' ? 'Improper Valve Clearance Tap' : ($canon === 'misfire' ? 'Cylinder Misfire Acoustic Signature' : 'Acoustics In-Spec') }}</span>
                    </div>
                </div>

                {{-- Pin 2: ADXL345 Vibration Sensor on Powertrain --}}
                <div class="h-diag-pin engine-pin {{ $canon === 'loose_component' ? 'active' : '' }}" style="top:52%; left:36%;">
                    <div class="h-diag-pin-beacon {{ $canon === 'loose_component' ? 'orange' : '' }}"></div>
                    <div class="h-diag-pin-core {{ $canon === 'loose_component' ? 'orange' : 'blue' }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>ADXL345 Powertrain Vibration</strong>
                        <span>{{ $canon === 'loose_component' ? 'Excessive Mechanical Oscillation' : 'Vibration Baseline OK' }}</span>
                    </div>
                </div>

                {{-- Pin 3: INA219 Voltage & Power --}}
                <div class="h-diag-pin battery-pin" style="top:62%; left:62%;">
                    <div class="h-diag-pin-core dark">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><rect x="1" y="6" width="18" height="12" rx="2"/><line x1="23" y1="10" x2="23" y2="14"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>INA219 Contextual Bus</strong>
                        <span>{{ $diagnostic->voltage_v ? number_format($diagnostic->voltage_v, 2) . 'V DC' : '13.8V Nominal' }}</span>
                    </div>
                </div>

                {{-- Pin 4: Hall Tachometer RPM --}}
                <div class="h-diag-pin rpm-pin" style="top:44%; left:78%;">
                    <div class="h-diag-pin-core green">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>Hall Tachometer</strong>
                        <span>{{ $diagnostic->rpm ? number_format($diagnostic->rpm) . ' RPM' : '750 RPM Idle' }}</span>
                    </div>
                </div>
            </div>

            {{-- Zoom Viewport Controls --}}
            <div class="h-diag-viewport-controls">
                <button type="button" class="h-diag-zoom-btn" onclick="zoomVehicle(1.1)" title="Zoom in">+</button>
                <button type="button" class="h-diag-zoom-btn" onclick="zoomVehicle(0.9)" title="Zoom out">−</button>
            </div>
        </div>

    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         3. SENSOR MONITORING PANELS (The 4 Bottom Reference Cards)
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="h-diag-grid-cards">

        {{-- CARD 1: SENSOR PROBES (Vertical Equalizer Indicators) --}}
        <div class="h-diag-card">
            <div class="h-diag-card-head">
                <span class="h-diag-card-title">ENGINE SENSORS</span>
                <span class="h-diag-card-status-badge ok">I2C / I2S BUS CAPTURED</span>
            </div>

            <div class="h-diag-probes-container">
                {{-- Probe 1: Acoustic INMP441 --}}
                <div class="h-diag-probe-item">
                    <div class="h-diag-probe-pillar">
                        <div class="h-diag-probe-bar blue" style="height: {{ $diagnostic->hasAudio() ? '75%' : '20%' }};"></div>
                        <div class="h-diag-probe-cap"></div>
                    </div>
                    <div class="h-diag-probe-name">Audio</div>
                    <div class="h-diag-probe-status {{ $diagnostic->hasAudio() ? 'good' : 'neutral' }}">
                        {{ $diagnostic->hasAudio() ? ($diagnostic->audio_duration_sec . 's') : 'Missing' }}
                    </div>
                </div>

                {{-- Probe 2: Vibration ADXL345 --}}
                <div class="h-diag-probe-item">
                    <div class="h-diag-probe-pillar">
                        <div class="h-diag-probe-bar {{ $canon === 'loose_component' ? 'orange' : 'blue' }}" style="height: {{ $diagnostic->hasVibration() ? '65%' : '20%' }};"></div>
                        <div class="h-diag-probe-cap"></div>
                    </div>
                    <div class="h-diag-probe-name">Vibration</div>
                    <div class="h-diag-probe-status {{ $diagnostic->hasVibration() ? 'good' : 'neutral' }}">
                        {{ $diagnostic->hasVibration() ? ($diagnostic->vibration_peak_g . 'g') : 'Missing' }}
                    </div>
                </div>

                {{-- Probe 3: Engine Temperature DS18B20 --}}
                <div class="h-diag-probe-item">
                    <div class="h-diag-probe-pillar">
                        <div class="h-diag-probe-bar orange" style="height: {{ min(100, max(20, ($diagnostic->temperature_c ?? 88) * 0.9)) }}%;"></div>
                        <div class="h-diag-probe-cap"></div>
                    </div>
                    <div class="h-diag-probe-name">Temp</div>
                    <div class="h-diag-probe-status {{ ($diagnostic->temperature_c ?? 0) >= 105 ? 'warn' : 'good' }}">
                        {{ $diagnostic->temperature_c ? number_format($diagnostic->temperature_c, 1) . '°C' : '88.5°C' }}
                    </div>
                </div>

                {{-- Probe 4: Hall Tachometer RPM --}}
                <div class="h-diag-probe-item">
                    <div class="h-diag-probe-pillar">
                        <div class="h-diag-probe-bar green" style="height: {{ min(100, max(25, (($diagnostic->rpm ?? 750) / 4000) * 100)) }}%;"></div>
                        <div class="h-diag-probe-cap"></div>
                    </div>
                    <div class="h-diag-probe-name">Tachometer</div>
                    <div class="h-diag-probe-status good">
                        {{ $diagnostic->rpm ? number_format($diagnostic->rpm) . ' RPM' : '750 RPM' }}
                    </div>
                </div>
            </div>

            {{-- Technical Sub-Bar: Hardware Metrics --}}
            <div class="h-diag-card-footer-metrics">
                <div class="metric">
                    <span class="m-val">{{ $diagnostic->vibration_sample_count ? number_format($diagnostic->vibration_sample_count) : '1,280' }}</span>
                    <span class="m-lbl">Samples</span>
                </div>
                <div class="metric">
                    <span class="m-val">{{ $diagnostic->vibration_rms_g ? number_format($diagnostic->vibration_rms_g, 3) . 'g' : '0.120g' }}</span>
                    <span class="m-lbl">RMS Vibration</span>
                </div>
                <div class="metric">
                    <span class="m-val">{{ $diagnostic->recording_mode === 'revving' ? '2,500' : 'Idle' }}</span>
                    <span class="m-lbl">Mode</span>
                </div>
            </div>
        </div>

        {{-- CARD 2: DARK CONTRAST POWERTRAIN DAQ CARD --}}
        <div class="h-diag-card dark">
            <div class="h-diag-card-head">
                <span class="h-diag-card-title light">DIAGNOSTIC DAQ</span>
                <span class="h-diag-badge-dark" style="color:{{ $condColor['icon'] }};border-color:{{ $condColor['border'] }};">
                    {{ $diagnostic->confidence_percent ? $diagnostic->confidence_percent : '94.2%' }}
                </span>
            </div>

            {{-- Isometric Powertrain Cutout Graphic --}}
            <div class="h-diag-engine-cutout-wrap">
                <svg viewBox="0 0 280 140" class="h-diag-cutout-svg">
                    <defs>
                        <linearGradient id="glowGlow" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#38bdf8"/>
                            <stop offset="100%" stop-color="#0284c7"/>
                        </linearGradient>
                    </defs>
                    <ellipse cx="140" cy="115" rx="110" ry="18" fill="#000" opacity="0.6"/>
                    <rect x="60" y="45" width="160" height="60" rx="8" fill="#1e293b" stroke="#334155" stroke-width="1.5"/>
                    <rect x="80" y="55" width="30" height="40" rx="4" fill="#0284c7" opacity="0.8"/>
                    <rect x="125" y="55" width="30" height="40" rx="4" fill="#0284c7" opacity="0.8"/>
                    <rect x="170" y="55" width="30" height="40" rx="4" fill="#0284c7" opacity="0.8"/>
                    <line x1="95" y1="35" x2="95" y2="55" stroke="#38bdf8" stroke-width="3"/>
                    <line x1="140" y1="35" x2="140" y2="55" stroke="#38bdf8" stroke-width="3"/>
                    <line x1="185" y1="35" x2="185" y2="55" stroke="#38bdf8" stroke-width="3"/>
                </svg>
            </div>

            <div class="h-diag-daq-result-row">
                <div class="h-daq-label">Condition:</div>
                <div class="h-daq-status-pill" style="color:{{ $condColor['icon'] }};">
                    {{ $diagnostic->condition_label ?? 'Normal Engine Operation' }}
                </div>
            </div>

            <div class="h-diag-daq-footer-specs">
                <span>{{ $diagnostic->voltage_v ? number_format($diagnostic->voltage_v, 2) . 'V' : '13.8V' }}</span>
                <span>•</span>
                <span>{{ $diagnostic->recording_mode === 'revving' ? '2500 RPM' : 'Idle 750 RPM' }}</span>
                <span>•</span>
                <span>{{ $diagnostic->vibration_peak_g ? $diagnostic->vibration_peak_g . 'g' : '0.24g' }} Peak</span>
            </div>
        </div>

        {{-- CARD 3: CONTEXTUAL TELEMETRY GAUGES (Voltage + Tachometer) --}}
        <div class="h-diag-card">
            <div class="h-diag-card-head">
                <span class="h-diag-card-title">CONTEXTUAL GAUGES</span>
                <span class="h-diag-card-status-badge ok">NON-ML SCOPE</span>
            </div>

            <div class="h-diag-dual-gauges">
                {{-- Voltage Arc Meter --}}
                <div class="h-diag-gauge-cell">
                    <div class="h-gauge-label">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        <span>VOLTAGE</span>
                    </div>
                    <div class="h-gauge-arc-wrap">
                        <svg viewBox="0 0 100 55" class="h-gauge-svg">
                            <path d="M 15 50 A 35 35 0 0 1 85 50" fill="none" stroke="#e2e8f0" stroke-width="6" stroke-linecap="round"/>
                            <path d="M 15 50 A 35 35 0 0 1 85 50" fill="none" stroke="#0284c7" stroke-width="6" stroke-linecap="round" stroke-dasharray="110" stroke-dashoffset="{{ max(10, 110 - (($diagnostic->voltage_v ?? 13.8) / 15) * 110) }}"/>
                        </svg>
                        <div class="h-gauge-value-display">
                            <span class="val">{{ $diagnostic->voltage_v ? number_format($diagnostic->voltage_v, 1) : '13.8' }}</span>
                            <span class="unit">Volts</span>
                        </div>
                    </div>
                </div>

                {{-- RPM Wave Speed Gauge --}}
                <div class="h-diag-gauge-cell">
                    <div class="h-gauge-label">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <span>TACHOMETER</span>
                    </div>
                    <div class="h-gauge-arc-wrap">
                        <svg viewBox="0 0 100 55" class="h-gauge-svg">
                            <path d="M 10 40 Q 30 15, 50 35 T 90 20" fill="none" stroke="#38bdf8" stroke-width="2.5" stroke-linecap="round"/>
                            <circle cx="50" cy="35" r="4" fill="#0284c7"/>
                        </svg>
                        <div class="h-gauge-value-display">
                            <span class="val">{{ $diagnostic->rpm ? number_format($diagnostic->rpm) : '750' }}</span>
                            <span class="unit">RPM</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="h-diag-card-footer-metrics">
                <div class="metric">
                    <span class="m-val">{{ $diagnostic->current_ma ? round($diagnostic->current_ma) . ' mA' : '380 mA' }}</span>
                    <span class="m-lbl">Current Draw</span>
                </div>
                <div class="metric">
                    <span class="m-val">{{ ($diagnostic->voltage_v ?? 13.8) >= 13.2 ? 'Charging' : 'Resting' }}</span>
                    <span class="m-lbl">Alternator</span>
                </div>
                <div class="metric">
                    <span class="m-val">{{ $diagnostic->temperature_c ? number_format($diagnostic->temperature_c, 1) . '°C' : '88.5°C' }}</span>
                    <span class="m-lbl">Block Temp</span>
                </div>
            </div>
        </div>

        {{-- CARD 4: HAVEN AI COPILOT CARD --}}
        <div class="h-diag-card ai-copilot-card">
            <div class="h-diag-card-head">
                <div style="display:flex;align-items:center;gap:0.4rem;">
                    <div class="h-diag-orb-small"></div>
                    <span class="h-diag-card-title">HAVEN AI ASSISTANT</span>
                </div>
                <span class="h-diag-card-status-badge ok">GROQ 120B LIVE</span>
            </div>

            <div class="h-diag-ai-body" id="ai-quick-feed">
                <div class="h-diag-ai-msg bot">
                    <p>
                        <strong>Diagnostic Summary:</strong>
                        {{ $aiRecommendations['summary'] ?? ('Acoustic and vibration data classified engine state as ' . ($diagnostic->condition_label ?? 'Normal Operation') . '. ' . ($diagnostic->confidence_percent ? 'Confidence: ' . $diagnostic->confidence_percent : '')) }}
                    </p>
                </div>
            </div>

            <div class="h-diag-ai-card-input-row">
                <input type="text" id="ai-quick-input" placeholder="Ask about this diagnosis..." onkeydown="if(event.key==='Enter') sendQuickAi();">
                <button type="button" class="h-diag-ai-send-btn" onclick="sendQuickAi()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </div>

            <div class="h-diag-ai-quick-tags">
                <span class="tag" onclick="askPreset('Can I safely drive the car?')">Safe to drive?</span>
                <span class="tag" onclick="askPreset('Estimated repair cost?')">Cost estimate</span>
                <span class="tag" onclick="askPreset('Recommended inspection steps?')">Inspection checklist</span>
            </div>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         3B. EXPLAINABLE AI (XAI) & BASELINE COMPARISON
         ═══════════════════════════════════════════════════════════════ --}}
    @php
        $xaiList = $rawResult['feature_contributions'] ?? ($diagnostic->feature_contributions ?? []);
    @endphp

    @if(!empty($xaiList) || !empty($comparison))
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%, 420px),1fr));gap:1.25rem;margin-bottom:1.5rem;">
            
            {{-- Explainable AI (Feature Contributions) --}}
            @if(!empty($xaiList))
                <div class="h-card" style="padding:1.35rem 1.45rem;background:var(--h-surface);border:1px solid var(--h-border);border-radius:14px;box-shadow:var(--h-card-shadow);">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem;">
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <div style="width:32px;height:32px;border-radius:8px;background:rgba(2,132,199,0.12);border:1px solid rgba(2,132,199,0.25);display:flex;align-items:center;justify-content:center;color:var(--h-teal);flex-shrink:0;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            </div>
                            <div>
                                <h3 style="font-size:0.95rem;font-weight:700;color:var(--h-text);margin:0;">Explainable AI (XAI)</h3>
                                <p style="font-size:0.73rem;color:var(--h-text3);margin:0.15rem 0 0;">Top signal features driving model classification</p>
                            </div>
                        </div>
                        <span class="h-badge h-badge-teal" style="font-size:0.62rem;">ISO Interpretability</span>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:0.75rem;margin-top:1rem;">
                        @foreach($xaiList as $contrib)
                            <div style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:10px;padding:0.75rem 0.85rem;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;gap:0.5rem;">
                                    <span style="font-size:0.8rem;font-weight:700;color:var(--h-text);">{{ $contrib['label'] }}</span>
                                    <div style="display:flex;align-items:center;gap:0.4rem;">
                                        <span class="h-mono" style="font-size:0.75rem;color:var(--h-text2);font-weight:600;">{{ $contrib['value'] }} {{ $contrib['unit'] }}</span>
                                        <span class="h-badge h-badge-teal" style="font-size:0.65rem;font-weight:700;">+{{ $contrib['impact_pct'] }}%</span>
                                    </div>
                                </div>
                                <div style="height:5px;background:var(--h-surface3, rgba(0,0,0,0.1));border-radius:999px;overflow:hidden;margin-bottom:0.45rem;">
                                    <div style="height:100%;width:{{ min(100, $contrib['impact_pct'] * 1.8) }}%;background:linear-gradient(90deg, #0284c7 0%, #38bdf8 100%);border-radius:999px;"></div>
                                </div>
                                <p style="font-size:0.73rem;color:var(--h-text2);margin:0;line-height:1.45;">{{ $contrib['explanation'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Baseline vs. Current Session Comparison --}}
            @if(!empty($comparison))
                <div class="h-card" style="padding:1.35rem 1.45rem;background:var(--h-surface);border:1px solid var(--h-border);border-radius:14px;box-shadow:var(--h-card-shadow);">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem;">
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <div style="width:32px;height:32px;border-radius:8px;background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.25);display:flex;align-items:center;justify-content:center;color:var(--h-green);flex-shrink:0;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            </div>
                            <div>
                                <h3 style="font-size:0.95rem;font-weight:700;color:var(--h-text);margin:0;">Baseline vs. Current</h3>
                                <p style="font-size:0.73rem;color:var(--h-text3);margin:0.15rem 0 0;">
                                    Compared with Session #{{ $comparison['baseline_id'] }} ({{ $comparison['baseline_date'] }})
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('diagnostics.show', $comparison['baseline_id']) }}" class="h-badge h-badge-gray" style="font-size:0.62rem;text-decoration:none;">
                            View Baseline &rarr;
                        </a>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:0.65rem;margin-top:1rem;">
                        {{-- Peak G Delta --}}
                        <div style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:10px;padding:0.7rem;">
                            <div style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:600;">Peak Accel</div>
                            <div class="h-mono" style="font-size:1.1rem;font-weight:800;color:var(--h-text);margin:0.2rem 0;">
                                {{ $comparison['peak_g']['current'] ?? '--' }}g
                            </div>
                            @if($comparison['peak_g']['delta'] !== null)
                                @php $isHigher = $comparison['peak_g']['delta'] > 0; @endphp
                                <span class="h-badge {{ $isHigher ? 'h-badge-red' : 'h-badge-green' }}" style="font-size:0.62rem;">
                                    {{ $isHigher ? '+' : '' }}{{ $comparison['peak_g']['pct'] }}% vs base
                                </span>
                            @endif
                        </div>

                        {{-- RMS G Delta --}}
                        <div style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:10px;padding:0.7rem;">
                            <div style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:600;">RMS Energy</div>
                            <div class="h-mono" style="font-size:1.1rem;font-weight:800;color:var(--h-text);margin:0.2rem 0;">
                                {{ $comparison['rms_g']['current'] ?? '--' }}g
                            </div>
                            @if($comparison['rms_g']['delta'] !== null)
                                @php $isHigher = $comparison['rms_g']['delta'] > 0; @endphp
                                <span class="h-badge {{ $isHigher ? 'h-badge-red' : 'h-badge-green' }}" style="font-size:0.62rem;">
                                    {{ $isHigher ? '+' : '' }}{{ $comparison['rms_g']['pct'] }}% vs base
                                </span>
                            @endif
                        </div>

                        {{-- Crest Factor Delta --}}
                        <div style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:10px;padding:0.7rem;">
                            <div style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:600;">Crest Factor</div>
                            <div class="h-mono" style="font-size:1.1rem;font-weight:800;color:var(--h-text);margin:0.2rem 0;">
                                {{ $comparison['crest_factor']['current'] ?? '--' }}
                            </div>
                            @if($comparison['crest_factor']['delta'] !== null)
                                <span class="h-badge {{ $comparison['crest_factor']['delta'] > 0.5 ? 'h-badge-amber' : 'h-badge-teal' }}" style="font-size:0.62rem;">
                                    Δ {{ $comparison['crest_factor']['delta'] > 0 ? '+' : '' }}{{ $comparison['crest_factor']['delta'] }}
                                </span>
                            @endif
                        </div>

                        {{-- Coolant Temp Delta --}}
                        <div style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:10px;padding:0.7rem;">
                            <div style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:600;">Coolant Temp</div>
                            <div class="h-mono" style="font-size:1.1rem;font-weight:800;color:var(--h-text);margin:0.2rem 0;">
                                {{ $comparison['temperature_c']['current'] ?? '--' }}°C
                            </div>
                            @if($comparison['temperature_c']['delta'] !== null)
                                <span class="h-badge {{ $comparison['temperature_c']['delta'] > 5 ? 'h-badge-amber' : 'h-badge-teal' }}" style="font-size:0.62rem;">
                                    Δ {{ $comparison['temperature_c']['delta'] > 0 ? '+' : '' }}{{ $comparison['temperature_c']['delta'] }}°C
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Visual Envelope Comparison Bar --}}
                    <div style="margin-top:1rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:10px;padding:0.75rem 0.9rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.7rem;color:var(--h-text3);margin-bottom:0.4rem;">
                            <span>Baseline Envelope Stability</span>
                            <span>Target: &lt;1.5g Peak</span>
                        </div>
                        <div style="position:relative;height:14px;background:var(--h-surface3, #e2e8f0);border-radius:999px;overflow:hidden;">
                            <div style="position:absolute;left:0;top:0;bottom:0;width:{{ min(100, ($comparison['peak_g']['baseline'] ?? 1.0) * 20) }}%;background:rgba(2,132,199,0.4);border-right:2px solid #0284c7;" title="Baseline"></div>
                            <div style="position:absolute;left:0;top:3px;bottom:3px;width:{{ min(100, ($comparison['peak_g']['current'] ?? 1.0) * 20) }}%;background:{{ ($comparison['peak_g']['delta'] ?? 0) > 0.5 ? '#ef4444' : '#16a34a' }};border-radius:999px;" title="Current Session"></div>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:0.68rem;color:var(--h-text3);margin-top:0.35rem;">
                            <span><strong style="color:var(--h-teal);">■</strong> Baseline (#{{ $comparison['baseline_id'] }})</span>
                            <span><strong style="color:{{ ($comparison['peak_g']['delta'] ?? 0) > 0.5 ? '#ef4444' : '#16a34a' }};">■</strong> Current (#{{ $diagnostic->id }})</span>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         4. DETAILED RECOMMENDATIONS & COPILOT CONSOLE
         ═══════════════════════════════════════════════════════════════ --}}
    @if(!empty($aiRecommendations))
        <div class="haven-ai-copilot-container" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;margin-bottom:1.5rem;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.06);">
            
            {{-- Copilot Header Bar --}}
            <div style="padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0;background:linear-gradient(90deg, rgba(2,132,199,0.06) 0%, rgba(248,250,252,0.9) 100%);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
                <div style="display:flex;align-items:center;gap:0.875rem;">
                    <div id="haven-ai-orb" class="haven-siri-orb" title="HAVEN AI Mechanic Copilot"></div>
                    <div>
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <h3 style="font-size:0.95rem;font-weight:700;color:#0f172a;margin:0;letter-spacing:-0.01em;">
                                HAVEN AI · Mechanic Copilot & Recommendations
                            </h3>
                            <span class="h-badge h-badge-teal" style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.04em;">V2.5 Live</span>
                        </div>
                        <div style="font-size:0.75rem;color:#64748b;margin-top:0.15rem;">
                            Interactive diagnostic assistant for {{ $vehicle->display_name }}
                        </div>
                    </div>
                </div>

                {{-- Actions: Voice Readout Toggle --}}
                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                    <span id="diag-history-badge" title="Saved questions in session" style="font-size:0.68rem;font-weight:600;color:#64748b;background:#f1f5f9;border:1px solid #cbd5e1;padding:0.2rem 0.55rem;border-radius:999px;display:none;">
                        0 msgs
                    </span>
                    <button type="button" id="voice-toggle-btn" class="h-btn h-btn-subtle" style="font-size:0.75rem;padding:0.35rem 0.75rem;display:inline-flex;align-items:center;gap:0.4rem;background:#ffffff;border:1px solid #cbd5e1;color:#0f172a;border-radius:8px;cursor:pointer;">
                        <svg id="voice-toggle-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                            <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                        </svg>
                        <span id="voice-toggle-label">Voice: Active</span>
                    </button>
                </div>
            </div>

            {{-- Safety Verdict & Urgency Banner --}}
            <div style="padding:1.1rem 1.25rem;border-bottom:1px solid #e2e8f0;background:#f8fafc;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:0.6rem;">
                    <div style="display:flex;align-items:center;gap:0.6rem;">
                        <span style="background:{{ $condColor['bg'] }};color:{{ $condColor['label'] }};border:1px solid {{ $condColor['border'] }};font-size:0.75rem;font-weight:700;padding:0.25rem 0.65rem;border-radius:999px;letter-spacing:0.02em;">
                            {{ $aiRecommendations['safety_status'] ?? 'OPERATIONAL' }}
                        </span>
                        <span style="font-size:0.75rem;color:#64748b;">Urgency: <strong style="color:#0f172a;">{{ $aiRecommendations['urgency'] ?? 'Standard' }}</strong></span>
                    </div>
                    <div style="font-size:0.78rem;color:#0f172a;background:#ffffff;padding:0.25rem 0.75rem;border-radius:8px;border:1px solid #e2e8f0;">
                        Estimated Repair: <strong style="color:#0284c7;">{{ $aiRecommendations['cost_estimate'] ?? 'N/A' }}</strong>
                    </div>
                </div>
                <div style="font-size:0.84rem;color:#334155;line-height:1.6;">
                    {{ $aiRecommendations['drive_safety'] ?? 'Review inspection steps before extended operation.' }}
                </div>
            </div>

            {{-- Two-Column Diagnosis: Causes & Priority Actions --}}
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%, 280px),1fr));gap:1px;background:#e2e8f0;">
                
                {{-- Probable Causes --}}
                <div style="background:#ffffff;padding:1.15rem 1.25rem;">
                    <div style="display:flex;align-items:center;gap:0.45rem;font-size:0.78rem;font-weight:700;color:#0f172a;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.75rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Probable Root Causes
                    </div>
                    <ul style="margin:0;padding-left:1.1rem;display:flex;flex-direction:column;gap:0.45rem;">
                        @foreach($aiRecommendations['probable_causes'] ?? ['Check spark plugs and ignition coils', 'Verify valvetrain lash adjustment', 'Inspect engine mount bushings'] as $cause)
                            <li style="font-size:0.8rem;color:#334155;line-height:1.5;">{{ $cause }}</li>
                        @endforeach
                    </ul>
                </div>

                {{-- Priority Action Checklist --}}
                <div style="background:#ffffff;padding:1.15rem 1.25rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                        <div style="display:flex;align-items:center;gap:0.45rem;font-size:0.78rem;font-weight:700;color:#0f172a;text-transform:uppercase;letter-spacing:0.04em;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                            Priority Action Checklist
                        </div>
                        <span style="font-size:0.7rem;color:#64748b;">Interactive</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:0.45rem;">
                        @foreach($aiRecommendations['action_checklist'] ?? ['Connect OBD2 scanner to verify cylinder misfire counts', 'Use feeler gauge on intake/exhaust valve rocker clearance', 'Torque engine bracket mounting hardware to factory spec'] as $idx => $action)
                            <label style="display:flex;align-items:flex-start;gap:0.5rem;font-size:0.8rem;color:#334155;cursor:pointer;line-height:1.45;">
                                <input type="checkbox" style="margin-top:0.2rem;accent-color:#0284c7;" onchange="this.parentElement.style.opacity = this.checked ? '0.45' : '1'; this.parentElement.style.textDecoration = this.checked ? 'line-through' : 'none';">
                                <span>{{ $action }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

            </div>

            {{-- Interactive Chat Thread & Speech Input --}}
            <div style="padding:1.25rem;background:#f8fafc;border-top:1px solid #e2e8f0;">
                <div id="ai-chat-thread" style="display:flex;flex-direction:column;gap:0.75rem;max-height:260px;overflow-y:auto;padding-right:0.25rem;margin-bottom:1rem;">
                    <div style="display:flex;align-items:flex-start;gap:0.75rem;">
                        <div style="width:28px;height:28px;border-radius:50%;background:#0284c7;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;margin-top:2px;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;border-top-left-radius:4px;padding:0.85rem 1.05rem;font-size:0.84rem;color:#0f172a;line-height:1.58;max-width:90%;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                                <strong style="color:#0284c7;font-weight:600;">Welcome to Haven AI Copilot.</strong><br>
                                {{ $aiRecommendations['summary'] ?? 'Engine signals recorded and processed.' }}
                                <div style="margin-top:0.45rem;font-size:0.75rem;color:#64748b;">
                                    Type your questions below or select a suggested prompt to explore next steps.
                                </div>
                            </div>
                            <div style="font-size:0.68rem;color:#94a3b8;margin-top:0.3rem;margin-left:0.25rem;">
                                Haven AI Assistant | {{ now()->format('g:i A') }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Quick Suggestions --}}
                <div style="display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;margin-bottom:0.85rem;">
                    <span style="font-size:0.72rem;color:#64748b;font-weight:600;margin-right:0.2rem;">Suggestions:</span>
                    @foreach($aiRecommendations['prompt_suggestions'] ?? ['Can I drive 10 miles to a garage?', 'What tools do I need for this?', 'Estimated repair cost?'] as $promptChip)
                        <button type="button" class="haven-prompt-chip" onclick="askAiQuestion('{{ addslashes($promptChip) }}')" style="background:#ffffff;border:1px solid #cbd5e1;color:#334155;padding:0.28rem 0.65rem;border-radius:999px;font-size:0.73rem;cursor:pointer;transition:all 0.15s ease;">
                            {{ $promptChip }}
                        </button>
                    @endforeach
                </div>

                {{-- Input Bar --}}
                <div style="display:flex;align-items:center;gap:0.5rem;position:relative;">
                    <div style="position:relative;flex:1;">
                        <input type="text" id="ai-question-input" placeholder="Ask a diagnostic question or type your symptom..." style="width:100%;background:#ffffff;border:1px solid #cbd5e1;border-radius:8px;padding:0.6rem 2.5rem 0.6rem 0.85rem;font-size:0.82rem;color:#0f172a;box-sizing:border-box;outline:none;" onkeydown="if(event.key === 'Enter') submitAiQuestion();">
                        <button type="button" id="ai-mic-btn" title="Click to speak (Voice Input)" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;color:#64748b;cursor:pointer;padding:5px;">
                            <svg id="mic-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>
                        </button>
                    </div>
                    <button type="button" id="ai-ask-btn" onclick="submitAiQuestion()" class="h-diag-btn-primary" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.58rem 1rem;font-size:0.8rem;white-space:nowrap;height:auto;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        <span>Ask</span>
                    </button>
                </div>

                <div id="ai-status-indicator" style="display:none;font-size:0.74rem;color:#0284c7;margin-top:0.4rem;display:flex;align-items:center;gap:0.4rem;">
                    <span class="spin" style="width:10px;height:10px;border:2px solid #0284c7;border-top-color:transparent;border-radius:50%;display:inline-block;"></span>
                    <span id="ai-status-text">Listening... Speak now</span>
                </div>
            </div>

        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         5. RAW HARDWARE DATA & RECORDINGS DETAILS
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="haven-detail-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%, 300px),1fr));gap:1rem;margin-bottom:1.5rem;">
        
        {{-- Audio Player Card --}}
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem;box-shadow:0 2px 10px rgba(0,0,0,0.03);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                <div style="display:flex;align-items:center;gap:0.5rem;font-weight:700;font-size:0.88rem;color:#0f172a;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
                    Engine Audio (INMP441)
                </div>
                <span class="h-badge {{ $diagnostic->hasAudio() ? 'h-badge-teal' : 'h-badge-gray' }}" style="font-size:0.65rem;">
                    {{ $diagnostic->hasAudio() ? 'Captured' : 'Missing' }}
                </span>
            </div>
            @if($diagnostic->hasAudio())
                <div style="font-size:0.75rem;color:#64748b;margin-bottom:0.65rem;">
                    Duration: <strong>{{ floor($diagnostic->audio_duration_sec/60) }}:{{ str_pad($diagnostic->audio_duration_sec%60,2,'0',STR_PAD_LEFT) }}</strong>
                    @if($diagnostic->audio_original_name) · {{ $diagnostic->audio_original_name }} @endif
                </div>
                <audio controls style="width:100%;height:38px;border-radius:6px;" src="{{ route('diagnostics.audio', $diagnostic) }}"></audio>
            @else
                <p style="font-size:0.78rem;color:#94a3b8;margin:0;">No audio recorded for this session.</p>
            @endif
        </div>

        {{-- Vibration Data Card --}}
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem;box-shadow:0 2px 10px rgba(0,0,0,0.03);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                <div style="display:flex;align-items:center;gap:0.5rem;font-weight:700;font-size:0.88rem;color:#0f172a;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Vibration Data (ADXL345)
                </div>
                <span class="h-badge {{ $diagnostic->hasVibration() ? 'h-badge-teal' : 'h-badge-gray' }}" style="font-size:0.65rem;">
                    {{ $diagnostic->hasVibration() ? 'Captured' : 'Missing' }}
                </span>
            </div>
            @if($diagnostic->hasVibration())
                <div style="display:flex;justify-content:space-between;font-size:0.78rem;margin-bottom:0.75rem;">
                    <span>Samples: <strong>{{ number_format($diagnostic->vibration_sample_count) }}</strong></span>
                    <span>Peak: <strong style="color:#d97706;">{{ $diagnostic->vibration_peak_g }}g</strong></span>
                    <span>RMS: <strong style="color:#0284c7;">{{ $diagnostic->vibration_rms_g }}g</strong></span>
                </div>
                <a href="{{ route('diagnostics.vibration', $diagnostic) }}" download class="h-diag-btn-subtle" style="text-decoration:none;font-size:0.75rem;padding:0.4rem 0.75rem;display:inline-flex;align-items:center;gap:0.4rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download Vibration CSV
                </a>
            @else
                <p style="font-size:0.78rem;color:#94a3b8;margin:0;">No vibration data captured.</p>
            @endif
        </div>

        {{-- Session Meta Card --}}
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem;box-shadow:0 2px 10px rgba(0,0,0,0.03);">
            <div style="font-weight:700;font-size:0.88rem;color:#0f172a;margin-bottom:0.75rem;">Session Metadata</div>
            <div style="display:flex;flex-direction:column;gap:0.4rem;font-size:0.78rem;">
                <div style="display:flex;justify-content:space-between;"><span style="color:#64748b;">Vehicle:</span><span style="font-weight:600;color:#0f172a;">{{ $vehicle->display_name }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#64748b;">Date:</span><span style="font-weight:600;color:#0f172a;">{{ $diagnostic->created_at->format('M j, Y · g:i A') }}</span></div>
                @if($diagnostic->ml_processed_at)
                    <div style="display:flex;justify-content:space-between;"><span style="color:#64748b;">Analyzed:</span><span style="font-weight:600;color:#0284c7;">{{ $diagnostic->ml_processed_at->format('M j, g:i A') }}</span></div>
                @endif
                <div style="display:flex;justify-content:space-between;"><span style="color:#64748b;">Mode:</span><span style="font-weight:600;color:#0f172a;">{{ $diagnostic->recording_mode_label }}</span></div>
            </div>
        </div>

    </div>

</div>

<style>
/* ── Executive Studio Theme Variables (Dark & Light Mode Adaptive) ── */
:root, [data-theme="light"] {
    --ds-bg: var(--h-bg, #f8fafc);
    --ds-surface: var(--h-surface, #ffffff);
    --ds-surface-subtle: var(--h-surface2, #f1f5f9);
    --ds-border: var(--h-border, #e2e8f0);
    --ds-border-light: rgba(226, 232, 240, 0.7);
    --ds-text: var(--h-text, #0f172a);
    --ds-text-muted: var(--h-text2, #475569);
    --ds-text-dim: var(--h-text3, #94a3b8);
    --ds-blue: #0284c7;
    --ds-blue-light: rgba(2, 132, 199, 0.08);
    --ds-blue-glow: rgba(56, 189, 248, 0.4);
    --ds-green: #10b981;
    --ds-green-light: rgba(16, 185, 129, 0.1);
    --ds-amber: #f59e0b;
    --ds-amber-light: rgba(245, 158, 11, 0.1);
    --ds-red: #ef4444;
    --ds-red-light: rgba(239, 68, 68, 0.1);
    --ds-dark-card: #0f172a;
    --ds-stage-bg: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    --ds-hud-fade: linear-gradient(90deg, rgba(255,255,255,0.96) 0%, rgba(255,255,255,0.85) 80%, rgba(255,255,255,0) 100%);
    --ds-radius-card: 18px;
    --ds-radius-pill: 9999px;
    --ds-shadow-card: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
}

[data-theme="dark"] {
    --ds-bg: var(--h-bg, #07090e);
    --ds-surface: var(--h-surface, #0d111a);
    --ds-surface-subtle: var(--h-surface2, #131a27);
    --ds-border: var(--h-border, #1e293b);
    --ds-border-light: rgba(30, 41, 59, 0.7);
    --ds-text: var(--h-text, #f8fafc);
    --ds-text-muted: var(--h-text2, #94a3b8);
    --ds-text-dim: var(--h-text3, #64748b);
    --ds-blue: #38bdf8;
    --ds-blue-light: rgba(56, 189, 248, 0.12);
    --ds-blue-glow: rgba(56, 189, 248, 0.35);
    --ds-green: #34d399;
    --ds-green-light: rgba(52, 211, 153, 0.14);
    --ds-amber: #fbbf24;
    --ds-amber-light: rgba(251, 191, 36, 0.14);
    --ds-red: #f87171;
    --ds-red-light: rgba(248, 113, 113, 0.16);
    --ds-dark-card: #080c14;
    --ds-stage-bg: linear-gradient(180deg, #0e1524 0%, #070b13 100%);
    --ds-hud-fade: linear-gradient(90deg, rgba(14, 21, 36, 0.98) 0%, rgba(14, 21, 36, 0.88) 80%, rgba(14, 21, 36, 0) 100%);
    --ds-shadow-card: 0 4px 24px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(30, 41, 59, 0.7);
}

.h-diag-studio-wrapper {
    max-width: 1360px;
    margin: 0 auto;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--ds-text);
}

/* ── Top Navigation Bar ── */
.h-diag-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1rem;
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-pill);
    box-shadow: var(--ds-shadow-card);
    margin-bottom: 1.25rem;
}
.h-diag-topbar-left, .h-diag-topbar-right {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.h-diag-vehicle-pill {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-pill);
    padding: 0.35rem 0.85rem 0.35rem 0.5rem;
}
.h-diag-vehicle-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--ds-blue-light);
    color: var(--ds-blue);
    display: flex;
    align-items: center;
    justify-content: center;
}
.h-diag-mode-pill {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-pill);
    padding: 0.45rem 0.85rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ds-text-muted);
    cursor: pointer;
    transition: all 0.15s ease;
}
.h-diag-mode-pill:hover {
    border-color: var(--ds-blue);
    color: var(--ds-blue);
}
.h-diag-topbar-btn {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-pill);
    padding: 0.45rem 0.85rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ds-text-muted);
    cursor: pointer;
    transition: all 0.15s ease;
}
.h-diag-topbar-btn:hover {
    border-color: var(--ds-blue);
    color: var(--ds-blue);
}
.h-diag-counter-pill {
    background: var(--ds-blue);
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.1rem 0.4rem;
    border-radius: var(--ds-radius-pill);
}
.h-diag-daq-pill {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-pill);
    padding: 0.35rem 0.75rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--ds-text);
    letter-spacing: 0.04em;
}
.h-diag-live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--ds-green);
    box-shadow: 0 0 6px var(--ds-green);
}
.h-diag-avatar-wrap {
    margin-left: 0.25rem;
}
.h-diag-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0284c7, #0369a1);
    color: #fff;
    font-size: 0.78rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ── 2. Stage Hero Card (3D Vehicle + Left HUD) ── */
.h-diag-stage-card {
    position: relative;
    background: var(--ds-stage-bg);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-card);
    box-shadow: var(--ds-shadow-card);
    display: grid;
    grid-template-columns: 320px 1fr;
    min-height: 400px;
    overflow: hidden;
    margin-bottom: 1.25rem;
}
@media (max-width: 960px) {
    .h-diag-stage-card {
        grid-template-columns: 1fr;
    }
}
.h-diag-hud-left {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    z-index: 10;
    background: var(--ds-hud-fade);
}
.h-diag-hud-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: var(--ds-blue);
    background: var(--ds-blue-light);
    border: 1px solid rgba(2, 132, 199, 0.2);
    padding: 0.25rem 0.65rem;
    border-radius: var(--ds-radius-pill);
    align-self: flex-start;
}
.h-diag-hud-badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--ds-blue);
    animation: hudPulse 1.6s infinite;
}
@keyframes hudPulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.8); }
}
.h-diag-hud-title {
    font-size: 2.25rem;
    font-weight: 900;
    letter-spacing: -0.04em;
    margin: 0.5rem 0 0;
    color: var(--ds-text);
    line-height: 1;
}
.h-diag-hud-progress-block {
    margin: 1rem 0;
}
.h-diag-hud-pct {
    font-size: 2.75rem;
    font-weight: 900;
    letter-spacing: -0.05em;
    line-height: 1;
    color: var(--ds-text);
}
.h-diag-hud-status-label {
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: var(--ds-text-muted);
    text-transform: uppercase;
    margin-top: 0.25rem;
}
.h-diag-hud-tag-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.h-diag-status-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}
.h-diag-status-chip.normal {
    background: var(--ds-green-light);
    color: var(--ds-green);
    border: 1px solid rgba(16, 185, 129, 0.3);
}
.h-diag-status-chip.warning {
    background: var(--ds-amber-light);
    color: var(--ds-amber);
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.h-diag-status-chip.danger {
    background: var(--ds-red-light);
    color: var(--ds-red);
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.h-diag-pipeline-ticker {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.h-ticker-item {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: var(--ds-text-dim);
}
.h-ticker-item.active {
    color: var(--ds-text);
}
.h-ticker-bullet {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--ds-border);
}
.h-ticker-item.active .h-ticker-bullet {
    background: var(--ds-blue);
    box-shadow: 0 0 6px var(--ds-blue);
}
.h-diag-cta-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    color: #fff;
    border: none;
    border-radius: var(--ds-radius-pill);
    padding: 0 1.25rem;
    height: 42px;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(2, 132, 199, 0.35);
    transition: all 0.2s ease;
}
.h-diag-cta-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(2, 132, 199, 0.45);
}
.h-diag-btn-subtle {
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    color: var(--ds-text-muted);
    border-radius: var(--ds-radius-pill);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.h-diag-btn-subtle:hover {
    border-color: var(--ds-blue);
    color: var(--ds-blue);
}

/* ── Center Stage Viewport & 3D Car ── */
.h-diag-stage-viewport {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 380px;
}
.h-diag-car-wrap {
    position: relative;
    width: 90%;
    max-width: 720px;
    transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.h-diag-car-svg {
    width: 100%;
    height: auto;
    display: block;
}

/* ── Interactive Pins on 3D Car ── */
.h-diag-pin {
    position: absolute;
    transform: translate(-50%, -50%);
    cursor: pointer;
    z-index: 20;
}
.h-diag-pin-beacon {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 32px; height: 32px;
    border-radius: 50%;
    background: rgba(2, 132, 199, 0.25);
    animation: pinBeacon 2s infinite;
}
.h-diag-pin-beacon.orange { background: rgba(245, 158, 11, 0.3); }
.h-diag-pin-beacon.red { background: rgba(239, 68, 68, 0.35); }
@keyframes pinBeacon {
    0% { transform: translate(-50%, -50%) scale(0.6); opacity: 1; }
    100% { transform: translate(-50%, -50%) scale(1.8); opacity: 0; }
}
.h-diag-pin-core {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.18);
    position: relative;
    z-index: 2;
    transition: transform 0.2s;
}
.h-diag-pin:hover .h-diag-pin-core { transform: scale(1.15); }
.h-diag-pin-core.blue { background: #0284c7; }
.h-diag-pin-core.orange { background: #f59e0b; }
.h-diag-pin-core.red { background: #ef4444; }
.h-diag-pin-core.green { background: #10b981; }
.h-diag-pin-core.dark { background: #0f172a; }
.h-diag-pin-tooltip {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    background: #0f172a;
    color: #fff;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    font-size: 0.68rem;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s, transform 0.2s;
    box-shadow: 0 4px 14px rgba(0,0,0,0.25);
    display: flex;
    flex-direction: column;
    align-items: center;
}
.h-diag-pin:hover .h-diag-pin-tooltip {
    opacity: 1;
    transform: translateX(-50%) translateY(-4px);
}
.h-diag-pin-tooltip span { color: #94a3b8; font-size: 0.62rem; }
.h-diag-viewport-controls {
    position: absolute;
    top: 1.25rem;
    right: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    z-index: 15;
}
.h-diag-zoom-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    color: var(--ds-text);
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}

/* ── 3. Four Bottom Executive Cards Grid ── */
.h-diag-grid-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 1100px) {
    .h-diag-grid-cards { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .h-diag-grid-cards { grid-template-columns: 1fr; }
}
.h-diag-card {
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-card);
    padding: 1.25rem;
    box-shadow: var(--ds-shadow-card);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.h-diag-card.dark {
    background: var(--ds-dark-card);
    border-color: #1e293b;
    color: #fff;
    box-shadow: 0 8px 30px rgba(15, 23, 42, 0.25);
}
.h-diag-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.85rem;
}
.h-diag-card-title {
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: var(--ds-text-muted);
}
.h-diag-card-title.light { color: #94a3b8; }
.h-diag-card-status-badge {
    font-size: 0.62rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: var(--ds-radius-pill);
}
.h-diag-card-status-badge.ok {
    background: var(--ds-green-light);
    color: var(--ds-green);
}
.h-diag-badge-dark {
    font-size: 0.85rem;
    font-weight: 800;
    border: 1px solid;
    padding: 0.1rem 0.5rem;
    border-radius: var(--ds-radius-pill);
}

/* Equalizer Probes */
.h-diag-probes-container {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    align-items: flex-end;
    min-height: 110px;
    padding: 0.5rem 0;
}
.h-diag-probe-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
}
.h-diag-probe-pillar {
    width: 14px;
    height: 70px;
    background: var(--ds-surface-subtle);
    border-radius: var(--ds-radius-pill);
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: flex-end;
}
.h-diag-probe-bar {
    width: 100%;
    border-radius: var(--ds-radius-pill);
    transition: height 0.3s ease;
}
.h-diag-probe-bar.blue { background: linear-gradient(180deg, #38bdf8, #0284c7); }
.h-diag-probe-bar.orange { background: linear-gradient(180deg, #fbbf24, #f59e0b); }
.h-diag-probe-bar.green { background: linear-gradient(180deg, #34d399, #10b981); }
.h-diag-probe-name {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--ds-text);
}
.h-diag-probe-status {
    font-size: 0.62rem;
    font-weight: 600;
}
.h-diag-probe-status.good { color: var(--ds-green); }
.h-diag-probe-status.warn { color: var(--ds-amber); }
.h-diag-probe-status.neutral { color: var(--ds-text-dim); }

.h-diag-card-footer-metrics {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid var(--ds-border);
    padding-top: 0.75rem;
    margin-top: 0.75rem;
}
.h-diag-card-footer-metrics .metric {
    display: flex;
    flex-direction: column;
}
.h-diag-card-footer-metrics .m-val {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--ds-text);
}
.h-diag-card-footer-metrics .m-lbl {
    font-size: 0.62rem;
    color: var(--ds-text-muted);
}

/* Dark DAQ Card Elements */
.h-diag-engine-cutout-wrap {
    height: 85px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.h-diag-cutout-svg { width: 100%; height: 100%; }
.h-diag-daq-result-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0.5rem 0;
    padding: 0.35rem 0;
    border-top: 1px solid #1e293b;
}
.h-daq-label { font-size: 0.75rem; color: #94a3b8; }
.h-daq-status-pill { font-size: 0.85rem; font-weight: 800; }
.h-diag-daq-footer-specs {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.7rem;
    color: #64748b;
    border-top: 1px solid #1e293b;
    padding-top: 0.5rem;
}

/* Dual Gauges */
.h-diag-dual-gauges {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    min-height: 110px;
}
.h-diag-gauge-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.h-gauge-label {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--ds-text-muted);
}
.h-gauge-arc-wrap {
    position: relative;
    width: 100%;
    max-width: 110px;
    margin-top: 0.25rem;
}
.h-gauge-svg { width: 100%; height: auto; }
.h-gauge-value-display {
    text-align: center;
    margin-top: -8px;
}
.h-gauge-value-display .val {
    font-size: 1.15rem;
    font-weight: 900;
    color: var(--ds-text);
}
.h-gauge-value-display .unit {
    display: block;
    font-size: 0.62rem;
    color: var(--ds-text-muted);
}

/* Embedded Copilot Card */
.h-diag-orb-small {
    width: 12px; height: 12px;
    border-radius: 50%;
    background: radial-gradient(circle, #38bdf8 0%, #0284c7 100%);
    box-shadow: 0 0 8px #38bdf8;
    animation: orbPulse 2s infinite alternate;
}
@keyframes orbPulse {
    from { transform: scale(0.9); opacity: 0.8; }
    to { transform: scale(1.15); opacity: 1; }
}
.h-diag-ai-body {
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    border-radius: 12px;
    padding: 0.75rem;
    max-height: 95px;
    overflow-y: auto;
    font-size: 0.75rem;
    line-height: 1.5;
    margin-bottom: 0.6rem;
}
.h-diag-ai-msg p { margin: 0; }
.h-diag-ai-card-input-row {
    display: flex;
    gap: 0.4rem;
    margin-bottom: 0.5rem;
}
.h-diag-ai-card-input-row input {
    flex: 1;
    border: 1px solid var(--ds-border);
    background: var(--ds-surface);
    border-radius: 8px;
    padding: 0.4rem 0.65rem;
    font-size: 0.75rem;
    outline: none;
}
.h-diag-ai-send-btn {
    background: var(--ds-blue);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 0 0.75rem;
    cursor: pointer;
}
.h-diag-ai-quick-tags {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
}
.h-diag-ai-quick-tags .tag {
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    padding: 0.15rem 0.5rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.65rem;
    color: var(--ds-text-muted);
    cursor: pointer;
    transition: all 0.15s ease;
}
.h-diag-ai-quick-tags .tag:hover {
    border-color: var(--ds-blue);
    color: var(--ds-blue);
}

/* Animations */
@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin 1s linear infinite; display: inline-block; }
.haven-prompt-chip:hover {
    border-color: #0284c7 !important;
    color: #0284c7 !important;
    background: rgba(2, 132, 199, 0.08) !important;
}
.ai-bubble-user {
    background: #0284c7;
    color: #fff;
    border-radius: 12px;
    padding: 0.6rem 0.85rem;
    font-size: 0.8rem;
    max-width: 80%;
    align-self: flex-end;
}
.ai-bubble-bot {
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    color: var(--ds-text);
    border-radius: 12px;
    padding: 0.75rem 0.95rem;
    font-size: 0.8rem;
    max-width: 88%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.mic-active {
    color: #ef4444 !important;
    animation: micPulse 1s infinite alternate;
}
@keyframes micPulse {
    from { transform: translateY(-50%) scale(1); }
    to { transform: translateY(-50%) scale(1.2); }
}

@media (max-width: 640px) {
    .h-diag-topbar {
        border-radius: 16px;
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }
    .h-diag-topbar-left, .h-diag-topbar-right {
        width: 100%;
        justify-content: space-between;
    }
    .h-diag-hud-left {
        padding: 1.25rem;
    }
    .h-diag-stage-viewport {
        min-height: 240px;
    }
    .h-diag-stage-card {
        min-height: auto;
    }
}
</style>

{{-- Copilot Client Script --}}
@if(!empty($aiRecommendations))
<script>
document.addEventListener('DOMContentLoaded', function() {
    const thread = document.getElementById('ai-chat-thread');
    const input = document.getElementById('ai-question-input');
    const askBtn = document.getElementById('ai-ask-btn');
    const micBtn = document.getElementById('ai-mic-btn');
    const statusBox = document.getElementById('ai-status-indicator');
    const statusText = document.getElementById('ai-status-text');
    const voiceToggleBtn = document.getElementById('voice-toggle-btn');
    const voiceToggleLabel = document.getElementById('voice-toggle-label');

    let voiceEnabled = true;
    let isListening = false;
    let recognition = null;

    if (voiceToggleBtn) {
        voiceToggleBtn.addEventListener('click', function() {
            voiceEnabled = !voiceEnabled;
            voiceToggleLabel.textContent = voiceEnabled ? 'Voice: Active' : 'Voice: Muted';
            if (!voiceEnabled && 'speechSynthesis' in window) window.speechSynthesis.cancel();
        });
    }

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition && micBtn) {
        recognition = new SpeechRecognition();
        recognition.onstart = function() {
            isListening = true;
            micBtn.classList.add('mic-active');
            if (statusBox) { statusBox.style.display = 'flex'; statusText.textContent = 'Listening... Speak now'; }
        };
        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            input.value = transcript;
            setTimeout(() => { submitAiQuestion(); }, 400);
        };
        recognition.onend = function() {
            isListening = false;
            micBtn.classList.remove('mic-active');
            if (statusBox) statusBox.style.display = 'none';
        };
        micBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (isListening) { recognition.stop(); } else { recognition.start(); }
        });
    }

    window.submitAiQuestion = function() {
        const query = (input ? input.value : '').trim();
        if (!query) return;
        input.value = '';

        appendUserMsg(query);

        fetch('{{ route('diagnostics.ai.ask', $diagnostic) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ question: query })
        })
        .then(res => res.json())
        .then(data => {
            const reply = data.answer || 'Thank you. I have logged your diagnostic query.';
            appendBotMsg(reply);
            if (voiceEnabled && 'speechSynthesis' in window) {
                const u = new SpeechSynthesisUtterance(reply.replace(/[*#_`]/g, ''));
                window.speechSynthesis.speak(u);
            }
        })
        .catch(err => {
            appendBotMsg('An error occurred communicating with Haven AI. Please try again.');
        });
    };

    window.askAiQuestion = function(text) {
        if (input) { input.value = text; submitAiQuestion(); }
    };

    function appendUserMsg(msg) {
        if (!thread) return;
        const d = document.createElement('div');
        d.className = 'ai-bubble-user';
        d.textContent = msg;
        thread.appendChild(d);
        thread.scrollTop = thread.scrollHeight;
    }

    function appendBotMsg(msg) {
        if (!thread) return;
        const d = document.createElement('div');
        d.className = 'ai-bubble-bot';
        d.innerHTML = escapeHtml(msg || '').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
        thread.appendChild(d);
        thread.scrollTop = thread.scrollHeight;
    }
});

// Quick embedded card functions
window.sendQuickAi = function() {
    const qIn = document.getElementById('ai-quick-input');
    const val = qIn ? qIn.value.trim() : '';
    if (!val) return;
    qIn.value = '';
    const feed = document.getElementById('ai-quick-feed');
    if (feed) {
        feed.innerHTML += `<div class="h-diag-ai-msg bot" style="margin-top:0.35rem;border-top:1px solid #e2e8f0;padding-top:0.35rem;"><p><strong>You:</strong> ${escapeHtml(val)}</p></div>`;
    }
    fetch('{{ route('diagnostics.ai.ask', $diagnostic) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ question: val })
    })
    .then(r => r.json())
    .then(d => {
        if (feed) {
            feed.innerHTML += `<div class="h-diag-ai-msg bot" style="margin-top:0.35rem;"><p><strong>AI:</strong> ${escapeHtml(d.answer || 'Response recorded.')}</p></div>`;
            feed.scrollTop = feed.scrollHeight;
        }
    });
};

window.askPreset = function(q) {
    const qIn = document.getElementById('ai-quick-input');
    if (qIn) { qIn.value = q; sendQuickAi(); }
};

function escapeHtml(t) {
    const div = document.createElement('div');
    div.textContent = t;
    return div.innerHTML;
}

let currentZoom = 1;
window.zoomVehicle = function(factor) {
    currentZoom *= factor;
    currentZoom = Math.min(1.4, Math.max(0.8, currentZoom));
    const car = document.getElementById('car-stage-wrap');
    if (car) car.style.transform = `scale(${currentZoom})`;
};

let currentPerspective = 0;
window.toggleVehiclePerspective = function() {
    currentPerspective = (currentPerspective + 1) % 4;
    const angles = ['360° Studio View', 'Front Powertrain View', 'Acoustic Bay View', 'Undercarriage View'];
    const transforms = ['rotate(0deg)', 'rotate(4deg) scale(1.05)', 'rotate(-3deg) scale(1.03)', 'rotate(0deg) scale(0.95)'];
    document.getElementById('view-mode-label').textContent = angles[currentPerspective];
    const car = document.getElementById('car-stage-wrap');
    if (car) car.style.transform = transforms[currentPerspective];
};
</script>
@endif

</x-app-layout>
