<x-app-layout title="Executive Diagnostic Studio">

@php
    $selectedVehicle = $vehicles->firstWhere('id', $selectedVehicleId) ?? $vehicles->first();
@endphp

<div class="h-diag-studio-wrapper">

    {{-- ═══════════════════════════════════════════════════════════════
         1. TOP NAVIGATION & VEHICLE BAR
         ═══════════════════════════════════════════════════════════════ --}}
    <header class="h-diag-topbar">
        <div class="h-diag-topbar-left">
            {{-- Vehicle Selector Dropdown --}}
            <div class="h-diag-vehicle-dropdown-wrap">
                <div class="h-diag-vehicle-pill">
                    <div class="h-diag-vehicle-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"></rect>
                            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                            <circle cx="5.5" cy="18.5" r="2.5"></circle>
                            <circle cx="18.5" cy="18.5" r="2.5"></circle>
                        </svg>
                    </div>
                    <select id="vehicle-switcher" class="h-diag-vehicle-select" onchange="switchVehicle(this.value)">
                        @forelse($vehicles as $v)
                            <option value="{{ $v->id }}"
                                    data-name="{{ $v->display_name }}"
                                    data-plate="{{ $v->plate_number ?: 'NO PLATE' }}"
                                    data-engine="{{ $v->engine_type ?: 'Gasoline ICE' }}"
                                    data-year="{{ $v->year }}"
                                    data-key="{{ $v->device_key }}"
                                    data-scope-label="{{ $v->scope_meta['label'] }}"
                                    data-scope-compliant="{{ $v->isScopeCompliant() ? '1' : '0' }}"
                                    {{ $v->id == ($selectedVehicle?->id) ? 'selected' : '' }}>
                                {{ $v->display_name }} ({{ $v->year }})
                            </option>
                        @empty
                            <option value="">No vehicles found</option>
                        @endforelse
                    </select>
                    <svg class="h-diag-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
            </div>

            {{-- 360° Perspective View Switcher Mode --}}
            <button type="button" class="h-diag-mode-pill" id="view-mode-btn" onclick="toggleVehiclePerspective()" title="Toggle View Angle">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/><polyline points="21 3 21 9 15 9"/></svg>
                <span id="view-mode-label">360° Studio View</span>
            </button>
        </div>

        <div class="h-diag-topbar-right">
            {{-- Quick Search & Symptoms --}}
            <div class="h-diag-search-wrap">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="diag-quick-search" placeholder="Search DTC or symptom..." oninput="handleQuickSearch(this.value)">
            </div>

            {{-- Vehicle History Slideout Trigger --}}
            <button type="button" class="h-diag-topbar-btn" onclick="toggleHistoryDrawer()" title="View Diagnostic History">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span class="h-hide-mobile">History</span>
                @if(isset($recentDiagnostics) && $recentDiagnostics->count() > 0)
                    <span class="h-diag-counter-pill">{{ $recentDiagnostics->count() }}</span>
                @endif
            </button>

            {{-- Live Hardware DAQ Ping --}}
            <div class="h-diag-daq-pill" id="daq-status-pill" title="ESP32 Ingestion Node Status">
                <span class="h-diag-live-dot" id="daq-live-dot"></span>
                <span id="daq-status-text">ESP32 STANDBY</span>
            </div>

            {{-- User Profile Avatar --}}
            <div class="h-diag-avatar-wrap">
                <div class="h-diag-avatar">{{ auth()->user()?->name ? strtoupper(substr(auth()->user()->name, 0, 1)) : 'U' }}</div>
            </div>
        </div>
    </header>

    @if($vehicles->isEmpty())
        {{-- Empty state: No vehicle registered --}}
        <div class="h-diag-empty-card">
            <div class="h-diag-empty-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="1.8"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <h2>No Vehicle Registered</h2>
            <p>You must add a gasoline passenger vehicle to your garage before running acoustic & vibration diagnostics.</p>
            <a href="{{ route('vehicles.create') }}" class="h-diag-btn-primary" style="text-decoration:none;display:inline-flex;margin-top:1rem;">
                + Register Vehicle First
            </a>
        </div>
    @else

    {{-- ═══════════════════════════════════════════════════════════════
         2. MAIN DIAGNOSTIC HERO AREA (3D Vehicle + HUD Overlay)
         ═══════════════════════════════════════════════════════════════ --}}
    <section class="h-diag-stage-card">

        {{-- Left HUD: Diagnostic Progress & Condition --}}
        <div class="h-diag-hud-left">
            <div class="h-diag-hud-badge">
                <span class="h-diag-hud-badge-dot"></span>
                <span>SYSTEM DIAGNOSTIC ACTIVE</span>
            </div>

            <h1 class="h-diag-hud-title">DIAGNOSTIC</h1>

            <div class="h-diag-hud-progress-block">
                <div class="h-diag-hud-pct" id="hud-progress-pct">0%</div>
                <div class="h-diag-hud-status-label" id="hud-progress-label">READY FOR ENGINE CAPTURE</div>
            </div>

            {{-- Primary Status Pill & Link --}}
            <div class="h-diag-hud-tag-row">
                <div class="h-diag-status-chip normal" id="hud-condition-chip">
                    <span id="hud-condition-icon">✓</span>
                    <span id="hud-condition-name">ENGINE STANDBY</span>
                </div>
                <button type="button" class="h-diag-ai-report-btn" onclick="openReportModal()" title="View Model Architecture & Thesis Specifications">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    <span>AI Report</span>
                </button>
            </div>

            {{-- Dynamic Mini Step Ticker --}}
            <div class="h-diag-pipeline-ticker" id="diag-pipeline-ticker">
                <div class="h-ticker-item active" id="tick-audio">
                    <span class="h-ticker-bullet"></span>
                    <span class="h-ticker-txt">ACOUSTIC INMP441</span>
                </div>
                <div class="h-ticker-item" id="tick-vib">
                    <span class="h-ticker-bullet"></span>
                    <span class="h-ticker-txt">VIBRATION ADXL345</span>
                </div>
                <div class="h-ticker-item" id="tick-ml">
                    <span class="h-ticker-bullet"></span>
                    <span class="h-ticker-txt">ML CLASSIFIER</span>
                </div>
            </div>

            {{-- Master Trigger CTA (Reference Image: Blue Glowing Button) --}}
            <div class="h-diag-hud-cta-wrap">
                <button type="button" id="btn-master-action" class="h-diag-cta-btn" onclick="triggerPrimaryAction()">
                    <span class="h-diag-cta-glow"></span>
                    <span class="h-diag-cta-icon" id="master-btn-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
                    </span>
                    <span id="master-btn-text">Start Live Diagnostic</span>
                    <span class="h-diag-cta-spinner" id="master-btn-spinner" style="display:none;"></span>
                </button>

                <div class="h-diag-sub-actions">
                    <button type="button" class="h-diag-sub-btn" onclick="openSampleUploadModal()">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Upload WAV / CSV
                    </button>
                    <button type="button" class="h-diag-sub-btn" onclick="openManualTelemetryDrawer()">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20v-6M6 20V10M18 20V4"/></svg>
                        Manual DAQ
                    </button>
                </div>
            </div>
        </div>

        {{-- Center/Right: 3D Isometric Automotive Visualization with Glowing Markers --}}
        <div class="h-diag-vehicle-canvas-area" id="vehicle-viewport">

            {{-- 3D Ground Plane Radial Grid --}}
            <div class="h-diag-grid-plane"></div>

            {{-- High-Precision 3D Automotive Vector Render --}}
            <div class="h-diag-car-stage" id="car-3d-model">
                <svg viewBox="0 0 740 380" class="h-diag-car-svg" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        {{-- Gradients for Sleek White/Silver Luxury Body --}}
                        <linearGradient id="bodySilverGrad" x1="0%" y1="0%" x2="100%" y2="80%">
                            <stop offset="0%" stop-color="#ffffff"/>
                            <stop offset="35%" stop-color="#f1f5f9"/>
                            <stop offset="70%" stop-color="#e2e8f0"/>
                            <stop offset="100%" stop-color="#cbd5e1"/>
                        </linearGradient>
                        <linearGradient id="hoodGlowGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="rgba(2, 132, 199, 0.45)"/>
                            <stop offset="50%" stop-color="rgba(56, 189, 248, 0.25)"/>
                            <stop offset="100%" stop-color="rgba(2, 132, 199, 0.05)"/>
                        </linearGradient>
                        <linearGradient id="glassGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#1e293b"/>
                            <stop offset="50%" stop-color="#0f172a"/>
                            <stop offset="100%" stop-color="#0284c7" stop-opacity="0.3"/>
                        </linearGradient>
                        <radialGradient id="wheelGlow" cx="50%" cy="50%" r="50%">
                            <stop offset="0%" stop-color="#f97316" stop-opacity="0.8"/>
                            <stop offset="70%" stop-color="#ea580c" stop-opacity="0.2"/>
                            <stop offset="100%" stop-color="transparent"/>
                        </radialGradient>
                        <filter id="softShadow" x="-20%" y="-20%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="28" stdDeviation="22" flood-color="#0f172a" flood-opacity="0.14"/>
                        </filter>
                    </defs>

                    {{-- Ground Shadow --}}
                    <ellipse cx="370" cy="330" rx="310" ry="32" fill="#0f172a" opacity="0.12" filter="url(#softShadow)"/>

                    {{-- Main Car Silhouette (Isometric Perspective) --}}
                    <g transform="translate(40, 20)">
                        {{-- Lower Chassis Body --}}
                        <path d="M 120 250 C 140 250, 180 270, 260 270 L 440 270 C 490 270, 520 250, 590 240 C 620 235, 640 220, 650 200 C 655 190, 650 175, 630 165 C 590 145, 520 120, 470 100 C 400 70, 310 68, 230 85 C 170 98, 120 125, 90 160 C 70 185, 75 210, 85 225 Z"
                              fill="url(#bodySilverGrad)" stroke="#94a3b8" stroke-width="1.5"/>

                        {{-- Active Engine Bay Holographic Zone (Hood Inspection Area) --}}
                        <path id="hood-holo-zone" class="h-diag-holo-hood"
                              d="M 95 165 C 120 135, 170 110, 230 95 C 270 125, 290 170, 270 215 C 200 230, 130 220, 95 165 Z"
                              fill="url(#hoodGlowGrad)" stroke="#0284c7" stroke-dasharray="3 3" stroke-width="1.5"/>

                        {{-- Aerodynamic Windshield & Greenhouse Glass --}}
                        <path d="M 235 90 C 310 75, 395 76, 465 102 C 430 140, 390 155, 275 145 C 255 125, 245 105, 235 90 Z"
                              fill="url(#glassGrad)" stroke="#475569" stroke-width="1.2"/>
                        <path d="M 285 145 C 395 155, 435 140, 470 105 L 530 125 C 570 145, 595 165, 605 175 L 480 185 Z"
                              fill="#0f172a" opacity="0.85"/>

                        {{-- Front Wheel (Alloy Rim) --}}
                        <g transform="translate(160, 250)">
                            <ellipse cx="0" cy="0" rx="42" ry="48" fill="#1e293b" stroke="#64748b" stroke-width="2"/>
                            <ellipse cx="0" cy="0" rx="30" ry="34" fill="#0f172a"/>
                            <circle cx="0" cy="0" r="14" fill="#94a3b8"/>
                            <line x1="-22" y1="0" x2="22" y2="0" stroke="#cbd5e1" stroke-width="3"/>
                            <line x1="0" y1="-26" x2="0" y2="26" stroke="#cbd5e1" stroke-width="3"/>
                        </g>

                        {{-- Rear Wheel (Alloy Rim with Telemetry Brake Heat Glow) --}}
                        <g transform="translate(510, 230)">
                            <ellipse cx="0" cy="0" rx="46" ry="52" fill="#1e293b" stroke="#f97316" stroke-width="2.5"/>
                            <circle cx="0" cy="0" r="40" fill="url(#wheelGlow)"/>
                            <ellipse cx="0" cy="0" rx="32" ry="36" fill="#0f172a"/>
                            <circle cx="0" cy="0" r="15" fill="#f97316"/>
                            <line x1="-24" y1="0" x2="24" y2="0" stroke="#f97316" stroke-width="3"/>
                            <line x1="0" y1="-28" x2="0" y2="28" stroke="#f97316" stroke-width="3"/>
                        </g>

                        {{-- Distinctive Side Crease & Aerodynamic Panels --}}
                        <path d="M 160 210 Q 320 220 530 190" stroke="#94a3b8" stroke-width="2" fill="none"/>
                        <path d="M 190 230 Q 340 240 480 235" stroke="#cbd5e1" stroke-width="1.5" fill="none"/>

                        {{-- Headlight & Tail Reflector Accents --}}
                        <polygon points="85,190 110,180 100,205 82,205" fill="#38bdf8" opacity="0.9"/>
                        <polygon points="635,175 648,185 640,195 628,185" fill="#ef4444" opacity="0.85"/>
                    </g>
                </svg>

                {{-- ═══════════════════════════════════════════════════════════════
                     INTERACTIVE TELEMETRY PINS OVERLAY
                     ═══════════════════════════════════════════════════════════════ --}}
                {{-- Pin 1: INMP441 Acoustic Mic on Hood --}}
                <div class="h-diag-pin hood-pin active" style="top:32%; left:22%;" onclick="focusPin('audio')" title="INMP441 Acoustic MEMS Sensor (Click to focus recording)">
                    <div class="h-diag-pin-beacon"></div>
                    <div class="h-diag-pin-core blue">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>INMP441 Acoustic Mic</strong>
                        <span>I2S 44.1 kHz · Cam/Valve Tap</span>
                    </div>
                </div>

                {{-- Pin 2: ADXL345 / MPU6050 Vibration Sensor on Powertrain --}}
                <div class="h-diag-pin engine-pin" style="top:52%; left:36%;" onclick="focusPin('vibration')" title="ADXL345 Vibration Accelerometer (Click to view vibration)">
                    <div class="h-diag-pin-beacon orange"></div>
                    <div class="h-diag-pin-core orange">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>ADXL345 Accelerometer</strong>
                        <span>I2C 0x53 · Peak &amp; RMS G</span>
                    </div>
                </div>

                {{-- Pin 3: INA219 Power & Battery Bus --}}
                <div class="h-diag-pin battery-pin" style="top:62%; left:62%;" onclick="focusPin('battery')" title="INA219 Voltage Sensor">
                    <div class="h-diag-pin-core dark">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><rect x="1" y="6" width="18" height="12" rx="2"/><line x1="23" y1="10" x2="23" y2="14"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>INA219 Power Monitor</strong>
                        <span>13.8V Bus · Alternator Charging</span>
                    </div>
                </div>

                {{-- Pin 4: Hall Tachometer RPM (Contextual) --}}
                <div class="h-diag-pin rpm-pin" style="top:44%; left:78%;" onclick="focusPin('rpm')" title="Hall RPM Tachometer Sensor">
                    <div class="h-diag-pin-core green">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>Hall Tachometer</strong>
                        <span>Contextual RPM Speed Indicator</span>
                    </div>
                </div>
            </div>

            {{-- Zoom / View Toggle Buttons (Reference Image Top-Right of Car) --}}
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
                <span class="h-diag-card-status-badge ok" id="sensor-bus-badge">I2C / I2S BUS ACTIVE</span>
            </div>

            <div class="h-diag-probes-container">
                {{-- Probe 1: Acoustic INMP441 --}}
                <div class="h-diag-probe-item">
                    <div class="h-diag-probe-pillar">
                        <div class="h-diag-probe-bar blue" style="height: 68%;"></div>
                        <div class="h-diag-probe-cap"></div>
                    </div>
                    <div class="h-diag-probe-name">Acoustics</div>
                    <div class="h-diag-probe-val" id="probe-audio-val">44.1 kHz</div>
                </div>

                {{-- Probe 2: Vibration ADXL345 --}}
                <div class="h-diag-probe-item">
                    <div class="h-diag-probe-pillar">
                        <div class="h-diag-probe-bar blue" style="height: 54%;"></div>
                        <div class="h-diag-probe-cap"></div>
                    </div>
                    <div class="h-diag-probe-name">Vibration</div>
                    <div class="h-diag-probe-val" id="probe-vib-val">1.24 g</div>
                </div>

                {{-- Probe 3: Temperature DS18B20 (Contextual) --}}
                <div class="h-diag-probe-item">
                    <div class="h-diag-probe-pillar">
                        <div class="h-diag-probe-bar orange" style="height: 78%;"></div>
                        <div class="h-diag-probe-cap orange"></div>
                    </div>
                    <div class="h-diag-probe-name" style="color:#ea580c;">Coolant</div>
                    <div class="h-diag-probe-val" id="probe-temp-val">88.5°C</div>
                </div>

                {{-- Probe 4: Hall Tachometer (Contextual) --}}
                <div class="h-diag-probe-item">
                    <div class="h-diag-probe-pillar">
                        <div class="h-diag-probe-bar blue" style="height: 48%;"></div>
                        <div class="h-diag-probe-cap"></div>
                    </div>
                    <div class="h-diag-probe-name">Tach</div>
                    <div class="h-diag-probe-val" id="probe-rpm-val">850 RPM</div>
                </div>

                {{-- Probe 5: Bus Voltage INA219 --}}
                <div class="h-diag-probe-item">
                    <div class="h-diag-probe-pillar">
                        <div class="h-diag-probe-bar blue" style="height: 60%;"></div>
                        <div class="h-diag-probe-cap"></div>
                    </div>
                    <div class="h-diag-probe-name">Voltage</div>
                    <div class="h-diag-probe-val" id="probe-volt-val">13.8 V</div>
                </div>
            </div>

            {{-- Card Footer Specs --}}
            <div class="h-diag-card-footer-metrics">
                <div class="h-diag-footer-metric">
                    <span class="lbl">Sample Rate</span>
                    <span class="val" id="daq-rate-val">50 Hz</span>
                </div>
                <div class="h-diag-footer-metric">
                    <span class="lbl">Continuous RMS</span>
                    <span class="val" id="daq-rms-val">0.38 g</span>
                </div>
                <div class="h-diag-footer-metric">
                    <span class="lbl">Thesis Scope</span>
                    <span class="val" style="color:var(--h-teal);" id="vehicle-scope-txt">2015+ Gas ICE</span>
                </div>
            </div>
        </div>

        {{-- CARD 2: HARDWARE DAQ BENCH (Dark Contrast Card from Reference) --}}
        <div class="h-diag-card dark-mode-card">
            <div class="h-diag-card-head" style="border-color: rgba(255,255,255,0.1);">
                <span class="h-diag-card-title" style="color:#fff;">POWERTRAIN DAQ</span>
                <span class="h-diag-daq-badge-live">94% SENSOR HEALTH</span>
            </div>

            <div class="h-diag-dark-card-body">
                <div class="h-diag-dark-left">
                    <div class="h-diag-dark-big-val" id="dark-peak-val">1.24 <span style="font-size:1rem;color:#94a3b8;">g</span></div>
                    <div class="h-diag-dark-sub">Peak Mechanical Impact Force</div>
                    <div class="h-diag-dark-chip" id="dark-status-chip">
                        <span class="dot"></span>
                        <span id="dark-status-txt">Harmonic Baseline Normal</span>
                    </div>
                </div>
                <div class="h-diag-dark-illustration">
                    {{-- Stylized Translucent Engine Block Wireframe --}}
                    <svg width="120" height="90" viewBox="0 0 120 90" fill="none">
                        <rect x="15" y="20" width="90" height="55" rx="8" fill="#0284c7" fill-opacity="0.12" stroke="#38bdf8" stroke-width="1.5"/>
                        <circle cx="35" cy="45" r="12" stroke="#38bdf8" stroke-width="1.5" stroke-dasharray="2 2"/>
                        <circle cx="60" cy="45" r="12" stroke="#38bdf8" stroke-width="1.5" stroke-dasharray="2 2"/>
                        <circle cx="85" cy="45" r="12" stroke="#38bdf8" stroke-width="1.5" stroke-dasharray="2 2"/>
                        <line x1="20" y1="20" x2="100" y2="20" stroke="#0ea5e9" stroke-width="3"/>
                        <path d="M40 75 L40 85 M80 75 L80 85" stroke="#64748b" stroke-width="2"/>
                    </svg>
                </div>
            </div>

            <div class="h-diag-dark-footer">
                <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg> Wi-Fi Node: ESP32-5SENSOR</span>
                <span id="dark-last-ping">Live Stream</span>
            </div>
        </div>

        {{-- CARD 3: CONTEXTUAL GAUGES (Speedometer Arc Voltage & Tachometer Wave) --}}
        <div class="h-diag-card">
            <div class="h-diag-card-head">
                <span class="h-diag-card-title">CONTEXTUAL INDICATORS</span>
                <span class="h-diag-card-subinfo">(Non-ML Scope)</span>
            </div>

            <div class="h-diag-gauges-row">
                {{-- Voltage Arc Speedometer --}}
                <div class="h-diag-gauge-cell">
                    <div class="h-diag-gauge-top">
                        <span class="name">VOLTAGE</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </div>
                    <div class="h-diag-arc-wrap">
                        <svg width="110" height="60" viewBox="0 0 110 60">
                            <path d="M 15 50 A 40 40 0 0 1 95 50" fill="none" stroke="#e2e8f0" stroke-width="8" stroke-linecap="round"/>
                            <path id="voltage-arc-path" d="M 15 50 A 40 40 0 0 1 95 50" fill="none" stroke="#0284c7" stroke-width="8" stroke-linecap="round" stroke-dasharray="125" stroke-dashoffset="35"/>
                        </svg>
                        <div class="h-diag-arc-val" id="gauge-voltage-num">13.8</div>
                    </div>
                    <div class="h-diag-gauge-lbl">Alternator DC Output</div>
                </div>

                {{-- RPM Sine Wave Tachometer --}}
                <div class="h-diag-gauge-cell">
                    <div class="h-diag-gauge-top">
                        <span class="name">TACHOMETER</span>
                        <span class="unit">RPM</span>
                    </div>
                    <div class="h-diag-rpm-wave-wrap">
                        <div class="h-diag-rpm-display" id="gauge-rpm-num">850</div>
                        <svg width="100" height="34" viewBox="0 0 100 34" fill="none" class="h-diag-sine-svg">
                            <path d="M 0 17 Q 15 0, 30 17 T 60 17 T 90 17 T 120 17" fill="none" stroke="#0ea5e9" stroke-width="2.5"/>
                            <circle cx="60" cy="17" r="4" fill="#0284c7"/>
                        </svg>
                    </div>
                    <div class="h-diag-gauge-lbl" id="gauge-rpm-mode">Idling (750–900)</div>
                </div>
            </div>

            {{-- Mode Switcher Pill inside Card --}}
            <div class="h-diag-mode-selector-strip">
                <button type="button" class="h-mode-btn active" id="btn-mode-idle" onclick="setRecordingMode('idle')">
                    Baseline Idle (800 RPM)
                </button>
                <button type="button" class="h-mode-btn" id="btn-mode-revving" onclick="setRecordingMode('revving')">
                    Baseline Revving (2,200 RPM)
                </button>
            </div>
        </div>

        {{-- CARD 4: HAVEN AI COPILOT & ASSISTANT (Groq Live) --}}
        <div class="h-diag-card ai-assistant-card">
            <div class="h-diag-card-head">
                <div style="display:flex;align-items:center;gap:0.45rem;">
                    <div class="h-diag-ai-avatar">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </div>
                    <span class="h-diag-card-title">HAVEN AI ASSISTANT</span>
                </div>
                <span class="h-diag-card-status-badge ok">Groq 120B Connected</span>
            </div>

            <div class="h-diag-ai-chat-body" id="card-ai-thread">
                <div class="h-diag-ai-msg bot">
                    <p id="card-ai-greeting">
                        Hi <strong>{{ auth()->user()?->name ?? 'User' }}</strong>! I am your HAVEN AI Automotive Specialist.
                        Select your vehicle above, start live acoustic &amp; vibration capture, and I will cross-reference signals against our four approved capstone fault classes.
                    </p>
                </div>
            </div>

            <div class="h-diag-ai-card-input-row">
                <input type="text" id="card-ai-input" placeholder="Ask a diagnostic question..." onkeydown="if(event.key==='Enter') submitCardAiQuery();">
                <button type="button" class="h-diag-ai-send-btn" onclick="submitCardAiQuery()" title="Send">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </div>

            <div class="h-diag-ai-quick-tags">
                <button type="button" class="tag" onclick="quickAiAsk('What is the difference between lifter tick and rod knock?')">Knock vs Tick</button>
                <button type="button" class="tag" onclick="quickAiAsk('Can I drive 10 miles with misfire?')">Misfire Safety</button>
                <button type="button" class="tag" onclick="quickAiAsk('Explain Crest Factor')">Crest Factor</button>
            </div>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         4. RECORDING CONTROLS & LIVE OSCILLOSCOPE WORKBENCH
         ═══════════════════════════════════════════════════════════════ --}}
    <section class="h-diag-workbench-card" id="recording-workbench">
        <div class="h-diag-workbench-head">
            <div style="display:flex;align-items:center;gap:0.6rem;">
                <div class="h-diag-workbench-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                </div>
                <div>
                    <h2 class="h-diag-workbench-title">Live Acoustic &amp; Vibration Ingestion Workbench</h2>
                    <p class="h-diag-workbench-sub">Dual-tier Web Audio API capture synchronized with ESP32 acceleration data</p>
                </div>
            </div>

            <div class="h-diag-workbench-timer-row">
                <span class="h-diag-rec-dot" id="live-rec-dot"></span>
                <span class="h-diag-rec-timer" id="live-rec-timer">00:00</span>
                <span class="h-diag-rec-target">/ 00:15 target</span>
            </div>
        </div>

        {{-- Live Canvas Oscilloscope --}}
        <div class="h-diag-oscilloscope-wrap">
            <canvas id="live-oscilloscope" width="1000" height="90" class="h-diag-canvas"></canvas>
            <div class="h-diag-canvas-overlay" id="canvas-overlay-text">
                Click <strong>"Start Live Recording"</strong> to stream microphone harmonics
            </div>
        </div>

        {{-- Workbench Control Actions --}}
        <div class="h-diag-workbench-actions">
            <div class="h-diag-actions-left">
                <button type="button" id="btn-start-rec" class="h-diag-btn-primary" onclick="startAudioRecording()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                    Start Audio Capture
                </button>
                <button type="button" id="btn-stop-rec" class="h-diag-btn-danger" style="display:none;" onclick="stopAudioRecording()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                    Stop Recording
                </button>
                <button type="button" id="btn-open-file-modal" class="h-diag-btn-secondary" onclick="openSampleUploadModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Upload Offline Sample
                </button>
            </div>

            {{-- Audio Playback Strip (Visible after recording) --}}
            <div class="h-diag-playback-strip" id="playback-strip" style="display:none;">
                <audio id="workbench-audio-playback" controls style="height:34px;"></audio>
                <span class="h-diag-playback-meta" id="playback-duration-meta">15.2s WebM Captured</span>
            </div>

            <div class="h-diag-actions-right">
                <button type="button" id="btn-submit-session" class="h-diag-btn-primary submit" onclick="submitDiagnosticForm()" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    Analyze Diagnostic
                </button>
            </div>
        </div>

    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         5. AI ANALYSIS STAGES PIPELINE (6-Stage Visual Stepper)
         ═══════════════════════════════════════════════════════════════ --}}
    <section class="h-diag-pipeline-card" id="analysis-pipeline-card">
        <h3 class="h-diag-pipeline-head">AI DUAL-TIER INFERENCE PIPELINE</h3>
        <div class="h-diag-stepper">
            {{-- Stage 1 --}}
            <div class="h-stage-node" id="stage-1">
                <div class="h-stage-circle">1</div>
                <div class="h-stage-label">Telemetry Ingested</div>
                <div class="h-stage-desc">Audio &amp; Vibration Sync</div>
            </div>
            <div class="h-stage-line" id="line-1"></div>

            {{-- Stage 2 --}}
            <div class="h-stage-node" id="stage-2">
                <div class="h-stage-circle">2</div>
                <div class="h-stage-label">Signal Preprocessing</div>
                <div class="h-stage-desc">Hamming Windowing &amp; FFT</div>
            </div>
            <div class="h-stage-line" id="line-2"></div>

            {{-- Stage 3 --}}
            <div class="h-stage-node" id="stage-3">
                <div class="h-stage-circle">3</div>
                <div class="h-stage-label">Audio Feature Ext.</div>
                <div class="h-stage-desc">13 MFCCs, ZCR, Centroid</div>
            </div>
            <div class="h-stage-line" id="line-3"></div>

            {{-- Stage 4 --}}
            <div class="h-stage-node" id="stage-4">
                <div class="h-stage-circle">4</div>
                <div class="h-stage-label">Vibration Feature Ext.</div>
                <div class="h-stage-desc">Peak G, RMS G, Crest Factor</div>
            </div>
            <div class="h-stage-line" id="line-4"></div>

            {{-- Stage 5 --}}
            <div class="h-stage-node" id="stage-5">
                <div class="h-stage-circle">5</div>
                <div class="h-stage-label">ML Classification</div>
                <div class="h-stage-desc">FastAPI Random Forest</div>
            </div>
            <div class="h-stage-line" id="line-5"></div>

            {{-- Stage 6 --}}
            <div class="h-stage-node" id="stage-6">
                <div class="h-stage-circle">6</div>
                <div class="h-stage-label">Diagnostic Result</div>
                <div class="h-stage-desc">SAE DTC &amp; Recommendations</div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         6. DIAGNOSTIC RESULTS DISPLAY (Hidden until analyzed)
         ═══════════════════════════════════════════════════════════════ --}}
    <section class="h-diag-results-card" id="results-display-area" style="display:none;">
        <div class="h-diag-results-banner" id="results-banner">
            <div class="h-diag-results-main">
                <span class="h-diag-results-eyebrow">PREDICTED ENGINE CONDITION</span>
                <h2 class="h-diag-results-title" id="res-condition-title">Normal Engine Operation</h2>
                <p class="h-diag-results-sub" id="res-condition-sub">Baseline harmonics detected within calibrated threshold.</p>
            </div>
            <div class="h-diag-results-confidence">
                <div class="h-diag-conf-num" id="res-confidence-pct">--%</div>
                <div class="h-diag-conf-lbl">Model Confidence</div>
                <div class="h-diag-conf-bar-bg">
                    <div class="h-diag-conf-bar-fill" id="res-confidence-bar" style="width:0%;"></div>
                </div>
            </div>
        </div>

        <div class="h-diag-results-grid">
            <div class="h-diag-res-box">
                <h4><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Probable Root Causes</h4>
                <ul id="res-causes-list">
                    <li>Evaluating mechanical clearance across cylinders.</li>
                </ul>
            </div>
            <div class="h-diag-res-box">
                <h4><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg> Priority Inspection Steps</h4>
                <ul id="res-steps-list">
                    <li>Check feeler gauge lash specifications against manufacturer service manual.</li>
                </ul>
            </div>
        </div>

        <div class="h-diag-results-footer">
            <a href="#" id="res-report-link" target="_blank" class="h-diag-btn-primary" style="text-decoration:none;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Open Full Inspection Report
            </a>
            <button type="button" class="h-diag-btn-secondary" onclick="resetStudio()">
                New Diagnostic Session
            </button>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════
         7. DIAGNOSTIC HISTORY STRIP
         ═══════════════════════════════════════════════════════════════ --}}
    <section class="h-diag-history-card">
        <div class="h-diag-history-head">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span class="h-diag-history-title">Recent Vehicle Diagnostic Sessions</span>
            </div>
            <a href="{{ route('diagnostics.index') }}" class="h-diag-history-viewall">View All Sessions &rarr;</a>
        </div>

        <div class="h-diag-history-grid">
            @forelse($recentDiagnostics as $d)
                @php
                    $isComp = $d->status === 'completed';
                    $cond = $d->ml_condition ? ucwords(str_replace('_',' ',$d->ml_condition)) : 'Pending';
                    $badgeClass = match($d->ml_condition) {
                        'normal' => 'badge-normal',
                        'valve_clearance' => 'badge-danger',
                        'misfire' => 'badge-warn',
                        'loose_component' => 'badge-warn',
                        default => 'badge-neutral',
                    };
                @endphp
                <div class="h-diag-history-item" onclick="window.location='{{ route('diagnostics.show', $d) }}'">
                    <div class="h-hist-head">
                        <span class="h-hist-id">Session #{{ $d->id }}</span>
                        <span class="h-hist-badge {{ $badgeClass }}">{{ $cond }}</span>
                    </div>
                    <div class="h-hist-car">{{ $d->vehicle?->display_name ?? 'Vehicle' }}</div>
                    <div class="h-hist-meta">
                        <span>{{ $d->created_at->format('M j, Y') }}</span>
                        <span>{{ $d->confidence_percent ?? $d->status_label }}</span>
                    </div>
                </div>
            @empty
                <div class="h-diag-history-empty">
                    No diagnostic sessions recorded yet. Start your first session above!
                </div>
            @endforelse
        </div>
    </section>

    @endif {{-- End vehicles check --}}

</div> {{-- End wrapper --}}


{{-- ═══════════════════════════════════════════════════════════════
     HIDDEN FORM FOR ACTUAL BACKEND SUBMISSION
     ═══════════════════════════════════════════════════════════════ --}}
<form id="haven-hidden-diag-form" method="POST" action="{{ route('diagnostics.store') }}" enctype="multipart/form-data" style="display:none;">
    @csrf
    <input type="hidden" name="vehicle_id" id="form_vehicle_id" value="{{ $selectedVehicle?->id }}">
    <input type="hidden" name="recording_mode" id="form_recording_mode" value="idle">
    <input type="hidden" name="notes" id="form_notes" value="Live Diagnostic Studio Session">
    <input type="file" name="audio_blob" id="form_audio_blob">
    <input type="file" name="audio_file" id="form_audio_file">
    <input type="file" name="vibration_file" id="form_vibration_file">
    <input type="hidden" name="audio_duration" id="form_audio_duration">
    <input type="hidden" name="temperature_c" id="form_temperature_c">
    <input type="hidden" name="rpm" id="form_rpm">
    <input type="hidden" name="voltage_v" id="form_voltage_v">
    <input type="hidden" name="current_ma" id="form_current_ma">
</form>

{{-- ═══════════════════════════════════════════════════════════════
     MODAL: SAMPLE FILE UPLOAD (Offline WAV / Vibration CSV)
     ═══════════════════════════════════════════════════════════════ --}}
<div id="modal-sample-upload" class="h-diag-modal-backdrop" style="display:none;" onclick="if(event.target===this) closeSampleUploadModal()">
    <div class="h-diag-modal-box">
        <div class="h-diag-modal-header">
            <h3>Upload Offline Sample Telemetry</h3>
            <button type="button" class="h-diag-modal-close" onclick="closeSampleUploadModal()">&times;</button>
        </div>
        <div class="h-diag-modal-body">
            <p style="font-size:0.82rem;color:#64748b;margin-bottom:1.25rem;">
                Select test audio (.wav, .mp3, .webm) and ESP32 accelerometer (.csv, .json) to evaluate the ML model offline.
            </p>
            <div class="h-diag-modal-group">
                <label>Engine Acoustic Audio (WAV/MP3)</label>
                <input type="file" id="modal_audio_input" accept=".wav,.mp3,.webm,.ogg,.m4a" class="h-diag-file-input">
            </div>
            <div class="h-diag-modal-group" style="margin-top:1rem;">
                <label>Vibration Accelerometer Data (CSV/JSON)</label>
                <input type="file" id="modal_vib_input" accept=".csv,.json,.txt" class="h-diag-file-input" onchange="previewModalVibration(this)">
                <div id="modal-vib-summary" style="margin-top:0.5rem;font-size:0.75rem;color:#0284c7;display:none;"></div>
            </div>
        </div>
        <div class="h-diag-modal-footer">
            <button type="button" class="h-diag-btn-secondary" onclick="closeSampleUploadModal()">Cancel</button>
            <button type="button" class="h-diag-btn-primary" onclick="applySampleUploads()">Attach to Session</button>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     DRAWER: MANUAL TELEMETRY INPUT (Contextual DAQ)
     ═══════════════════════════════════════════════════════════════ --}}
<div id="drawer-manual-telemetry" class="h-diag-modal-backdrop" style="display:none;" onclick="if(event.target===this) closeManualTelemetryDrawer()">
    <div class="h-diag-modal-box">
        <div class="h-diag-modal-header">
            <h3>Manual Hardware Telemetry (Contextual)</h3>
            <button type="button" class="h-diag-modal-close" onclick="closeManualTelemetryDrawer()">&times;</button>
        </div>
        <div class="h-diag-modal-body">
            <p style="font-size:0.8rem;color:#64748b;margin-bottom:1rem;">
                Adjust contextual sensors if testing without physical ESP32 Wi-Fi hardware.
                <em>Note: RPM and Temperature serve as contextual indicators per thesis scope.</em>
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.85rem;">
                <div>
                    <label style="font-size:0.75rem;font-weight:600;color:#334155;">Coolant Temp (°C · DS18B20)</label>
                    <input type="number" step="0.5" id="man_temp" value="88.5" class="h-diag-file-input">
                </div>
                <div>
                    <label style="font-size:0.75rem;font-weight:600;color:#334155;">Tachometer RPM (Hall)</label>
                    <input type="number" id="man_rpm" value="850" class="h-diag-file-input">
                </div>
                <div>
                    <label style="font-size:0.75rem;font-weight:600;color:#334155;">DC Bus Voltage (V · INA219)</label>
                    <input type="number" step="0.1" id="man_volt" value="13.8" class="h-diag-file-input">
                </div>
                <div>
                    <label style="font-size:0.75rem;font-weight:600;color:#334155;">Current Draw (mA)</label>
                    <input type="number" step="10" id="man_curr" value="420" class="h-diag-file-input">
                </div>
            </div>
        </div>
        <div class="h-diag-modal-footer">
            <button type="button" class="h-diag-btn-primary" onclick="saveManualTelemetry()">Apply to Gauges</button>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     STYLES: PREMIUM AUTOMOTIVE DIAGNOSTIC STUDIO (TESLA INSPIRED)
     ═══════════════════════════════════════════════════════════════ --}}
<style>
/* ── Reset & Core Theme Variables (Theme-Adaptive System: Dark & Light Mode) ── */
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
    --ds-shadow-hover: 0 16px 40px -8px rgba(2, 132, 199, 0.08);
    --ds-input-bg: #ffffff;
    --ds-input-border: #e2e8f0;
    --ds-pill-bg: #f8fafc;
    --ds-pill-hover: #eff6ff;
    --ds-modal-bg: #ffffff;
    --ds-modal-overlay: rgba(15, 23, 42, 0.65);
    --ds-radius-card: 20px;
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
    --ds-shadow-hover: 0 16px 40px -8px rgba(56, 189, 248, 0.15);
    --ds-input-bg: #131a27;
    --ds-input-border: #2e3d55;
    --ds-pill-bg: #131a27;
    --ds-pill-hover: #1e293b;
    --ds-modal-bg: #0d111a;
    --ds-modal-overlay: rgba(0, 0, 0, 0.8);
    --ds-radius-card: 20px;
    --ds-radius-pill: 9999px;
}

.h-diag-studio-wrapper {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: var(--ds-text);
    padding-bottom: 3rem;
}

/* ── 1. Top Navigation Bar ── */
.h-diag-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    padding: 0.65rem 1rem;
    border-radius: var(--ds-radius-pill);
    box-shadow: var(--ds-shadow-card);
}
.h-diag-topbar-left, .h-diag-topbar-right {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}
.h-diag-vehicle-pill {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    padding: 0.35rem 0.85rem;
    border-radius: var(--ds-radius-pill);
    position: relative;
}
.h-diag-vehicle-icon {
    color: var(--ds-blue);
    display: flex;
    align-items: center;
}
.h-diag-vehicle-select {
    border: none;
    background: transparent;
    font-weight: 700;
    font-size: 0.86rem;
    color: var(--ds-text);
    outline: none;
    cursor: pointer;
    padding-right: 0.5rem;
    -webkit-appearance: none;
}
.h-diag-chevron {
    color: var(--ds-text-muted);
    pointer-events: none;
}
.h-diag-mode-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    padding: 0.45rem 0.85rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--ds-text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}
.h-diag-mode-pill:hover {
    border-color: var(--ds-blue);
    color: var(--ds-blue);
}
.h-diag-search-wrap {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    padding: 0.35rem 0.85rem;
    border-radius: var(--ds-radius-pill);
    color: var(--ds-text-muted);
}
.h-diag-search-wrap input {
    border: none;
    background: transparent;
    outline: none;
    font-size: 0.78rem;
    color: var(--ds-text);
    width: 140px;
}
.h-diag-topbar-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    padding: 0.4rem 0.75rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--ds-text);
    cursor: pointer;
    transition: all 0.2s ease;
}
.h-diag-topbar-btn:hover {
    background: var(--ds-surface-subtle);
    border-color: var(--ds-blue);
}
.h-diag-counter-pill {
    background: var(--ds-blue-light);
    color: var(--ds-blue);
    font-size: 0.65rem;
    font-weight: 800;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
}
.h-diag-daq-pill {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    padding: 0.35rem 0.75rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.7rem;
    font-weight: 700;
    color: #166534;
}
.h-diag-live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 8px #22c55e;
    animation: pulseDot 2s infinite ease-in-out;
}
@keyframes pulseDot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
.h-diag-avatar-wrap {
    display: flex;
    align-items: center;
}
.h-diag-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0284c7, #38bdf8);
    color: #fff;
    font-weight: 800;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(2, 132, 199, 0.3);
}

/* ── 2. Main Diagnostic Stage (Hero) ── */
.h-diag-stage-card {
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-card);
    box-shadow: var(--ds-shadow-card);
    padding: 2.25rem 2.25rem;
    margin-bottom: 1.5rem;
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 2rem;
    position: relative;
    overflow: hidden;
}
@media (max-width: 1024px) {
    .h-diag-stage-card { grid-template-columns: 1fr; }
}

/* Left HUD */
.h-diag-hud-left {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    z-index: 2;
}
.h-diag-hud-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: var(--ds-blue);
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}
.h-diag-hud-badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--ds-blue);
}
.h-diag-hud-title {
    font-size: 2.6rem;
    font-weight: 900;
    letter-spacing: -0.04em;
    color: var(--ds-text);
    margin: 0 0 1.25rem 0;
    line-height: 1;
}
.h-diag-hud-progress-block {
    margin-bottom: 1rem;
}
.h-diag-hud-pct {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--ds-blue);
    line-height: 1;
    font-variant-numeric: tabular-nums;
}
.h-diag-hud-status-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--ds-text-muted);
    margin-top: 0.35rem;
}
.h-diag-hud-tag-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}
.h-diag-status-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.8rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.03em;
}
.h-diag-status-chip.normal {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}
.h-diag-status-chip.warn {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}
.h-diag-status-chip.danger {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.h-diag-ai-report-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    padding: 0.35rem 0.75rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ds-blue);
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.h-diag-pipeline-ticker {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-bottom: 1.5rem;
    padding-left: 0.5rem;
    border-left: 2px solid var(--ds-border);
}
.h-ticker-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--ds-text-muted);
}
.h-ticker-item.active {
    color: var(--ds-blue);
}
.h-ticker-bullet {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--ds-text-muted);
}
.h-ticker-item.active .h-ticker-bullet {
    background: var(--ds-blue);
    box-shadow: 0 0 6px var(--ds-blue);
}
.h-diag-hud-cta-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.h-diag-cta-btn {
    position: relative;
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    color: #ffffff;
    border: none;
    border-radius: var(--ds-radius-pill);
    padding: 0.85rem 1.75rem;
    font-size: 0.92rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    box-shadow: 0 8px 24px -4px rgba(2, 132, 199, 0.45);
    transition: all 0.25s ease;
    overflow: hidden;
}
.h-diag-cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px -4px rgba(2, 132, 199, 0.55);
}
.h-diag-cta-glow {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: radial-gradient(circle at center, rgba(255,255,255,0.25) 0%, transparent 70%);
    opacity: 0;
    transition: opacity 0.3s;
}
.h-diag-cta-btn:hover .h-diag-cta-glow { opacity: 1; }
.h-diag-sub-actions {
    display: flex;
    gap: 0.5rem;
}
.h-diag-sub-btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    padding: 0.45rem 0.6rem;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--ds-text-muted);
    cursor: pointer;
    transition: all 0.15s ease;
}
.h-diag-sub-btn:hover {
    border-color: var(--ds-blue);
    color: var(--ds-blue);
}

/* Center Vehicle Viewport */
.h-diag-vehicle-canvas-area {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 380px;
}
.h-diag-grid-plane {
    position: absolute;
    width: 90%;
    height: 70%;
    bottom: 5%;
    border-radius: 50%;
    background-image: radial-gradient(#cbd5e1 1.2px, transparent 1.2px);
    background-size: 18px 18px;
    opacity: 0.75;
    mask-image: radial-gradient(ellipse at center, rgba(0,0,0,1) 30%, transparent 70%);
    -webkit-mask-image: radial-gradient(ellipse at center, rgba(0,0,0,1) 30%, transparent 70%);
    pointer-events: none;
}
.h-diag-car-stage {
    position: relative;
    width: 100%;
    max-width: 640px;
    transition: transform 0.3s ease;
}
.h-diag-car-svg {
    width: 100%;
    height: auto;
    filter: drop-shadow(0 20px 25px rgba(15, 23, 42, 0.08));
}
.h-diag-viewport-controls {
    position: absolute;
    right: 0.5rem;
    top: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    z-index: 5;
}
.h-diag-zoom-btn {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    color: var(--ds-text);
    font-weight: 700;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0,0,0,0.04);
}

/* Glowing Telemetry Pins */
.h-diag-pin {
    position: absolute;
    cursor: pointer;
    z-index: 10;
    transform: translate(-50%, -50%);
}
.h-diag-pin-beacon {
    position: absolute;
    top: 50%; left: 50%;
    width: 32px; height: 32px;
    transform: translate(-50%, -50%);
    border-radius: 50%;
    background: rgba(2, 132, 199, 0.35);
    animation: beaconPulse 2s infinite ease-out;
}
.h-diag-pin-beacon.orange {
    background: rgba(249, 115, 22, 0.35);
}
@keyframes beaconPulse {
    0% { transform: translate(-50%, -50%) scale(0.6); opacity: 0.9; }
    100% { transform: translate(-50%, -50%) scale(1.6); opacity: 0; }
}
.h-diag-pin-core {
    position: relative;
    width: 28px; height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.18);
    transition: transform 0.2s ease;
}
.h-diag-pin:hover .h-diag-pin-core { transform: scale(1.15); }
.h-diag-pin-core.blue { background: #0284c7; }
.h-diag-pin-core.orange { background: #ea580c; }
.h-diag-pin-core.dark { background: #0f172a; }
.h-diag-pin-core.green { background: #16a34a; }
.h-diag-pin-tooltip {
    position: absolute;
    bottom: 125%; left: 50%;
    transform: translateX(-50%) translateY(6px);
    background: #0f172a;
    color: #ffffff;
    padding: 0.45rem 0.75rem;
    border-radius: 8px;
    font-size: 0.68rem;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: all 0.2s ease;
    box-shadow: 0 8px 20px rgba(0,0,0,0.25);
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    text-align: center;
}
.h-diag-pin:hover .h-diag-pin-tooltip {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

/* ── 3. Sensor Monitoring Panels (The 4 Bottom Cards) ── */
.h-diag-grid-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.5rem;
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
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.h-diag-card:hover {
    box-shadow: var(--ds-shadow-hover);
}
.h-diag-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--ds-border);
    margin-bottom: 1rem;
}
.h-diag-card-title {
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: var(--ds-text);
    text-transform: uppercase;
}
.h-diag-card-status-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: var(--ds-radius-pill);
}
.h-diag-card-status-badge.ok {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}
.h-diag-card-subinfo {
    font-size: 0.68rem;
    color: var(--ds-text-muted);
}

/* Card 1: Probe Equalizers */
.h-diag-probes-container {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.5rem;
    align-items: flex-end;
    margin-bottom: 1rem;
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
    height: 75px;
    background: var(--ds-surface-subtle);
    border-radius: 9999px;
    position: relative;
    display: flex;
    align-items: flex-end;
    overflow: hidden;
    border: 1px solid var(--ds-border);
}
.h-diag-probe-bar {
    width: 100%;
    border-radius: 9999px;
    transition: height 0.6s ease;
}
.h-diag-probe-bar.blue { background: linear-gradient(180deg, #38bdf8 0%, #0284c7 100%); }
.h-diag-probe-bar.orange { background: linear-gradient(180deg, #fb923c 0%, #ea580c 100%); }
.h-diag-probe-cap {
    position: absolute;
    top: 2px; left: 50%;
    transform: translateX(-50%);
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #0284c7;
    box-shadow: 0 0 6px #0284c7;
}
.h-diag-probe-cap.orange { background: #ea580c; box-shadow: 0 0 6px #ea580c; }
.h-diag-probe-name {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--ds-text-muted);
    text-transform: uppercase;
}
.h-diag-probe-val {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--ds-text);
    font-variant-numeric: tabular-nums;
}
.h-diag-card-footer-metrics {
    display: flex;
    justify-content: space-between;
    border-top: 1px solid var(--ds-border);
    padding-top: 0.65rem;
    font-size: 0.72rem;
}
.h-diag-footer-metric {
    display: flex;
    flex-direction: column;
}
.h-diag-footer-metric .lbl {
    font-size: 0.62rem;
    color: var(--ds-text-muted);
    text-transform: uppercase;
}
.h-diag-footer-metric .val {
    font-weight: 700;
    color: var(--ds-text);
}

/* Card 2: Dark Contrast DAQ */
.h-diag-card.dark-mode-card {
    background: #0f172a;
    border-color: #1e293b;
    color: #ffffff;
}
.h-diag-daq-badge-live {
    font-size: 0.65rem;
    font-weight: 700;
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
    padding: 0.15rem 0.5rem;
    border-radius: var(--ds-radius-pill);
}
.h-diag-dark-card-body {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
}
.h-diag-dark-big-val {
    font-size: 2.2rem;
    font-weight: 800;
    color: #f8fafc;
    line-height: 1;
}
.h-diag-dark-sub {
    font-size: 0.7rem;
    color: #94a3b8;
    margin-top: 0.35rem;
}
.h-diag-dark-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.65rem;
    font-size: 0.7rem;
    font-weight: 600;
    color: #38bdf8;
    background: rgba(2, 132, 199, 0.15);
    border: 1px solid rgba(56, 189, 248, 0.3);
    padding: 0.2rem 0.55rem;
    border-radius: var(--ds-radius-pill);
}
.h-diag-dark-chip .dot {
    width: 6px; height: 6px; border-radius: 50%; background: #38bdf8;
}
.h-diag-dark-footer {
    display: flex;
    justify-content: space-between;
    border-top: 1px solid #1e293b;
    padding-top: 0.65rem;
    font-size: 0.68rem;
    color: #94a3b8;
}

/* Card 3: Gauges & Mode Selector */
.h-diag-gauges-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    align-items: center;
    margin-bottom: 0.85rem;
}
.h-diag-gauge-cell {
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    border-radius: 12px;
    padding: 0.65rem 0.75rem;
    text-align: center;
}
.h-diag-gauge-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.25rem;
}
.h-diag-gauge-top .name {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--ds-text-muted);
}
.h-diag-arc-wrap {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0 auto;
}
.h-diag-arc-val {
    position: absolute;
    bottom: 2px;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--ds-blue);
}
.h-diag-gauge-lbl {
    font-size: 0.62rem;
    color: var(--ds-text-muted);
    margin-top: 0.2rem;
}
.h-diag-rpm-wave-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.h-diag-rpm-display {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--ds-text);
    line-height: 1;
    margin-bottom: 0.2rem;
}
.h-diag-sine-svg path {
    stroke-dasharray: 100;
    animation: waveScroll 3s linear infinite;
}
@keyframes waveScroll {
    from { stroke-dashoffset: 0; }
    to { stroke-dashoffset: 200; }
}
.h-diag-mode-selector-strip {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.4rem;
    background: var(--ds-surface-subtle);
    padding: 0.25rem;
    border-radius: 10px;
}
.h-mode-btn {
    border: none;
    background: transparent;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--ds-text-muted);
    padding: 0.35rem 0.2rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.h-mode-btn.active {
    background: var(--ds-surface);
    color: var(--ds-blue);
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}

/* Card 4: AI Assistant Terminal */
.h-diag-ai-avatar {
    width: 20px; height: 20px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0284c7, #38bdf8);
    display: flex; align-items: center; justify-content: center;
}
.h-diag-ai-chat-body {
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    border-radius: 12px;
    padding: 0.75rem;
    max-height: 120px;
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

/* ── 4. Recording Workbench & Oscilloscope ── */
.h-diag-workbench-card {
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-card);
    padding: 1.5rem;
    box-shadow: var(--ds-shadow-card);
    margin-bottom: 1.5rem;
}
.h-diag-workbench-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
}
.h-diag-workbench-icon {
    width: 34px; height: 34px;
    border-radius: 10px;
    background: var(--ds-blue-light);
    display: flex; align-items: center; justify-content: center;
}
.h-diag-workbench-title {
    font-size: 1rem;
    font-weight: 800;
    margin: 0;
    color: var(--ds-text);
}
.h-diag-workbench-sub {
    font-size: 0.75rem;
    color: var(--ds-text-muted);
    margin: 0.15rem 0 0;
}
.h-diag-workbench-timer-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'JetBrains Mono', monospace;
}
.h-diag-rec-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #94a3b8;
}
.h-diag-rec-dot.active {
    background: #ef4444;
    box-shadow: 0 0 10px #ef4444;
    animation: recPulse 1s infinite;
}
@keyframes recPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
.h-diag-rec-timer {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--ds-text);
}
.h-diag-rec-target {
    font-size: 0.75rem;
    color: var(--ds-text-muted);
}
.h-diag-oscilloscope-wrap {
    position: relative;
    background: #0f172a;
    border-radius: 12px;
    overflow: hidden;
    height: 90px;
    margin-bottom: 1rem;
    box-shadow: inset 0 2px 8px rgba(0,0,0,0.4);
}
.h-diag-canvas {
    width: 100%;
    height: 100%;
    display: block;
}
.h-diag-canvas-overlay {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    color: #94a3b8;
    font-size: 0.78rem;
    pointer-events: none;
    letter-spacing: 0.02em;
}
.h-diag-workbench-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.h-diag-actions-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.h-diag-btn-primary {
    background: var(--ds-blue);
    color: #fff;
    border: none;
    border-radius: var(--ds-radius-pill);
    padding: 0.6rem 1.25rem;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    box-shadow: 0 4px 14px rgba(2, 132, 199, 0.3);
    transition: all 0.2s ease;
}
.h-diag-btn-primary:hover:not(:disabled) {
    background: #0369a1;
    transform: translateY(-1px);
}
.h-diag-btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    box-shadow: none;
}
.h-diag-btn-danger {
    background: #ef4444;
    color: #fff;
    border: none;
    border-radius: var(--ds-radius-pill);
    padding: 0.6rem 1.25rem;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}
.h-diag-btn-secondary {
    background: var(--ds-surface-subtle);
    color: var(--ds-text);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-pill);
    padding: 0.6rem 1.15rem;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
}
.h-diag-playback-strip {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    background: var(--ds-surface-subtle);
    padding: 0.35rem 0.75rem;
    border-radius: var(--ds-radius-pill);
    border: 1px solid var(--ds-border);
}
.h-diag-playback-meta {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--ds-blue);
}

/* ── 5. AI Analysis Stepper Pipeline ── */
.h-diag-pipeline-card {
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-card);
    padding: 1.5rem;
    box-shadow: var(--ds-shadow-card);
    margin-bottom: 1.5rem;
}
.h-diag-pipeline-head {
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: var(--ds-text-muted);
    text-transform: uppercase;
    margin: 0 0 1.25rem;
}
.h-diag-stepper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow-x: auto;
    padding-bottom: 0.5rem;
}
.h-stage-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    min-width: 110px;
    z-index: 2;
}
.h-stage-circle {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: var(--ds-surface-subtle);
    border: 2px solid var(--ds-border);
    color: var(--ds-text-muted);
    font-weight: 800;
    font-size: 0.78rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.4rem;
    transition: all 0.3s ease;
}
.h-stage-node.active .h-stage-circle {
    background: var(--ds-blue);
    border-color: var(--ds-blue);
    color: #fff;
    box-shadow: 0 0 12px rgba(2, 132, 199, 0.4);
}
.h-stage-node.completed .h-stage-circle {
    background: #16a34a;
    border-color: #16a34a;
    color: #fff;
}
.h-stage-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--ds-text);
}
.h-stage-desc {
    font-size: 0.62rem;
    color: var(--ds-text-muted);
    margin-top: 0.15rem;
}
.h-stage-line {
    flex: 1;
    height: 2px;
    background: var(--ds-border);
    margin: 0 0.4rem;
    margin-bottom: 1.5rem;
}
.h-stage-line.active { background: var(--ds-blue); }

/* ── 6. Diagnostic Results Card ── */
.h-diag-results-card {
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-card);
    padding: 1.5rem;
    box-shadow: 0 12px 36px -4px rgba(2, 132, 199, 0.12);
    margin-bottom: 1.5rem;
}
.h-diag-results-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-radius: 14px;
    background: #f0fdf4;
    border: 1.5px solid #86efac;
    margin-bottom: 1.25rem;
}
.h-diag-results-banner.warn {
    background: #fffbeb;
    border-color: #fcd34d;
}
.h-diag-results-banner.danger {
    background: #fef2f2;
    border-color: #fca5a5;
}
.h-diag-results-eyebrow {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #166534;
    text-transform: uppercase;
}
.h-diag-results-banner.warn .h-diag-results-eyebrow { color: #b45309; }
.h-diag-results-banner.danger .h-diag-results-eyebrow { color: #b91c1c; }
.h-diag-results-title {
    font-size: 1.8rem;
    font-weight: 900;
    letter-spacing: -0.03em;
    margin: 0.2rem 0;
    color: var(--ds-text);
}
.h-diag-results-sub {
    font-size: 0.8rem;
    color: var(--ds-text-muted);
    margin: 0;
}
.h-diag-results-confidence {
    text-align: right;
    min-width: 140px;
}
.h-diag-conf-num {
    font-size: 2.2rem;
    font-weight: 900;
    color: #166534;
    line-height: 1;
}
.h-diag-conf-lbl {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--ds-text-muted);
    text-transform: uppercase;
    margin-top: 0.25rem;
}
.h-diag-conf-bar-bg {
    width: 100%;
    height: 6px;
    background: rgba(0,0,0,0.08);
    border-radius: 999px;
    margin-top: 0.4rem;
    overflow: hidden;
}
.h-diag-conf-bar-fill {
    height: 100%;
    background: #22c55e;
    border-radius: 999px;
    transition: width 0.8s ease;
}
.h-diag-results-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}
@media (max-width: 640px) { .h-diag-results-grid { grid-template-columns: 1fr; } }
.h-diag-res-box {
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    border-radius: 12px;
    padding: 1rem 1.25rem;
}
.h-diag-res-box h4 {
    margin: 0 0 0.65rem;
    font-size: 0.82rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--ds-text);
}
.h-diag-res-box ul {
    margin: 0;
    padding-left: 1.2rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    font-size: 0.78rem;
    color: var(--ds-text-muted);
    line-height: 1.45;
}
.h-diag-results-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    border-top: 1px solid var(--ds-border);
    padding-top: 1rem;
}

/* ── 7. History Cards Strip ── */
.h-diag-history-card {
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    border-radius: var(--ds-radius-card);
    padding: 1.5rem;
    box-shadow: var(--ds-shadow-card);
}
.h-diag-history-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.h-diag-history-title {
    font-size: 0.82rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--ds-text);
}
.h-diag-history-viewall {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--ds-blue);
    text-decoration: none;
}
.h-diag-history-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.85rem;
}
.h-diag-history-item {
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
    border-radius: 12px;
    padding: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.h-diag-history-item:hover {
    border-color: var(--ds-blue);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.08);
}
.h-hist-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.35rem;
}
.h-hist-id {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--ds-text-muted);
}
.h-hist-badge {
    font-size: 0.62rem;
    font-weight: 800;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
}
.badge-normal { background: #f0fdf4; color: #166534; }
.badge-warn { background: #fffbeb; color: #b45309; }
.badge-danger { background: #fef2f2; color: #b91c1c; }
.badge-neutral { background: #f1f5f9; color: #475569; }
.h-hist-car {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--ds-text);
    margin-bottom: 0.35rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.h-hist-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.68rem;
    color: var(--ds-text-muted);
}
.h-diag-history-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 1.5rem;
    font-size: 0.8rem;
    color: var(--ds-text-muted);
}

/* ── Modals & Drawers ── */
.h-diag-modal-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(8px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.h-diag-modal-box {
    background: #ffffff;
    border-radius: 18px;
    max-width: 500px;
    width: 100%;
    padding: 1.5rem;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
}
.h-diag-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.h-diag-modal-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
}
.h-diag-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #94a3b8;
}
.h-diag-file-input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid var(--ds-border);
    border-radius: 8px;
    font-size: 0.8rem;
}
.h-diag-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1.25rem;
}
.h-diag-empty-card {
    text-align: center;
    background: var(--ds-surface);
    border: 1px solid var(--ds-border);
    border-radius: 20px;
    padding: 3rem 1.5rem;
    max-width: 480px;
    margin: 3rem auto;
    color: var(--ds-text);
}

/* ── Dark Theme Overrides & Refinements ── */
[data-theme="dark"] .h-diag-modal-box {
    background: var(--ds-modal-bg);
    border-color: var(--ds-border);
    color: var(--ds-text);
}
[data-theme="dark"] .h-diag-file-input {
    background: var(--ds-surface-subtle);
    color: var(--ds-text);
    border-color: var(--ds-border);
}
[data-theme="dark"] .h-diag-results-banner {
    background: #052e16;
    border-color: #166534;
}
[data-theme="dark"] .h-diag-results-banner.warn {
    background: #451a03;
    border-color: #854d0e;
}
[data-theme="dark"] .h-diag-results-banner.danger {
    background: #450a0a;
    border-color: #991b1b;
}
[data-theme="dark"] .h-diag-results-banner .h-diag-conf-num {
    color: #4ade80;
}
[data-theme="dark"] .h-diag-results-banner.warn .h-diag-conf-num {
    color: #facc15;
}
[data-theme="dark"] .h-diag-results-banner.danger .h-diag-conf-num {
    color: #f87171;
}
[data-theme="dark"] .h-diag-results-banner .h-diag-results-eyebrow {
    color: #4ade80;
}
[data-theme="dark"] .h-diag-results-banner.warn .h-diag-results-eyebrow {
    color: #facc15;
}
[data-theme="dark"] .h-diag-results-banner.danger .h-diag-results-eyebrow {
    color: #f87171;
}
[data-theme="dark"] .h-diag-results-banner .h-diag-results-sub {
    color: #94a3b8;
}
[data-theme="dark"] .h-diag-results-banner .h-diag-conf-lbl {
    color: #94a3b8;
}
[data-theme="dark"] .h-diag-card-status-badge.ok {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border-color: rgba(34, 197, 94, 0.3);
}
[data-theme="dark"] .h-diag-daq-pill {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border-color: rgba(34, 197, 94, 0.3);
}
[data-theme="dark"] .badge-normal {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}
[data-theme="dark"] .badge-warn {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
[data-theme="dark"] .badge-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
[data-theme="dark"] .badge-neutral {
    background: #1e293b;
    color: #94a3b8;
    border: 1px solid #334155;
}
[data-theme="dark"] .h-diag-grid-plane {
    background-image: radial-gradient(#334155 1.2px, transparent 1.2px);
    opacity: 0.45;
}
[data-theme="dark"] #drawer-manual-telemetry > div {
    background: var(--ds-surface) !important;
    color: var(--ds-text) !important;
    border: 1px solid var(--ds-border) !important;
}
[data-theme="dark"] #drawer-manual-telemetry label {
    color: var(--ds-text-muted) !important;
}
[data-theme="dark"] .h-diag-ai-card-input-row input {
    background: var(--ds-surface-subtle);
    color: var(--ds-text);
    border-color: var(--ds-border);
}
[data-theme="dark"] .h-diag-ai-msg {
    background: var(--ds-surface-subtle);
    border-color: var(--ds-border);
    color: var(--ds-text);
}
[data-theme="dark"] .h-diag-ai-feed-box {
    background: var(--ds-surface-subtle);
    border-color: var(--ds-border);
    color: var(--ds-text);
}

/* ── Mobile Viewport Overrides (Phone & Small Screens <= 640px) ── */
@media (max-width: 640px) {
    .h-hide-mobile { display: none !important; }

    /* Top Navigation Stacking */
    .h-diag-topbar {
        flex-direction: column !important;
        align-items: stretch !important;
        border-radius: 16px !important;
        padding: 0.75rem !important;
        gap: 0.65rem !important;
    }
    .h-diag-topbar-left {
        width: 100% !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.5rem !important;
    }
    .h-diag-vehicle-pill {
        width: 100% !important;
        justify-content: space-between !important;
        box-sizing: border-box !important;
    }
    .h-diag-vehicle-select {
        flex: 1 !important;
        max-width: calc(100% - 32px) !important;
        font-size: 0.8rem !important;
    }
    .h-diag-mode-pill {
        width: 100% !important;
        justify-content: center !important;
        min-height: 38px !important;
    }
    .h-diag-topbar-right {
        width: 100% !important;
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.45rem !important;
    }
    .h-diag-search-wrap {
        flex: 1 1 100% !important;
        box-sizing: border-box !important;
    }
    .h-diag-search-wrap input {
        width: 100% !important;
    }
    .h-diag-topbar-btn {
        flex: 1 !important;
        justify-content: center !important;
        min-height: 36px !important;
    }
    .h-diag-daq-pill {
        flex: 1 !important;
        justify-content: center !important;
        min-height: 36px !important;
    }

    /* Main Diagnostic Hero (Stage) */
    .h-diag-stage-card {
        grid-template-columns: 1fr !important;
        padding: 1.25rem 1rem !important;
        border-radius: 16px !important;
        gap: 1.5rem !important;
    }
    .h-diag-hud-title {
        font-size: 1.75rem !important;
    }
    .h-diag-hud-pct {
        font-size: 2rem !important;
    }
    .h-diag-hud-tag-row {
        flex-wrap: wrap !important;
        gap: 0.5rem !important;
    }
    .h-diag-cta-btn {
        width: 100% !important;
        justify-content: center !important;
        min-height: 46px !important;
    }
    .h-diag-sub-actions {
        width: 100% !important;
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 0.5rem !important;
    }
    .h-diag-sub-btn {
        width: 100% !important;
        justify-content: center !important;
        min-height: 40px !important;
        font-size: 0.72rem !important;
    }
    .h-diag-vehicle-canvas-area {
        min-height: 220px !important;
    }
    .h-diag-car-svg {
        max-height: 200px !important;
    }
    .h-diag-viewport-controls {
        top: 0.25rem !important;
        right: 0.25rem !important;
    }

    /* 4-Card Sensor Monitoring Grid */
    .h-diag-grid-cards {
        grid-template-columns: 1fr !important;
        gap: 0.85rem !important;
    }
    .h-diag-probes-container {
        grid-template-columns: repeat(5, 1fr) !important;
        gap: 0.2rem !important;
    }
    .h-diag-probe-name {
        font-size: 0.55rem !important;
        word-break: break-all !important;
    }
    .h-diag-probe-pillar {
        height: 60px !important;
        width: 11px !important;
    }
    .h-diag-probe-val {
        font-size: 0.6rem !important;
    }

    /* Recording Workbench */
    .h-diag-workbench-card {
        padding: 1rem !important;
        border-radius: 16px !important;
    }
    .h-diag-workbench-head {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.5rem !important;
    }
    .h-diag-workbench-actions {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.75rem !important;
    }
    .h-diag-actions-left {
        flex-direction: column !important;
        width: 100% !important;
        align-items: stretch !important;
        gap: 0.5rem !important;
    }
    .h-diag-actions-left button,
    .h-diag-actions-left .h-diag-btn-primary,
    .h-diag-actions-left .h-diag-btn-danger,
    .h-diag-actions-left .h-diag-btn-secondary {
        width: 100% !important;
        justify-content: center !important;
        min-height: 44px !important;
    }
    .h-diag-playback-strip {
        width: 100% !important;
        justify-content: space-between !important;
        box-sizing: border-box !important;
    }
    .h-diag-actions-right {
        width: 100% !important;
    }
    .h-diag-actions-right button,
    .h-diag-actions-right .h-diag-btn-primary {
        width: 100% !important;
        justify-content: center !important;
        min-height: 44px !important;
    }

    /* 6-Stage ML Stepper */
    .h-diag-stepper {
        justify-content: flex-start !important;
        gap: 0.75rem !important;
        padding-bottom: 0.75rem !important;
        -webkit-overflow-scrolling: touch;
    }
    .h-stage-node {
        min-width: 85px !important;
    }
    .h-stage-circle {
        width: 28px !important;
        height: 28px !important;
        font-size: 0.72rem !important;
    }

    /* Diagnostic Results Card */
    .h-diag-results-card {
        padding: 1rem !important;
        border-radius: 16px !important;
    }
    .h-diag-results-banner {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 1rem !important;
        padding: 1.15rem 1rem !important;
    }
    .h-diag-results-confidence {
        text-align: left !important;
        width: 100% !important;
        min-width: auto !important;
    }
    .h-diag-results-grid {
        grid-template-columns: 1fr !important;
        gap: 0.85rem !important;
    }
    .h-diag-results-footer {
        flex-direction: column-reverse !important;
        align-items: stretch !important;
        gap: 0.5rem !important;
    }
    .h-diag-results-footer a,
    .h-diag-results-footer button {
        width: 100% !important;
        justify-content: center !important;
        min-height: 42px !important;
    }

    /* History Grid & Modals */
    .h-diag-history-card {
        padding: 1rem !important;
        border-radius: 16px !important;
    }
    .h-diag-history-grid {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }
    .h-diag-modal-box {
        max-width: 95vw !important;
        padding: 1.15rem !important;
        border-radius: 14px !important;
    }
    #drawer-manual-telemetry > div {
        width: 95vw !important;
        max-height: 92vh !important;
        padding: 1.25rem !important;
    }
}
</style>

{{-- ═══════════════════════════════════════════════════════════════
     CLIENT JAVASCRIPT LOGIC (Live DAQ, Audio Recorder, AI Engine)
     ═══════════════════════════════════════════════════════════════ --}}
<script>
(function() {
    // 1. Vehicle Switching
    window.switchVehicle = function(vehicleId) {
        if (!vehicleId) return;
        const url = new URL(window.location.href);
        url.searchParams.set('vehicle_id', vehicleId);
        window.location.href = url.toString();
    };

    // 2. Perspective View Angle Toggle
    let isPerspective360 = true;
    window.toggleVehiclePerspective = function() {
        const car = document.getElementById('car-3d-model');
        const lbl = document.getElementById('view-mode-label');
        if (!car) return;
        isPerspective360 = !isPerspective360;
        if (isPerspective360) {
            car.style.transform = 'scale(1) rotate(0deg)';
            lbl.textContent = '360° Studio View';
        } else {
            car.style.transform = 'scale(1.15) rotate(4deg) translateY(-8px)';
            lbl.textContent = 'Powertrain Top Angle';
        }
    };

    let zoomLevel = 1.0;
    window.zoomVehicle = function(factor) {
        const car = document.getElementById('car-3d-model');
        if (!car) return;
        zoomLevel = Math.max(0.8, Math.min(1.4, zoomLevel * factor));
        car.style.transform = `scale(${zoomLevel})`;
    };

    // 3. Interactive Marker Focus
    window.focusPin = function(type) {
        if (type === 'audio') {
            document.getElementById('recording-workbench').scrollIntoView({ behavior: 'smooth' });
            startAudioRecording();
        } else if (type === 'vibration') {
            openSampleUploadModal();
        } else if (type === 'battery' || type === 'rpm') {
            openManualTelemetryDrawer();
        }
    };

    // 4. Live Audio Recording Engine (Web Audio API)
    let mediaRecorder, audioChunks = [], audioBlob, analyser, animFrame, audioCtx, recordTimer, recSeconds = 0;
    const canvas = document.getElementById('live-oscilloscope');
    const canvasCtx = canvas ? canvas.getContext('2d') : null;

    window.startAudioRecording = async function() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const source = audioCtx.createMediaStreamSource(stream);
            analyser = audioCtx.createAnalyser();
            analyser.fftSize = 256;
            source.connect(analyser);

            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];
            mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
            mediaRecorder.onstop = () => finalizeAudioCapture(stream);
            mediaRecorder.start(100);

            // UI adjustments
            document.getElementById('btn-start-rec').style.display = 'none';
            document.getElementById('btn-stop-rec').style.display = 'inline-flex';
            document.getElementById('live-rec-dot').classList.add('active');
            document.getElementById('canvas-overlay-text').style.display = 'none';
            document.getElementById('hud-progress-label').textContent = 'RECORDING AUDIO HARMONICS...';
            document.getElementById('hud-progress-pct').textContent = '35%';

            // Start timer
            recSeconds = 0;
            clearInterval(recordTimer);
            recordTimer = setInterval(() => {
                recSeconds++;
                const mins = String(Math.floor(recSeconds / 60)).padStart(2, '0');
                const secs = String(recSeconds % 60).padStart(2, '0');
                document.getElementById('live-rec-timer').textContent = `${mins}:${secs}`;
                if (recSeconds >= 15) {
                    document.getElementById('hud-progress-pct').textContent = '70%';
                    document.getElementById('btn-submit-session').disabled = false;
                }
            }, 1000);

            drawOscilloscope();
        } catch (err) {
            console.warn('Microphone error:', err);
            alert('Microphone permission required for live diagnostic audio capture. Alternatively, click "Upload Offline Sample".');
        }
    };

    window.stopAudioRecording = function() {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
        clearInterval(recordTimer);
        cancelAnimationFrame(animFrame);
        document.getElementById('btn-start-rec').style.display = 'inline-flex';
        document.getElementById('btn-stop-rec').style.display = 'none';
        document.getElementById('live-rec-dot').classList.remove('active');
    };

    function finalizeAudioCapture(stream) {
        stream.getTracks().forEach(t => t.stop());
        audioBlob = new Blob(audioChunks, { type: 'audio/webm' });

        // Bind to hidden form
        const file = new File([audioBlob], 'engine_audio_' + Date.now() + '.webm', { type: 'audio/webm' });
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('form_audio_blob').files = dt.files;
        document.getElementById('form_audio_duration').value = recSeconds;

        // Playback preview
        const audioUrl = URL.createObjectURL(audioBlob);
        const player = document.getElementById('workbench-audio-playback');
        player.src = audioUrl;
        document.getElementById('playback-strip').style.display = 'flex';
        document.getElementById('playback-duration-meta').textContent = `${recSeconds}s Audio Ready`;

        document.getElementById('btn-submit-session').disabled = false;
        document.getElementById('tick-audio').classList.add('active');
        document.getElementById('hud-progress-pct').textContent = '80%';
        document.getElementById('hud-progress-label').textContent = 'ACOUSTIC CAPTURED · READY TO ANALYZE';
    }

    function drawOscilloscope() {
        if (!analyser || !canvasCtx) return;
        const bufferLength = analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);

        function render() {
            animFrame = requestAnimationFrame(render);
            analyser.getByteFrequencyData(dataArray);

            canvasCtx.fillStyle = '#0f172a';
            canvasCtx.fillRect(0, 0, canvas.width, canvas.height);

            const barWidth = (canvas.width / bufferLength) * 2.5;
            let barHeight;
            let x = 0;

            for (let i = 0; i < bufferLength; i++) {
                barHeight = (dataArray[i] / 255) * canvas.height * 0.9;
                const grad = canvasCtx.createLinearGradient(0, canvas.height, 0, 0);
                grad.addColorStop(0, '#0284c7');
                grad.addColorStop(1, '#38bdf8');
                canvasCtx.fillStyle = grad;
                canvasCtx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);
                x += barWidth + 1;
            }
        }
        render();
    }

    // 5. Trigger Primary Action CTA (HUD Main Button)
    window.triggerPrimaryAction = function() {
        const btn = document.getElementById('btn-master-action');
        if (recSeconds === 0) {
            startAudioRecording();
            btn.querySelector('#master-btn-text').textContent = 'Stop Recording';
        } else if (mediaRecorder && mediaRecorder.state === 'recording') {
            stopAudioRecording();
            btn.querySelector('#master-btn-text').textContent = 'Run ML Diagnostics';
        } else {
            submitDiagnosticForm();
        }
    };

    // 6. Recording Mode Selection (Idle vs Revving)
    window.setRecordingMode = function(mode) {
        document.getElementById('form_recording_mode').value = mode;
        const isIdle = (mode === 'idle');
        document.getElementById('btn-mode-idle').classList.toggle('active', isIdle);
        document.getElementById('btn-mode-revving').classList.toggle('active', !isIdle);
        document.getElementById('gauge-rpm-num').textContent = isIdle ? '850' : '2,200';
        document.getElementById('gauge-rpm-mode').textContent = isIdle ? 'Idling (750–900)' : 'Revving (2,000–2,500)';
    };

    // 7. Modals & Sample Uploads
    window.openSampleUploadModal = () => document.getElementById('modal-sample-upload').style.display = 'flex';
    window.closeSampleUploadModal = () => document.getElementById('modal-sample-upload').style.display = 'none';
    window.openManualTelemetryDrawer = () => document.getElementById('drawer-manual-telemetry').style.display = 'flex';
    window.closeManualTelemetryDrawer = () => document.getElementById('drawer-manual-telemetry').style.display = 'none';

    window.previewModalVibration = function(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const lines = e.target.result.split('\n').filter(l => l.trim().length > 0);
            const count = Math.max(0, lines.length - 1);
            const summary = document.getElementById('modal-vib-summary');
            summary.style.display = 'block';
            summary.textContent = `✓ Loaded ${count} accelerometer samples from ${file.name}`;
            document.getElementById('probe-vib-val').textContent = 'CSV Attached';
            document.getElementById('tick-vib').classList.add('active');
        };
        reader.readAsText(file.slice(0, 50000));
    };

    window.applySampleUploads = function() {
        const audioIn = document.getElementById('modal_audio_input');
        const vibIn = document.getElementById('modal_vib_input');

        if (audioIn.files && audioIn.files[0]) {
            document.getElementById('form_audio_file').files = audioIn.files;
            document.getElementById('playback-duration-meta').textContent = audioIn.files[0].name;
            document.getElementById('playback-strip').style.display = 'flex';
            document.getElementById('tick-audio').classList.add('active');
        }
        if (vibIn.files && vibIn.files[0]) {
            document.getElementById('form_vibration_file').files = vibIn.files;
            document.getElementById('tick-vib').classList.add('active');
        }
        closeSampleUploadModal();
        document.getElementById('btn-submit-session').disabled = false;
        document.getElementById('hud-progress-pct').textContent = '90%';
        document.getElementById('hud-progress-label').textContent = 'OFFLINE SAMPLES ATTACHED · READY';
    };

    window.saveManualTelemetry = function() {
        const temp = document.getElementById('man_temp').value;
        const rpm = document.getElementById('man_rpm').value;
        const volt = document.getElementById('man_volt').value;
        const curr = document.getElementById('man_curr').value;

        document.getElementById('form_temperature_c').value = temp;
        document.getElementById('form_rpm').value = rpm;
        document.getElementById('form_voltage_v').value = volt;
        document.getElementById('form_current_ma').value = curr;

        document.getElementById('probe-temp-val').textContent = temp + '°C';
        document.getElementById('probe-rpm-val').textContent = rpm + ' RPM';
        document.getElementById('probe-volt-val').textContent = volt + ' V';
        document.getElementById('gauge-voltage-num').textContent = volt;
        document.getElementById('gauge-rpm-num').textContent = rpm;

        closeManualTelemetryDrawer();
    };

    // 8. 6-Stage AI Stepper Animation & Form Submission
    window.submitDiagnosticForm = function() {
        const form = document.getElementById('haven-hidden-diag-form');
        document.getElementById('analysis-pipeline-card').scrollIntoView({ behavior: 'smooth' });

        // Animate Pipeline Stages
        let step = 1;
        function advanceStepper() {
            if (step <= 6) {
                document.getElementById('stage-' + step).classList.add('active');
                if (step > 1) {
                    document.getElementById('stage-' + (step - 1)).classList.add('completed');
                    const line = document.getElementById('line-' + (step - 1));
                    if (line) line.classList.add('active');
                }
                document.getElementById('hud-progress-pct').textContent = (Math.round((step / 6) * 100)) + '%';
                step++;
                setTimeout(advanceStepper, 400);
            } else {
                // Submit actual Laravel form
                form.submit();
            }
        }
        advanceStepper();
    };

    // 9. Quick AI Assistant in Card
    window.submitCardAiQuery = function() {
        const input = document.getElementById('card-ai-input');
        const q = input.value.trim();
        if (!q) return;
        input.value = '';

        const thread = document.getElementById('card-ai-thread');
        thread.innerHTML += `<div class="h-diag-ai-msg user" style="color:#0284c7;font-weight:600;margin-top:0.4rem;">You: ${escapeHtml(q)}</div>`;
        thread.innerHTML += `<div class="h-diag-ai-msg bot" id="ai-loading" style="color:#64748b;margin-top:0.3rem;">HAVEN AI is consulting powertrain models...</div>`;
        thread.scrollTop = thread.scrollHeight;

        fetch('/ai/ask', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ question: q })
        })
        .then(res => res.json())
        .then(data => {
            const el = document.getElementById('ai-loading');
            if (el) el.remove();
            thread.innerHTML += `<div class="h-diag-ai-msg bot" style="margin-top:0.4rem;"><p>${formatAiResponse(data.answer)}</p></div>`;
            thread.scrollTop = thread.scrollHeight;
        })
        .catch(err => {
            const el = document.getElementById('ai-loading');
            if (el) el.textContent = 'Service momentarily busy. Please try again.';
        });
    };

    window.quickAiAsk = function(promptText) {
        document.getElementById('card-ai-input').value = promptText;
        submitCardAiQuery();
    };

    // Helper functions
    function escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }
    function formatAiResponse(text) {
        return text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
    }
    window.resetStudio = function() { window.location.reload(); };

    // 10. Live Polling for ESP32 Hardware Status
    function checkLiveDaq() {
        fetch('/api/v1/telemetry/latest?vehicle_id={{ $selectedVehicle?->id ?? 1 }}')
            .then(res => res.json())
            .then(data => {
                if (data.online) {
                    document.getElementById('daq-live-dot').style.background = '#22c55e';
                    document.getElementById('daq-status-text').textContent = 'ESP32 STREAMING';
                    document.getElementById('daq-status-pill').style.background = '#f0fdf4';
                    document.getElementById('sensor-bus-badge').textContent = 'HARDWARE NODE CONNECTED';
                    if (data.temperature_c) document.getElementById('probe-temp-val').textContent = data.temperature_c + '°C';
                    if (data.rpm) {
                        document.getElementById('probe-rpm-val').textContent = data.rpm + ' RPM';
                        document.getElementById('gauge-rpm-num').textContent = data.rpm;
                    }
                    if (data.voltage_v) {
                        document.getElementById('probe-volt-val').textContent = data.voltage_v + ' V';
                        document.getElementById('gauge-voltage-num').textContent = data.voltage_v;
                    }
                    if (data.peak_g) {
                        document.getElementById('dark-peak-val').innerHTML = `${data.peak_g} <span style="font-size:1rem;color:#94a3b8;">g</span>`;
                    }
                }
            })
            .catch(() => {});
    }
    setInterval(checkLiveDaq, 3000);
    checkLiveDaq();

})();
</script>

</x-app-layout>
