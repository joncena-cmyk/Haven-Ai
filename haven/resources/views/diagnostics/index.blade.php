<x-app-layout title="Diagnostics — HAVEN AI">

{{-- Official Sketchfab Viewer API v1.12.1 --}}
<script src="https://static.sketchfab.com/api/sketchfab-viewer-1.12.1.js"></script>

@php
    // All variables injected by index() controller:
    // $vehicles, $selectedVehicle, $selectedVid, $diagnostics,
    // $vehicleStats, $total, $completed, $conditions
    $latestDiag      = $diagnostics->first();
    $latestCondition = $latestDiag?->ml_condition ? \App\Models\Diagnostic::canonicalCondition($latestDiag->ml_condition) : null;
    $condColor = match($latestCondition) {
        'normal'          => ['icon'=>'#10b981','chip'=>'normal',  'label'=>'var(--ds-green)'],
        'misfire'         => ['icon'=>'#f59e0b','chip'=>'warning', 'label'=>'var(--ds-amber)'],
        'loose_component' => ['icon'=>'#f59e0b','chip'=>'warning', 'label'=>'var(--ds-amber)'],
        'valve_clearance' => ['icon'=>'#ef4444','chip'=>'danger',  'label'=>'var(--ds-red)'],
        default           => ['icon'=>'#0284c7','chip'=>'normal',  'label'=>'var(--ds-blue)'],
    };
@endphp

<div class="h-diag-studio-wrapper" id="diag-hub">

    {{-- ═══════════════════════════════════════════════════════════════
         1. TOP NAVIGATION — Vehicle Selector + Responsive Controls
         ═══════════════════════════════════════════════════════════════ --}}
    <header class="h-diag-topbar">
        <div class="h-diag-topbar-left">
            {{-- Vehicle Dropdown Selector (Pill) --}}
            <div class="h-diag-vehicle-dropdown-wrap">
                <button type="button" class="h-diag-vehicle-pill" id="vehicle-pill" onclick="toggleVehicleMenu()" aria-expanded="false" title="Switch Active Vehicle">
                    <div class="h-diag-vehicle-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                            <circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle>
                        </svg>
                    </div>
                    <span id="active-vehicle-name" class="h-diag-pill-title">
                        {{ $selectedVehicle?->display_name ?? 'Select Vehicle' }}
                    </span>
                    @if($selectedVehicle)
                        <span class="h-diag-vehicle-year">
                            {{ $selectedVehicle->year }}
                        </span>
                    @endif
                    <svg class="h-diag-chevron" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </button>

                {{-- Vehicle Dropdown Panel --}}
                <div id="vehicle-menu" class="h-diag-dropdown-menu" style="display:none;">
                    <div class="h-diag-dropdown-header">
                        Your Vehicles
                    </div>
                    <div class="h-diag-dropdown-scroll">
                        @forelse($vehicles as $v)
                            <a href="{{ route('diagnostics.index', ['vehicle_id' => $v->id]) }}" class="h-diag-dropdown-item {{ $v->id == $selectedVid ? 'is-active' : '' }}">
                                <div class="h-diag-dropdown-item-left">
                                    <div class="h-diag-dropdown-v-icon">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                    </div>
                                    <div>
                                        <div class="h-diag-dropdown-v-name">{{ $v->display_name }}</div>
                                        <div class="h-diag-dropdown-v-meta">{{ $v->year }} · {{ $v->engine_type ?: 'Gasoline ICE' }}</div>
                                    </div>
                                </div>
                                <div class="h-diag-dropdown-v-stat">
                                    {{ $vehicleStats[$v->id]['total'] ?? 0 }} sessions
                                </div>
                            </a>
                        @empty
                            <div class="h-diag-dropdown-empty">No vehicles registered yet.</div>
                        @endforelse
                    </div>
                    <div class="h-diag-dropdown-footer">
                        <a href="{{ route('vehicles.create') }}" class="h-diag-dropdown-add">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Register New Vehicle
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <div class="h-diag-topbar-right">
            {{-- Quick Search --}}
            <div class="h-diag-search-wrap">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search sessions..." oninput="filterHistory(this.value)" id="hist-search" aria-label="Search diagnostic history">
                <button type="button" onclick="focusHistorySearch()" aria-label="Search diagnostic history" title="Search sessions">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </div>

            {{-- Notifications / History Count --}}
            <a href="#history-section" class="h-diag-topbar-btn" title="Total Sessions" style="text-decoration:none;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                @if($total > 0)
                    <span class="h-diag-notif-badge">{{ min($total, 99) }}</span>
                @endif
            </a>

            {{-- User Profile Avatar --}}
            <div class="h-diag-avatar-wrap">
                <div class="h-diag-avatar">{{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}</div>
            </div>
        </div>
    </header>

    @if($vehicles->isEmpty())
        {{-- No Vehicles State --}}
        <div class="h-diag-empty-card">
            <div class="h-diag-empty-icon">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <h2>Register Your Vehicle First</h2>
            <p>Add your ICE gasoline vehicle to start acoustic and vibration diagnostics with ESP32 sensors.</p>
            <a href="{{ route('vehicles.create') }}" class="h-diag-btn-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Register Vehicle</span>
            </a>
        </div>
    @else

    @if(false)
    {{-- ═══════════════════════════════════════════════════════════════
         2. MAIN DIAGNOSTIC HERO (3D Vehicle Stage + HUD)
         ═══════════════════════════════════════════════════════════════ --}}
    <section class="h-diag-stage-card">

        {{-- Left HUD --}}
        <div class="h-diag-hud-left">
            <div class="h-diag-hud-badge">
                <span class="h-diag-hud-badge-dot"></span>
                <span>SYSTEM DIAGNOSTIC ACTIVE</span>
            </div>

            <h1 class="h-diag-hud-title">DIAGNOSTIC</h1>

            {{-- Status display from last session --}}
            <div class="h-diag-hud-progress-block">
                @if($latestDiag && $latestDiag->status === 'completed')
                    <div class="h-diag-hud-pct" style="color:{{ $condColor['label'] }};">
                        {{ $latestDiag->confidence_percent ?: '—' }}
                    </div>
                    <div class="h-diag-hud-status-label">LAST SESSION · ENGINE ANALYZED</div>
                @else
                    <div class="h-diag-hud-pct">0%</div>
                    <div class="h-diag-hud-status-label">READY FOR ENGINE CAPTURE</div>
                @endif
            </div>

            {{-- Condition Tags --}}
            <div class="h-diag-hud-tag-row">
                @if($latestDiag && $latestDiag->ml_condition)
                    @php $clabel = ucwords(str_replace('_', ' ', $latestDiag->ml_condition)); @endphp
                    <div class="h-diag-status-chip {{ $condColor['chip'] }}">
                        <span>{{ $latestCondition === 'normal' ? '✓' : '⚠️' }}</span>
                        <span>{{ strtoupper($clabel) }}</span>
                    </div>
                    <a href="{{ route('diagnostics.show', $latestDiag) }}" class="h-diag-report-pill">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        <span>AI Report</span>
                    </a>
                @else
                    <div class="h-diag-status-chip normal">
                        <span>✓</span>
                        <span>ENGINE STANDBY</span>
                    </div>
                @endif
            </div>

            {{-- Recent Session Ticker --}}
            <div class="h-diag-pipeline-ticker">
                @if($latestDiag)
                    <div class="h-ticker-item is-live">
                        <span class="h-ticker-bullet"></span>
                        <span class="h-ticker-txt">{{ strtoupper($latestDiag->status === 'completed' ? 'DONE' : $latestDiag->status) }} · SESSION #{{ $latestDiag->id }}</span>
                    </div>
                    @if($latestDiag->ml_condition)
                        <div class="h-ticker-item is-live">
                            <span class="h-ticker-bullet"></span>
                            <span class="h-ticker-txt">{{ strtoupper(str_replace('_',' ',$latestDiag->ml_condition)) }}</span>
                        </div>
                    @endif
                @endif
                @if($diagnostics->count() > 1)
                    @php $prev = $diagnostics->get(1); @endphp
                    <div class="h-ticker-item">
                        <span class="h-ticker-bullet"></span>
                        <span class="h-ticker-txt">PREV #{{ $prev->id }} · {{ $prev->ml_condition ? strtoupper(str_replace('_',' ',$prev->ml_condition)) : strtoupper($prev->status) }}</span>
                    </div>
                @endif
                <div class="h-ticker-item {{ $completed > 0 ? 'is-live' : '' }}">
                    <span class="h-ticker-bullet"></span>
                    <span class="h-ticker-txt">{{ $completed }}/{{ $total }} SESSIONS COMPLETE</span>
                </div>
            </div>

            {{-- Master CTA: Start Diagnostic --}}
            <div class="h-diag-hud-cta-wrap">
                <a href="{{ route('diagnostics.create', ['vehicle_id' => $selectedVid]) }}" class="h-diag-cta-btn">
                    <span class="h-diag-cta-glow"></span>
                    <span class="h-diag-cta-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
                    </span>
                    <span>Diagnose</span>
                    <svg class="h-diag-cta-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg>
                </a>
            </div>
        </div>

        {{-- Center/Right: 3D Vehicle Stage --}}
        <div class="h-diag-stage-viewport" id="stage-viewport">

            {{-- 3D Interactive Vehicle Stage (Sketchfab Viewer API 1.12.1) --}}
            <div class="h-diag-3d-stage" id="sketchfab-stage" style="{{ $selectedVehicle?->sketchfab_uid ? '' : 'display: none;' }}">
                <iframe
                    id="sketchfab-frame"
                    class="h-diag-sketchfab-iframe"
                    title="HAVEN AI 3D Automotive Diagnostic Stage"
                    src=""
                    allow="autoplay; fullscreen; xr-spatial-tracking"
                    xr-spatial-tracking
                    execution-while-out-of-viewport
                    execution-while-not-rendered
                    web-share
                    allowfullscreen
                    mozallowfullscreen="true"
                    webkitallowfullscreen="true">
                </iframe>

                {{-- Interactive Telemetry Sensor Pins Overlay (Click pin to orbit/focus component) --}}
                <div class="h-diag-pins-overlay" id="pins-overlay">
                    {{-- INMP441 Acoustic on Hood / Engine Bay --}}
                    <div class="h-diag-pin" style="top:30%;left:24%;" title="INMP441 Acoustic Sensor — Click to Inspect" onclick="focusComponent('engine')">
                        <div class="h-diag-pin-beacon {{ in_array($latestCondition, ['valve_clearance','misfire']) ? 'red' : '' }}"></div>
                        <div class="h-diag-pin-core {{ in_array($latestCondition, ['valve_clearance','misfire']) ? 'red' : 'blue' }}">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
                        </div>
                        <div class="h-diag-pin-tooltip">
                            <strong>INMP441 Acoustic Mic</strong>
                            <span>I2S 44.1kHz · Engine Audio</span>
                        </div>
                    </div>

                    {{-- ADXL345 Vibration on Powertrain --}}
                    <div class="h-diag-pin" style="top:48%;left:36%;" title="ADXL345 Vibration Sensor — Click to Inspect" onclick="focusComponent('powertrain')">
                        <div class="h-diag-pin-beacon {{ $latestCondition === 'loose_component' ? 'orange' : '' }}"></div>
                        <div class="h-diag-pin-core {{ $latestCondition === 'loose_component' ? 'orange' : 'blue' }}">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <div class="h-diag-pin-tooltip">
                            <strong>ADXL345 Accelerometer</strong>
                            <span>I2C · Vibration G-Force</span>
                        </div>
                    </div>

                    {{-- INA219 Battery Power Sensor --}}
                    <div class="h-diag-pin" style="top:62%;left:64%;" title="INA219 Power Monitor — Click to Inspect" onclick="focusComponent('battery')">
                        <div class="h-diag-pin-core dark">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><rect x="1" y="6" width="18" height="12" rx="2"/><line x1="23" y1="10" x2="23" y2="14"/></svg>
                        </div>
                        <div class="h-diag-pin-tooltip">
                            <strong>INA219 Power Monitor</strong>
                            <span>12V Bus · Current Draw</span>
                        </div>
                    </div>

                    {{-- Hall RPM Sensor --}}
                    <div class="h-diag-pin" style="top:44%;left:78%;" title="Hall Effect RPM Sensor — Click to Inspect" onclick="focusComponent('rpm')">
                        <div class="h-diag-pin-core green">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        </div>
                        <div class="h-diag-pin-tooltip">
                            <strong>Hall Effect Tachometer</strong>
                            <span>RPM · Contextual Indicator</span>
                        </div>
                    </div>
                </div>

                {{-- 3D Stage Loader --}}
                <div class="h-diag-3d-loader" id="sketchfab-loader">
                    <div class="h-diag-3d-loader-spinner"></div>
                    <div class="h-diag-3d-loader-text">
                        <span class="h-diag-3d-loader-title">3D VEHICLE STAGE</span>
                        <span class="h-diag-3d-loader-sub">Connecting Sketchfab Viewer API 1.12.1...</span>
                    </div>
                </div>

                {{-- Vehicle 3D Model Pill (Bottom-Left of 3D Stage) --}}
                <div class="h-diag-model-picker-wrap">
                    <button type="button" class="h-diag-model-picker-btn" onclick="toggleModelMenu()" id="model-picker-btn" title="Vehicle 3D Model Correlation">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        <span id="active-model-name">{{ $selectedVehicle?->sketchfab_model_name ?? $selectedVehicle?->display_name ?? 'Vehicle 3D Stage' }}</span>
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="h-diag-model-menu" id="model-menu" style="display:none;">
                        <div class="h-diag-model-menu-title">Vehicle 3D Correlation</div>
                        <div class="h-diag-model-current-box">
                            <span class="h-diag-model-dot-live"></span>
                            <div class="h-diag-model-current-text">
                                <strong>{{ $selectedVehicle?->display_name ?? 'Selected Vehicle' }}</strong>
                                <span>{{ $selectedVehicle?->year }} · {{ $selectedVehicle?->engine_type ?: 'Gasoline ICE' }}</span>
                            </div>
                        </div>

                        <button type="button" class="h-diag-model-option is-active" id="mopt-active-car" onclick="restoreActiveVehicleModel()">
                            <span class="h-diag-model-opt-name">Active: {{ $selectedVehicle?->make }} {{ $selectedVehicle?->model }}</span>
                            <span class="h-diag-model-opt-sub">Correlated 3D Model</span>
                        </button>

                        <div class="h-diag-model-menu-divider"></div>
                        <button type="button" class="h-diag-model-option custom" onclick="promptCustomModel()">
                            <span class="h-diag-model-opt-name">Bind Custom Model UID...</span>
                            <span class="h-diag-model-opt-sub">Save Sketchfab 3D UID to {{ $selectedVehicle?->nickname ?: 'vehicle' }}</span>
                        </button>
                    </div>
                </div>

            </div>

            {{-- 2D Vector Blueprint Fallback / Alternate View --}}
            <div class="h-diag-car-wrap" id="car-stage-wrap" style="{{ $selectedVehicle?->sketchfab_uid ? 'display: none;' : '' }}">

                {{-- 2D Mode Badge --}}
                <div class="h-diag-2d-mode-badge">
                    <span class="h-diag-2d-pill">2D Blueprint Mode</span>
                    @if(!$selectedVehicle?->sketchfab_uid)
                        <span class="h-diag-2d-note">No verified 3D model bound · Shifted to 2D</span>
                        <button type="button" class="h-diag-2d-bind-btn" onclick="promptCustomModel()">+ Bind 3D Model</button>
                    @else
                        <span class="h-diag-2d-note">Active Vector Telemetry</span>
                    @endif
                </div>

                {{-- Car SVG (Isometric Sedan) with Theme-Adaptive Gradients --}}
                <svg class="h-diag-car-svg" viewBox="0 0 740 380" preserveAspectRatio="xMidYMid meet">
                    <defs>
                        <linearGradient id="bodyGradIdx" x1="0%" y1="0%" x2="100%" y2="80%">
                            <stop offset="0%" stop-color="var(--ds-car-body-1)"/>
                            <stop offset="35%" stop-color="var(--ds-car-body-2)"/>
                            <stop offset="70%" stop-color="var(--ds-car-body-3)"/>
                            <stop offset="100%" stop-color="var(--ds-car-body-4)"/>
                        </linearGradient>
                        <linearGradient id="glassGradIdx" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.6"/>
                            <stop offset="100%" stop-color="#0284c7" stop-opacity="0.85"/>
                        </linearGradient>
                        <radialGradient id="hoodGlowIdx" cx="40%" cy="50%" r="55%">
                            <stop offset="0%" stop-color="{{ $condColor['icon'] }}" stop-opacity="0.55"/>
                            <stop offset="75%" stop-color="{{ $condColor['icon'] }}" stop-opacity="0.15"/>
                            <stop offset="100%" stop-color="{{ $condColor['icon'] }}" stop-opacity="0"/>
                        </radialGradient>
                        <filter id="shadowIdx">
                            <feDropShadow dx="0" dy="24" stdDeviation="20" flood-color="var(--ds-car-shadow-color)" flood-opacity="0.5"/>
                        </filter>
                    </defs>

                    {{-- Ground Shadow --}}
                    <ellipse cx="370" cy="330" rx="310" ry="32" fill="var(--ds-car-shadow-color)" filter="url(#shadowIdx)"/>

                    <g transform="translate(40, 20)">
                        {{-- Vehicle Body Contour --}}
                        <path d="M 120 250 C 140 250, 180 270, 260 270 L 440 270 C 490 270, 520 250, 590 240 C 620 235, 640 220, 650 200 C 655 190, 650 175, 630 165 C 590 145, 520 120, 470 100 C 400 70, 310 68, 230 85 C 170 98, 120 125, 90 160 C 70 185, 75 210, 85 225 Z" fill="url(#bodyGradIdx)" stroke="var(--ds-car-stroke)" stroke-width="1.5"/>

                        {{-- Acoustic/Engine Bay Holographic Zone --}}
                        <path d="M 95 165 C 120 135, 170 110, 230 95 C 270 125, 290 170, 270 215 C 200 230, 130 220, 95 165 Z" fill="url(#hoodGlowIdx)" stroke="{{ $condColor['icon'] }}" stroke-dasharray="4 3" stroke-width="1.6"/>

                        {{-- Windshield --}}
                        <path d="M 235 90 C 310 75, 395 76, 465 102 C 430 140, 390 155, 275 145 C 255 125, 245 105, 235 90 Z" fill="url(#glassGradIdx)" stroke="var(--ds-car-stroke)" stroke-width="1.2"/>

                        {{-- Side Windows --}}
                        <path d="M 285 145 C 395 155, 435 140, 470 105 L 530 125 C 570 145, 595 165, 605 175 L 480 185 Z" fill="var(--ds-car-underbody)" opacity="0.85"/>

                        {{-- Front Left Wheel --}}
                        <g transform="translate(160, 250)">
                            <ellipse cx="0" cy="0" rx="42" ry="48" fill="#1e293b" stroke="var(--ds-car-stroke)" stroke-width="2"/>
                            <ellipse cx="0" cy="0" rx="30" ry="34" fill="#0f172a"/>
                            <circle cx="0" cy="0" r="14" fill="#94a3b8"/>
                            <line x1="-22" y1="0" x2="22" y2="0" stroke="#cbd5e1" stroke-width="3"/>
                            <line x1="0" y1="-26" x2="0" y2="26" stroke="#cbd5e1" stroke-width="3"/>
                        </g>

                        {{-- Rear Right Wheel (Highlighted) --}}
                        <g transform="translate(510, 230)">
                            <ellipse cx="0" cy="0" rx="46" ry="52" fill="#1e293b" stroke="#f97316" stroke-width="2.5"/>
                            <ellipse cx="0" cy="0" rx="32" ry="36" fill="#0f172a"/>
                            <circle cx="0" cy="0" r="15" fill="#f97316"/>
                            <line x1="-24" y1="0" x2="24" y2="0" stroke="#f97316" stroke-width="3"/>
                            <line x1="0" y1="-28" x2="0" y2="28" stroke="#f97316" stroke-width="3"/>
                        </g>

                        {{-- Body Aerodynamic Character Lines --}}
                        <path d="M 160 210 Q 320 220 530 190" stroke="var(--ds-car-stroke)" stroke-width="1.8" fill="none" opacity="0.75"/>
                        <path d="M 190 230 Q 340 240 480 235" stroke="var(--ds-car-stroke)" stroke-width="1.4" fill="none" opacity="0.6"/>

                        {{-- Headlight & Taillight Accents --}}
                        <polygon points="85,190 110,180 100,205 82,205" fill="#38bdf8" opacity="0.95"/>
                        <polygon points="635,175 648,185 640,195 628,185" fill="#ef4444" opacity="0.9"/>
                    </g>
                </svg>

                {{-- Interactive Sensor Pins (2D Mode) --}}
                <div class="h-diag-pin" style="top:32%;left:22%;" title="INMP441 Acoustic Sensor">
                    <div class="h-diag-pin-beacon {{ in_array($latestCondition, ['valve_clearance','misfire']) ? 'red' : '' }}"></div>
                    <div class="h-diag-pin-core {{ in_array($latestCondition, ['valve_clearance','misfire']) ? 'red' : 'blue' }}">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>INMP441 Acoustic Mic</strong>
                        <span>I2S 44.1kHz · Engine Audio</span>
                    </div>
                </div>

                <div class="h-diag-pin" style="top:52%;left:36%;" title="ADXL345 Vibration Sensor">
                    <div class="h-diag-pin-beacon {{ $latestCondition === 'loose_component' ? 'orange' : '' }}"></div>
                    <div class="h-diag-pin-core {{ $latestCondition === 'loose_component' ? 'orange' : 'blue' }}">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>ADXL345 Accelerometer</strong>
                        <span>I2C · Vibration G-Force</span>
                    </div>
                </div>

                <div class="h-diag-pin" style="top:62%;left:64%;" title="INA219 Power Monitor">
                    <div class="h-diag-pin-core dark">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><rect x="1" y="6" width="18" height="12" rx="2"/><line x1="23" y1="10" x2="23" y2="14"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>INA219 Power Monitor</strong>
                        <span>Voltage · Current Draw</span>
                    </div>
                </div>

                <div class="h-diag-pin" style="top:44%;left:79%;" title="Hall Effect RPM Sensor">
                    <div class="h-diag-pin-core green">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>Hall Effect Tachometer</strong>
                        <span>RPM · Contextual Indicator</span>
                    </div>
                </div>

            </div>

            {{-- Tier 3: No Preview Fallback --}}
            <div class="h-diag-no-preview-wrap" id="stage-no-preview" style="display: none;">
                <div class="h-diag-no-preview-card">
                    <div class="h-diag-no-preview-icon">
                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"/>
                            <polygon points="16 8 20 8 23 11 23 16 16 16 8"/>
                            <circle cx="5.5" cy="18.5" r="2.5"/>
                            <circle cx="18.5" cy="18.5" r="2.5"/>
                            <line x1="2" y1="2" x2="22" y2="22" stroke="var(--ds-red)" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="h-diag-no-preview-title">No Preview Available</div>
                    <p class="h-diag-no-preview-sub">
                        Visual telemetry preview is not bound for <strong>{{ $selectedVehicle?->display_name ?? 'this vehicle' }}</strong>.
                    </p>
                    <div class="h-diag-no-preview-actions">
                        <button type="button" class="h-diag-no-preview-btn primary" onclick="switchTo2D()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <span>View 2D Blueprint</span>
                        </button>
                        <button type="button" class="h-diag-no-preview-btn secondary" onclick="promptCustomModel()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                            <span>Bind 3D Model</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Viewport Controls --}}
            <div class="h-diag-viewport-controls">
                <button type="button" class="h-diag-zoom-btn" onclick="zoomVehicle(1.2)" aria-label="Zoom In" title="Zoom In">+</button>
                <button type="button" class="h-diag-zoom-btn" onclick="zoomVehicle(0.8)" aria-label="Zoom Out" title="Zoom Out">−</button>
                <button type="button" class="h-diag-zoom-btn" onclick="reset3DView()" aria-label="Reset Camera" title="Reset Camera">⟲</button>
                <button type="button" class="h-diag-zoom-btn h-diag-toggle-view-btn" onclick="cycleViewMode()" aria-label="Cycle View Mode (3D / 2D / No Preview)" title="Cycle View Mode">
                    <span id="view-dimension-badge">{{ $selectedVehicle?->sketchfab_uid ? '3D' : '2D' }}</span>
                </button>
            </div>
        </div>

    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         2.5  ESP32 LIVE TELEMETRY — Real-Time Hardware Stream
         ═══════════════════════════════════════════════════════════════ --}}
    <x-esp32-live-telemetry />

    {{-- ═══════════════════════════════════════════════════════════════
         3. SENSOR MONITORING PANELS — 4 Responsive Cockpit Cards
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="h-diag-grid-cards">

        {{-- CARD 1: CAR ELECTRONICS / HAVEN SENSOR PROBES --}}
        <div class="h-diag-card h-diag-card-probes">
            <div class="h-diag-card-head">
                <span class="h-diag-card-title">CAR ELECTRONICS</span>
                <span class="h-diag-card-status-badge ok">SENSOR BUS READY</span>
            </div>

            @php
                // Real HAVEN sensor probes — status derived from latest diagnostic
                $audioOk   = $latestDiag && $latestDiag->audio_path;
                $vibOk     = $latestDiag && $latestDiag->vibration_path;
                $tempVal   = $latestDiag?->temperature_c;
                $rpmVal    = $latestDiag?->rpm;
                $voltVal   = $latestDiag?->voltage_v;

                $havenSensors = [
                    [
                        'name'   => 'INMP441',
                        'label'  => 'Audio Mic',
                        'status' => $audioOk ? 'Captured' : ($latestDiag ? 'No audio' : 'Standby'),
                        'color'  => $audioOk ? '#0284c7' : 'var(--ds-text-dim)',
                        'height' => $audioOk ? '80%' : '25%',
                        'alert'  => !$audioOk && $latestDiag,
                    ],
                    [
                        'name'   => 'ADXL345',
                        'label'  => 'Vibration',
                        'status' => $vibOk ? ($latestDiag->vibration_peak_g ? round($latestDiag->vibration_peak_g,2).'G' : 'OK') : ($latestDiag ? 'No data' : 'Standby'),
                        'color'  => $vibOk ? '#10b981' : 'var(--ds-text-dim)',
                        'height' => $vibOk ? '70%' : '20%',
                        'alert'  => !$vibOk && $latestDiag,
                    ],
                    [
                        'name'   => 'DS18B20',
                        'label'  => 'Temperature',
                        'status' => $tempVal ? round($tempVal,1).'°C' : 'Standby',
                        'color'  => $tempVal ? ($tempVal > 90 ? '#ef4444' : ($tempVal > 70 ? '#f59e0b' : '#10b981')) : 'var(--ds-text-dim)',
                        'height' => $tempVal ? min(95, max(20, intval($tempVal))).'%' : '30%',
                        'alert'  => $tempVal && $tempVal > 90,
                        'context_only' => true,
                    ],
                    [
                        'name'   => 'Hall RPM',
                        'label'  => 'Tachometer',
                        'status' => $rpmVal ? number_format($rpmVal).' RPM' : 'Standby',
                        'color'  => $rpmVal ? '#f59e0b' : 'var(--ds-text-dim)',
                        'height' => $rpmVal ? min(90, max(15, intval($rpmVal/80))).'%' : '25%',
                        'context_only' => true,
                    ],
                    [
                        'name'   => 'INA219',
                        'label'  => 'Voltage',
                        'status' => $voltVal ? number_format($voltVal,1).'V' : 'Standby',
                        'color'  => $voltVal ? ($voltVal < 11.5 ? '#ef4444' : ($voltVal < 12.4 ? '#f59e0b' : '#10b981')) : 'var(--ds-text-dim)',
                        'height' => $voltVal ? min(95, max(10, intval(($voltVal / 15) * 100))).'%' : '40%',
                    ],
                ];
            @endphp

            <div class="h-diag-probe-bars-grid">
                @foreach($havenSensors as $s)
                    <div class="h-diag-probe-col" title="{{ $s['name'] }}: {{ $s['label'] }} — {{ $s['status'] }}{{ !empty($s['context_only']) ? ' (Contextual indicator only — not an ML input)' : '' }}">
                        <div class="h-diag-probe-track">
                            <div class="h-probe-bar" data-height="{{ $s['height'] }}" style="height:{{ $s['height'] }};background:linear-gradient(180deg, {{ $s['color'] }} 0%, {{ $s['color'] }}99 100%);"></div>
                        </div>
                        <div class="h-diag-probe-name">
                            <span>{{ $s['name'] }}</span>@if(!empty($s['context_only']))<span class="h-diag-ctx-dot" title="Contextual indicator only — not an ML input">ℹ</span>@endif
                        </div>
                        <div class="h-diag-probe-status {{ !empty($s['alert']) ? 'is-alert' : '' }}">{{ $s['status'] }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Quick stats row --}}
            <div class="h-diag-probes-stats-row">
                <div class="h-diag-stat-block">
                    <div class="h-diag-stat-val">
                        {{ $vehicleStats[$selectedVid]['total'] ?? 0 }}
                        <span class="h-diag-stat-unit">sessions</span>
                    </div>
                    <div class="h-diag-stat-lbl">Diagnostic history</div>
                </div>
                <div class="h-diag-stat-block">
                    <div class="h-diag-stat-val">
                        {{ $latestDiag?->audio_duration_sec ? $latestDiag->audio_duration_sec . 's' : '—' }}
                    </div>
                    <div class="h-diag-stat-lbl">Last audio length</div>
                </div>
                <div class="h-diag-stat-block">
                    <div class="h-diag-stat-val">
                        {{ $selectedVehicle?->engine_type ?: 'Gasoline ICE' }}
                    </div>
                    <div class="h-diag-stat-lbl">Powertrain</div>
                </div>
                <div class="h-diag-stat-block">
                    <div class="h-diag-stat-val">
                        {{ $completed ?? 0 }}
                        <span class="h-diag-stat-unit">done</span>
                    </div>
                    <div class="h-diag-stat-lbl">Completed analysis</div>
                </div>
            </div>
        </div>

        {{-- CARD 2: DARK ENGINE DAQ CARD (live readings from last session) --}}
        @php
            $hasData = $latestDiag && $latestDiag->status === 'completed';
            $condPct = $hasData && $latestDiag->ml_confidence ? round($latestDiag->ml_confidence * 100) . '%' : null;
            $displayVolt = $latestDiag?->voltage_v ? number_format($latestDiag->voltage_v, 1) . 'V' : '—';
            $displayRpm  = $latestDiag?->rpm ? number_format($latestDiag->rpm) . ' RPM' : '—';
            $displayPeakG = $latestDiag?->vibration_peak_g ? $latestDiag->vibration_peak_g . ' G' : '—';
        @endphp
        <div class="h-diag-card h-diag-card-dark">
            <div class="h-diag-card-head">
                <span class="h-diag-card-title light">ENGINE DATA</span>
                @if($condPct)
                    <span class="h-diag-dark-confidence">{{ $condPct }}</span>
                @else
                    <span class="h-diag-dark-nodata">No Data</span>
                @endif
            </div>

            {{-- Engine Isometric Visualization --}}
            <div class="h-diag-engine-svg-wrap">
                <svg viewBox="0 0 220 110" class="h-diag-engine-svg">
                    <ellipse cx="110" cy="95" rx="90" ry="14" fill="#000" opacity="0.6"/>
                    <rect x="40" y="38" width="140" height="48" rx="8" fill="#1e3a5f" stroke="#1d4ed8" stroke-width="1.5"/>
                    {{-- Cylinder blocks --}}
                    <rect x="55" y="46" width="28" height="32" rx="4" fill="{{ $hasData ? '#0284c7' : '#1e3a5f' }}" opacity="0.85"/>
                    <rect x="96" y="46" width="28" height="32" rx="4" fill="{{ $hasData ? '#0284c7' : '#1e3a5f' }}" opacity="0.85"/>
                    <rect x="137" y="46" width="28" height="32" rx="4" fill="{{ $hasData ? ($latestCondition === 'normal' ? '#10b981' : ($latestCondition === 'valve_clearance' ? '#ef4444' : '#f59e0b')) : '#1e3a5f' }}" opacity="0.85"/>
                    {{-- Spark lines --}}
                    <line x1="69" y1="28" x2="69" y2="46" stroke="{{ $hasData ? '#38bdf8' : '#334155' }}" stroke-width="3" stroke-linecap="round"/>
                    <line x1="110" y1="28" x2="110" y2="46" stroke="{{ $hasData ? '#38bdf8' : '#334155' }}" stroke-width="3" stroke-linecap="round"/>
                    <line x1="151" y1="28" x2="151" y2="46" stroke="{{ $hasData ? '#38bdf8' : '#334155' }}" stroke-width="3" stroke-linecap="round"/>
                    <text x="110" y="82" text-anchor="middle" fill="{{ $hasData ? '#38bdf8' : '#94a3b8' }}" font-size="9" font-weight="700">
                        {{ $hasData ? ($latestDiag->condition_label ?? 'N/A') : 'Run Diagnostic' }}
                    </text>
                </svg>
            </div>

            <div class="h-diag-dark-footer">
                <span title="Voltage (INA219)">⚡ {{ $displayVolt }}</span>
                <span class="h-diag-dark-condition" style="color:{{ $hasData ? $condColor['icon'] : 'var(--ds-text-dim)' }};">
                    {{ $hasData ? ($latestDiag->condition_label ?? 'Good') : 'No session yet' }}
                </span>
                <span title="Peak G-force (ADXL345)">📈 {{ $displayPeakG }}</span>
            </div>
        </div>

        {{-- CARD 3: VOLTAGE GAUGE (INA219 — contextual indicator) --}}
        <div class="h-diag-card">
            <div class="h-diag-card-head">
                <span class="h-diag-card-title">VOLTAGE</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            </div>
            <div class="h-diag-voltage-center">
                <div class="h-diag-voltage-val">
                    {{ $latestDiag?->voltage_v ? number_format($latestDiag->voltage_v, 1) : '—' }}
                </div>
                <div class="h-diag-voltage-lbl">
                    {{ $latestDiag?->voltage_v ? 'Volts (INA219)' : 'No reading yet' }}
                </div>
                {{-- Voltage Wave --}}
                <svg viewBox="0 0 120 40" class="h-diag-voltage-wave">
                    <polyline points="0,20 10,10 20,30 30,5 40,25 50,15 60,28 70,8 80,22 90,12 100,26 110,10 120,20" fill="none" stroke="{{ $latestDiag?->voltage_v ? 'var(--ds-blue)' : 'var(--ds-border)' }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <div class="h-diag-voltage-rpm">
                    @if($latestDiag?->rpm)
                        RPM <strong>{{ number_format($latestDiag->rpm) }}</strong>
                        <span class="h-diag-rpm-ctx" title="RPM is a contextual indicator only — not an ML classifier input">ℹ ctx</span>
                    @else
                        RPM <strong>—</strong>
                    @endif
                </div>
            </div>
        </div>

        {{-- CARD 4: AI ASSISTANT CHAT --}}
        <div class="h-diag-card h-diag-card-ai">
            <div class="h-diag-card-head">
                <div class="h-diag-ai-head-left">
                    <div class="h-diag-orb-small"></div>
                    <span class="h-diag-card-title">AI ASSISTANT</span>
                </div>
                <button type="button" onclick="sendStopSignal()" class="h-diag-ai-stop-btn" title="Stop Assistant Audio">
                    <span class="h-diag-stop-square"></span>
                    <span>Stop</span>
                </button>
            </div>

            <div id="ai-idx-feed" class="h-diag-ai-feed">
                <p><strong>Hi {{ auth()->user()?->name ?? 'there' }}!</strong> I'm your AI Assistant.<br>
                @if($latestDiag && $latestDiag->ml_condition)
                    The analysis of your {{ $selectedVehicle?->display_name ?? 'car' }} has been completed. {{ $latestDiag->condition_label }} detected. I'll help you eliminate the issues.
                @else
                    Ready to analyze your {{ $selectedVehicle?->display_name ?? 'vehicle' }}. Start a diagnostic to receive your AI-powered engine condition analysis.
                @endif
                </p>
            </div>

            <div class="h-diag-ai-input-wrap">
                <input type="text" id="ai-idx-input" placeholder="Ask AI Copilot..." onkeydown="if(event.key==='Enter') sendIdxAi();" aria-label="Ask AI Assistant">
                <button type="button" onclick="sendIdxAi()" class="h-diag-ai-send-btn" title="Send Question">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </div>
            <div class="h-diag-ai-status-row">
                <div class="h-diag-live-dot"></div>
                <span id="ai-idx-status">Ready · Ask me anything</span>
            </div>
        </div>

    </div>

    @endif

    <div class="h-diag-history-page-heading">
        <div>
            <span class="h-diag-history-page-eyebrow">DIAGNOSTIC HISTORY</span>
            <h1>Vehicle Diagnostic Sessions</h1>
            <p>Review completed tests, reports, and previous vehicle conditions.</p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         4. DIAGNOSTIC HISTORY FOR SELECTED VEHICLE
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="h-diag-history-card" id="history-section">

        {{-- History Header --}}
        <div class="h-diag-hist-header">
            <div>
                <div class="h-diag-hist-subtitle">VEHICLE HISTORY</div>
                <h3 class="h-diag-hist-title">
                    {{ $selectedVehicle?->display_name ?? 'All Vehicles' }} — Diagnostic Sessions
                </h3>
            </div>
            <div class="h-diag-hist-actions">
                <div class="h-diag-hist-btn-group">
                    <a href="{{ route('diagnostics.export.dataset') }}" class="h-diag-hist-export">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span>Export CSV</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="h-diag-alert-success">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- Sessions List --}}
        @if($diagnostics->isEmpty())
            <div class="h-diag-no-sessions">
                <div class="h-diag-no-sessions-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <h3>No Sessions Yet for {{ $selectedVehicle?->display_name ?? 'this vehicle' }}</h3>
                <p>Start a new diagnostic to record engine audio and vibration data.</p>
                <a href="{{ route('diagnostics.create', ['vehicle_id' => $selectedVid]) }}" class="h-diag-btn-primary">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Start First Diagnostic</span>
                </a>
            </div>
        @else
            <div id="history-list" class="h-diag-history-list">
            @foreach($diagnostics as $d)
                @php
                    $dc = \App\Models\Diagnostic::canonicalCondition($d->ml_condition ?? '');
                    $dColor = match($dc) {
                        'normal'          => ['dot'=>'#10b981','chip'=>'is-normal','icon-txt'=>'#10b981'],
                        'misfire'         => ['dot'=>'#f59e0b','chip'=>'is-warning','icon-txt'=>'#f59e0b'],
                        'loose_component' => ['dot'=>'#f59e0b','chip'=>'is-warning','icon-txt'=>'#f59e0b'],
                        'valve_clearance' => ['dot'=>'#ef4444','chip'=>'is-danger','icon-txt'=>'#ef4444'],
                        default           => ['dot'=>'#0284c7','chip'=>'is-info','icon-txt'=>'#0284c7'],
                    };
                @endphp
                <div
                    class="h-hist-row diag-hist-item"
                    data-session-id="{{ $d->id }}"
                    data-session-date="{{ $d->created_at->format('Y-m-d') }}"
                    data-search="{{ e(Str::lower($d->id . ' ' . $d->vehicle->display_name . ' ' . $d->created_at->format('M j, Y g:i A') . ' ' . $d->created_at->format('Y-m-d') . ' ' . $d->created_at->format('m/d/Y') . ' ' . $d->created_at->format('F j, Y') . ' ' . ($d->ml_condition ?? '') . ' ' . ($d->status_label ?? '') )) }}"
                >

                    {{-- Status Icon --}}
                    <div class="h-hist-icon-box {{ $dColor['chip'] }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </div>

                    {{-- Session Info --}}
                    <div class="h-hist-info-col">
                        <div class="h-hist-title-row">
                            <a href="{{ route('diagnostics.show', $d) }}" class="h-hist-title-link">
                                Session #{{ $d->id }} · {{ $d->vehicle->display_name }}
                            </a>
                            @if($d->ml_condition)
                                <span class="h-hist-cond-badge {{ $dColor['chip'] }}">
                                    {{ $d->condition_label }}
                                </span>
                            @endif
                            <span class="h-badge {{ $d->status_badge }}">{{ $d->status_label }}</span>
                        </div>
                        <div class="h-hist-meta-row">
                            <span>{{ $d->created_at->format('M j, Y · g:i A') }}</span>
                            @if($d->audio_duration_sec)
                                <span>🎙 {{ floor($d->audio_duration_sec/60) }}:{{ str_pad($d->audio_duration_sec%60,2,'0',STR_PAD_LEFT) }}</span>
                            @endif
                            @if($d->vibration_sample_count)
                                <span>📈 {{ number_format($d->vibration_sample_count) }} samples</span>
                            @endif
                            @if($d->recording_mode)
                                <span class="h-hist-mode-tag">{{ $d->recording_mode }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Confidence + Action Buttons --}}
                    <div class="h-hist-actions-col">
                        @if($d->ml_confidence)
                            <div class="h-hist-conf-box">
                                <div class="h-hist-conf-num" style="color:{{ $dColor['icon-txt'] }};">{{ round($d->ml_confidence * 100) }}%</div>
                                <div class="h-hist-conf-lbl">confidence</div>
                            </div>
                        @endif
                        <a href="{{ route('diagnostics.show', $d) }}" class="h-hist-action-btn" title="View Session Details">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                        @if(in_array($d->status, ['draft','submitted','failed']))
                            <form method="POST" action="{{ route('diagnostics.analyze', $d) }}" style="margin:0;">
                                @csrf
                                <button type="submit" class="h-hist-action-btn analyze" title="Run ML Analysis">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 3l14 9-14 9V3z"/></svg>
                                </button>
                            </form>
                        @endif
                        @if($d->status === 'completed')
                            <a href="{{ route('diagnostics.report', $d) }}" target="_blank" class="h-hist-action-btn report" title="Print Official Diagnostic Report">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
            </div>

            {{-- Pagination --}}
            @if($diagnostics->hasPages())
                <div class="h-diag-pagination-wrap">
                    {{ $diagnostics->links() }}
                </div>
            @endif
        @endif
    </div>

    @endif {{-- end vehicles check --}}

{{-- ═══════════════════════════════════════════════════════════════
     IoT Hub Modal — ESP32 Hardware Info & Connection Guide
     ═══════════════════════════════════════════════════════════════ --}}
<div id="iot-modal" class="h-diag-modal-backdrop" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
    <div class="h-diag-modal-dialog">

        {{-- Modal Header --}}
        <div class="h-diag-modal-head">
            <div class="h-diag-modal-title-box">
                <div class="h-diag-modal-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                </div>
                <div>
                    <div class="h-diag-modal-heading">ESP32 IoT Hub</div>
                    <div class="h-diag-modal-sub">HAVEN AI Hardware Configuration & API Reference</div>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('iot-modal').style.display='none'" class="h-diag-modal-close" aria-label="Close Modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Tab Switcher --}}
        <div class="h-diag-modal-tabs">
            @foreach(['Overview','Pinout','API','Firmware'] as $tab)
                <button type="button" onclick="switchIotTab('{{ strtolower($tab) }}')" id="iot-tab-{{ strtolower($tab) }}"
                    class="h-diag-modal-tab-btn {{ $loop->first ? 'is-active' : '' }}">
                    {{ $tab }}
                </button>
            @endforeach
        </div>

        {{-- Tab Content --}}
        <div class="h-diag-modal-body">

            {{-- Overview Tab --}}
            <div id="iot-content-overview">
                <div class="h-diag-hw-grid">
                    @foreach([
                        ['ESP32 DevKit','Microcontroller','Main MCU — WiFi + Dual Core','#0284c7'],
                        ['INMP441','Microphone','I2S 44.1kHz — Engine Audio','#10b981'],
                        ['ADXL345','Accelerometer','I2C ±16G — Vibration','#f59e0b'],
                        ['INA219','Power Monitor','I2C — Voltage + Current','#8b5cf6'],
                    ] as [$hw,$type,$desc,$c])
                        <div class="h-diag-hw-card">
                            <div class="h-diag-hw-icon" style="background:{{ $c }}18;color:{{ $c }};">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="18" r="1"/></svg>
                            </div>
                            <div>
                                <div class="h-diag-hw-name">{{ $hw }}</div>
                                <div class="h-diag-hw-type" style="color:{{ $c }};">{{ $type }}</div>
                                <div class="h-diag-hw-desc">{{ $desc }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="h-diag-status-callout">
                    <strong>📡 Hardware Stream Target:</strong>
                    @if($selectedVehicle)
                        Your ESP32 publishes diagnostics to <code>{{ url('/api/v1/telemetry') }}</code> with vehicle token.
                    @else
                        Register a vehicle first to bind your ESP32 telemetry hardware.
                    @endif
                </div>
            </div>

            {{-- Pinout Tab --}}
            <div id="iot-content-pinout" style="display:none;">
                <div class="h-diag-tab-hint">ESP32 GPIO pin assignments for HAVEN AI sensors:</div>
                <div class="h-diag-table-wrap">
                    <table class="h-diag-pinout-table">
                        <thead>
                            <tr>
                                <th>SENSOR</th>
                                <th>PROTOCOL</th>
                                <th>PINS</th>
                                <th>NOTES</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach([
                                ['INMP441 Mic','I2S','WS=GPIO25 · SCK=GPIO26 · SD=GPIO33','44.1kHz 32-bit PDM audio capture'],
                                ['ADXL345 Accel','I2C','SDA=GPIO21 · SCL=GPIO22','±16G range, ODR=800Hz'],
                                ['INA219 Power','I2C','SDA=GPIO21 · SCL=GPIO22 · Addr=0x40','Shared I2C bus, 12-bit resolution'],
                                ['Hall Effect RPM','Digital','DATA=GPIO34','Contextual indicator only — not ML input'],
                                ['DS18B20 Temp','1-Wire','DATA=GPIO4 · 4.7kΩ pullup','Contextual indicator only — not ML input'],
                                ['Status LED','Digital','GPIO2 (built-in)','Blue = WiFi, Green = Active, Red = Error'],
                            ] as [$s,$p,$pin,$note])
                                <tr>
                                    <td class="h-diag-col-bold">{{ $s }}</td>
                                    <td><span class="h-diag-proto-tag">{{ $p }}</span></td>
                                    <td class="h-diag-col-mono">{{ $pin }}</td>
                                    <td class="h-diag-col-dim">{{ $note }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- API Tab --}}
            <div id="iot-content-api" style="display:none;">
                <div class="h-diag-tab-hint">REST endpoints for ESP32 firmware to call:</div>
                @foreach([
                    ['POST','POST /api/v1/telemetry','Send live sensor readings (voltage, RPM, temp)','{"vehicle_token":"…","voltage_v":13.8,"rpm":750,"temperature_c":82}'],
                    ['POST','POST /api/v1/audio','Upload raw audio blob from INMP441','multipart/form-data: audio_blob + vehicle_token'],
                    ['POST','POST /api/v1/vibration','Upload ADXL345 CSV data','multipart/form-data: vibration_file + vehicle_token'],
                    ['GET','GET /api/v1/status','Check server reachability & vehicle token validity','Returns: {"status":"ok","vehicle_id":1}'],
                ] as [$method,$endpoint,$desc,$body])
                    <div class="h-diag-api-card">
                        <div class="h-diag-api-head">
                            <span class="h-diag-method-badge {{ strtolower($method) }}">{{ $method }}</span>
                            <code class="h-diag-api-route">{{ $endpoint }}</code>
                        </div>
                        <div class="h-diag-api-desc">{{ $desc }}</div>
                        <pre class="h-diag-api-body"><code>{{ $body }}</code></pre>
                    </div>
                @endforeach
            </div>

            {{-- Firmware Tab --}}
            <div id="iot-content-firmware" style="display:none;">
                <div class="h-diag-tab-hint">Arduino/PlatformIO quick-start for HAVEN AI ESP32 firmware:</div>
                <pre class="h-diag-code-block"><code><span style="color:#64748b;">// HAVEN AI — ESP32 Firmware Stub</span>
<span style="color:#38bdf8;">#include</span> &lt;WiFi.h&gt;
<span style="color:#38bdf8;">#include</span> &lt;HTTPClient.h&gt;
<span style="color:#38bdf8;">#include</span> &lt;Wire.h&gt;
<span style="color:#38bdf8;">#include</span> &lt;Adafruit_ADXL345_U.h&gt;
<span style="color:#38bdf8;">#include</span> &lt;driver/i2s.h&gt;

<span style="color:#94a3b8;">const char* ssid     = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASS";
const char* apiBase  = "{{ url('/api/v1') }}";
const char* token    = "YOUR_VEHICLE_TOKEN";</span>

<span style="color:#10b981;">void</span> <span style="color:#f59e0b;">sendTelemetry</span>(<span style="color:#10b981;">float</span> v, <span style="color:#10b981;">int</span> rpm, <span style="color:#10b981;">float</span> t) {
  HTTPClient http;
  http.begin(String(apiBase) + <span style="color:#a3e635;">"/telemetry"</span>);
  http.addHeader(<span style="color:#a3e635;">"Content-Type"</span>, <span style="color:#a3e635;">"application/json"</span>);
  String body = <span style="color:#a3e635;">"{"</span>
    <span style="color:#a3e635;">"\"vehicle_token\":\""</span> + String(token) + <span style="color:#a3e635;">"\","</span>
    <span style="color:#a3e635;">"\"voltage_v\":"</span>    + String(v)   + <span style="color:#a3e635;">","</span>
    <span style="color:#a3e635;">"\"rpm\":"</span>          + String(rpm) + <span style="color:#a3e635;">","</span>
    <span style="color:#a3e635;">"\"temperature_c\":"</span>+ String(t)   + <span style="color:#a3e635;">"}"</span>;
  http.POST(body);
  http.end();
}

<span style="color:#10b981;">void</span> <span style="color:#f59e0b;">setup</span>() {
  Wire.begin(<span style="color:#fb923c;">21</span>, <span style="color:#fb923c;">22</span>);  <span style="color:#64748b;">// SDA, SCL</span>
  WiFi.begin(ssid, password);
  <span style="color:#10b981;">while</span> (WiFi.status() != WL_CONNECTED) delay(<span style="color:#fb923c;">500</span>);
}

<span style="color:#10b981;">void</span> <span style="color:#f59e0b;">loop</span>() {
  sendTelemetry(<span style="color:#fb923c;">13.8</span>, <span style="color:#fb923c;">750</span>, <span style="color:#fb923c;">82.0</span>);
  delay(<span style="color:#fb923c;">5000</span>);
}</code></pre>
                <div class="h-diag-warn-box">
                    ⚠ <strong>WiFiManager Integration:</strong> For zero-code setup in the field, use <code class="h-diag-inline-code">tzapu/WiFiManager</code> to host a captive portal on boot. Reference <code class="h-diag-inline-code">ESP32_ZERO_CODE_SETUP_SPEC.md</code>.
                </div>
            </div>

        </div>
    </div>
</div>

</div>{{-- end h-diag-studio-wrapper --}}

<style>
/* ═══════════════════════════════════════════════════════════════
   HAVEN AI — THEME ADAPTIVE DESIGN SYSTEM (Dark & Light Mode)
   ═══════════════════════════════════════════════════════════════ */

:root, [data-theme="light"] {
    --ds-bg: var(--h-bg, #f8fafc);
    --ds-surface: var(--h-surface, #ffffff);
    --ds-surface-subtle: var(--h-surface2, #f1f5f9);
    --ds-surface-elevated: var(--h-surface3, #e2e8f0);
    --ds-border: var(--h-border, #e2e8f0);
    --ds-border-light: rgba(226, 232, 240, 0.75);
    --ds-text: var(--h-text, #0f172a);
    --ds-text-muted: var(--h-text2, #475569);
    --ds-text-dim: var(--h-text3, #94a3b8);
    --ds-blue: #0284c7;
    --ds-blue-light: rgba(2, 132, 199, 0.08);
    --ds-blue-glow: rgba(2, 132, 199, 0.25);
    --ds-green: #10b981;
    --ds-green-bg: rgba(16, 185, 129, 0.1);
    --ds-amber: #f59e0b;
    --ds-amber-bg: rgba(245, 158, 11, 0.1);
    --ds-red: #ef4444;
    --ds-red-bg: rgba(239, 68, 68, 0.1);
    --ds-card-bg: var(--h-surface, #ffffff);
    --ds-card-border: var(--h-border, #e2e8f0);
    --ds-dark-card: #0f172a;
    --ds-dark-card-border: #1e293b;
    --ds-stage-bg: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    --ds-stage-border: #e2e8f0;
    --ds-hud-fade: linear-gradient(90deg, rgba(255,255,255,0.96) 0%, rgba(255,255,255,0.85) 75%, rgba(255,255,255,0) 100%);
    --ds-shadow-card: var(--h-card-shadow, 0 4px 20px -2px rgba(15,23,42,0.05), 0 2px 6px -1px rgba(15,23,42,0.03));
    --ds-input-bg: #ffffff;
    --ds-input-border: #e2e8f0;
    --ds-pill-bg: #f8fafc;
    --ds-pill-hover: #eff6ff;
    --ds-hover-bg: #f8fafc;
    --ds-car-shadow-color: rgba(15, 23, 42, 0.14);
    --ds-car-body-1: #ffffff;
    --ds-car-body-2: #f1f5f9;
    --ds-car-body-3: #cbd5e1;
    --ds-car-body-4: #94a3b8;
    --ds-car-stroke: #94a3b8;
    --ds-car-underbody: #0f172a;
    --ds-modal-bg: #ffffff;
    --ds-modal-overlay: rgba(15, 23, 42, 0.65);
    --ds-radius-card: 18px;
    --ds-radius-pill: 9999px;
}

[data-theme="dark"] {
    --ds-bg: var(--h-bg, #07090e);
    --ds-surface: var(--h-surface, #0d111a);
    --ds-surface-subtle: var(--h-surface2, #131a27);
    --ds-surface-elevated: var(--h-surface3, #1a2436);
    --ds-border: var(--h-border, #1e293b);
    --ds-border-light: rgba(30, 41, 59, 0.7);
    --ds-text: var(--h-text, #f8fafc);
    --ds-text-muted: var(--h-text2, #94a3b8);
    --ds-text-dim: var(--h-text3, #64748b);
    --ds-blue: #38bdf8;
    --ds-blue-light: rgba(56, 189, 248, 0.12);
    --ds-blue-glow: rgba(56, 189, 248, 0.35);
    --ds-green: #34d399;
    --ds-green-bg: rgba(52, 211, 153, 0.14);
    --ds-amber: #fbbf24;
    --ds-amber-bg: rgba(251, 191, 36, 0.14);
    --ds-red: #f87171;
    --ds-red-bg: rgba(248, 113, 113, 0.16);
    --ds-card-bg: var(--h-surface, #0d111a);
    --ds-card-border: var(--h-border, #1e293b);
    --ds-dark-card: #080c14;
    --ds-dark-card-border: #1e293b;
    --ds-stage-bg: linear-gradient(180deg, #0e1524 0%, #070b13 100%);
    --ds-stage-border: #1e293b;
    --ds-hud-fade: linear-gradient(90deg, rgba(14, 21, 36, 0.98) 0%, rgba(14, 21, 36, 0.88) 75%, rgba(14, 21, 36, 0) 100%);
    --ds-shadow-card: 0 4px 24px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(30, 41, 59, 0.7);
    --ds-input-bg: #131a27;
    --ds-input-border: #2e3d55;
    --ds-pill-bg: #131a27;
    --ds-pill-hover: #1e293b;
    --ds-hover-bg: #131a27;
    --ds-car-shadow-color: rgba(0, 0, 0, 0.7);
    --ds-car-body-1: #334155;
    --ds-car-body-2: #1e293b;
    --ds-car-body-3: #0f172a;
    --ds-car-body-4: #020617;
    --ds-car-stroke: #475569;
    --ds-car-underbody: #020617;
    --ds-modal-bg: #0d111a;
    --ds-modal-overlay: rgba(0, 0, 0, 0.8);
}

.h-diag-studio-wrapper {
    max-width: 1360px;
    margin: 0 auto;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--ds-text);
    padding-bottom: 2.5rem;
    box-sizing: border-box;
}

/* ─── Top Navigation ─── */
.h-diag-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 1rem;
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-pill);
    box-shadow: var(--ds-shadow-card);
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
    transition: background 0.25s, border-color 0.25s;
}

.h-diag-topbar-left,
.h-diag-topbar-right {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex-wrap: wrap;
}

.h-diag-vehicle-dropdown-wrap {
    position: relative;
}

.h-diag-vehicle-pill {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    background: var(--ds-pill-bg);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-pill);
    padding: 0.38rem 0.75rem 0.38rem 0.45rem;
    color: var(--ds-text);
    cursor: pointer;
    user-select: none;
    transition: all 0.2s ease;
}
.h-diag-vehicle-pill:hover {
    border-color: var(--ds-blue);
    background: var(--ds-pill-hover);
}

.h-diag-vehicle-icon {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--ds-blue-light);
    color: var(--ds-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.h-diag-pill-title {
    font-weight: 700;
    font-size: 0.82rem;
    color: var(--ds-text);
}

.h-diag-vehicle-year {
    font-size: 0.65rem;
    color: var(--ds-text-muted);
    background: var(--ds-surface-subtle);
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}

.h-diag-chevron {
    color: var(--ds-text-dim);
    transition: transform 0.2s;
}

.h-diag-dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    width: min(300px, 92vw);
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    border-radius: 14px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.22);
    z-index: 999;
    overflow: hidden;
    animation: fadeIn 0.15s ease-out;
}

.h-diag-dropdown-header {
    padding: 0.65rem 0.85rem;
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--ds-text-dim);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    border-bottom: 1px solid var(--ds-border);
}

.h-diag-dropdown-scroll {
    max-height: 280px;
    overflow-y: auto;
}

.h-diag-dropdown-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 0.85rem;
    text-decoration: none;
    color: var(--ds-text);
    border-bottom: 1px solid var(--ds-border-light);
    transition: background 0.15s;
}
.h-diag-dropdown-item:hover {
    background: var(--ds-hover-bg);
}
.h-diag-dropdown-item.is-active {
    background: var(--ds-blue-light);
}

.h-diag-dropdown-item-left {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    min-width: 0;
}

.h-diag-dropdown-v-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: var(--ds-surface-subtle);
    color: var(--ds-text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.h-diag-dropdown-item.is-active .h-diag-dropdown-v-icon {
    background: var(--ds-blue);
    color: #fff;
}

.h-diag-dropdown-v-name {
    font-size: 0.8rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
}

.h-diag-dropdown-v-meta {
    font-size: 0.68rem;
    color: var(--ds-text-dim);
}

.h-diag-dropdown-v-stat {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--ds-text-dim);
    white-space: nowrap;
}
.h-diag-dropdown-item.is-active .h-diag-dropdown-v-stat {
    color: var(--ds-blue);
}

.h-diag-dropdown-footer {
    padding: 0.5rem 0.85rem;
    border-top: 1px solid var(--ds-border);
    background: var(--ds-surface-subtle);
}

.h-diag-dropdown-add {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--ds-blue);
    text-decoration: none;
}

.h-diag-mode-pill {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--ds-pill-bg);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-pill);
    padding: 0.42rem 0.8rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--ds-text-muted);
    cursor: pointer;
    transition: all 0.2s;
}
.h-diag-mode-pill:hover {
    border-color: var(--ds-blue);
    color: var(--ds-blue);
}
.h-diag-action-switch {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 3px;
    border: 1px solid var(--ds-border);
    border-radius: 999px;
    background: var(--ds-pill-bg);
}
.h-diag-action-switch-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    min-height: 30px;
    padding: 0.35rem 0.7rem;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: var(--ds-text-muted);
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.16s ease;
}
.h-diag-action-switch-btn:hover,
.h-diag-action-switch-btn.is-active {
    background: var(--ds-blue);
    color: #fff;
    box-shadow: 0 3px 10px rgba(37, 99, 235, 0.28);
}
.h-diag-diagnose-btn {
    background: #2563eb;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18), 0 4px 14px rgba(37, 99, 235, 0.35);
    color: #fff;
    font-weight: 800;
    letter-spacing: 0.025em;
    text-decoration: none;
    text-transform: uppercase;
}
.h-diag-diagnose-btn:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.22), 0 6px 18px rgba(37, 99, 235, 0.45);
    color: #fff;
    transform: translateY(-1px);
}

.h-diag-search-wrap {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--ds-pill-bg);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-pill);
    padding: 0.38rem 0.85rem;
    color: var(--ds-text-dim);
    transition: border-color 0.2s;
}
.h-diag-search-wrap:focus-within {
    border-color: var(--ds-blue);
    color: var(--ds-blue);
}
.h-diag-search-wrap input {
    background: none;
    border: none;
    outline: none;
    font-size: 0.78rem;
    color: var(--ds-text);
    width: 120px;
}
.h-diag-search-wrap input::placeholder {
    color: var(--ds-text-dim);
}
.h-diag-search-wrap button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: var(--ds-text-dim);
    cursor: pointer;
}
.h-diag-search-wrap button:hover {
    background: var(--ds-blue);
    color: #fff;
}

.h-diag-topbar-btn {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--ds-pill-bg);
    border: 1px solid var(--ds-border);
    color: var(--ds-text-muted);
    cursor: pointer;
    transition: all 0.2s;
}
.h-diag-topbar-btn:hover {
    border-color: var(--ds-blue);
    color: var(--ds-blue);
}

.h-diag-notif-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 15px;
    height: 15px;
    background: var(--ds-blue);
    color: #fff;
    font-size: 0.55rem;
    font-weight: 800;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
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

/* ─── Main Stage Hero (3D Car + HUD) ─── */
.h-diag-stage-card {
    position: relative;
    background: var(--ds-stage-bg);
    border: 1px solid var(--ds-stage-border);
    border-radius: var(--ds-radius-card);
    box-shadow: var(--ds-shadow-card);
    display: grid;
    grid-template-columns: 320px 1fr;
    min-height: 400px;
    overflow: hidden;
    margin-bottom: 1.25rem;
    transition: background 0.3s, border-color 0.3s;
}

.h-diag-hud-left {
    padding: 1.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    z-index: 10;
    background: var(--ds-hud-fade);
    backdrop-filter: blur(2px);
}

.h-diag-hud-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: var(--ds-blue);
    background: var(--ds-blue-light);
    border: 1px solid var(--ds-blue-glow);
    padding: 0.22rem 0.65rem;
    border-radius: var(--ds-radius-pill);
    align-self: flex-start;
}

.h-diag-hud-badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--ds-blue);
    box-shadow: 0 0 8px var(--ds-blue);
    animation: hudPulse 1.6s infinite;
}
@keyframes hudPulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.35; transform: scale(0.7); }
}

.h-diag-hud-title {
    font-size: 2.5rem;
    font-weight: 900;
    letter-spacing: -0.04em;
    margin: 0;
    color: var(--ds-text);
    line-height: 1;
}

.h-diag-hud-progress-block {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.h-diag-hud-pct {
    font-size: 2.1rem;
    font-weight: 900;
    letter-spacing: -0.04em;
    line-height: 1;
    color: var(--ds-text);
}

.h-diag-hud-status-label {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.07em;
    color: var(--ds-text-dim);
    text-transform: uppercase;
}

.h-diag-hud-tag-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.h-diag-status-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.7rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.7rem;
    font-weight: 700;
}
.h-diag-status-chip.normal {
    background: var(--ds-green-bg);
    color: var(--ds-green);
    border: 1px solid rgba(16, 185, 129, 0.3);
}
.h-diag-status-chip.warning {
    background: var(--ds-amber-bg);
    color: var(--ds-amber);
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.h-diag-status-chip.danger {
    background: var(--ds-red-bg);
    color: var(--ds-red);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.h-diag-report-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: var(--ds-blue-light);
    color: var(--ds-blue);
    border: 1px solid var(--ds-blue-glow);
    padding: 0.3rem 0.65rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.72rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s;
}
.h-diag-report-pill:hover {
    background: var(--ds-blue);
    color: #fff;
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
    font-size: 0.64rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: var(--ds-text-dim);
}
.h-ticker-item.is-live {
    color: var(--ds-text);
}

.h-ticker-bullet {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--ds-border);
    flex-shrink: 0;
}
.h-ticker-item.is-live .h-ticker-bullet {
    background: var(--ds-blue);
    box-shadow: 0 0 6px var(--ds-blue);
}

.h-diag-hud-cta-wrap {
    margin-top: auto;
    padding-top: 0.5rem;
}

.h-diag-cta-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    color: #fff;
    border: none;
    border-radius: var(--ds-radius-pill);
    padding: 0.75rem 1.45rem;
    font-size: 0.85rem;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 4px 18px rgba(2, 132, 199, 0.4);
    transition: all 0.25s ease;
}
.h-diag-cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 26px rgba(2, 132, 199, 0.5);
    color: #fff;
}
.h-diag-cta-glow {
    position: absolute;
    inset: -3px;
    border-radius: var(--ds-radius-pill);
    background: linear-gradient(135deg, #38bdf8, #0284c7);
    opacity: 0.35;
    filter: blur(8px);
    z-index: -1;
}
.h-diag-cta-spin {
    animation: spin 2.5s linear infinite;
}

/* ─── Stage Viewport ─── */
.h-diag-stage-viewport {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 400px;
    padding: 0;
    overflow: hidden;
    border-radius: 0 0 18px 18px;
}

/* ─── Sketchfab 3D Stage ─── */
.h-diag-3d-stage {
    position: relative;
    width: 100%;
    flex: 1;
    height: 420px;
    min-height: 320px;
    overflow: hidden;
    border-radius: 0 0 18px 18px;
    background: radial-gradient(ellipse at 50% 55%, rgba(2, 132, 199, 0.16) 0%, rgba(10, 22, 40, 0.6) 60%, var(--ds-surface, #0b1329) 100%);
}

[data-theme="light"] .h-diag-3d-stage {
    background: radial-gradient(ellipse at 50% 55%, rgba(2, 132, 199, 0.12) 0%, rgba(219, 234, 254, 0.5) 60%, #f8fafc 100%);
}

.h-diag-sketchfab-iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: none;
    display: block;
    border-radius: 0 0 18px 18px;
}

/* Sensor pins overlay (floats above the iframe) */
.h-diag-pins-overlay {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 10;
}
.h-diag-pins-overlay .h-diag-pin {
    pointer-events: all;
}

/* 3D Loading State */
.h-diag-3d-loader {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    z-index: 20;
    background: var(--ds-stage-bg, #0a1628);
    border-radius: 0 0 18px 18px;
    transition: opacity 0.4s ease;
}
[data-theme="light"] .h-diag-3d-loader {
    background: linear-gradient(160deg, #e8f4ff 0%, #dbeafe 60%, #eff6ff 100%);
}
.h-diag-3d-loader.is-hidden {
    opacity: 0;
    pointer-events: none;
}

.h-diag-3d-loader-spinner {
    width: 44px;
    height: 44px;
    border: 3px solid rgba(2, 132, 199, 0.25);
    border-top-color: var(--ds-blue, #0284c7);
    border-radius: 50%;
    animation: spin 0.9s linear infinite;
}

.h-diag-3d-loader-text {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
}
.h-diag-3d-loader-title {
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    color: var(--ds-blue, #0284c7);
}
.h-diag-3d-loader-sub {
    font-size: 0.63rem;
    color: var(--ds-text-dim, #94a3b8);
    letter-spacing: 0.03em;
}

/* Model Picker Pill (Bottom-Left of 3D Stage) */
.h-diag-model-picker-wrap {
    position: absolute;
    bottom: 1rem;
    left: 1rem;
    z-index: 25;
}
.h-diag-model-picker-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(2, 10, 30, 0.72);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(2, 132, 199, 0.35);
    border-radius: 20px;
    padding: 0.35rem 0.75rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #e2e8f0;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
[data-theme="light"] .h-diag-model-picker-btn {
    background: rgba(255,255,255,0.82);
    color: var(--ds-text, #1e293b);
    border-color: rgba(2, 132, 199, 0.3);
}
.h-diag-model-picker-btn:hover {
    background: rgba(2, 132, 199, 0.25);
    border-color: var(--ds-blue, #0284c7);
}
.h-diag-model-menu {
    position: absolute;
    bottom: calc(100% + 0.5rem);
    left: 0;
    background: var(--ds-surface, #0f172a);
    border: 1px solid var(--ds-border, #1e293b);
    border-radius: 14px;
    padding: 0.5rem;
    min-width: 220px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.45);
    z-index: 50;
}
.h-diag-model-menu-title {
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    color: var(--ds-text-muted, #64748b);
    padding: 0.25rem 0.5rem 0.5rem;
    text-transform: uppercase;
}
.h-diag-model-current-box {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.45rem 0.65rem;
    background: var(--ds-blue-light, rgba(2,132,199,0.12));
    border: 1px solid var(--ds-blue-glow, rgba(2,132,199,0.3));
    border-radius: 9px;
    margin-bottom: 0.45rem;
}
.h-diag-model-dot-live {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.8);
    flex-shrink: 0;
}
.h-diag-model-current-text {
    display: flex;
    flex-direction: column;
}
.h-diag-model-current-text strong {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--ds-text, #f1f5f9);
}
.h-diag-model-current-text span {
    font-size: 0.62rem;
    color: var(--ds-text-dim, #94a3b8);
}
.h-diag-model-menu-divider {
    height: 1px;
    background: var(--ds-border, #1e293b);
    margin: 0.45rem 0;
}

.h-diag-model-option {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    width: 100%;
    padding: 0.5rem 0.65rem;
    border-radius: 9px;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: background 0.15s;
    gap: 0.1rem;
}
.h-diag-model-option:hover {
    background: var(--ds-blue-light, rgba(2,132,199,0.12));
}
.h-diag-model-option.is-active {
    background: var(--ds-blue-light, rgba(2,132,199,0.12));
}
.h-diag-model-opt-name {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--ds-text, #f1f5f9);
}
.h-diag-model-opt-sub {
    font-size: 0.62rem;
    color: var(--ds-text-muted, #64748b);
}
.h-diag-model-option.custom .h-diag-model-opt-name {
    color: var(--ds-blue, #0284c7);
}

/* 2D Blueprint fallback car wrap */
.h-diag-car-wrap {
    position: relative;
    width: 92%;
    max-width: 720px;
    transition: transform 0.45s cubic-bezier(0.16, 1, 0.3, 1);
    padding: 1rem;
}

.h-diag-car-svg {
    width: 100%;
    height: auto;
    display: block;
}

/* Viewport Controls (floating button column) */
.h-diag-viewport-controls {
    position: absolute;
    top: 1.25rem;
    right: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    z-index: 25;
}

.h-diag-zoom-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(2, 10, 30, 0.65);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(2, 132, 199, 0.3);
    color: #e2e8f0;
    font-weight: 800;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    line-height: 1;
}
[data-theme="light"] .h-diag-zoom-btn {
    background: rgba(255,255,255,0.82);
    color: var(--ds-text, #1e293b);
    border-color: rgba(2, 132, 199, 0.25);
}
.h-diag-zoom-btn:hover {
    border-color: var(--ds-blue, #0284c7);
    color: var(--ds-blue, #0284c7);
    background: rgba(2, 132, 199, 0.18);
}
.h-diag-toggle-view-btn {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.03em;
}

/* ─── Sensor Pins on 3D Car ─── */
.h-diag-pin {
    position: absolute;
    transform: translate(-50%, -50%);
    cursor: pointer;
    z-index: 20;
}

.h-diag-pin-beacon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(2, 132, 199, 0.25);
    animation: pinBeacon 2s infinite;
}
.h-diag-pin-beacon.red { background: rgba(239, 68, 68, 0.35); }
.h-diag-pin-beacon.orange { background: rgba(245, 158, 11, 0.35); }
@keyframes pinBeacon {
    0%   { transform: translate(-50%, -50%) scale(0.6); opacity: 1; }
    100% { transform: translate(-50%, -50%) scale(1.9); opacity: 0; }
}

.h-diag-pin-core {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    position: relative;
    z-index: 2;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.h-diag-pin:hover .h-diag-pin-core { transform: scale(1.2); }
.h-diag-pin-core.blue   { background: #0284c7; }
.h-diag-pin-core.orange { background: #f59e0b; }
.h-diag-pin-core.red    { background: #ef4444; }
.h-diag-pin-core.green  { background: #10b981; }
.h-diag-pin-core.dark   { background: #0f172a; }

.h-diag-pin-tooltip {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    background: var(--ds-dark-card);
    color: #fff;
    padding: 0.35rem 0.65rem;
    border-radius: 8px;
    font-size: 0.66rem;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s, transform 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
    border: 1px solid var(--ds-dark-card-border);
    box-shadow: 0 4px 16px rgba(0,0,0,0.35);
}
.h-diag-pin:hover .h-diag-pin-tooltip {
    opacity: 1;
    transform: translateX(-50%) translateY(-4px);
}
.h-diag-pin-tooltip span {
    color: #94a3b8;
    font-size: 0.59rem;
}

/* ─── 4 Cockpit Sensor Cards Grid ─── */
.h-diag-grid-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.h-diag-card {
    background: var(--ds-card-bg);
    border: 1px solid var(--ds-card-border);
    border-radius: var(--ds-radius-card);
    padding: 1.2rem;
    box-shadow: var(--ds-shadow-card);
    display: flex;
    flex-direction: column;
    transition: background 0.25s, border-color 0.25s, box-shadow 0.25s;
}

.h-diag-card-probes {
    grid-column: span 2;
}

.h-diag-card-dark {
    background: var(--ds-dark-card);
    border-color: var(--ds-dark-card-border);
    color: #fff;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.45);
    min-height: 220px;
}

.h-diag-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.85rem;
}

.h-diag-card-title {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: var(--ds-text-dim);
}
.h-diag-card-title.light { color: #94a3b8; }

.h-diag-card-status-badge {
    font-size: 0.58rem;
    font-weight: 800;
    padding: 0.15rem 0.5rem;
    border-radius: var(--ds-radius-pill);
}
.h-diag-card-status-badge.ok {
    background: var(--ds-green-bg);
    color: var(--ds-green);
    border: 1px solid rgba(16, 185, 129, 0.25);
}

/* Probe Bars */
.h-diag-probe-bars-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: clamp(4px, 1.2vw, 0.75rem);
    margin-bottom: 1rem;
    width: 100%;
    box-sizing: border-box;
    overflow: hidden;
}

.h-diag-probe-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
    width: 100%;
    overflow: hidden;
    box-sizing: border-box;
}

.h-diag-probe-track {
    width: 12px;
    height: 70px;
    background: var(--ds-surface-subtle);
    border-radius: var(--ds-radius-pill);
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: flex-end;
}

.h-probe-bar {
    width: 100%;
    border-radius: var(--ds-radius-pill);
    transition: height 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.h-diag-probe-name {
    font-size: clamp(0.55rem, 1.8vw, 0.72rem);
    font-weight: 700;
    color: var(--ds-text);
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.h-diag-ctx-dot {
    display: inline-block;
    font-size: 0.58rem;
    color: var(--ds-blue);
    margin-left: 2px;
    vertical-align: middle;
    font-weight: 800;
    cursor: help;
}

.h-diag-probe-ctx {
    font-size: 0.55rem;
    color: var(--ds-text-dim);
    text-align: center;
    line-height: 1.1;
    cursor: help;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}

.h-diag-probe-status {
    font-size: clamp(0.50rem, 1.6vw, 0.62rem);
    font-weight: 600;
    color: var(--ds-text-muted);
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
.h-diag-probe-status.is-alert {
    color: var(--ds-amber);
    font-weight: 700;
}

.h-diag-probes-stats-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem 1rem;
    padding-top: 0.85rem;
    border-top: 1px solid var(--ds-border);
    width: 100%;
    box-sizing: border-box;
    overflow: hidden;
}

.h-diag-stat-block {
    min-width: 0;
    overflow: hidden;
}

.h-diag-stat-val {
    font-size: 1rem;
    font-weight: 800;
    color: var(--ds-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.h-diag-stat-unit {
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--ds-text-dim);
}

.h-diag-stat-lbl {
    font-size: 0.65rem;
    color: var(--ds-text-dim);
    white-space: nowrap;
}

/* Dark Card Elements */
.h-diag-dark-confidence {
    font-size: 2rem;
    font-weight: 900;
    color: #ffffff;
    line-height: 1;
}

.h-diag-dark-nodata {
    font-size: 1.1rem;
    font-weight: 700;
    color: #475569;
}

.h-diag-engine-svg-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: -0.25rem 0;
}
.h-diag-engine-svg {
    width: 100%;
    max-height: 90px;
}

.h-diag-dark-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.55rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    font-size: 0.68rem;
    color: #94a3b8;
}

.h-diag-dark-condition {
    font-weight: 700;
}

/* Voltage Center */
.h-diag-voltage-center {
    text-align: center;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 0.35rem;
}

.h-diag-voltage-val {
    font-size: 2rem;
    font-weight: 900;
    color: var(--ds-text);
    line-height: 1;
}

.h-diag-voltage-lbl {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--ds-text-dim);
    letter-spacing: 0.06em;
}

.h-diag-voltage-wave {
    width: 100%;
    max-height: 45px;
    margin-top: 0.25rem;
}

.h-diag-voltage-rpm {
    font-size: 0.66rem;
    color: var(--ds-text-dim);
}
.h-diag-voltage-rpm strong {
    color: var(--ds-text);
}

.h-diag-rpm-ctx {
    font-size: 0.55rem;
    color: var(--ds-text-dim);
}

/* AI Assistant Card */
.h-diag-card-ai {
    border-color: var(--ds-blue-glow);
}

.h-diag-ai-head-left {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.h-diag-orb-small {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: radial-gradient(circle, #38bdf8 0%, #0284c7 100%);
    box-shadow: 0 0 8px #38bdf8;
    animation: orbPulse 2s infinite alternate;
}
@keyframes orbPulse {
    from { transform: scale(0.9); opacity: 0.8; }
    to   { transform: scale(1.2); opacity: 1; }
}

.h-diag-ai-stop-btn {
    background: none;
    border: none;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--ds-text-dim);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0;
}
.h-diag-ai-stop-btn:hover { color: var(--ds-red); }

.h-diag-stop-square {
    width: 8px;
    height: 8px;
    background: var(--ds-text-dim);
    border-radius: 1px;
    display: inline-block;
}

.h-diag-ai-feed {
    flex: 1;
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    border-radius: 10px;
    padding: 0.65rem;
    font-size: 0.75rem;
    color: var(--ds-text);
    line-height: 1.55;
    overflow-y: auto;
    max-height: 100px;
    margin-bottom: 0.5rem;
}
.h-diag-ai-feed p { margin: 0; }

.h-diag-ai-input-wrap {
    display: flex;
    gap: 0.35rem;
    margin-bottom: 0.4rem;
}
.h-diag-ai-input-wrap input {
    flex: 1;
    background: var(--ds-input-bg);
    border: 1px solid var(--ds-input-border);
    border-radius: 8px;
    padding: 0.4rem 0.65rem;
    font-size: 0.73rem;
    color: var(--ds-text);
    outline: none;
    transition: border-color 0.2s;
}
.h-diag-ai-input-wrap input:focus {
    border-color: var(--ds-blue);
}
.h-diag-ai-input-wrap input::placeholder {
    color: var(--ds-text-dim);
}

.h-diag-ai-send-btn {
    background: var(--ds-blue);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 0 0.75rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.15s;
}
.h-diag-ai-send-btn:hover { opacity: 0.9; }

.h-diag-ai-status-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.h-diag-live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 6px #10b981;
}

#ai-idx-status {
    font-size: 0.65rem;
    color: var(--ds-text-dim);
}

/* ─── Diagnostic History Section ─── */
.h-diag-history-page-heading {
    margin: 0 0 0.85rem;
    padding: 0.25rem 0;
}
.h-diag-history-page-eyebrow {
    color: var(--ds-blue);
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.12em;
}
.h-diag-history-page-heading h1 {
    margin: 0.35rem 0 0.25rem;
    color: var(--ds-text);
    font-size: 1.65rem;
    line-height: 1.1;
}
.h-diag-history-page-heading p {
    margin: 0;
    color: var(--ds-text-muted);
    font-size: 0.82rem;
}
.h-diag-history-card {
    background: var(--ds-card-bg);
    border: 1px solid var(--ds-card-border);
    border-radius: var(--ds-radius-card);
    overflow: hidden;
    box-shadow: var(--ds-shadow-card);
    margin-top: 1.5rem;
    transition: background 0.25s, border-color 0.25s;
}

.h-diag-hist-header {
    padding: 1.1rem 1.35rem;
    border-bottom: 1px solid var(--ds-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.85rem;
}

.h-diag-hist-subtitle {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--ds-text-dim);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 0.15rem;
}

.h-diag-hist-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--ds-text);
    margin: 0;
}

.h-diag-hist-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.h-diag-v-tabs-track {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    overflow-x: auto;
    padding-bottom: 2px;
    max-width: 480px;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.h-diag-v-tabs-track::-webkit-scrollbar { display: none; }

.h-diag-v-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.32rem 0.7rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.72rem;
    font-weight: 700;
    text-decoration: none;
    background: var(--ds-surface-subtle);
    color: var(--ds-text-muted);
    border: 1px solid var(--ds-border);
    white-space: nowrap;
    transition: all 0.15s;
}
.h-diag-v-tab:hover {
    border-color: var(--ds-blue);
    color: var(--ds-text);
}
.h-diag-v-tab.is-active {
    background: var(--ds-blue);
    color: #fff;
    border-color: var(--ds-blue);
}

.h-diag-v-tab-count {
    font-size: 0.6rem;
    background: rgba(0, 0, 0, 0.15);
    padding: 0.05rem 0.35rem;
    border-radius: var(--ds-radius-pill);
}
.h-diag-v-tab.is-active .h-diag-v-tab-count {
    background: rgba(255, 255, 255, 0.25);
    color: #fff;
}

.h-diag-hist-btn-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.h-diag-hist-cta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: linear-gradient(135deg, #0284c7, #0369a1);
    color: #fff;
    padding: 0.42rem 0.85rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.75rem;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 3px 12px rgba(2, 132, 199, 0.3);
    white-space: nowrap;
    transition: all 0.2s;
}
.h-diag-hist-cta:hover { color: #fff; transform: translateY(-1px); }

.h-diag-hist-export {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: var(--ds-surface-subtle);
    color: var(--ds-text-muted);
    padding: 0.42rem 0.75rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.72rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid var(--ds-border);
    white-space: nowrap;
    transition: all 0.15s;
}
.h-diag-hist-export:hover {
    border-color: var(--ds-blue);
    color: var(--ds-text);
}

.h-diag-alert-success {
    margin: 0.85rem 1.35rem;
    padding: 0.75rem 1rem;
    background: var(--ds-green-bg);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 10px;
    font-size: 0.8rem;
    color: var(--ds-green);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.h-diag-no-sessions {
    padding: 3.5rem 1.5rem;
    text-align: center;
}
.h-diag-no-sessions-icon {
    width: 54px;
    height: 54px;
    background: var(--ds-surface-subtle);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.85rem;
    color: var(--ds-text-dim);
}
.h-diag-no-sessions h3 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--ds-text);
    margin: 0 0 0.35rem;
}
.h-diag-no-sessions p {
    font-size: 0.8rem;
    color: var(--ds-text-dim);
    margin: 0 0 1.25rem;
}

/* History Rows */
.h-hist-row {
    display: flex;
    align-items: center;
    padding: 0.95rem 1.35rem;
    border-bottom: 1px solid var(--ds-border-light);
    transition: background 0.15s;
    gap: 0.85rem;
}
.h-hist-row:last-child { border-bottom: none; }
.h-hist-row:hover { background: var(--ds-hover-bg); }

.h-hist-icon-box {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.h-hist-icon-box.is-normal  { background: var(--ds-green-bg); color: var(--ds-green); }
.h-hist-icon-box.is-warning { background: var(--ds-amber-bg); color: var(--ds-amber); }
.h-hist-icon-box.is-danger  { background: var(--ds-red-bg);   color: var(--ds-red); }
.h-hist-icon-box.is-info    { background: var(--ds-blue-light); color: var(--ds-blue); }

.h-hist-info-col {
    flex: 1;
    min-width: 0;
}

.h-hist-title-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    margin-bottom: 0.25rem;
}

.h-hist-title-link {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--ds-text);
    text-decoration: none;
    transition: color 0.15s;
}
.h-hist-title-link:hover { color: var(--ds-blue); }

.h-hist-cond-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.12rem 0.5rem;
    border-radius: var(--ds-radius-pill);
}
.h-hist-cond-badge.is-normal  { background: var(--ds-green-bg); color: var(--ds-green); }
.h-hist-cond-badge.is-warning { background: var(--ds-amber-bg); color: var(--ds-amber); }
.h-hist-cond-badge.is-danger  { background: var(--ds-red-bg);   color: var(--ds-red); }
.h-hist-cond-badge.is-info    { background: var(--ds-blue-light); color: var(--ds-blue); }

.h-hist-meta-row {
    font-size: 0.72rem;
    color: var(--ds-text-dim);
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.h-hist-mode-tag {
    background: var(--ds-surface-subtle);
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-size: 0.63rem;
    text-transform: capitalize;
    color: var(--ds-text-muted);
}

.h-hist-actions-col {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.h-hist-conf-box {
    text-align: right;
    margin-right: 0.35rem;
}
.h-hist-conf-num {
    font-size: 0.9rem;
    font-weight: 800;
    line-height: 1;
}
.h-hist-conf-lbl {
    font-size: 0.58rem;
    color: var(--ds-text-dim);
}

.h-hist-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    border-radius: 8px;
    color: var(--ds-text-muted);
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s;
}
.h-hist-action-btn:hover {
    border-color: var(--ds-blue);
    color: var(--ds-blue);
}
.h-hist-action-btn.analyze {
    background: var(--ds-blue-light);
    border-color: var(--ds-blue-glow);
    color: var(--ds-blue);
}
.h-hist-action-btn.report:hover {
    border-color: var(--ds-text);
    color: var(--ds-text);
}

.h-diag-pagination-wrap {
    padding: 0.85rem 1.35rem;
    border-top: 1px solid var(--ds-border);
}

/* ─── IoT Modal ─── */
.h-diag-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    align-items: center;
    justify-content: center;
    background: var(--ds-modal-overlay);
    backdrop-filter: blur(4px);
    padding: 1rem;
    box-sizing: border-box;
}

.h-diag-modal-dialog {
    background: var(--ds-modal-bg);
    border: 1px solid var(--ds-border);
    border-radius: 20px;
    width: min(720px, 96vw);
    max-height: 88vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 24px 80px rgba(0,0,0,0.4);
    animation: fadeIn 0.2s ease-out;
}

.h-diag-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--ds-border);
}

.h-diag-modal-title-box {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.h-diag-modal-icon {
    width: 38px;
    height: 38px;
    background: var(--ds-blue-light);
    border-radius: 10px;
    color: var(--ds-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.h-diag-modal-heading {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--ds-text);
}

.h-diag-modal-sub {
    font-size: 0.72rem;
    color: var(--ds-text-dim);
}

.h-diag-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--ds-text-muted);
    padding: 0.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s;
}
.h-diag-modal-close:hover { color: var(--ds-red); }

.h-diag-modal-tabs {
    display: flex;
    padding: 0.75rem 1.5rem 0;
    gap: 0.5rem;
    border-bottom: 1px solid var(--ds-border);
    background: var(--ds-surface-subtle);
    overflow-x: auto;
    scrollbar-width: none;
}
.h-diag-modal-tabs::-webkit-scrollbar { display: none; }

.h-diag-modal-tab-btn {
    padding: 0.4rem 0.9rem;
    border-radius: var(--ds-radius-pill);
    border: none;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    background: transparent;
    color: var(--ds-text-muted);
    transition: all 0.15s;
    white-space: nowrap;
}
.h-diag-modal-tab-btn.is-active {
    background: var(--ds-blue);
    color: #fff;
}

.h-diag-modal-body {
    overflow-y: auto;
    padding: 1.5rem;
    flex: 1;
}

.h-diag-hw-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.h-diag-hw-card {
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
}

.h-diag-hw-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.h-diag-hw-name {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--ds-text);
}
.h-diag-hw-type {
    font-size: 0.68rem;
    font-weight: 700;
    margin-bottom: 0.2rem;
}
.h-diag-hw-desc {
    font-size: 0.72rem;
    color: var(--ds-text-dim);
}

.h-diag-status-callout {
    background: var(--ds-blue-light);
    border: 1px solid var(--ds-blue-glow);
    border-radius: 10px;
    padding: 0.85rem 1rem;
    font-size: 0.8rem;
    color: var(--ds-text);
}
.h-diag-status-callout code {
    background: rgba(2, 132, 199, 0.15);
    color: var(--ds-blue);
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
}

.h-diag-tab-hint {
    font-size: 0.75rem;
    color: var(--ds-text-dim);
    margin-bottom: 0.85rem;
}

.h-diag-table-wrap {
    overflow-x: auto;
    border: 1px solid var(--ds-border);
    border-radius: 10px;
}

.h-diag-pinout-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}
.h-diag-pinout-table th {
    background: var(--ds-surface-subtle);
    padding: 0.65rem 0.85rem;
    text-align: left;
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--ds-text-dim);
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--ds-border);
}
.h-diag-pinout-table td {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid var(--ds-border-light);
}
.h-diag-pinout-table tr:last-child td { border-bottom: none; }

.h-diag-col-bold { font-weight: 700; color: var(--ds-text); }
.h-diag-col-mono { font-family: monospace; font-size: 0.73rem; color: var(--ds-text-muted); }
.h-diag-col-dim  { font-size: 0.72rem; color: var(--ds-text-dim); }

.h-diag-proto-tag {
    background: var(--ds-blue-light);
    color: var(--ds-blue);
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
}

.h-diag-api-card {
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    border-radius: 10px;
    padding: 0.85rem;
    margin-bottom: 0.75rem;
}
.h-diag-api-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
}
.h-diag-method-badge {
    padding: 0.12rem 0.45rem;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 800;
}
.h-diag-method-badge.post { background: var(--ds-blue-light); color: var(--ds-blue); }
.h-diag-method-badge.get  { background: var(--ds-green-bg); color: var(--ds-green); }

.h-diag-api-route {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--ds-text);
}
.h-diag-api-desc {
    font-size: 0.74rem;
    color: var(--ds-text-dim);
    margin-bottom: 0.35rem;
}
.h-diag-api-body {
    background: var(--ds-dark-card);
    color: #38bdf8;
    padding: 0.55rem 0.75rem;
    border-radius: 6px;
    font-size: 0.7rem;
    line-height: 1.5;
    margin: 0;
    overflow-x: auto;
}

.h-diag-code-block {
    background: var(--ds-dark-card);
    color: #e2e8f0;
    padding: 1.1rem;
    border-radius: 10px;
    font-size: 0.72rem;
    line-height: 1.7;
    overflow-x: auto;
    white-space: pre-wrap;
    margin: 0;
    border: 1px solid var(--ds-dark-card-border);
}

.h-diag-warn-box {
    margin-top: 0.85rem;
    background: var(--ds-amber-bg);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 8px;
    padding: 0.75rem 0.9rem;
    font-size: 0.75rem;
    color: var(--ds-text);
}
.h-diag-inline-code {
    background: rgba(245, 158, 11, 0.2);
    color: var(--ds-amber);
    padding: 0.1rem 0.3rem;
    border-radius: 3px;
}

/* Empty Card State */
.h-diag-empty-card {
    background: var(--ds-card-bg);
    border: 1px solid var(--ds-card-border);
    border-radius: var(--ds-radius-card);
    padding: 3.5rem 2rem;
    text-align: center;
    box-shadow: var(--ds-shadow-card);
    margin-top: 2rem;
}
.h-diag-empty-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--ds-blue-light);
    color: var(--ds-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
}
.h-diag-empty-card h2 {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--ds-text);
    margin: 0 0 0.5rem;
}
.h-diag-empty-card p {
    font-size: 0.85rem;
    color: var(--ds-text-dim);
    margin: 0 0 1.5rem;
}

.h-diag-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: linear-gradient(135deg, #0284c7, #0369a1);
    color: #fff;
    border: none;
    border-radius: var(--ds-radius-pill);
    padding: 0.65rem 1.35rem;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35);
    transition: all 0.2s;
}
.h-diag-btn-primary:hover {
    color: #fff;
    transform: translateY(-1px);
}

/* Badge utility */
.h-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.02em;
}
.h-badge-green { background: var(--ds-green-bg); color: var(--ds-green); }
.h-badge-amber { background: var(--ds-amber-bg); color: var(--ds-amber); }
.h-badge-red   { background: var(--ds-red-bg);   color: var(--ds-red); }
.h-badge-teal  { background: var(--ds-blue-light); color: var(--ds-blue); }
.h-badge-gray  { background: var(--ds-surface-subtle); color: var(--ds-text-dim); }

@keyframes spin { to { transform: rotate(360deg); } }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE DESIGN (Tablets & Phones)
   ═══════════════════════════════════════════════════════════════ */

/* Tablet (<= 1100px) */
@media (max-width: 1100px) {
    .h-diag-stage-card {
        grid-template-columns: 290px 1fr;
    }
    .h-diag-grid-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    .h-diag-card-probes {
        grid-column: span 2;
    }
}

/* Intermediate Mobile (<= 880px) */
@media (max-width: 880px) {
    .h-diag-stage-card {
        grid-template-columns: 1fr;
        min-height: auto;
    }
    .h-diag-hud-left {
        background: var(--ds-surface);
        padding: 1.35rem;
    }
    .h-diag-stage-viewport {
        min-height: 300px;
        padding: 0;
    }
    .h-diag-3d-stage {
        height: 320px;
    }
    .h-diag-cta-btn {
        width: 100%;
    }
    .h-diag-grid-cards {
        grid-template-columns: 1fr !important;
    }
    .h-diag-card-probes {
        grid-column: span 1 !important;
        overflow: hidden !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .h-diag-probe-ctx {
        display: none !important;
    }
    .h-diag-probe-bars-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
        gap: 0.25rem !important;
    }
}

/* Phone Mode (<= 640px) */
@media (max-width: 640px) {
    .h-diag-studio-wrapper {
        padding: 0 0.5rem 6rem !important;
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: hidden !important;
        box-sizing: border-box !important;
    }

    /* Top Navigation on Mobile */
    .h-diag-topbar {
        border-radius: 16px !important;
        padding: 0.65rem 0.75rem !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.6rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .h-diag-topbar-left {
        width: 100% !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.5rem !important;
    }
    .h-diag-vehicle-dropdown-wrap {
        width: 100% !important;
        position: relative !important;
    }
    .h-diag-vehicle-pill {
        width: 100% !important;
        justify-content: space-between !important;
        box-sizing: border-box !important;
    }
    .h-diag-pill-title {
        flex: 1 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        max-width: calc(100% - 90px) !important;
        font-size: 0.82rem !important;
    }
    .h-diag-topbar-left-subrow {
        display: flex !important;
        align-items: center !important;
        gap: 0.45rem !important;
        width: 100% !important;
    }
    .h-diag-topbar-left-subrow .h-diag-mode-pill {
        flex: 1 !important;
        justify-content: center !important;
        min-height: 36px !important;
    }
    .h-diag-topbar-right {
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.45rem !important;
    }
    .h-diag-search-wrap {
        flex: 1 !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }
    .h-diag-search-wrap input {
        width: 100% !important;
        min-width: 0 !important;
    }
    .h-diag-dropdown-menu {
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
    }

    /* Hero Stage on Mobile */
    .h-diag-stage-card {
        border-radius: 16px !important;
        padding: 0 !important;
        margin-bottom: 1rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .h-diag-hud-left {
        padding: 1.15rem 1rem !important;
        gap: 0.65rem !important;
    }
    .h-diag-hud-title {
        font-size: 1.85rem !important;
    }
    .h-diag-hud-pct {
        font-size: 1.75rem !important;
    }
    .h-diag-hud-tag-row {
        flex-wrap: wrap !important;
        gap: 0.4rem !important;
    }
    .h-diag-cta-btn {
        width: 100% !important;
        justify-content: center !important;
        min-height: 44px !important;
    }
    .h-diag-stage-viewport {
        min-height: 220px !important;
        padding: 0 !important;
    }
    .h-diag-3d-stage {
        height: 260px !important;
    }
    .h-diag-car-wrap {
        width: 100% !important;
        max-width: 100% !important;
    }
    .h-diag-model-picker-btn {
        font-size: 0.65rem !important;
        padding: 0.28rem 0.55rem !important;
    }

    /* Grid Cards Collapse to 1 Column with zero overflow */
    .h-diag-grid-cards {
        display: flex !important;
        flex-direction: column !important;
        gap: 0.85rem !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    .h-diag-card {
        padding: 1rem 0.85rem !important;
        border-radius: 16px !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
    }
    .h-diag-card-probes {
        grid-column: span 1 !important;
    }
    .h-diag-card-head {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
        gap: 0.35rem !important;
        margin-bottom: 0.75rem !important;
    }
    .h-diag-card-title {
        font-size: 0.68rem !important;
        letter-spacing: 0.05em !important;
    }
    .h-diag-card-status-badge {
        font-size: 0.58rem !important;
        padding: 0.15rem 0.45rem !important;
        white-space: nowrap !important;
    }

    /* Probes 5-bar grid compact & non-overflowing */
    .h-diag-probe-bars-grid {
        display: grid !important;
        grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
        gap: 0.25rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
        margin-bottom: 0.85rem !important;
    }
    .h-diag-probe-col {
        min-width: 0 !important;
        width: 100% !important;
        align-items: center !important;
        gap: 0.2rem !important;
        overflow: hidden !important;
    }
    .h-diag-probe-track {
        height: 52px !important;
        width: 10px !important;
    }
    .h-diag-probe-name {
        font-size: 0.58rem !important;
        font-weight: 700 !important;
        text-align: center !important;
        width: 100% !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        line-height: 1.2 !important;
    }
    .h-diag-probe-status {
        font-size: 0.54rem !important;
        text-align: center !important;
        width: 100% !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        line-height: 1.2 !important;
    }
    .h-diag-probe-ctx {
        display: none !important;
    }

    /* Probes bottom stats row */
    .h-diag-probes-stats-row {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 0.65rem 0.5rem !important;
        padding-top: 0.65rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .h-diag-stat-block {
        min-width: 0 !important;
        overflow: hidden !important;
    }
    .h-diag-stat-val {
        font-size: 0.92rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    .h-diag-stat-lbl {
        font-size: 0.62rem !important;
        white-space: normal !important;
        line-height: 1.2 !important;
    }

    /* History on Mobile */
    .h-diag-history-card {
        padding: 1rem 0.85rem !important;
        border-radius: 16px !important;
        width: 100% !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
    }
    .h-diag-hist-header {
        padding: 0 0 0.85rem 0 !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.75rem !important;
    }
    .h-diag-hist-title {
        font-size: 1rem !important;
    }
    .h-diag-hist-actions {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.65rem !important;
    }
    .h-diag-v-tabs-track {
        max-width: 100% !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .h-diag-hist-btn-group {
        width: 100% !important;
        display: flex !important;
        gap: 0.5rem !important;
    }
    .h-diag-hist-cta,
    .h-diag-hist-export {
        flex: 1 !important;
        justify-content: center !important;
        min-height: 38px !important;
    }

    /* History Row on Mobile */
    .h-hist-row {
        padding: 0.85rem 0.5rem !important;
        flex-wrap: wrap !important;
        gap: 0.65rem !important;
        box-sizing: border-box !important;
        width: 100% !important;
    }
    .h-hist-info-col {
        min-width: calc(100% - 50px) !important;
        overflow: hidden !important;
    }
    .h-hist-title-row {
        flex-wrap: wrap !important;
        gap: 0.35rem !important;
    }
    .h-hist-title-link {
        font-size: 0.8rem !important;
    }
    .h-hist-meta-row {
        font-size: 0.68rem !important;
        gap: 0.35rem !important;
    }
    .h-hist-actions-col {
        width: 100% !important;
        justify-content: flex-end !important;
        padding-top: 0.35rem !important;
        border-top: 1px dashed var(--ds-border-light) !important;
    }

    /* IoT Modal on Mobile */
    .h-diag-modal-dialog {
        width: 100% !important;
        max-height: 94vh !important;
        border-radius: 16px !important;
    }
    .h-diag-modal-head {
        padding: 1rem 1.15rem !important;
    }
    .h-diag-modal-body {
        padding: 1rem 1.15rem !important;
    }
    .h-diag-hw-grid {
        grid-template-columns: 1fr !important;
    }
}

/* ─── No Preview State Card ─── */
.h-diag-no-preview-wrap {
    width: 100%;
    height: 100%;
    min-height: 380px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1.5rem;
    box-sizing: border-box;
}

.h-diag-no-preview-card {
    background: var(--ds-surface);
    border: 1px dashed var(--ds-border);
    border-radius: var(--ds-radius-card);
    padding: 2.25rem 2rem;
    max-width: 440px;
    width: 100%;
    text-align: center;
    box-shadow: var(--ds-shadow-card);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.85rem;
    animation: hDiagFadeIn 0.3s ease;
}

.h-diag-no-preview-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ds-text-dim);
}

.h-diag-no-preview-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--ds-text);
    letter-spacing: -0.01em;
}

.h-diag-no-preview-sub {
    font-size: 0.82rem;
    color: var(--ds-text-muted);
    line-height: 1.5;
    margin: 0;
}

.h-diag-no-preview-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 0.5rem;
}

.h-diag-no-preview-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 0.95rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.h-diag-no-preview-btn.primary {
    background: var(--ds-blue);
    color: #ffffff;
    border: none;
    box-shadow: 0 4px 14px var(--ds-blue-glow);
}
.h-diag-no-preview-btn.primary:hover {
    filter: brightness(1.1);
    transform: translateY(-1px);
}

.h-diag-no-preview-btn.secondary {
    background: var(--ds-surface-subtle);
    color: var(--ds-text);
    border: 1px solid var(--ds-border);
}
.h-diag-no-preview-btn.secondary:hover {
    background: var(--ds-hover-bg);
    border-color: var(--ds-blue);
}

/* 2D Mode Badge Overlay */
.h-diag-2d-mode-badge {
    position: absolute;
    top: 14px;
    left: 14px;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(13, 17, 26, 0.82);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid var(--ds-border);
    padding: 0.28rem 0.65rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.72rem;
    color: var(--ds-text);
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    flex-wrap: wrap;
}

[data-theme="light"] .h-diag-2d-mode-badge {
    background: rgba(255, 255, 255, 0.9);
}

.h-diag-2d-pill {
    background: var(--ds-blue-light);
    color: var(--ds-blue);
    font-weight: 800;
    font-size: 0.64rem;
    padding: 0.12rem 0.45rem;
    border-radius: var(--ds-radius-pill);
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.h-diag-2d-note {
    font-size: 0.68rem;
    color: var(--ds-text-muted);
}

.h-diag-2d-bind-btn {
    background: none;
    border: none;
    color: var(--ds-blue);
    font-size: 0.68rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: underline;
    padding: 0;
}
.h-diag-2d-bind-btn:hover {
    color: #ffffff;
}

@keyframes hDiagFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
// Vehicle dropdown toggle
window.toggleVehicleMenu = function() {
    const menu = document.getElementById('vehicle-menu');
    const pill = document.getElementById('vehicle-pill');
    if (!menu) return;
    const isShowing = menu.style.display !== 'none';
    menu.style.display = isShowing ? 'none' : 'block';
    if (pill) pill.setAttribute('aria-expanded', isShowing ? 'false' : 'true');
};

document.addEventListener('click', function(e) {
    if (!e.target.closest('.h-diag-vehicle-dropdown-wrap')) {
        const menu = document.getElementById('vehicle-menu');
        const pill = document.getElementById('vehicle-pill');
        if (menu) menu.style.display = 'none';
        if (pill) pill.setAttribute('aria-expanded', 'false');
    }
    // Close model picker menu if clicked outside
    if (!e.target.closest('.h-diag-model-picker-wrap')) {
        const m = document.getElementById('model-menu');
        if (m) m.style.display = 'none';
    }
});

// ──────────────────────────────────────────────────────────
// SKETCHFAB VIEWER API 1.12.1 INTEGRATION — DYNAMIC VEHICLE CORRELATION
// ──────────────────────────────────────────────────────────
const HAVEN_ACTIVE_VEHICLE = {
    id: {{ $selectedVehicle?->id ?? 'null' }},
    name: @json($selectedVehicle?->sketchfab_model_name ?? $selectedVehicle?->display_name ?? 'Vehicle'),
    make: @json($selectedVehicle?->make ?? ''),
    model: @json($selectedVehicle?->model ?? ''),
    uid: @json($selectedVehicle?->sketchfab_uid),
    updateUrl: @json($selectedVehicle ? route('vehicles.sketchfab-uid', $selectedVehicle) : '')
};

let havenSFApi = null;
let havenSFCurrentKey = 'active-car';
let havenSFMode = '{{ $selectedVehicle?->sketchfab_uid ? "3d" : "2d" }}'; // '3d' | '2d' | 'none'
let isAutoSpinning = false;

// Camera presets for component focus
const SF_CAMERA_PRESETS = {
    default:    { eye: [0, 2.2, 4.6],    target: [0, 0, 0] },
    engine:     { eye: [-2.2, 1.8, 2.5], target: [-1.2, 0.4, 0] },
    powertrain: { eye: [0, 1.2, 3.8],    target: [0, -0.4, 0] },
    battery:    { eye: [1.6, 1.4, 2.8],  target: [0.7, -0.2, 0] },
    rpm:        { eye: [2.1, 0.9, 2.7],  target: [1.4, -0.5, 0] },
};

function initSketchfab(modelKey, modelUid) {
    const iframe  = document.getElementById('sketchfab-frame');
    const loader  = document.getElementById('sketchfab-loader');
    const stage   = document.getElementById('sketchfab-stage');
    if (!iframe || !window.Sketchfab) {
        window.switchTo2D('Sketchfab API not loaded — showing 2D blueprint.');
        return;
    }
    if (!modelUid) {
        window.switchTo2D('No verified 3D model available — showing 2D blueprint.');
        return;
    }
    // Show loader
    if (loader) { loader.classList.remove('is-hidden'); }

    // 1. Explicitly parameterize iframe URL so Sketchfab loads cleanly without header or bottom chrome
    const embedParams = new URLSearchParams({
        autostart: '1',
        transparent: '1',
        ui_controls: '0',
        ui_infos: '0',
        ui_watermark: '0',
        ui_watermark_link: '0',
        ui_help: '0',
        ui_settings: '0',
        ui_inspector: '0',
        ui_hint: '0',
        ui_stop: '0',
        ui_loading: '0',
        ui_annotations: '0',
        annotations_visible: '0',
        annotation_cycle: '0',
        ui_theme: 'dark'
    });
    iframe.src = `https://sketchfab.com/models/${modelUid}/embed?${embedParams.toString()}`;

    // 2. Initialize Sketchfab Viewer API client
    const client = new window.Sketchfab(iframe);
    client.init(modelUid, {
        success: function(api) {
            havenSFApi = api;
            havenSFCurrentKey = modelKey;
            api.start();
            api.addEventListener('viewerready', function() {
                if (loader) loader.classList.add('is-hidden');
                // HIDE all author numbered markers (1, 2, 5, 10, "View 9", etc.)
                if (typeof api.hideAnnotations === 'function') {
                    api.hideAnnotations();
                }
                if (typeof api.setBackground === 'function') {
                    api.setBackground({ color: [0, 0, 0, 0] });
                }
                console.log('[HAVEN AI] 3D Model ready for', HAVEN_ACTIVE_VEHICLE.name, 'UID:', modelUid);
            });
        },
        error: function(err) {
            console.warn('[HAVEN AI] Sketchfab failed to load:', modelUid, err);
            window.switchTo2D('3D model unavailable — showing 2D blueprint.');
        },
        // Clean embedded experience — no Sketchfab UI chrome & transparent background
        autostart: 1,
        transparent: 1, // MUST BE 1 - Transparent background
        ui_controls: 0,
        ui_infos: 0,
        ui_watermark: 0,
        ui_watermark_link: 0,
        ui_help: 0,
        ui_settings: 0,
        ui_inspector: 0,
        ui_hint: 0,
        ui_stop: 0,
        ui_loading: 0,
        ui_annotations: 0,
        annotations_visible: 0, // MUST BE 0 - No author circles!
        annotation_cycle: 0,
        autospin: 0.15,
        camera: 0,
        ui_theme: 'dark'
    });
}

// Mode Switchers
window.switchTo3D = function(uid) {
    const stage     = document.getElementById('sketchfab-stage');
    const car2d     = document.getElementById('car-stage-wrap');
    const noPrev    = document.getElementById('stage-no-preview');
    const badge     = document.getElementById('view-dimension-badge');
    const modelPill = document.getElementById('active-model-name');

    const targetUid = uid || HAVEN_ACTIVE_VEHICLE.uid;
    if (!targetUid) {
        window.switchTo2D('No verified 3D model bound for ' + HAVEN_ACTIVE_VEHICLE.name);
        return;
    }

    if (noPrev)  noPrev.style.display = 'none';
    if (car2d)   car2d.style.display = 'none';
    if (stage)   stage.style.display = '';
    if (badge)   badge.textContent = '3D';
    if (modelPill) modelPill.textContent = HAVEN_ACTIVE_VEHICLE.name;
    havenSFMode = '3d';

    if (!havenSFApi || havenSFCurrentKey !== 'active-car') {
        initSketchfab('active-car', targetUid);
    }
};

window.switchTo2D = function(reason) {
    const stage     = document.getElementById('sketchfab-stage');
    const car2d     = document.getElementById('car-stage-wrap');
    const noPrev    = document.getElementById('stage-no-preview');
    const loader    = document.getElementById('sketchfab-loader');
    const badge     = document.getElementById('view-dimension-badge');
    const modelPill = document.getElementById('active-model-name');

    if (stage)   stage.style.display = 'none';
    if (noPrev)  noPrev.style.display = 'none';
    if (car2d)   car2d.style.display = '';
    if (loader)  loader.classList.add('is-hidden');
    if (badge)   badge.textContent = '2D';
    if (modelPill) modelPill.textContent = '2D Blueprint · ' + HAVEN_ACTIVE_VEHICLE.name;
    havenSFMode = '2d';

    if (reason) console.info('[HAVEN AI]', reason);
};

window.switchToNoPreview = function() {
    const stage     = document.getElementById('sketchfab-stage');
    const car2d     = document.getElementById('car-stage-wrap');
    const noPrev    = document.getElementById('stage-no-preview');
    const loader    = document.getElementById('sketchfab-loader');
    const badge     = document.getElementById('view-dimension-badge');
    const modelPill = document.getElementById('active-model-name');

    if (stage)   stage.style.display = 'none';
    if (car2d)   car2d.style.display = 'none';
    if (loader)  loader.classList.add('is-hidden');
    if (noPrev)  noPrev.style.display = 'flex';
    if (badge)   badge.textContent = 'OFF';
    if (modelPill) modelPill.textContent = 'No Preview';
    havenSFMode = 'none';
};

window.cycleViewMode = function() {
    if (havenSFMode === '3d') {
        window.switchTo2D();
    } else if (havenSFMode === '2d') {
        window.switchToNoPreview();
    } else {
        // From none: go to 3D if UID available, else go to 2D
        if (HAVEN_ACTIVE_VEHICLE.uid && window.Sketchfab) {
            window.switchTo3D(HAVEN_ACTIVE_VEHICLE.uid);
        } else {
            window.switchTo2D();
        }
    }
};
window.toggle3D2D = window.cycleViewMode;

// Restore default correlated 3D model for this vehicle
window.restoreActiveVehicleModel = function() {
    if (HAVEN_ACTIVE_VEHICLE.uid) {
        switch3DModel('active-car', HAVEN_ACTIVE_VEHICLE.uid, HAVEN_ACTIVE_VEHICLE.name);
    } else {
        window.switchTo2D('No verified 3D model for ' + HAVEN_ACTIVE_VEHICLE.name);
    }
};

// Switch model from the model picker
window.switch3DModel = function(key, uid, name) {
    // Update active state
    document.querySelectorAll('.h-diag-model-option').forEach(btn => btn.classList.remove('is-active'));
    const btn = document.getElementById('mopt-' + key);
    if (btn) btn.classList.add('is-active');

    // Update label
    const lbl = document.getElementById('active-model-name');
    if (lbl) lbl.textContent = name;

    // Close menu
    const menu = document.getElementById('model-menu');
    if (menu) menu.style.display = 'none';

    // Ensure 3D stage is visible before re-init
    const stage = document.getElementById('sketchfab-stage');
    const car2d = document.getElementById('car-stage-wrap');
    const noPrev = document.getElementById('stage-no-preview');
    if (stage)  stage.style.display = '';
    if (car2d)  car2d.style.display = 'none';
    if (noPrev) noPrev.style.display = 'none';
    havenSFMode = '3d';
    const badge = document.getElementById('view-dimension-badge');
    if (badge) badge.textContent = '3D';

    // Re-init Sketchfab
    havenSFApi = null;
    havenSFCurrentKey = key;
    initSketchfab(key, uid);
};

// Custom UID prompt — binds permanently to the vehicle
window.promptCustomModel = function() {
    const defaultUid = HAVEN_ACTIVE_VEHICLE.uid;
    const uid = prompt('Enter a 32-character Sketchfab 3D Model UID for ' + HAVEN_ACTIVE_VEHICLE.name + ':', defaultUid);
    if (!uid) return;
    const cleanUid = uid.trim().replace(/^.*\/models\//, '').replace(/\?.*$/, '');
    if (/^[a-f0-9]{32}$/i.test(cleanUid)) {
        const menu = document.getElementById('model-menu');
        if (menu) menu.style.display = 'none';

        // Update locally and in database if vehicle exists
        if (HAVEN_ACTIVE_VEHICLE.id && HAVEN_ACTIVE_VEHICLE.updateUrl) {
            fetch(HAVEN_ACTIVE_VEHICLE.updateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ sketchfab_uid: cleanUid })
            }).then(r => r.json()).then(data => {
                console.log('[HAVEN AI] Custom 3D model saved to vehicle:', data);
            }).catch(e => console.warn('Could not save to DB:', e));
        }

        HAVEN_ACTIVE_VEHICLE.uid = cleanUid;
        switch3DModel('custom', cleanUid, HAVEN_ACTIVE_VEHICLE.name + ' (Custom)');
    } else {
        alert('Invalid Sketchfab UID. Please enter a 32-character hexadecimal UID.\n(Found in the Sketchfab URL: sketchfab.com/3d-models/...-[UID])');
    }
};

// Toggle model picker menu
window.toggleModelMenu = function() {
    const menu = document.getElementById('model-menu');
    if (!menu) return;
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
};

// Focus on a specific component (orbit camera to that area)
window.focusComponent = function(component) {
    if (!havenSFApi) return;
    const preset = SF_CAMERA_PRESETS[component] || SF_CAMERA_PRESETS.default;
    havenSFApi.setCameraLookAt(preset.eye, preset.target, 1.2, function() {});
};

// Zoom using Sketchfab camera FOV or fallback 2D scale
let zoom2d = 1;
window.zoomVehicle = function(f) {
    if (havenSFApi && havenSFMode === '3d') {
        havenSFApi.getCameraLookAt(function(err, camera) {
            if (err || !camera) return;
            const eye    = camera.position;
            const target = camera.target;
            // f > 1 means zoom in -> distance to target decreases
            const factor = f > 1 ? 0.82 : 1.22;
            const dx = (eye[0] - target[0]) * factor;
            const dy = (eye[1] - target[1]) * factor;
            const dz = (eye[2] - target[2]) * factor;
            havenSFApi.setCameraLookAt(
                [target[0] + dx, target[1] + dy, target[2] + dz],
                target, 0.35
            );
        });
    } else {
        // 2D fallback
        zoom2d = Math.min(1.6, Math.max(0.65, zoom2d * f));
        const car = document.getElementById('car-stage-wrap');
        if (car) car.style.transform = `scale(${zoom2d})`;
    }
};

// Reset 3D camera to default view
window.reset3DView = function() {
    if (havenSFApi && havenSFMode === '3d') {
        const preset = SF_CAMERA_PRESETS.default;
        havenSFApi.setCameraLookAt(preset.eye, preset.target, 1.0);
    } else {
        zoom2d = 1;
        const car = document.getElementById('car-stage-wrap');
        if (car) car.style.transform = 'scale(1)';
    }
};

// cycleViewMode is defined above as window.cycleViewMode; toggle3D2D is aliased there too.

// 360 Perspective toggling (3D: orbit; 2D: SVG transform)
let perspIdx = 0;
const persp = [
    {label: '360° view', eye: [0, 2.5, 5],   target: [0, 0, 0],    t: 'rotate(0deg) scale(1)'},
    {label: 'Powertrain', eye: [0, 1.0, 4],   target: [0, -0.5, 0], t: 'rotate(4deg) scale(1.05)'},
    {label: 'Acoustic Bay', eye: [-2.5, 1.8, 3], target: [-1.5, 0.3, 0], t: 'rotate(-3deg) scale(1.04)'},
    {label: 'Undercarriage', eye: [0, -1.5, 4], target: [0, 0, 0],  t: 'rotate(0deg) scale(0.95)'},
];
window.toggleVehiclePerspective = function() {
    perspIdx = (perspIdx + 1) % persp.length;
    const label = document.getElementById('view-mode-label');
    if (label) label.textContent = persp[perspIdx].label;
    if (havenSFApi && havenSFMode === '3d') {
        havenSFApi.setCameraLookAt(persp[perspIdx].eye, persp[perspIdx].target, 1.2);
    } else {
        const car = document.getElementById('car-stage-wrap');
        if (car) car.style.transform = persp[perspIdx].t;
    }
};

// History search filter
window.filterHistory = function(q) {
    const query = (q || '').trim().toLowerCase();
    if (!query) {
        document.querySelectorAll('.diag-hist-item').forEach(row => row.style.display = '');
        return;
    }

    const normalizedQuery = query.replace(/#\s*/g, '').replace(/\s+/g, ' ').trim();
    const sessionQuery = normalizedQuery.match(/^session(?:\s+number)?\s*(\d+)$/);
    const numericQuery = normalizedQuery.match(/^#?(\d+)$/);

    document.querySelectorAll('.diag-hist-item').forEach(row => {
        const haystack = [
            row.dataset.search || '',
            row.textContent.toLowerCase(),
            row.dataset.sessionDate || '',
        ].join(' ').replace(/\s+/g, ' ');
        const normalizedHaystack = haystack.replace(/#\s*/g, '').replace(/\s+/g, ' ');

        let matches = false;
        if (sessionQuery || numericQuery) {
            const sessionId = sessionQuery ? sessionQuery[1] : numericQuery[1];
            matches = String(row.dataset.sessionId || '') === sessionId;
        } else {
            matches = normalizedHaystack.includes(normalizedQuery);
        }

        row.style.display = matches ? '' : 'none';
    });
};

window.focusHistorySearch = function() {
    const input = document.getElementById('hist-search');
    if (!input) return;
    input.focus();
    input.select();
    document.getElementById('history-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    window.filterHistory(input.value);
};

window.openIotHub = function() {
    const modal = document.getElementById('iot-modal');
    if (modal) modal.style.display = 'flex';
};

const historySearch = document.getElementById('hist-search');
if (historySearch) {
    historySearch.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            window.focusHistorySearch();
        }
    });
}

// Inline AI assistant on index
window.sendIdxAi = function() {
    const input = document.getElementById('ai-idx-input');
    const feed = document.getElementById('ai-idx-feed');
    const status = document.getElementById('ai-idx-status');
    const q = input ? input.value.trim() : '';
    if (!q) return;
    input.value = '';
    if (status) status.textContent = 'Thinking...';

    @if($latestDiag)
    fetch('{{ route('diagnostics.ai.ask', $latestDiag) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ question: q })
    })
    .then(r => r.json())
    .then(d => {
        if (feed) {
            const p = document.createElement('p');
            p.style.margin = '0.35rem 0 0';
            p.innerHTML = '<strong style="color:var(--ds-blue);">You:</strong> ' + escHtml(q) + '<br><strong style="color:var(--ds-text);">AI:</strong> ' + escHtml(d.answer || 'Response received.');
            feed.appendChild(p);
            feed.scrollTop = feed.scrollHeight;
        }
        if (status) status.textContent = 'Done · ' + new Date().toLocaleTimeString();
    })
    .catch(() => { if (status) status.textContent = 'Error — try again.'; });
    @else
    if (feed) {
        const p = document.createElement('p');
        p.style.margin = '0.35rem 0 0';
        p.innerHTML = '<strong style="color:var(--ds-blue);">You:</strong> ' + escHtml(q) + '<br><em style="color:var(--ds-text-dim);">Start a diagnostic session first to unlock full AI analysis.</em>';
        feed.appendChild(p);
    }
    if (status) status.textContent = 'No active session';
    @endif
};

window.sendStopSignal = function() {
    const status = document.getElementById('ai-idx-status');
    if (status) status.textContent = 'Stopped';
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
};

function escHtml(t) {
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

// IoT Hub Modal tab switcher
const iotTabs = ['overview','pinout','api','firmware'];
window.switchIotTab = function(tab) {
    iotTabs.forEach(t => {
        const content = document.getElementById('iot-content-' + t);
        const btn     = document.getElementById('iot-tab-' + t);
        if (!content || !btn) return;
        const active = t === tab;
        content.style.display = active ? 'block' : 'none';
        if (active) {
            btn.classList.add('is-active');
        } else {
            btn.classList.remove('is-active');
        }
    });
};

// Keyboard ESC closes IoT modal and menus
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('iot-modal');
        if (modal && modal.style.display !== 'none') {
            modal.style.display = 'none';
        }
        const menu = document.getElementById('vehicle-menu');
        if (menu && menu.style.display !== 'none') {
            menu.style.display = 'none';
        }
        const modelMenu = document.getElementById('model-menu');
        if (modelMenu && modelMenu.style.display !== 'none') {
            modelMenu.style.display = 'none';
        }
    }
});

// ── Auto-initialize Sketchfab on DOMContentLoaded (Bound to Selected Vehicle) ──
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        if (HAVEN_ACTIVE_VEHICLE.uid) {
            // Vehicle has a verified 3D model
            if (window.Sketchfab) {
                initSketchfab('active-car', HAVEN_ACTIVE_VEHICLE.uid);
            } else {
                window.switchTo2D('Sketchfab API unavailable — using 2D blueprint.');
            }
        } else {
            // No verified 3D model for this vehicle — auto-shift to 2D
            window.switchTo2D('No verified 3D asset for ' + HAVEN_ACTIVE_VEHICLE.name + ' — shifted to 2D blueprint.');
        }
    }, 250);
});
</script>

</x-app-layout>
