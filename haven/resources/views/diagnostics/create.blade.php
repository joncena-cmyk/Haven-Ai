<x-app-layout title="Executive Diagnostic Studio">

@php
    $selectedVehicle = $vehicles->firstWhere('id', $selectedVehicleId) ?? $vehicles->first();
    $diagnosticModelUid = $selectedVehicle?->sketchfab_uid;
@endphp

<script defer src="https://static.sketchfab.com/api/sketchfab-viewer-1.12.1.js"></script>

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
                    <button type="button" id="vehicle-pill" class="h-diag-dropdown-trigger" onclick="toggleVehicleMenu()" aria-expanded="false">
                        <span>{{ $selectedVehicle?->display_name }}</span>
                        @if($selectedVehicle)
                            <span class="h-diag-vehicle-year">{{ $selectedVehicle->year }}</span>
                        @endif
                    </button>
                    <input type="hidden" id="vehicle-switcher" value="{{ $selectedVehicle?->id }}">
                    <svg class="h-diag-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </div>

                <div id="vehicle-menu" class="h-diag-dropdown-menu" style="display:none;">
                    <div class="h-diag-dropdown-header">Your Vehicles</div>
                    <div class="h-diag-dropdown-scroll">
                        @forelse($vehicles as $v)
                            <a href="{{ route('diagnostics.create', ['vehicle_id' => $v->id]) }}" class="h-diag-dropdown-item {{ $v->id == $selectedVehicle?->id ? 'is-active' : '' }}">
                                <div class="h-diag-dropdown-item-left">
                                    <div class="h-diag-dropdown-v-icon">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                    </div>
                                    <div>
                                        <div class="h-diag-dropdown-v-name">{{ $v->display_name }}</div>
                                        <div class="h-diag-dropdown-v-meta">{{ $v->year }} · {{ $v->engine_type ?: 'Gasoline ICE' }}</div>
                                    </div>
                                </div>
                                <div class="h-diag-dropdown-v-stat">{{ $recentDiagnostics->where('vehicle_id', $v->id)->count() }} sessions</div>
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

            <div class="h-diagnosis-flow" aria-label="Diagnosis progress">
                <div class="h-diagnosis-flow-step is-complete" data-flow-step="vehicle">
                    <span class="h-diagnosis-flow-number">1</span>
                    <span class="h-diagnosis-flow-label">Vehicle</span>
                </div>
                <div class="h-diagnosis-flow-line is-complete"></div>
                <div class="h-diagnosis-flow-step is-active" data-flow-step="capture">
                    <span class="h-diagnosis-flow-number">2</span>
                    <span class="h-diagnosis-flow-label">Acoustics</span>
                </div>
                <div class="h-diagnosis-flow-line"></div>
                <div class="h-diagnosis-flow-step" data-flow-step="vibration">
                    <span class="h-diagnosis-flow-number">3</span>
                    <span class="h-diagnosis-flow-label">Vibration</span>
                </div>
                <div class="h-diagnosis-flow-line"></div>
                <div class="h-diagnosis-flow-step" data-flow-step="analyze">
                    <span class="h-diagnosis-flow-number">4</span>
                    <span class="h-diagnosis-flow-label">Analyze</span>
                </div>
                <div class="h-diagnosis-flow-line"></div>
                <div class="h-diagnosis-flow-step" data-flow-step="results">
                    <span class="h-diagnosis-flow-number">5</span>
                    <span class="h-diagnosis-flow-label">Results</span>
                </div>
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
                    <span class="h-ticker-txt">ACOUSTIC INMP441 (ML)</span>
                </div>
                <div class="h-ticker-item" id="tick-vib">
                    <span class="h-ticker-bullet"></span>
                    <span class="h-ticker-txt">VIBRATION ADXL345 (ML)</span>
                </div>
                <div class="h-ticker-item active" id="tick-aux">
                    <span class="h-ticker-bullet"></span>
                    <span class="h-ticker-txt">AUX DAQ: DS18B20 · HALL · INA219 (DB)</span>
                </div>
                <div class="h-ticker-item" id="tick-ml">
                    <span class="h-ticker-bullet"></span>
                    <span class="h-ticker-txt">ML CLASSIFIER</span>
                </div>
            </div>

            {{-- Master Trigger CTA (Navigates directly to the unified Workbench) --}}
            <div class="h-diag-hud-cta-wrap">
                <button type="button" id="btn-master-action" class="h-diag-cta-btn" onclick="triggerPrimaryAction()" title="Scroll down to the Diagnostic Workbench to capture or upload engine signals">
                    <span class="h-diag-cta-glow"></span>
                    <span class="h-diag-cta-icon" id="master-btn-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14"/><polyline points="19 12 12 19 5 12"/></svg>
                    </span>
                    <span id="master-btn-text">Go to Diagnostic Workbench</span>
                    <span class="h-diag-cta-spinner" id="master-btn-spinner" style="display:none;"></span>
                </button>
            </div>
        </div>

        {{-- Center/Right: 3D Isometric Automotive Visualization with Glowing Markers --}}
        <div class="h-diag-vehicle-canvas-area" id="vehicle-viewport">

            {{-- 3D Ground Plane Radial Grid --}}
            <div class="h-diag-grid-plane"></div>

            <div id="diagnostic-sketchfab-stage" style="{{ $diagnosticModelUid ? '' : 'display:none;' }}">
                <iframe
                    id="diagnostic-sketchfab-frame"
                    title="Interactive 3D vehicle viewer"
                    src="{{ $diagnosticModelUid ? ('https://sketchfab.com/models/' . $diagnosticModelUid . '/embed?autostart=1&transparent=1&ui_controls=0&ui_infos=0&ui_watermark=0&ui_help=0&ui_settings=0&ui_annotations=0&ui_theme=dark&ui_hint=0&ui_ar=0&ui_vr=0&dnt=1') : '' }}"
                    allow="autoplay; fullscreen; xr-spatial-tracking"
                    xr-spatial-tracking
                    execution-while-out-of-viewport
                    execution-while-not-rendered
                    web-share
                    allowfullscreen
                    mozallowfullscreen="true"
                    webkitallowfullscreen="true">
                </iframe>
            </div>

            {{-- High-Precision 3D Automotive Vector Render --}}
            <div class="h-diag-car-stage" id="car-3d-model" style="{{ $diagnosticModelUid ? 'display:none;' : '' }}">
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
                        {{-- Component-Specific Glow Filters --}}
                        <filter id="valvetrainTapFilter" x="-30%" y="-30%" width="160%" height="160%">
                            <feGaussianBlur stdDeviation="3.5" result="blur"/>
                            <feComponentTransfer in="blur" result="glow">
                                <feFuncA type="linear" slope="2"/>
                            </feComponentTransfer>
                            <feMerge>
                                <feMergeNode in="glow"/>
                                <feMergeNode in="SourceGraphic"/>
                            </feMerge>
                        </filter>
                        <filter id="misfireFlameFilter" x="-30%" y="-30%" width="160%" height="160%">
                            <feGaussianBlur stdDeviation="4" result="blur"/>
                            <feMerge>
                                <feMergeNode in="blur"/>
                                <feMergeNode in="SourceGraphic"/>
                            </feMerge>
                        </filter>
                        <filter id="mountOscillationFilter" x="-30%" y="-30%" width="160%" height="160%">
                            <feGaussianBlur stdDeviation="3" result="blur"/>
                            <feMerge>
                                <feMergeNode in="blur"/>
                                <feMergeNode in="SourceGraphic"/>
                            </feMerge>
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

                        {{-- ── 3D Vehicle Component Overlays (Workbench Diagnostic Inspection) ── --}}
                        {{-- 1. Valvetrain & Camshaft Zone --}}
                        <g id="wb-component-valvetrain" class="h-diag-fault-component" onclick="focusPin('audio')" style="cursor:pointer;" title="Valvetrain Camshaft & Lash Tappet Inspection Zone">
                            <path class="h-diag-component-path"
                                  d="M 148 132 C 172 118, 208 110, 238 114 L 244 134 C 212 130, 175 138, 146 146 Z"
                                  fill="rgba(56, 189, 248, 0.18)"
                                  stroke="#38bdf8"
                                  stroke-width="1.5"/>
                            <circle cx="168" cy="132" r="3.5" fill="#38bdf8" class="h-diag-valvetrain-tap"/>
                            <circle cx="188" cy="129" r="3.5" fill="#38bdf8" class="h-diag-valvetrain-tap delay-1"/>
                            <circle cx="208" cy="126" r="3.5" fill="#38bdf8" class="h-diag-valvetrain-tap delay-2"/>
                            <circle cx="228" cy="124" r="3.5" fill="#38bdf8" class="h-diag-valvetrain-tap delay-3"/>
                        </g>

                        {{-- 2. Combustion Chamber & Cylinders Zone --}}
                        <g id="wb-component-combustion" class="h-diag-fault-component" style="cursor:pointer;" title="Engine Block & Cylinder Combustion Chambers">
                            <rect x="156" y="148" width="16" height="34" rx="2" fill="rgba(148, 163, 184, 0.14)" stroke="#64748b" stroke-width="1"/>
                            <rect x="178" y="146" width="16" height="34" rx="2" fill="rgba(148, 163, 184, 0.14)" stroke="#64748b" stroke-width="1"/>
                            <rect x="200" y="144" width="16" height="34" rx="2" fill="rgba(148, 163, 184, 0.14)" stroke="#64748b" stroke-width="1"/>
                            <rect x="222" y="142" width="16" height="34" rx="2" fill="rgba(148, 163, 184, 0.14)" stroke="#64748b" stroke-width="1"/>
                        </g>

                        {{-- 3. Powertrain Mounts & Subframe Bushing Zone --}}
                        <g id="wb-component-mounts" class="h-diag-fault-component" onclick="focusPin('vibration')" style="cursor:pointer;" title="Subframe Engine Mounts & Chassis Dampener Zone">
                            <path d="M 134 208 Q 192 222 254 204" fill="none" stroke="#64748b" stroke-width="1.5"/>
                            <ellipse cx="140" cy="208" rx="10" ry="7" fill="rgba(249, 115, 22, 0.2)" stroke="#f97316" stroke-width="1.8"/>
                            <ellipse cx="250" cy="204" rx="10" ry="7" fill="rgba(249, 115, 22, 0.2)" stroke="#f97316" stroke-width="1.8"/>
                        </g>

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

                {{-- Pin 3: DS18B20 Coolant / Block Temperature Sensor (Contextual DB Recorded) --}}
                <div class="h-diag-pin temp-pin" style="top:38%; left:48%;" onclick="focusPin('temp')" title="DS18B20 Thermal Probe (Click to adjust contextual telemetry)">
                    <div class="h-diag-pin-core orange">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>DS18B20 Thermal Probe</strong>
                        <span>OneWire D4 · 88.5°C Recorded (Non-ML)</span>
                    </div>
                </div>

                {{-- Pin 4: INA219 Power & Battery Bus (Contextual DB Recorded) --}}
                <div class="h-diag-pin battery-pin" style="top:62%; left:62%;" onclick="focusPin('battery')" title="INA219 Voltage & Current Sensor">
                    <div class="h-diag-pin-core dark">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><rect x="1" y="6" width="18" height="12" rx="2"/><line x1="23" y1="10" x2="23" y2="14"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>INA219 Power Monitor</strong>
                        <span>I2C 0x40 · 13.8V / 420mA (Non-ML)</span>
                    </div>
                </div>

                {{-- Pin 5: Hall Tachometer RPM (Contextual DB Recorded) --}}
                <div class="h-diag-pin rpm-pin" style="top:44%; left:78%;" onclick="focusPin('rpm')" title="Hall RPM Tachometer Sensor">
                    <div class="h-diag-pin-core green">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83"/></svg>
                    </div>
                    <div class="h-diag-pin-tooltip">
                        <strong>Hall Tachometer</strong>
                        <span>GPIO D23 · Crankshaft RPM (Non-ML)</span>
                    </div>
                </div>
            </div>

            {{-- Zoom / View Toggle Buttons (Reference Image Top-Right of Car) --}}
            <div class="h-diag-viewport-controls">
                <button type="button" class="h-diag-zoom-btn" onclick="zoomVehicle(1.1)" title="Zoom in">+</button>
                <button type="button" class="h-diag-zoom-btn" onclick="zoomVehicle(0.9)" title="Zoom out">−</button>
                <button type="button" class="h-diag-zoom-btn h-diag-reset-view-btn" onclick="resetDiagnosticView()" title="Reset view" aria-label="Reset view">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>
                </button>
                <div class="h-diag-view-mode-rail" role="group" aria-label="Vehicle model view">
                    <button type="button" class="h-diag-view-mode-btn {{ $diagnosticModelUid ? 'is-active' : '' }}" id="view-mode-3d" onclick="setDiagnosticViewMode('3d')">3D</button>
                    <button type="button" class="h-diag-view-mode-btn {{ $diagnosticModelUid ? '' : 'is-active' }}" id="view-mode-2d" onclick="setDiagnosticViewMode('2d')">2D</button>
                </div>
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
                        <div class="h-diag-probe-bar orange" style="height: 0%;"></div>
                        <div class="h-diag-probe-cap orange"></div>
                    </div>
                    <div class="h-diag-probe-name" style="color:#ea580c;">Coolant</div>
                    <div class="h-diag-probe-val" id="probe-temp-val">--</div>
                </div>

                {{-- Probe 4: Hall Tachometer (Contextual) --}}
                <div class="h-diag-probe-item">
                    <div class="h-diag-probe-pillar">
                        <div class="h-diag-probe-bar blue" style="height: 0%;"></div>
                        <div class="h-diag-probe-cap"></div>
                    </div>
                    <div class="h-diag-probe-name">Tach</div>
                    <div class="h-diag-probe-val" id="probe-rpm-val">--</div>
                </div>

                {{-- Probe 5: Bus Voltage INA219 --}}
                <div class="h-diag-probe-item">
                    <div class="h-diag-probe-pillar">
                        <div class="h-diag-probe-bar blue" style="height: 0%;"></div>
                        <div class="h-diag-probe-cap"></div>
                    </div>
                    <div class="h-diag-probe-name">Voltage</div>
                    <div class="h-diag-probe-val" id="probe-volt-val">--</div>
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

        {{-- CARD 3: AUXILIARY HARDWARE TELEMETRY (DS18B20 · Hall RPM · INA219 — DB Recorded, Non-ML) --}}
        <div class="h-diag-card">
            <div class="h-diag-card-head">
                <span class="h-diag-card-title">AUXILIARY HARDWARE DAQ</span>
                <span class="h-diag-card-status-badge ok" title="Recorded into database for every session; excluded from ML feature vector per thesis scope">DB RECORDED · NON-ML</span>
            </div>

            <div class="h-diag-gauges-row">
                {{-- INA219 Voltage Arc Speedometer --}}
                <div class="h-diag-gauge-cell">
                    <div class="h-diag-gauge-top">
                        <span class="name">INA219 VOLTAGE</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </div>
                    <div class="h-diag-arc-wrap">
                        <svg width="110" height="60" viewBox="0 0 110 60">
                            <path d="M 15 50 A 40 40 0 0 1 95 50" fill="none" stroke="#e2e8f0" stroke-width="8" stroke-linecap="round"/>
                            <path id="voltage-arc-path" d="M 15 50 A 40 40 0 0 1 95 50" fill="none" stroke="#0284c7" stroke-width="8" stroke-linecap="round" stroke-dasharray="125" stroke-dashoffset="125"/>
                        </svg>
                        <div class="h-diag-arc-val" id="gauge-voltage-num">--</div>
                    </div>
                    <div class="h-diag-gauge-lbl">I2C 0x40 · DC Bus (V)</div>
                </div>

                {{-- Hall-Effect RPM Sine Wave Tachometer --}}
                <div class="h-diag-gauge-cell">
                    <div class="h-diag-gauge-top">
                        <span class="name">HALL TACH</span>
                        <span class="unit">RPM</span>
                    </div>
                    <div class="h-diag-rpm-wave-wrap">
                        <div class="h-diag-rpm-display" id="gauge-rpm-num">--</div>
                        <svg width="100" height="34" viewBox="0 0 100 34" fill="none" class="h-diag-sine-svg">
                            <path d="M 0 17 Q 15 0, 30 17 T 60 17 T 90 17 T 120 17" fill="none" stroke="#0ea5e9" stroke-width="2.5"/>
                            <circle cx="60" cy="17" r="4" fill="#0284c7"/>
                        </svg>
                    </div>
                    <div class="h-diag-gauge-lbl" id="gauge-rpm-mode">GPIO D23 · Standby</div>
                </div>
            </div>

            {{-- DS18B20 Temperature & INA219 Current Strip --}}
            <div class="h-diag-card-footer-metrics" style="margin-top:0.35rem;margin-bottom:0.45rem;">
                <div class="h-diag-footer-metric">
                    <span class="lbl">DS18B20 Temp (D4)</span>
                    <span class="val" style="color:#ea580c;" id="gauge-temp-num">--</span>
                </div>
                <div class="h-diag-footer-metric">
                    <span class="lbl">INA219 Current</span>
                    <span class="val" id="gauge-curr-num">--</span>
                </div>
                <div class="h-diag-footer-metric">
                    <button type="button" onclick="openManualTelemetryDrawer()" style="background:rgba(2,132,199,0.1);color:#0284c7;border:1px solid rgba(2,132,199,0.28);border-radius:6px;padding:0.22rem 0.5rem;font-size:0.68rem;font-weight:700;cursor:pointer;">
                        Edit DAQ
                    </button>
                </div>
            </div>

            {{-- Mode Switcher Pill inside Card --}}
            <div class="h-diag-mode-selector-strip">
                <button type="button" class="h-mode-btn active" id="btn-mode-idle" onclick="setRecordingMode('idle')">
                    Baseline Idle (850 RPM target)
                </button>
                <button type="button" class="h-mode-btn" id="btn-mode-revving" onclick="setRecordingMode('revving')">
                    Baseline Revving (2,200 RPM target)
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
                {{-- Live Audio Signal Quality Badge --}}
                <div class="h-diag-quality-pill" id="live-audio-quality-pill" style="display:none;" title="Real-time acoustic signal level & harmonic clarity">
                    <span class="dot" id="live-quality-dot"></span>
                    <span id="live-quality-txt">Checking Signal...</span>
                </div>
                <span class="h-diag-rec-dot" id="live-rec-dot"></span>
                <span class="h-diag-rec-timer" id="live-rec-timer">00:00</span>
                <span class="h-diag-rec-target">/ 00:15 target</span>
            </div>
        </div>

        {{-- Live Canvas Oscilloscope --}}
        <div class="h-diag-oscilloscope-wrap" style="position:relative;">
            <canvas id="live-oscilloscope" width="1000" height="90" class="h-diag-canvas"></canvas>
            <div class="h-diag-canvas-overlay" id="canvas-overlay-text">
                ESP32 DAQ Node: <strong style="color:#f87171;">OFFLINE</strong> · Connect ESP32 for live capture, or click <strong>"Upload Audio"</strong> (.wav/.mp3)
            </div>
            <div class="h-diag-canvas-channels" id="canvas-channels-legend" style="display:none;position:absolute;top:8px;right:12px;gap:10px;font-size:0.7rem;font-weight:700;z-index:2;background:rgba(15,23,42,0.85);padding:2px 8px;border-radius:4px;border:1px solid rgba(51,65,85,0.6);">
                <span style="color:#06b6d4;display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:2px;background:#06b6d4;display:inline-block;"></span>X (Lateral)</span>
                <span style="color:#10b981;display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:2px;background:#10b981;display:inline-block;"></span>Y (Longitudinal)</span>
                <span style="color:#f59e0b;display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:2px;background:#f59e0b;display:inline-block;"></span>Z (Vertical)</span>
            </div>
        </div>

        {{-- Workbench Control Actions --}}
        <div class="h-diag-workbench-actions">
            <div class="h-diag-actions-left" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                <button type="button" id="btn-start-rec" class="h-diag-btn-primary h-diag-btn-gated" onclick="startAudioRecording()" title="ESP32 DAQ Node is offline. Connect ESP32 hardware to enable live capture, or click Upload Audio below.">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                    <span id="btn-start-rec-text"><span style="font-size:0.8rem;margin-right:2px;">🔒</span> Live Capture (ESP32 Offline)</span>
                </button>
                <button type="button" id="btn-stop-rec" class="h-diag-btn-danger" style="display:none;" onclick="stopAudioRecording()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                    Stop Audio
                </button>
                <button type="button" id="btn-upload-audio" class="h-diag-btn-secondary" onclick="triggerAudioUpload()" title="Upload pre-recorded engine audio file (.wav, .mp3, .webm, .m4a)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Upload Audio
                </button>
                <input type="file" id="workbench-audio-input" accept=".wav,.mp3,.webm,.ogg,.m4a" style="display:none;" onchange="handleWorkbenchAudioUpload(this)">
                <button type="button" id="btn-start-vib" class="h-diag-btn-primary" style="display:none;background:#0284c7;" onclick="startVibrationRecording()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    Start Vibration Capture
                </button>
                <button type="button" id="btn-stop-vib" class="h-diag-btn-danger" style="display:none;" onclick="stopVibrationRecording()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                    Stop Vibration
                </button>
                <button type="button" id="btn-skip-vib" class="h-diag-btn-secondary" style="display:none;" onclick="skipVibrationCapture()" title="Proceed directly to ML analysis with acoustic telemetry only">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                    Skip Vibration (Audio Only)
                </button>
                <button type="button" id="btn-open-file-modal" class="h-diag-btn-secondary" onclick="openSampleUploadModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    Import Vibration CSV
                </button>
                <button type="button" id="btn-sample-telemetry" class="h-diag-btn-secondary" onclick="captureLiveTelemetrySnapshot(true)" title="Snapshot real-time Coolant Temperature, Engine RPM, and Battery Voltage from vehicle" style="display:inline-flex;align-items:center;gap:0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <span>Capture Live DAQ</span>
                </button>
                <button type="button" id="btn-manual-telemetry" class="h-diag-btn-secondary" onclick="openManualTelemetryDrawer()" title="Manually enter contextual RPM, Coolant Temp, or Voltage" style="display:inline-flex;align-items:center;gap:0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20v-6M6 20V10M18 20V4"/></svg>
                    <span>Manual DAQ</span>
                </button>
            </div>

            {{-- Audio Playback Strip (Visible after recording) --}}
            <div class="h-diag-playback-strip" id="playback-strip" style="display:none;">
                <audio id="workbench-audio-playback" controls style="height:34px;"></audio>
                <span class="h-diag-playback-meta" id="playback-duration-meta">Audio Ready</span>
            </div>

            {{-- Vibration Telemetry Strip (Visible after vibration capture) --}}
            <div class="h-diag-playback-strip" id="vibration-strip" style="display:none;border-color:rgba(6,182,212,0.4);background:rgba(6,182,212,0.08);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                <span class="h-diag-playback-meta" id="vibration-meta" style="color:var(--ds-text);">ADXL345 3-Axis Data Ingested</span>
            </div>

            {{-- 3-Component Auxiliary Telemetry Metadata Strip (RPM, Temp, Voltage) --}}
            <div class="h-diag-playback-strip" id="workbench-telemetry-strip" style="display:inline-flex;align-items:center;gap:0.6rem;padding:0.4rem 0.75rem;background:rgba(15,23,42,0.85);border:1px solid rgba(51,65,85,0.7);border-radius:8px;font-size:0.78rem;">
                <span style="font-size:0.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.04em;">Aux Hardware (Optional):</span>
                <div style="display:flex;align-items:center;gap:0.3rem;" title="Engine RPM (Hall Tachometer / D23)">
                    <span style="color:#38bdf8;font-weight:700;">RPM:</span>
                    <span id="wb-val-rpm" style="font-family:monospace;font-weight:700;color:#f8fafc;">--</span>
                </div>
                <span style="color:#475569;">·</span>
                <div style="display:flex;align-items:center;gap:0.3rem;" title="Coolant Temperature (DS18B20 / D4)">
                    <span style="color:#fb923c;font-weight:700;">Temp:</span>
                    <span id="wb-val-temp" style="font-family:monospace;font-weight:700;color:#f8fafc;">--</span>
                </div>
                <span style="color:#475569;">·</span>
                <div style="display:flex;align-items:center;gap:0.3rem;" title="Battery / Alternator Voltage (INA219 / D21-D22)">
                    <span style="color:#4ade80;font-weight:700;">Volt:</span>
                    <span id="wb-val-volt" style="font-family:monospace;font-weight:700;color:#f8fafc;">--</span>
                </div>
                <span id="wb-status-tag" style="font-size:0.62rem;padding:0.12rem 0.35rem;border-radius:4px;background:rgba(100,116,139,0.18);color:#94a3b8;border:1px solid rgba(100,116,139,0.3);font-weight:700;">NOT CAPTURED (OPTIONAL)</span>
                <button type="button" id="btn-clear-hardware" onclick="clearAuxiliaryTelemetry()" style="margin-left:0.25rem;background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.35);border-radius:4px;padding:0.18rem 0.45rem;font-size:0.68rem;cursor:pointer;font-weight:700;display:none;" title="Do not record auxiliary hardware metadata for this session">
                    ✕ Skip
                </button>
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
                        'ambient_noise' => 'badge-warn',
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
    <input type="hidden" name="vibration_source" id="form_vibration_source" value="none">
    <input type="file" name="audio_blob" id="form_audio_blob">
    <input type="file" name="audio_file" id="form_audio_file">
    <input type="file" name="vibration_file" id="form_vibration_file">
    <input type="hidden" name="audio_duration" id="form_audio_duration">
    <input type="hidden" name="temperature_c" id="form_temperature_c">
    <input type="hidden" name="rpm" id="form_rpm">
    <input type="hidden" name="voltage_v" id="form_voltage_v">
    <input type="hidden" name="current_ma" id="form_current_ma">
    <input type="hidden" name="true_condition" id="form_true_condition">
    <input type="hidden" name="session_id" id="form_session_id">
    <input type="hidden" name="fault_detail" id="form_fault_detail">
    <input type="hidden" name="target_rpm" id="form_target_rpm">
    <input type="hidden" name="load_state" id="form_load_state">
    <input type="hidden" name="sensor_position" id="form_sensor_position">
    <input type="hidden" name="mic_position" id="form_mic_position">
    <input type="hidden" name="operator" id="form_operator">
    <input type="hidden" name="verified_by" id="form_verified_by">
    <input type="hidden" name="sample_rate_hz" id="form_sample_rate_hz">
    <input type="hidden" name="is_training_sample" id="form_is_training_sample" value="0">
</form>

{{-- ═══════════════════════════════════════════════════════════════
     MODAL: VIBRATION CSV IMPORT (ESP32 ADXL345 Telemetry)
     ═══════════════════════════════════════════════════════════════ --}}
<div id="modal-sample-upload" class="h-diag-modal-backdrop" style="display:none;" onclick="if(event.target===this) closeSampleUploadModal()">
    <div class="h-diag-modal-box">
        <div class="h-diag-modal-header">
            <h3>Import ADXL345 Vibration Data</h3>
            <button type="button" class="h-diag-modal-close" onclick="closeSampleUploadModal()">&times;</button>
        </div>
        <div class="h-diag-modal-body">
            <p style="font-size:0.82rem;color:#64748b;margin-bottom:1.25rem;">
                Attach external ESP32 ADXL345 accelerometer data (.csv or .json) to pair with the live acoustic engine recording.
            </p>
            <div class="h-diag-modal-group">
                <label style="display:block;font-size:0.82rem;font-weight:700;color:var(--ds-text);margin-bottom:0.4rem;">Vibration Accelerometer Data (CSV/JSON)</label>
                <input type="file" id="modal_vib_input" accept=".csv,.json,.txt" class="h-diag-file-input" onchange="previewModalVibration(this)">
                <div id="modal-vib-summary" style="margin-top:0.5rem;font-size:0.75rem;color:#0284c7;display:none;"></div>
            </div>
        </div>
        <div class="h-diag-modal-footer">
            <button type="button" class="h-diag-btn-secondary" onclick="closeSampleUploadModal()">Cancel</button>
            <button type="button" class="h-diag-btn-primary" onclick="applySampleUploads()">Attach Vibration CSV</button>
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
                    <input type="number" step="0.5" id="man_temp" value="" placeholder="e.g. 88.5" class="h-diag-file-input">
                </div>
                <div>
                    <label style="font-size:0.75rem;font-weight:600;color:#334155;">Tachometer RPM (Hall)</label>
                    <input type="number" id="man_rpm" value="" placeholder="e.g. 850" class="h-diag-file-input">
                </div>
                <div>
                    <label style="font-size:0.75rem;font-weight:600;color:#334155;">DC Bus Voltage (V · INA219)</label>
                    <input type="number" step="0.1" id="man_volt" value="" placeholder="e.g. 13.8" class="h-diag-file-input">
                </div>
                <div>
                    <label style="font-size:0.75rem;font-weight:600;color:#334155;">Current Draw (mA)</label>
                    <input type="number" step="10" id="man_curr" value="" placeholder="e.g. 420" class="h-diag-file-input">
                </div>
            </div>

            <hr style="border:none;border-top:1px solid var(--ds-border);margin:1.1rem 0;">
            <p style="font-size:0.78rem;font-weight:700;color:var(--ds-text);margin-bottom:0.65rem;">
                Ground-Truth Labeling &amp; Session Metadata (Optional for Training)
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                <div>
                    <label style="font-size:0.72rem;font-weight:600;color:#334155;">True Engine Condition (Ground Truth)</label>
                    <select id="man_true_condition" class="h-diag-file-input">
                        <option value="">-- Unlabeled (Inference Only) --</option>
                        <option value="normal">Normal Engine Operation</option>
                        <option value="misfire">Engine Misfire</option>
                        <option value="loose_component">Loose or Worn Component</option>
                        <option value="valve_clearance">Improper Valve Clearance</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.72rem;font-weight:600;color:#334155;">Session ID</label>
                    <input type="text" id="man_session_id" placeholder="e.g. SESS-2026-001" class="h-diag-file-input">
                </div>
                <div>
                    <label style="font-size:0.72rem;font-weight:600;color:#334155;">Target RPM</label>
                    <input type="number" id="man_target_rpm" placeholder="e.g. 850" class="h-diag-file-input">
                </div>
                <div>
                    <label style="font-size:0.72rem;font-weight:600;color:#334155;">Load State</label>
                    <input type="text" id="man_load_state" placeholder="e.g. Park / Neutral AC Off" class="h-diag-file-input">
                </div>
                <div>
                    <label style="font-size:0.72rem;font-weight:600;color:#334155;">Sensor Position</label>
                    <input type="text" id="man_sensor_position" placeholder="e.g. Cylinder head cover" class="h-diag-file-input">
                </div>
                <div>
                    <label style="font-size:0.72rem;font-weight:600;color:#334155;">Mic Position</label>
                    <input type="text" id="man_mic_position" placeholder="e.g. 15cm above valve cover" class="h-diag-file-input">
                </div>
                <div>
                    <label style="font-size:0.72rem;font-weight:600;color:#334155;">Operator</label>
                    <input type="text" id="man_operator" placeholder="Operator name" class="h-diag-file-input">
                </div>
                <div>
                    <label style="font-size:0.72rem;font-weight:600;color:#334155;">Verified By</label>
                    <input type="text" id="man_verified_by" placeholder="Mechanic / Verifier" class="h-diag-file-input">
                </div>
                <div>
                    <label style="font-size:0.72rem;font-weight:600;color:#334155;">Sample Rate (Hz)</label>
                    <input type="number" id="man_sample_rate_hz" placeholder="e.g. 1000" class="h-diag-file-input">
                </div>
                <div style="display:flex;align-items:center;gap:0.45rem;padding-top:1.1rem;">
                    <input type="checkbox" id="man_is_training_sample" value="1">
                    <label for="man_is_training_sample" style="font-size:0.74rem;font-weight:700;color:#0284c7;cursor:pointer;">Include in ML Training Dataset</label>
                </div>
                <div style="grid-column:1 / -1;">
                    <label style="font-size:0.72rem;font-weight:600;color:#334155;">Fault Detail / Verification Notes</label>
                    <input type="text" id="man_fault_detail" placeholder="Specific mechanical fault observation..." class="h-diag-file-input">
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
    gap: 0.75rem;
    flex-wrap: wrap;
}
.h-diag-topbar-left {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.45rem;
}
.h-diag-view-mode-row {
    display: flex;
    align-items: center;
    min-height: 36px;
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
.h-diag-vehicle-dropdown-wrap {
    position: relative;
}
.h-diag-dropdown-trigger {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    border: 0;
    background: transparent;
    color: var(--ds-text);
    font: inherit;
    font-weight: 700;
    font-size: 0.86rem;
    padding: 0;
    cursor: pointer;
}
.h-diag-vehicle-year {
    color: var(--ds-text-muted);
    font-size: 0.75rem;
    font-weight: 700;
}
.h-diag-dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    width: min(300px, 92vw);
    background: #111827;
    border: 1px solid var(--ds-border);
    border-radius: 14px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.35);
    z-index: 999;
    overflow: hidden;
}
.h-diag-dropdown-header {
    padding: 0.65rem 0.85rem;
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--ds-text-muted);
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
    gap: 0.75rem;
    padding: 0.75rem 0.85rem;
    text-decoration: none;
    color: var(--ds-text);
    background: #111827;
    border-bottom: 1px solid var(--ds-border);
    transition: background 0.15s;
}
.h-diag-dropdown-item:hover,
.h-diag-dropdown-item.is-active {
    background: #1e293b;
}
.h-diag-dropdown-item-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.h-diag-dropdown-v-icon {
    color: var(--ds-blue);
}
.h-diag-dropdown-v-name {
    font-size: 0.82rem;
    font-weight: 700;
}
.h-diag-dropdown-v-meta,
.h-diag-dropdown-v-stat {
    color: var(--ds-text-muted);
    font-size: 0.68rem;
}
.h-diag-dropdown-footer {
    border-top: 1px solid var(--ds-border);
}
.h-diag-dropdown-add {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.75rem 0.85rem;
    color: var(--ds-blue);
    font-size: 0.75rem;
    font-weight: 700;
    text-decoration: none;
}
.h-diag-dropdown-empty {
    padding: 0.85rem;
    color: var(--ds-text-muted);
    font-size: 0.75rem;
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
.h-diag-topbar .h-diag-mode-pill {
    min-height: 34px;
    box-sizing: border-box;
    justify-content: center;
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
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
.h-diag-daq-pill.is-offline {
    background: rgba(239, 68, 68, 0.1) !important;
    border-color: rgba(239, 68, 68, 0.25) !important;
    color: #f87171 !important;
}
.h-diag-daq-pill.is-offline .h-diag-live-dot {
    background: #ef4444 !important;
    box-shadow: none !important;
    animation: none !important;
}
.h-diag-btn-gated {
    opacity: 0.65 !important;
    cursor: not-allowed !important;
    border-color: rgba(148, 163, 184, 0.3) !important;
    filter: saturate(0.6);
}
.h-diag-btn-gated:hover {
    transform: none !important;
    box-shadow: none !important;
}
@keyframes workbenchFocusPulse {
    0% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.7), 0 0 28px rgba(2, 132, 199, 0.5); border-color: #38bdf8; }
    50% { box-shadow: 0 0 0 8px rgba(56, 189, 248, 0), 0 0 36px rgba(56, 189, 248, 0.45); border-color: #38bdf8; }
    100% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0); }
}
.h-diag-workbench-focus-pulse {
    animation: workbenchFocusPulse 1.8s ease-out;
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
.h-diagnosis-flow {
    display: grid;
    grid-template-columns: auto 1fr auto 1fr auto 1fr auto 1fr auto;
    align-items: center;
    width: min(100%, 480px);
    margin-bottom: 1rem;
}
.h-diagnosis-flow-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.28rem;
    color: var(--ds-text-dim);
    font-size: 0.68rem;
    font-weight: 700;
}
.h-diagnosis-flow-number {
    display: grid;
    place-items: center;
    width: 24px;
    height: 24px;
    border: 1px solid var(--ds-border);
    border-radius: 50%;
    background: var(--ds-surface-subtle);
    font-size: 0.72rem;
}
.h-diagnosis-flow-step.is-active {
    color: var(--ds-blue);
}
.h-diagnosis-flow-step.is-active .h-diagnosis-flow-number {
    border-color: var(--ds-blue);
    background: var(--ds-blue);
    color: #fff;
}
.h-diagnosis-flow-step.is-complete {
    color: var(--ds-text-muted);
}
.h-diagnosis-flow-step.is-complete .h-diagnosis-flow-number {
    border-color: rgba(34, 197, 94, 0.55);
    background: rgba(34, 197, 94, 0.14);
    color: #16a34a;
}
.h-diagnosis-flow-line {
    height: 1px;
    margin: 0 0.35rem 1rem;
    background: var(--ds-border);
}
.h-diagnosis-flow-line.is-complete {
    background: rgba(34, 197, 94, 0.55);
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
.h-ticker-item.completed {
    color: #16a34a;
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
.h-ticker-item.completed .h-ticker-bullet {
    background: #16a34a;
    box-shadow: 0 0 6px #16a34a;
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
#diagnostic-sketchfab-stage {
    position: absolute;
    inset: 0;
    z-index: 2;
    min-width: 0;
    min-height: 380px;
    background: transparent;
    overflow: hidden;
    border-radius: var(--ds-radius-card, 16px);
}
#diagnostic-sketchfab-frame {
    position: absolute;
    top: -60px;
    left: 0;
    width: 100%;
    height: calc(100% + 118px);
    min-height: calc(380px + 118px);
    border: 0;
    display: block;
    pointer-events: auto;
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
    z-index: 3;
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
    box-shadow: none;
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

/* ── 3D Vehicle Component Overlays (Workbench Inspection) ── */
.h-diag-fault-component {
    transition: all 0.3s ease;
}
.h-diag-fault-component:hover .h-diag-component-path {
    stroke-width: 2.5px;
    filter: drop-shadow(0 0 6px rgba(56, 189, 248, 0.6));
}
.h-diag-valvetrain-tap {
    transition: all 0.2s ease;
}
.recording-audio #wb-component-valvetrain .h-diag-valvetrain-tap {
    animation: liveValvePulse 0.45s infinite alternate ease-in-out;
}
.recording-audio #wb-component-valvetrain .h-diag-valvetrain-tap.delay-1 { animation-delay: 0.11s; }
.recording-audio #wb-component-valvetrain .h-diag-valvetrain-tap.delay-2 { animation-delay: 0.22s; }
.recording-audio #wb-component-valvetrain .h-diag-valvetrain-tap.delay-3 { animation-delay: 0.33s; }
@keyframes liveValvePulse {
    0% { transform: scale(1); opacity: 0.7; }
    100% { transform: scale(1.6); opacity: 1; fill: #38bdf8; }
}
.recording-vib #wb-component-mounts {
    animation: liveMountVib 0.4s infinite alternate ease-in-out;
}
@keyframes liveMountVib {
    0% { transform: translateX(-2px); }
    100% { transform: translateX(2px); }
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
.h-diag-view-mode-rail {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
    padding: 0;
    border: 1px solid var(--ds-border);
    border-radius: 8px;
    overflow: hidden;
    background: var(--ds-surface);
    box-shadow: none;
}
.h-diag-view-mode-btn {
    width: 38px;
    height: 30px;
    border: 0;
    border-radius: 0;
    background: transparent;
    color: var(--ds-text-muted);
    font-size: 0.76rem;
    font-weight: 800;
    cursor: pointer;
}
.h-diag-view-mode-btn.is-active {
    background: #111827;
    color: #fff;
    box-shadow: none;
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
.h-diag-quality-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.22rem 0.65rem;
    border-radius: var(--ds-radius-pill);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    transition: all 0.25s ease;
}
.h-diag-quality-pill .dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    display: inline-block;
}
.h-diag-quality-pill.optimal {
    background: rgba(16, 185, 129, 0.14);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.35);
}
.h-diag-quality-pill.optimal .dot {
    background: #10b981;
    box-shadow: 0 0 6px #10b981;
}
.h-diag-quality-pill.low {
    background: rgba(245, 158, 11, 0.14);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.35);
}
.h-diag-quality-pill.low .dot {
    background: #f59e0b;
    box-shadow: 0 0 6px #f59e0b;
}
.h-diag-quality-pill.clipping {
    background: rgba(239, 68, 68, 0.14);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.35);
}
.h-diag-quality-pill.clipping .dot {
    background: #ef4444;
    box-shadow: 0 0 6px #ef4444;
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
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(15, 23, 42, 0.7) !important;
    backdrop-filter: blur(8px) !important;
    z-index: 99999 !important;
    display: none;
    align-items: center !important;
    justify-content: center !important;
    padding: 1.5rem !important;
    margin: 0 !important;
    overflow-y: auto !important;
}
.h-diag-modal-backdrop.is-open {
    display: flex !important;
}
.h-diag-modal-box {
    background: #ffffff;
    border-radius: 18px;
    max-width: 500px;
    width: 100%;
    padding: 1.5rem;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    margin: auto !important;
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
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
[data-theme="dark"] .h-diag-daq-pill.is-offline {
    background: rgba(239, 68, 68, 0.12) !important;
    color: #f87171 !important;
    border-color: rgba(239, 68, 68, 0.3) !important;
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
    #diagnostic-sketchfab-stage {
        min-height: 220px !important;
    }
    #diagnostic-sketchfab-frame {
        min-height: calc(220px + 118px) !important;
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
    const diagnosticModelUid = @json($diagnosticModelUid);
    let diagnosticSketchfabApi = null;
    let diagnosticViewerReady = false;
    let diagnosticApiAttempts = 0;
    const diagnosticInitialCamera = [0, 1.35, 3.25];

    function setDiagnosticInitialCamera(api, delay = 0) {
        if (!api) return;
        window.setTimeout(function() {
            api.getBoundingBox(function(error, bounds) {
                if (!error && bounds && bounds.min && bounds.max) {
                    const center = bounds.min.map(function(value, index) {
                        return (value + bounds.max[index]) / 2;
                    });
                    const size = bounds.max.map(function(value, index) {
                        return value - bounds.min[index];
                    });
                    const radius = Math.max(...size) / 2;
                    const distanceScale = radius > 0 ? Math.max(2.4, Math.min(3.6, 5.8 / radius)) : 3.2;
                    const eye = [
                        center[0] + radius * 1.25,
                        center[1] + radius * (0.65 * distanceScale / 3.2),
                        center[2] + radius * 1.8 * distanceScale / 3.2
                    ];
                    api.setCameraLookAt(eye, center, 0.45);
                    return;
                }
                api.setCameraLookAt(diagnosticInitialCamera, [0, 0, 0], 0.45);
            });
        }, delay);
    }

    function initDiagnosticSketchfab() {
        const iframe = document.getElementById('diagnostic-sketchfab-frame');
        const stage = document.getElementById('diagnostic-sketchfab-stage');
        if (!diagnosticModelUid || !iframe || !stage || !window.Sketchfab) return;

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
            ui_theme: 'dark',
            ui_ar: '0',
            ui_vr: '0',
            dnt: '1'
        });
        iframe.src = `https://sketchfab.com/models/${diagnosticModelUid}/embed?${embedParams.toString()}`;

        const client = new window.Sketchfab(iframe);
        client.init(diagnosticModelUid, {
            success: function(api) {
                diagnosticSketchfabApi = api;
                api.start();
                api.addEventListener('viewerready', function() {
                    diagnosticViewerReady = true;
                    setDiagnosticInitialCamera(api, 0);
                    setDiagnosticInitialCamera(api, 500);
                    setDiagnosticInitialCamera(api, 1200);
                    window.dispatchEvent(new Event('resize'));
                });
            },
            error: function() {
                diagnosticSketchfabApi = null;
            },
            autostart: 1,
            transparent: 1,
            ui_controls: 0,
            ui_infos: 0,
            ui_watermark: 0,
            ui_help: 0,
            ui_settings: 0,
            ui_annotations: 0
        });
    }

    function waitForDiagnosticSketchfab() {
        if (window.Sketchfab) {
            initDiagnosticSketchfab();
            return;
        }
        diagnosticApiAttempts += 1;
        if (diagnosticApiAttempts >= 40) {
            const iframe = document.getElementById('diagnostic-sketchfab-frame');
            if (iframe && diagnosticModelUid) {
                iframe.src = `https://sketchfab.com/models/${diagnosticModelUid}/embed?autostart=1&transparent=1&ui_controls=0&ui_infos=0&ui_watermark=0&ui_help=0&ui_settings=0&ui_annotations=0&ui_theme=dark&ui_hint=0&ui_ar=0&ui_vr=0&dnt=1`;
            }
            return;
        }
        window.setTimeout(waitForDiagnosticSketchfab, 250);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', waitForDiagnosticSketchfab);
    } else {
        waitForDiagnosticSketchfab();
    }

    // 1. Vehicle Switching
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
    });

    window.switchVehicle = function(vehicleId) {
        if (!vehicleId) return;
        const url = new URL(window.location.href);
        url.searchParams.set('vehicle_id', vehicleId);
        window.location.href = url.toString();
    };

    // 2D / 3D vehicle presentation
    let diagnosticViewMode = diagnosticModelUid ? '3d' : '2d';
    window.setDiagnosticViewMode = function(mode) {
        const stage3d = document.getElementById('diagnostic-sketchfab-stage');
        const stage2d = document.getElementById('car-3d-model');
        const button3d = document.getElementById('view-mode-3d');
        const button2d = document.getElementById('view-mode-2d');

        if (!stage3d || !stage2d || !button3d || !button2d) return;
        if (mode === '3d' && !diagnosticModelUid) {
            alert('No 3D asset mapped for this specific vehicle. Showing 2D vector schematic.');
            mode = '2d';
        }

        diagnosticViewMode = mode;
        stage3d.style.display = mode === '3d' ? 'block' : 'none';
        stage2d.style.display = mode === '2d' ? 'block' : 'none';
        button3d.classList.toggle('is-active', mode === '3d');
        button2d.classList.toggle('is-active', mode === '2d');
        button3d.disabled = !diagnosticModelUid;

        if (mode === '3d') {
            initDiagnosticSketchfab();
            window.dispatchEvent(new Event('resize'));
            setDiagnosticInitialCamera(diagnosticSketchfabApi, 350);
        }
    };
    window.setDiagnosticViewMode(diagnosticViewMode);

    let zoomLevel = 1.0;
    window.resetDiagnosticView = function() {
        zoomLevel = 1.0;
        const car = document.getElementById('car-3d-model');
        if (car) car.style.transform = `scale(${zoomLevel})`;
        if (diagnosticSketchfabApi) {
            setDiagnosticInitialCamera(diagnosticSketchfabApi, 0);
        }
    };
    window.zoomVehicle = function(factor) {
        const car = document.getElementById('car-3d-model');
        if (!car) return;
        zoomLevel = Math.max(0.8, Math.min(1.4, zoomLevel * factor));
        car.style.transform = `scale(${zoomLevel})`;

        if (diagnosticSketchfabApi) {
            diagnosticSketchfabApi.getCameraLookAt(function(error, camera) {
                if (error || !camera) return;
                const scale = factor > 1 ? 0.9 : 1.1;
                const eye = camera.position.map(function(value, index) {
                    return value * scale + camera.target[index] * (1 - scale);
                });
                diagnosticSketchfabApi.setCameraLookAt(eye, camera.target, 0.35);
            });
        }
    };

    // 3. Interactive Marker Focus
    window.focusPin = function(type) {
        if (type === 'audio') {
            document.getElementById('recording-workbench').scrollIntoView({ behavior: 'smooth' });
            startAudioRecording();
        } else if (type === 'vibration') {
            document.getElementById('recording-workbench').scrollIntoView({ behavior: 'smooth' });
            startVibrationRecording();
        } else if (type === 'battery' || type === 'rpm' || type === 'temp') {
            openManualTelemetryDrawer();
        }
    };

    // 4. Live Multi-Stage Telemetry Engine (Web Audio API + ADXL345 3-Axis Accelerometer)
    // Flow State Machine: 'IDLE' -> 'RECORDING_AUDIO' -> 'AUDIO_DONE' -> 'RECORDING_VIBRATION' -> 'VIBRATION_DONE' -> 'ANALYZING'
    let captureState = 'IDLE';
    let mediaRecorder, audioChunks = [], audioBlob, analyser, animFrame, audioCtx, recordTimer, recSeconds = 0;
    let vibInterval = null, vibSeconds = 0, vibrationSamples = [], vibAnimFrame = null;
    let checkLiveDaqInterval = null;
    let lastVibPacketId = null;
    let vibStartMs = 0;
    window.isEsp32Online = false;
    window.latestEsp32Accel = null;

    const canvas = document.getElementById('live-oscilloscope');
    const canvasCtx = canvas ? canvas.getContext('2d') : null;

    window.setDiagnosisFlowStep = function(activeStep) {
        const steps = ['vehicle', 'capture', 'vibration', 'analyze', 'results'];
        const activeIndex = steps.indexOf(activeStep);
        if (activeIndex < 0) return;

        document.querySelectorAll('[data-flow-step]').forEach(function(stepElement) {
            const stepIndex = steps.indexOf(stepElement.dataset.flowStep);
            stepElement.classList.toggle('is-complete', stepIndex < activeIndex);
            stepElement.classList.toggle('is-active', stepIndex === activeIndex);
        });
        document.querySelectorAll('.h-diagnosis-flow-line').forEach(function(line, index) {
            line.classList.toggle('is-complete', index < activeIndex);
        });
    };

    // ─────────────────────────────────────────────
    // Stage 1: Live Audio Recording (Acoustics)
    // ─────────────────────────────────────────────
    window.startAudioRecording = async function() {
        if (!window.isEsp32Online) {
            alert('⚠️ ESP32 DAQ Hardware Node Offline\n\nLive acoustic capture requires an active ESP32 sensor node streaming over Wi-Fi.\n\nTo diagnose without the physical ESP32 node, please use "Upload Audio" (.wav/.mp3) or "Import Vibration CSV".');
            const uploadBtn = document.getElementById('btn-upload-audio');
            if (uploadBtn) {
                uploadBtn.focus();
                uploadBtn.style.outline = '2px solid #38bdf8';
                setTimeout(() => { uploadBtn.style.outline = ''; }, 2500);
            }
            return;
        }

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

            captureState = 'RECORDING_AUDIO';
            const carModel = document.getElementById('car-3d-model');
            if (carModel) carModel.classList.add('recording-audio');

            // Snapshot live hardware DAQ telemetry (RPM, Temp, Voltage) at recording start
            if (typeof window.captureLiveTelemetrySnapshot === 'function') {
                window.captureLiveTelemetrySnapshot(false);
            }

            // UI adjustments
            document.getElementById('btn-start-rec').style.display = 'none';
            const uploadBtn = document.getElementById('btn-upload-audio');
            if (uploadBtn) uploadBtn.style.display = 'none';
            document.getElementById('btn-stop-rec').style.display = 'inline-flex';
            document.getElementById('btn-start-vib').style.display = 'none';
            document.getElementById('btn-stop-vib').style.display = 'none';
            document.getElementById('live-rec-dot').classList.add('active');
            document.getElementById('canvas-overlay-text').style.display = 'none';
            const channels = document.getElementById('canvas-channels-legend');
            if (channels) channels.style.display = 'none';
            const qPill = document.getElementById('live-audio-quality-pill');
            if (qPill) {
                qPill.style.display = 'inline-flex';
                qPill.className = 'h-diag-quality-pill optimal';
                const qTxt = document.getElementById('live-quality-txt');
                if (qTxt) qTxt.textContent = 'INMP441 I2S Correlated';
            }

            document.getElementById('hud-progress-label').textContent = 'RECORDING AUDIO HARMONICS (INMP441 I2S CORRELATED)...';
            document.getElementById('hud-progress-pct').textContent = '25%';
            window.setDiagnosisFlowStep('capture');

            // Start timer
            recSeconds = 0;
            document.getElementById('live-rec-timer').textContent = '00:00';
            clearInterval(recordTimer);
            recordTimer = setInterval(() => {
                recSeconds++;
                const mins = String(Math.floor(recSeconds / 60)).padStart(2, '0');
                const secs = String(recSeconds % 60).padStart(2, '0');
                document.getElementById('live-rec-timer').textContent = `${mins}:${secs}`;
                if (recSeconds >= 15) {
                    document.getElementById('hud-progress-pct').textContent = '45%';
                }
            }, 1000);

            drawOscilloscope();
        } catch (err) {
            console.warn('Microphone error:', err);
            alert('Microphone permission required for live diagnostic audio capture. Please allow microphone access in your browser to record engine acoustic harmonics.');
        }
    };

    window.stopAudioRecording = function() {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
        clearInterval(recordTimer);
        cancelAnimationFrame(animFrame);
        document.getElementById('btn-start-rec').style.display = 'none';
        document.getElementById('btn-stop-rec').style.display = 'none';
        document.getElementById('live-rec-dot').classList.remove('active');
        const carModel = document.getElementById('car-3d-model');
        if (carModel) carModel.classList.remove('recording-audio');
    };

    function encodeAudioBufferToMonoWav16(audioBuffer) {
        const numChannels = audioBuffer.numberOfChannels || 1;
        const sampleRate = audioBuffer.sampleRate || 44100;
        const length = audioBuffer.length;
        const mono = new Float32Array(length);

        for (let ch = 0; ch < numChannels; ch++) {
            const chData = audioBuffer.getChannelData(ch);
            for (let i = 0; i < length; i++) {
                mono[i] += chData[i] / numChannels;
            }
        }

        const dataBytes = length * 2;
        const buffer = new ArrayBuffer(44 + dataBytes);
        const view = new DataView(buffer);

        const writeStr = (offset, str) => {
            for (let i = 0; i < str.length; i++) {
                view.setUint8(offset + i, str.charCodeAt(i));
            }
        };

        writeStr(0, 'RIFF');
        view.setUint32(4, 36 + dataBytes, true);
        writeStr(8, 'WAVE');
        writeStr(12, 'fmt ');
        view.setUint32(16, 16, true);          // PCM chunk size
        view.setUint16(20, 1, true);           // Format = 1 (Linear PCM)
        view.setUint16(22, 1, true);           // Channels = 1 (Mono)
        view.setUint32(24, sampleRate, true);  // Sample rate
        view.setUint32(28, sampleRate * 2, true); // Byte rate
        view.setUint16(32, 2, true);           // Block align
        view.setUint16(34, 16, true);          // Bits per sample = 16
        writeStr(36, 'data');
        view.setUint32(40, dataBytes, true);

        let offset = 44;
        for (let i = 0; i < length; i++) {
            const clamped = Math.max(-1, Math.min(1, mono[i]));
            const int16 = clamped < 0 ? Math.round(clamped * 0x8000) : Math.round(clamped * 0x7FFF);
            view.setInt16(offset, int16, true);
            offset += 2;
        }

        return new Blob([buffer], { type: 'audio/wav' });
    }

    async function finalizeAudioCapture(stream) {
        if (stream) {
            stream.getTracks().forEach(t => t.stop());
        }
        const rawMime = (mediaRecorder && mediaRecorder.mimeType) ? mediaRecorder.mimeType : 'audio/webm';
        const rawBlob = new Blob(audioChunks, { type: rawMime });
        let uploadExt = 'webm';
        let uploadMime = 'audio/webm';
        audioBlob = rawBlob;

        // Transcode browser capture to uncompressed 16-bit mono PCM WAV so Python DSP decodes natively without ffmpeg
        try {
            const decodeCtx = (audioCtx && audioCtx.state !== 'closed')
                ? audioCtx
                : new (window.AudioContext || window.webkitAudioContext)();
            const arrayBuf = await rawBlob.arrayBuffer();
            const decoded = await decodeCtx.decodeAudioData(arrayBuf.slice(0));
            audioBlob = encodeAudioBufferToMonoWav16(decoded);
            uploadExt = 'wav';
            uploadMime = 'audio/wav';
        } catch (wavErr) {
            console.warn('WAV PCM conversion fallback:', wavErr);
        }

        // Bind to hidden form
        const file = new File([audioBlob], 'engine_audio_' + Date.now() + '.' + uploadExt, { type: uploadMime });
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('form_audio_blob').files = dt.files;
        document.getElementById('form_audio_duration').value = recSeconds;

        // Playback preview
        const audioUrl = URL.createObjectURL(audioBlob);
        const player = document.getElementById('workbench-audio-playback');
        player.src = audioUrl;
        document.getElementById('playback-strip').style.display = 'flex';
        document.getElementById('playback-duration-meta').textContent = `${recSeconds}s Audio Ingested`;

        // Update pipeline state & guide into Phase 2: Vibration
        captureState = 'AUDIO_DONE';
        document.getElementById('tick-audio').classList.add('active');
        document.getElementById('tick-audio').classList.add('completed');
        document.getElementById('tick-vib').classList.add('active');

        // Stepper: advance to Vibration
        window.setDiagnosisFlowStep('vibration');
        document.getElementById('hud-progress-pct').textContent = '50%';
        document.getElementById('hud-progress-label').textContent = 'ACOUSTIC CAPTURED · PROCEED TO VIBRATION';

        const condChip = document.getElementById('hud-condition-chip');
        if (condChip) {
            condChip.className = 'h-diag-status-chip warn';
            const icon = document.getElementById('hud-condition-icon');
            if (icon) icon.textContent = '⚡';
            const name = document.getElementById('hud-condition-name');
            if (name) name.textContent = 'PHASE 2: VIBRATION';
        }

        // Workbench controls
        document.getElementById('btn-start-rec').style.display = 'none';
        const uploadBtn = document.getElementById('btn-upload-audio');
        if (uploadBtn) uploadBtn.style.display = 'none';
        document.getElementById('btn-stop-rec').style.display = 'none';
        document.getElementById('btn-start-vib').style.display = 'inline-flex';
        document.getElementById('btn-stop-vib').style.display = 'none';
        const skipVibBtn = document.getElementById('btn-skip-vib');
        if (skipVibBtn) skipVibBtn.style.display = 'inline-flex';

        // Master CTA update: If no physical sensor attached, offer Audio-Only ML inference directly
        const hasHardwareVib = window.isEsp32Online;
        const btnStartVib = document.getElementById('btn-start-vib');
        if (btnStartVib) {
            if (!hasHardwareVib) {
                btnStartVib.classList.add('h-diag-btn-gated');
                btnStartVib.innerHTML = '<span style="font-size:0.8rem;margin-right:2px;">🔒</span> Live Vibration (ESP32 Offline)';
                btnStartVib.title = 'ESP32 DAQ is offline. Import a vibration CSV or skip vibration for audio-only analysis.';
            } else {
                btnStartVib.classList.remove('h-diag-btn-gated');
                btnStartVib.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg> Start Vibration Capture';
                btnStartVib.title = 'Start live vibration capture from ESP32 ADXL345 node';
            }
        }

        // Overlay Guide
        const overlay = document.getElementById('canvas-overlay-text');
        if (overlay) {
            overlay.innerHTML = hasHardwareVib
                ? 'Phase 1 Complete. Click <strong>"Start Vibration Capture"</strong> for 3-axis accelerometer data, or <strong>"Skip Vibration"</strong> / <strong>"Analyze Diagnostic"</strong>.'
                : 'Acoustic Capture Complete. Click <strong>"Analyze Diagnostic"</strong> for instant classification, or <strong>"Import Vibration CSV"</strong> to attach vibration data.';
            overlay.style.display = 'block';
        }

        // Allow direct submission if user wants audio-only
        document.getElementById('btn-submit-session').disabled = false;
    }

    // ─────────────────────────────────────────────
    // Acoustic Signal Quality Checker & Oscilloscope
    // ─────────────────────────────────────────────
    function updateSignalQualityBadge(rms, peak, isStatic = false) {
        const pill = document.getElementById('live-audio-quality-pill');
        const txt = document.getElementById('live-quality-txt');
        if (!pill || !txt) return;

        pill.style.display = 'inline-flex';

        if (peak > 0.96) {
            pill.className = 'h-diag-quality-pill clipping';
            txt.textContent = isStatic ? 'Distortion Risk (Peak > 96%)' : 'Distortion / High Gain';
            pill.title = 'Acoustic amplitude is clipping (>96% dynamic range). Reduce microphone gain or avoid direct wind buffeting.';
        } else if (rms < 0.015) {
            pill.className = 'h-diag-quality-pill low';
            txt.textContent = isStatic ? 'Low Level (RMS < 1.5%)' : 'Low Level (Move Closer)';
            pill.title = 'Acoustic energy is very quiet (<1.5% RMS). Place microphone closer to the valve cover / engine bay.';
        } else {
            pill.className = 'h-diag-quality-pill optimal';
            txt.textContent = isStatic ? 'Optimal Harmonics (Clean SNR)' : 'Optimal Engine Harmonics';
            pill.title = 'Clean signal-to-noise ratio and balanced periodic cylinder firing harmonics detected.';
        }
    }

    function renderStaticWaveform(audioBuffer) {
        if (!canvas || !canvasCtx) return;
        const w = canvas.width;
        const h = canvas.height;
        canvasCtx.fillStyle = '#0f172a';
        canvasCtx.fillRect(0, 0, w, h);

        const data = audioBuffer.getChannelData(0);
        const step = Math.ceil(data.length / w);
        const amp = h / 2;

        const grad = canvasCtx.createLinearGradient(0, h, 0, 0);
        grad.addColorStop(0, '#0284c7');
        grad.addColorStop(1, '#38bdf8');
        canvasCtx.fillStyle = grad;

        for (let i = 0; i < w; i++) {
            let min = 1.0;
            let max = -1.0;
            const start = i * step;
            for (let j = 0; j < step && (start + j) < data.length; j++) {
                const val = data[start + j];
                if (val < min) min = val;
                if (val > max) max = val;
            }
            const barHeight = Math.max(3, (max - min) * amp * 0.95);
            canvasCtx.fillRect(i, (h - barHeight) / 2, 1, barHeight);
        }
    }

    function drawOscilloscope() {
        if (!analyser || !canvasCtx) return;
        const bufferLength = analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        const timeData = new Uint8Array(bufferLength);
        let frameCount = 0;

        function render() {
            if (captureState !== 'RECORDING_AUDIO') return;
            animFrame = requestAnimationFrame(render);
            analyser.getByteFrequencyData(dataArray);
            analyser.getByteTimeDomainData(timeData);

            frameCount++;
            if (frameCount % 10 === 0) {
                let sumSq = 0;
                let peak = 0;
                for (let i = 0; i < bufferLength; i++) {
                    const norm = (timeData[i] - 128) / 128;
                    const absN = Math.abs(norm);
                    if (absN > peak) peak = absN;
                    sumSq += norm * norm;
                }
                const rms = Math.sqrt(sumSq / bufferLength);
                updateSignalQualityBadge(rms, peak, false);
            }

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

    // ─────────────────────────────────────────────
    // Pre-Recorded Engine Audio File Upload
    // ─────────────────────────────────────────────
    window.triggerAudioUpload = function() {
        const inp = document.getElementById('workbench-audio-input');
        if (inp) inp.click();
    };

    window.handleWorkbenchAudioUpload = async function(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];

        // Size check (max 50MB)
        if (file.size > 52428800) {
            alert('Audio file size exceeds the 50 MB limit.');
            return;
        }

        // Snapshot live hardware DAQ telemetry (RPM, Temp, Voltage)
        if (typeof window.captureLiveTelemetrySnapshot === 'function') {
            window.captureLiveTelemetrySnapshot(false);
        }

        // Bind directly to hidden submission form
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('form_audio_file').files = dt.files;
        const blobInput = document.getElementById('form_audio_blob');
        if (blobInput) blobInput.value = '';

        try {
            const tempCtx = new (window.AudioContext || window.webkitAudioContext)();
            const arrayBuf = await file.arrayBuffer();
            const decodedBuffer = await tempCtx.decodeAudioData(arrayBuf);
            const durationSec = Math.max(1, Math.round(decodedBuffer.duration));
            document.getElementById('form_audio_duration').value = durationSec;

            // Signal quality evaluation (RMS & Peak)
            const channelData = decodedBuffer.getChannelData(0);
            let sumSq = 0;
            let peak = 0;
            const step = Math.max(1, Math.floor(channelData.length / 5000));
            let samplesEvaluated = 0;
            for (let i = 0; i < channelData.length; i += step) {
                const val = Math.abs(channelData[i]);
                if (val > peak) peak = val;
                sumSq += val * val;
                samplesEvaluated++;
            }
            const rms = Math.sqrt(sumSq / Math.max(1, samplesEvaluated));
            updateSignalQualityBadge(rms, peak, true);

            // Render static waveform onto oscilloscope canvas
            renderStaticWaveform(decodedBuffer);

            // Audio preview playback
            const audioUrl = URL.createObjectURL(file);
            const player = document.getElementById('workbench-audio-playback');
            if (player) {
                player.src = audioUrl;
                document.getElementById('playback-strip').style.display = 'flex';
                document.getElementById('playback-duration-meta').innerHTML = `<strong>${file.name}</strong> (${durationSec}s audio attached)`;
            }

            // Update UI State & Stepper
            captureState = 'AUDIO_DONE';
            document.getElementById('tick-audio').classList.add('active');
            document.getElementById('tick-audio').classList.add('completed');
            document.getElementById('tick-vib').classList.add('active');
            document.getElementById('probe-audio-val').textContent = `${durationSec}s File`;

            window.setDiagnosisFlowStep('vibration');
            document.getElementById('hud-progress-pct').textContent = '50%';
            document.getElementById('hud-progress-label').textContent = 'AUDIO FILE LOADED · PROCEED TO VIBRATION';

            const condChip = document.getElementById('hud-condition-chip');
            if (condChip) {
                condChip.className = 'h-diag-status-chip normal';
                const icon = document.getElementById('hud-condition-icon');
                if (icon) icon.textContent = '✓';
                const name = document.getElementById('hud-condition-name');
                if (name) name.textContent = 'AUDIO FILE READY';
            }

            // Button visibility
            document.getElementById('btn-start-rec').style.display = 'none';
            const uploadBtn = document.getElementById('btn-upload-audio');
            if (uploadBtn) uploadBtn.style.display = 'none';
            const hasHardwareVib = window.isEsp32Online;
            const btnStartVib = document.getElementById('btn-start-vib');
            if (btnStartVib) {
                btnStartVib.style.display = 'inline-flex';
                if (!hasHardwareVib) {
                    btnStartVib.classList.add('h-diag-btn-gated');
                    btnStartVib.innerHTML = '<span style="font-size:0.8rem;margin-right:2px;">🔒</span> Live Vibration (ESP32 Offline)';
                    btnStartVib.title = 'ESP32 DAQ is offline. Import a vibration CSV or skip vibration for audio-only analysis.';
                } else {
                    btnStartVib.classList.remove('h-diag-btn-gated');
                    btnStartVib.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg> Start Vibration Capture';
                    btnStartVib.title = 'Start live vibration capture from ESP32 ADXL345 node';
                }
            }
            const skipVibBtn = document.getElementById('btn-skip-vib');
            if (skipVibBtn) skipVibBtn.style.display = 'inline-flex';

            document.getElementById('canvas-overlay-text').style.display = 'none';
            document.getElementById('btn-submit-session').disabled = false;
        } catch (decodeErr) {
            console.warn('Audio decoding fallback:', decodeErr);
            document.getElementById('playback-strip').style.display = 'flex';
            document.getElementById('playback-duration-meta').textContent = `${file.name} (Attached)`;
            captureState = 'AUDIO_DONE';
            document.getElementById('btn-submit-session').disabled = false;
            document.getElementById('btn-start-vib').style.display = 'inline-flex';
            document.getElementById('btn-skip-vib').style.display = 'inline-flex';
        }
    };

    // ─────────────────────────────────────────────
    // Stage 2: Live Vibration Recording (ADXL345 Hardware DAQ Node)
    // ─────────────────────────────────────────────
    function ingestLiveVibrationPacket(data) {
        if (!data || captureState !== 'RECORDING_VIBRATION') return;
        const packetId = data.packet_id || (data.timestamp + '_' + (data.peak_g ?? ''));
        if (packetId && packetId === lastVibPacketId) {
            return;
        }
        lastVibPacketId = packetId;

        const rateHz = data.sample_rate_hz || 100;
        const dt = 1.0 / rateHz;
        const elapsedSec = vibStartMs > 0 ? Math.max(0, (performance.now() - vibStartMs) / 1000) : 0;
        const minNextT = vibrationSamples.length > 0 ? (vibrationSamples[vibrationSamples.length - 1].t + dt) : 0;
        let baseT = Math.max(minNextT, elapsedSec);

        if (Array.isArray(data.samples) && data.samples.length > 0) {
            data.samples.forEach(s => {
                let sx = 0, sy = 0, sz = 0;
                if (typeof s === 'object' && s !== null) {
                    sx = Number(s.x ?? s.ax ?? 0);
                    sy = Number(s.y ?? s.ay ?? 0);
                    sz = Number(s.z ?? s.az ?? (s.magnitude ?? 0));
                } else if (typeof s === 'number') {
                    sz = Number(s);
                }
                vibrationSamples.push({ t: baseT, x: sx, y: sy, z: sz });
                baseT += dt;
            });
        } else if (data.accel_x !== undefined && data.accel_x !== null) {
            vibrationSamples.push({
                t: baseT,
                x: Number(data.accel_x),
                y: Number(data.accel_y),
                z: Number(data.accel_z)
            });
        }

        if (vibrationSamples.length > 0) {
            const last = vibrationSamples[vibrationSamples.length - 1];
            const mag = Math.sqrt(last.x * last.x + last.y * last.y + last.z * last.z);
            const probeVal = document.getElementById('probe-vib-val');
            if (probeVal) {
                probeVal.textContent = mag.toFixed(2) + ' g';
            }
        }
    }

    window.startVibrationRecording = function() {
        if (!window.isEsp32Online) {
            alert('⚠️ ESP32 DAQ Hardware Node Offline\n\nLive vibration capture requires the physical ESP32 accelerometer node streaming over Wi-Fi.\n\nPlease import an ADXL345 vibration CSV file or click "Skip Vibration" for audio-only analysis.');
            return;
        }

        captureState = 'RECORDING_VIBRATION';
        const carModel = document.getElementById('car-3d-model');
        if (carModel) carModel.classList.add('recording-vib');
        vibrationSamples = [];
        lastVibPacketId = null;
        vibStartMs = performance.now();
        vibSeconds = 0;

        window.setDiagnosisFlowStep('vibration');
        document.getElementById('hud-progress-pct').textContent = '65%';
        document.getElementById('hud-progress-label').textContent = 'STREAMING ESP32 ADXL345 ACCELEROMETER...';
        document.getElementById('tick-vib').classList.add('active');

        // Workbench UI
        document.getElementById('btn-start-vib').style.display = 'none';
        document.getElementById('btn-stop-vib').style.display = 'inline-flex';
        const skipVibBtn = document.getElementById('btn-skip-vib');
        if (skipVibBtn) skipVibBtn.style.display = 'none';
        document.getElementById('live-rec-dot').classList.add('active');
        document.getElementById('canvas-overlay-text').style.display = 'none';
        const channels = document.getElementById('canvas-channels-legend');
        if (channels) channels.style.display = 'flex';

        // Timer reset and count
        document.getElementById('live-rec-timer').textContent = '00:00';
        clearInterval(recordTimer);
        recordTimer = setInterval(() => {
            vibSeconds++;
            const mins = String(Math.floor(vibSeconds / 60)).padStart(2, '0');
            const secs = String(vibSeconds % 60).padStart(2, '0');
            document.getElementById('live-rec-timer').textContent = `${mins}:${secs}`;
            if (vibSeconds >= 15) {
                document.getElementById('hud-progress-pct').textContent = '80%';
            }
        }, 1000);

        // Immediately poll live telemetry and accelerate polling to 1000ms during capture
        checkLiveDaq();
        if (checkLiveDaqInterval) clearInterval(checkLiveDaqInterval);
        checkLiveDaqInterval = setInterval(checkLiveDaq, 1000);

        drawVibrationOscilloscope();
    };

    window.stopVibrationRecording = function() {
        captureState = 'VIBRATION_DONE';
        const carModel = document.getElementById('car-3d-model');
        if (carModel) carModel.classList.remove('recording-vib');
        if (checkLiveDaqInterval) clearInterval(checkLiveDaqInterval);
        checkLiveDaqInterval = setInterval(checkLiveDaq, 3000);
        clearInterval(recordTimer);
        cancelAnimationFrame(vibAnimFrame);

        document.getElementById('live-rec-dot').classList.remove('active');
        document.getElementById('btn-stop-vib').style.display = 'none';
        const channels = document.getElementById('canvas-channels-legend');
        if (channels) channels.style.display = 'none';

        if (vibrationSamples.length === 0) {
            alert('⚠️ No live accelerometer samples received from ESP32 node.\n\nPlease verify the sensor node is powered and streaming, or import a vibration CSV file.');
            skipVibrationCapture();
            return;
        }

        // Compute DC mean vector (x, y, z) to isolate AC dynamic mechanical vibration from 1.0G Earth gravity
        let meanX = 0, meanY = 0, meanZ = 0;
        for (let i = 0; i < vibrationSamples.length; i++) {
            meanX += vibrationSamples[i].x;
            meanY += vibrationSamples[i].y;
            meanZ += vibrationSamples[i].z;
        }
        meanX /= vibrationSamples.length;
        meanY /= vibrationSamples.length;
        meanZ /= vibrationSamples.length;

        // Generate standard CSV: timestamp,x,y,z
        let csvContent = "timestamp,x,y,z\n";
        let sumSq = 0;
        let peakMag = 0;
        for (let i = 0; i < vibrationSamples.length; i++) {
            const s = vibrationSamples[i];
            csvContent += `${s.t.toFixed(4)},${s.x.toFixed(4)},${s.y.toFixed(4)},${s.z.toFixed(4)}\n`;
            const dx = s.x - meanX;
            const dy = s.y - meanY;
            const dz = s.z - meanZ;
            const mag = Math.sqrt(dx * dx + dy * dy + dz * dz);
            sumSq += (mag * mag);
            if (mag > peakMag) peakMag = mag;
        }

        const rms = Math.sqrt(sumSq / vibrationSamples.length);
        const csvBlob = new Blob([csvContent], { type: 'text/csv' });
        const vibFile = new File([csvBlob], 'engine_vibration_' + Date.now() + '.csv', { type: 'text/csv' });
        const dt = new DataTransfer();
        dt.items.add(vibFile);
        document.getElementById('form_vibration_file').files = dt.files;

        // Determine sensor source transparency
        document.getElementById('form_vibration_source').value = 'adxl345_live';

        // UI indicators
        const probeVal = document.getElementById('probe-vib-val');
        if (probeVal) {
            probeVal.textContent = rms.toFixed(2) + ' g RMS';
        }
        document.getElementById('tick-vib').classList.add('active');
        document.getElementById('tick-vib').classList.add('completed');
        document.getElementById('tick-ml').classList.add('active');

        // Show Vibration Strip with Source Transparency
        const vibStrip = document.getElementById('vibration-strip');
        if (vibStrip) {
            vibStrip.style.display = 'flex';
            const meta = document.getElementById('vibration-meta');
            if (meta) {
                meta.innerHTML = `<span style="color:#0284c7;font-weight:700;">[HARDWARE DAQ]</span> ESP32 ADXL345 Telemetry (${vibrationSamples.length} samples @ 100Hz)`;
            }
        }

        // Stepper: advance to Analyze
        window.setDiagnosisFlowStep('analyze');
        document.getElementById('hud-progress-pct').textContent = '90%';
        document.getElementById('hud-progress-label').textContent = 'ALL SIGNALS CAPTURED · READY TO ANALYZE';

        const condChip = document.getElementById('hud-condition-chip');
        if (condChip) {
            condChip.className = 'h-diag-status-chip normal';
            const icon = document.getElementById('hud-condition-icon');
            if (icon) icon.textContent = '✓';
            const name = document.getElementById('hud-condition-name');
            if (name) name.textContent = 'TELEMETRY SYNCED';
        }

        // Enable workbench submit
        document.getElementById('btn-submit-session').disabled = false;

        // Overlay Message
        const overlay = document.getElementById('canvas-overlay-text');
        if (overlay) {
            overlay.innerHTML = 'Acoustic and vibration telemetry captured. Click <strong>"Analyze Diagnostic"</strong> to perform inference.';
            overlay.style.display = 'block';
        }
    };

    window.skipVibrationCapture = function() {
        captureState = 'VIBRATION_DONE';
        document.getElementById('form_vibration_source').value = 'skipped';
        document.getElementById('form_vibration_file').value = '';

        // UI buttons
        document.getElementById('btn-start-vib').style.display = 'none';
        document.getElementById('btn-stop-vib').style.display = 'none';
        const skipVibBtn = document.getElementById('btn-skip-vib');
        if (skipVibBtn) skipVibBtn.style.display = 'none';

        // Ticks
        document.getElementById('tick-vib').classList.add('active');
        document.getElementById('tick-vib').classList.add('completed');
        document.getElementById('tick-ml').classList.add('active');

        // Stepper: advance to Analyze
        window.setDiagnosisFlowStep('analyze');
        document.getElementById('hud-progress-pct').textContent = '90%';
        document.getElementById('hud-progress-label').textContent = 'AUDIO CAPTURED · VIBRATION SKIPPED (AUDIO ONLY)';

        const condChip = document.getElementById('hud-condition-chip');
        if (condChip) {
            condChip.className = 'h-diag-status-chip normal';
            const icon = document.getElementById('hud-condition-icon');
            if (icon) icon.textContent = '✓';
            const name = document.getElementById('hud-condition-name');
            if (name) name.textContent = 'ACOUSTIC TELEMETRY READY';
        }

        // Show vibration strip with skipped note
        const vibStrip = document.getElementById('vibration-strip');
        if (vibStrip) {
            vibStrip.style.display = 'flex';
            const meta = document.getElementById('vibration-meta');
            if (meta) {
                meta.innerHTML = `<span style="color:#94a3b8;font-weight:600;">[SKIPPED]</span> Vibration omitted — running acoustic-only ML inference`;
            }
        }

        // Enable workbench submit
        document.getElementById('btn-submit-session').disabled = false;

        // Overlay Message
        const overlay = document.getElementById('canvas-overlay-text');
        if (overlay) {
            overlay.innerHTML = 'Acoustic telemetry captured (Vibration skipped). Click <strong>"Analyze Diagnostic"</strong> to perform inference.';
            overlay.style.display = 'block';
        }
    };

    function drawVibrationOscilloscope() {
        if (!canvasCtx || !canvas) return;

        function renderVib() {
            if (captureState !== 'RECORDING_VIBRATION') return;
            vibAnimFrame = requestAnimationFrame(renderVib);

            const w = canvas.width;
            const h = canvas.height;

            // Background
            canvasCtx.fillStyle = '#0b1120';
            canvasCtx.fillRect(0, 0, w, h);

            // Gridlines & reference levels
            canvasCtx.strokeStyle = 'rgba(51, 65, 85, 0.4)';
            canvasCtx.lineWidth = 1;
            canvasCtx.beginPath();
            canvasCtx.moveTo(0, h / 2);
            canvasCtx.lineTo(w, h / 2);
            canvasCtx.moveTo(0, h * 0.25);
            canvasCtx.lineTo(w, h * 0.25);
            canvasCtx.moveTo(0, h * 0.75);
            canvasCtx.lineTo(w, h * 0.75);
            canvasCtx.stroke();

            const windowSize = 100;
            const len = vibrationSamples.length;
            if (len < 2) return;

            const slice = vibrationSamples.slice(Math.max(0, len - windowSize));
            const step = w / (windowSize - 1);

            const scaleY = (val, center) => {
                const normalized = (val - center) / 1.5;
                return (h / 2) - (normalized * (h * 0.4));
            };

            // Draw X (Cyan)
            canvasCtx.strokeStyle = '#06b6d4';
            canvasCtx.lineWidth = 2;
            canvasCtx.beginPath();
            for (let i = 0; i < slice.length; i++) {
                const xPos = i * step;
                const yPos = scaleY(slice[i].x, 0);
                if (i === 0) canvasCtx.moveTo(xPos, yPos);
                else canvasCtx.lineTo(xPos, yPos);
            }
            canvasCtx.stroke();

            // Draw Y (Emerald)
            canvasCtx.strokeStyle = '#10b981';
            canvasCtx.lineWidth = 2;
            canvasCtx.beginPath();
            for (let i = 0; i < slice.length; i++) {
                const xPos = i * step;
                const yPos = scaleY(slice[i].y, 0);
                if (i === 0) canvasCtx.moveTo(xPos, yPos);
                else canvasCtx.lineTo(xPos, yPos);
            }
            canvasCtx.stroke();

            // Draw Z (Amber)
            canvasCtx.strokeStyle = '#f59e0b';
            canvasCtx.lineWidth = 2;
            canvasCtx.beginPath();
            for (let i = 0; i < slice.length; i++) {
                const xPos = i * step;
                const yPos = scaleY(slice[i].z, 1.0);
                if (i === 0) canvasCtx.moveTo(xPos, yPos);
                else canvasCtx.lineTo(xPos, yPos);
            }
            canvasCtx.stroke();
        }

        renderVib();
    }

    // ─────────────────────────────────────────────
    // 5. Trigger Primary Action CTA (HUD Main Button -> Scrolls to Unified Workbench)
    // ─────────────────────────────────────────────
    window.triggerPrimaryAction = function() {
        const workbench = document.getElementById('recording-workbench');
        if (workbench) {
            workbench.scrollIntoView({ behavior: 'smooth', block: 'center' });
            workbench.classList.remove('h-diag-workbench-focus-pulse');
            void workbench.offsetWidth; // trigger reflow
            workbench.classList.add('h-diag-workbench-focus-pulse');
            setTimeout(() => {
                workbench.classList.remove('h-diag-workbench-focus-pulse');
            }, 1900);
        }

        // Focus the primary active control inside the workbench
        setTimeout(() => {
            if (captureState === 'IDLE') {
                const targetBtn = window.isEsp32Online
                    ? document.getElementById('btn-start-rec')
                    : document.getElementById('btn-upload-audio');
                if (targetBtn) targetBtn.focus({ preventScroll: true });
            } else {
                const submitBtn = document.getElementById('btn-submit-session');
                if (submitBtn && !submitBtn.disabled) submitBtn.focus({ preventScroll: true });
            }
        }, 450);
    };

    // 6. Recording Mode Selection (Idle vs Revving target regime)
    window.setRecordingMode = function(mode) {
        document.getElementById('form_recording_mode').value = mode;
        const isIdle = (mode === 'idle');
        const targetRpm = isIdle ? 850 : 2200;
        document.getElementById('btn-mode-idle').classList.toggle('active', isIdle);
        document.getElementById('btn-mode-revving').classList.toggle('active', !isIdle);
        document.getElementById('gauge-rpm-mode').textContent = isIdle ? 'Target: 850 RPM (Idle)' : 'Target: 2,200 RPM (Revving)';
        const formTargetRpm = document.getElementById('form_target_rpm');
        if (formTargetRpm) formTargetRpm.value = targetRpm;
    };

    // 7. Modals & Sample Uploads
    window.openSampleUploadModal = () => {
        const el = document.getElementById('modal-sample-upload');
        if (el) {
            el.classList.add('is-open');
            el.style.setProperty('display', 'flex', 'important');
        }
    };
    window.closeSampleUploadModal = () => {
        const el = document.getElementById('modal-sample-upload');
        if (el) {
            el.classList.remove('is-open');
            el.style.setProperty('display', 'none', 'important');
        }
    };
    window.openManualTelemetryDrawer = () => {
        const el = document.getElementById('drawer-manual-telemetry');
        if (el) {
            el.classList.add('is-open');
            el.style.setProperty('display', 'flex', 'important');
        }
    };
    window.closeManualTelemetryDrawer = () => {
        const el = document.getElementById('drawer-manual-telemetry');
        if (el) {
            el.classList.remove('is-open');
            el.style.setProperty('display', 'none', 'important');
        }
    };

    // Global keyboard listener to close modals on Escape key
    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            closeSampleUploadModal();
            closeManualTelemetryDrawer();
        }
    });

    window.previewModalVibration = function(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const lines = e.target.result.split('\n').map(l => l.trim()).filter(l => l.length > 0 && !l.startsWith('#'));
            const firstCol = lines.length > 0 ? lines[0].split(',')[0].trim() : '';
            const hasHeader = firstCol.length > 0 && isNaN(Number(firstCol));
            const count = Math.max(0, lines.length - (hasHeader ? 1 : 0));
            const summary = document.getElementById('modal-vib-summary');
            summary.style.display = 'block';
            summary.textContent = `✓ Loaded ${count} accelerometer samples from ${file.name}`;
            document.getElementById('probe-vib-val').textContent = 'CSV Attached';
            document.getElementById('tick-vib').classList.add('active');
        };
        reader.readAsText(file.slice(0, 50000));
    };

    window.applySampleUploads = function() {
        const vibIn = document.getElementById('modal_vib_input');

        if (vibIn && vibIn.files && vibIn.files[0]) {
            document.getElementById('form_vibration_file').files = vibIn.files;
            document.getElementById('form_vibration_source').value = 'csv_import';
            document.getElementById('tick-vib').classList.add('active');
            document.getElementById('tick-vib').classList.add('completed');
            document.getElementById('probe-vib-val').textContent = 'CSV Attached';

            const skipVibBtn = document.getElementById('btn-skip-vib');
            if (skipVibBtn) skipVibBtn.style.display = 'none';

            const vibStrip = document.getElementById('vibration-strip');
            if (vibStrip) {
                vibStrip.style.display = 'flex';
                const meta = document.getElementById('vibration-meta');
                if (meta) {
                    meta.innerHTML = `<span style="color:#0284c7;font-weight:700;">[CSV IMPORT]</span> Ingested ${vibIn.files[0].name}`;
                }
            }

            // If audio was already recorded, advance workflow to analyze
            if (captureState === 'AUDIO_DONE' || captureState === 'RECORDING_VIBRATION') {
                captureState = 'VIBRATION_DONE';
                document.getElementById('btn-submit-session').disabled = false;
                document.getElementById('hud-progress-pct').textContent = '90%';
                document.getElementById('hud-progress-label').textContent = 'ALL SIGNALS CAPTURED · READY TO ANALYZE';
                window.setDiagnosisFlowStep('analyze');
            }
        }
        closeSampleUploadModal();
    };

    window.saveManualTelemetry = function() {
        const temp = document.getElementById('man_temp')?.value?.trim() || '';
        const rpm = document.getElementById('man_rpm')?.value?.trim() || '';
        const volt = document.getElementById('man_volt')?.value?.trim() || '';
        const curr = document.getElementById('man_curr')?.value?.trim() || '';

        document.getElementById('form_temperature_c').value = temp;
        document.getElementById('form_rpm').value = rpm;
        document.getElementById('form_voltage_v').value = volt;
        document.getElementById('form_current_ma').value = curr;

        document.getElementById('form_true_condition').value = document.getElementById('man_true_condition')?.value || '';
        document.getElementById('form_session_id').value = document.getElementById('man_session_id')?.value || '';
        document.getElementById('form_fault_detail').value = document.getElementById('man_fault_detail')?.value || '';
        document.getElementById('form_target_rpm').value = document.getElementById('man_target_rpm')?.value || '';
        document.getElementById('form_load_state').value = document.getElementById('man_load_state')?.value || '';
        document.getElementById('form_sensor_position').value = document.getElementById('man_sensor_position')?.value || '';
        document.getElementById('form_mic_position').value = document.getElementById('man_mic_position')?.value || '';
        document.getElementById('form_operator').value = document.getElementById('man_operator')?.value || '';
        document.getElementById('form_verified_by').value = document.getElementById('man_verified_by')?.value || '';
        document.getElementById('form_sample_rate_hz').value = document.getElementById('man_sample_rate_hz')?.value || '';
        document.getElementById('form_is_training_sample').value = document.getElementById('man_is_training_sample')?.checked ? '1' : '0';

        if (temp) {
            document.getElementById('probe-temp-val').textContent = temp + '°C';
            const gTemp = document.getElementById('gauge-temp-num');
            if (gTemp) gTemp.textContent = temp + '°C';
            const wbTemp = document.getElementById('wb-val-temp');
            if (wbTemp) wbTemp.textContent = temp + '°C';
        } else {
            document.getElementById('probe-temp-val').textContent = '--';
            const wbTemp = document.getElementById('wb-val-temp');
            if (wbTemp) wbTemp.textContent = '--';
        }

        if (rpm) {
            document.getElementById('probe-rpm-val').textContent = rpm + ' RPM';
            document.getElementById('gauge-rpm-num').textContent = rpm;
            const wbRpm = document.getElementById('wb-val-rpm');
            if (wbRpm) wbRpm.textContent = rpm;
        } else {
            document.getElementById('probe-rpm-val').textContent = '--';
            const wbRpm = document.getElementById('wb-val-rpm');
            if (wbRpm) wbRpm.textContent = '--';
        }

        if (volt) {
            document.getElementById('probe-volt-val').textContent = volt + ' V';
            document.getElementById('gauge-voltage-num').textContent = volt;
            const wbVolt = document.getElementById('wb-val-volt');
            if (wbVolt) wbVolt.textContent = volt + 'V';
        } else {
            document.getElementById('probe-volt-val').textContent = '--';
            const wbVolt = document.getElementById('wb-val-volt');
            if (wbVolt) wbVolt.textContent = '--';
        }

        if (curr) {
            const gCurr = document.getElementById('gauge-curr-num');
            if (gCurr) gCurr.textContent = curr + ' mA';
        }

        const tag = document.getElementById('wb-status-tag');
        const btnClear = document.getElementById('btn-clear-hardware');
        if (temp || rpm || volt) {
            if (tag) {
                tag.textContent = 'MANUAL SET';
                tag.style.background = 'rgba(56,189,248,0.15)';
                tag.style.color = '#38bdf8';
            }
            if (btnClear) btnClear.style.display = 'inline-flex';
        } else {
            if (tag) {
                tag.textContent = 'NOT CAPTURED (OPTIONAL)';
                tag.style.background = 'rgba(100,116,139,0.18)';
                tag.style.color = '#94a3b8';
            }
            if (btnClear) btnClear.style.display = 'none';
        }

        const tickAux = document.getElementById('tick-aux');
        if (tickAux) {
            if (temp || rpm || volt) {
                tickAux.classList.add('completed');
            } else {
                tickAux.classList.remove('completed');
            }
        }

        closeManualTelemetryDrawer();
    };

    // Keep-alive heartbeat to prevent session expiration while in the studio
    setInterval(() => {
        fetch('{{ route('keep-alive') }}', { credentials: 'same-origin' })
            .then(res => res.json())
            .then(data => {
                if (data.csrf_token) {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) meta.setAttribute('content', data.csrf_token);
                    const formToken = document.querySelector('#haven-hidden-diag-form input[name="_token"]');
                    if (formToken) formToken.value = data.csrf_token;
                }
            })
            .catch(() => {});
    }, 120000); // Every 2 minutes

    function resetAnalyzeButtons() {
        captureState = 'READY_TO_SUBMIT';
        const submitBtn = document.getElementById('btn-submit-session');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Analyze Diagnostic';
        }
    }

    // 8. 6-Stage AI Stepper Animation & Form Submission
    window.submitDiagnosticForm = function() {
        if (captureState === 'ANALYZING') return;
        captureState = 'ANALYZING';

        const form = document.getElementById('haven-hidden-diag-form');
        document.getElementById('analysis-pipeline-card').scrollIntoView({ behavior: 'smooth' });
        window.setDiagnosisFlowStep('analyze');

        const submitBtn = document.getElementById('btn-submit-session');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Running Inference...';
        }

        // Animate Pipeline Stages
        let step = 1;
        let animationDone = false;
        let submissionDone = false;
        let redirectTargetUrl = null;

        function checkCompletion() {
            if (animationDone && submissionDone && redirectTargetUrl) {
                window.location.href = redirectTargetUrl;
            }
        }

        function advanceStepper() {
            if (step <= 6) {
                document.getElementById('stage-' + step).classList.add('active');
                if (step > 1) {
                    document.getElementById('stage-' + (step - 1)).classList.add('completed');
                    const line = document.getElementById('line-' + (step - 1));
                    if (line) line.classList.add('active');
                }
                document.getElementById('hud-progress-pct').textContent = (Math.round((step / 6) * 100)) + '%';
                if (step === 6) {
                    window.setDiagnosisFlowStep('results');
                }
                step++;
                setTimeout(advanceStepper, 350);
            } else {
                animationDone = true;
                checkCompletion();
            }
        }
        advanceStepper();

        // Auxiliary hardware metadata (DS18B20 Temp, Hall RPM, INA219 Voltage/Current):
        // Only attach if genuinely captured from live ESP32 hardware or entered by user.
        // If not captured, delete them so database stores NULL (no auto-generated fake numbers).
        const formData = new FormData(form);
        const inpRpm = document.getElementById('form_rpm');
        const inpTemp = document.getElementById('form_temperature_c');
        const inpVolt = document.getElementById('form_voltage_v');
        const inpCurr = document.getElementById('form_current_ma');

        const isValidVal = v => (v !== null && v !== undefined && v !== '' && v !== '--' && !isNaN(parseFloat(v)));

        if (isValidVal(inpRpm?.value)) {
            formData.set('rpm', inpRpm.value);
        } else {
            formData.delete('rpm');
        }

        if (isValidVal(inpTemp?.value)) {
            formData.set('temperature_c', inpTemp.value);
        } else {
            formData.delete('temperature_c');
        }

        if (isValidVal(inpVolt?.value)) {
            formData.set('voltage_v', inpVolt.value);
        } else {
            formData.delete('voltage_v');
        }

        if (isValidVal(inpCurr?.value)) {
            formData.set('current_ma', inpCurr.value);
        } else {
            formData.delete('current_ma');
        }

        const metaCsrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (metaCsrf) {
            formData.set('_token', metaCsrf);
            const tokenInp = form.querySelector('input[name="_token"]');
            if (tokenInp) tokenInp.value = metaCsrf;
        }

        async function executeSubmission() {
            try {
                let res = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                });

                // Auto-recover if session / CSRF expired (419)
                if (res.status === 419) {
                    try {
                        const pingRes = await fetch('{{ route('keep-alive') }}', { credentials: 'same-origin' });
                        if (pingRes.ok) {
                            const pingData = await pingRes.json();
                            if (pingData.csrf_token) {
                                const meta = document.querySelector('meta[name="csrf-token"]');
                                if (meta) meta.setAttribute('content', pingData.csrf_token);
                                formData.set('_token', pingData.csrf_token);

                                // Retry POST with refreshed token
                                res = await fetch(form.action, {
                                    method: 'POST',
                                    body: formData,
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-CSRF-TOKEN': pingData.csrf_token
                                    }
                                });
                            }
                        }
                    } catch (e) {
                        console.warn('CSRF refresh failed', e);
                    }
                }

                if (res.ok) {
                    const data = await res.json();
                    if (data.redirect_url) {
                        redirectTargetUrl = data.redirect_url;
                        submissionDone = true;
                        checkCompletion();
                        return;
                    }
                }

                if (res.status === 419) {
                    alert('Your session has expired. Please sign in again. Your recorded data remains in memory.');
                    resetAnalyzeButtons();
                    return;
                }

                if (res.status === 422) {
                    const err = await res.json();
                    const msg = err.message || (err.errors ? Object.values(err.errors).flat().join('\n') : 'Validation failed.');
                    alert('Submission error: ' + msg);
                    resetAnalyzeButtons();
                    return;
                }

                // Fallback: standard submit if response didn't contain JSON redirect
                form.submit();
            } catch (err) {
                console.warn('Fetch submission error, falling back to form.submit()', err);
                form.submit();
            }
        }

        executeSubmission();
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
        return escapeHtml(text || '').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
    }
    window.resetStudio = function() { window.location.reload(); };

    // 10. Live Polling for ESP32 Hardware Status
    window.clearAuxiliaryTelemetry = function() {
        window._userSkippedAuxHardware = true;
        const formTemp = document.getElementById('form_temperature_c');
        const formRpm = document.getElementById('form_rpm');
        const formVolt = document.getElementById('form_voltage_v');
        const formCurr = document.getElementById('form_current_ma');

        if (formTemp) formTemp.value = '';
        if (formRpm) { formRpm.value = ''; delete formRpm.dataset.liveLocked; }
        if (formVolt) formVolt.value = '';
        if (formCurr) formCurr.value = '';

        const wbRpm = document.getElementById('wb-val-rpm');
        if (wbRpm) wbRpm.textContent = '--';
        const wbTemp = document.getElementById('wb-val-temp');
        if (wbTemp) wbTemp.textContent = '--';
        const wbVolt = document.getElementById('wb-val-volt');
        if (wbVolt) wbVolt.textContent = '--';

        const tag = document.getElementById('wb-status-tag');
        if (tag) {
            tag.textContent = 'NOT CAPTURED (OPTIONAL)';
            tag.style.background = 'rgba(100,116,139,0.18)';
            tag.style.color = '#94a3b8';
            tag.style.borderColor = 'rgba(100,116,139,0.3)';
        }

        const btnClear = document.getElementById('btn-clear-hardware');
        if (btnClear) btnClear.style.display = 'none';

        const tickAux = document.getElementById('tick-aux');
        if (tickAux) tickAux.classList.remove('completed');
    };

    function updateEsp32ConnectionState(isOnline, data) {
        window.isEsp32Online = !!isOnline;

        const dot = document.getElementById('daq-live-dot');
        const stTxt = document.getElementById('daq-status-text');
        const stPill = document.getElementById('daq-status-pill');
        const busBdg = document.getElementById('sensor-bus-badge');
        const btnStartRec = document.getElementById('btn-start-rec');
        const btnStartRecTxt = document.getElementById('btn-start-rec-text');
        const masterBtn = document.getElementById('btn-master-action');
        const masterTxt = masterBtn ? masterBtn.querySelector('#master-btn-text') : null;
        const masterIcon = masterBtn ? masterBtn.querySelector('#master-btn-icon') : null;
        const btnStartVib = document.getElementById('btn-start-vib');
        const overlay = document.getElementById('canvas-overlay-text');

        if (window.isEsp32Online) {
            if (dot) {
                dot.style.background = '#22c55e';
                dot.style.boxShadow = '0 0 8px #22c55e';
                dot.style.animation = 'pulseDot 2s infinite ease-in-out';
            }
            if (stTxt) stTxt.textContent = 'ESP32 STREAMING';
            if (stPill) {
                stPill.classList.remove('is-offline');
                stPill.style.background = 'rgba(34,197,94,0.12)';
                stPill.style.borderColor = 'rgba(34,197,94,0.3)';
                stPill.style.color = '#34d399';
            }
            if (busBdg) busBdg.textContent = 'HARDWARE NODE CONNECTED';

            if (data && data.accel_x !== undefined && data.accel_x !== null) {
                window.latestEsp32Accel = {
                    x: Number(data.accel_x),
                    y: Number(data.accel_y),
                    z: Number(data.accel_z)
                };
            }

            // Unlock live capture buttons if still in IDLE state
            if (captureState === 'IDLE') {
                if (btnStartRec) {
                    btnStartRec.classList.remove('h-diag-btn-gated');
                    btnStartRec.title = 'Start live acoustic capture via connected ESP32 / INMP441 node';
                    if (btnStartRecTxt) btnStartRecTxt.textContent = 'Start Audio Capture';
                }
                if (overlay && !overlay.dataset.hasCustomContent) {
                    overlay.innerHTML = 'ESP32 DAQ Node: <strong style="color:#34d399;">ONLINE (STREAMING)</strong> · Click <strong>"Start Audio Capture"</strong> to begin';
                }
            } else if (captureState === 'AUDIO_DONE') {
                if (btnStartVib) {
                    btnStartVib.classList.remove('h-diag-btn-gated');
                    btnStartVib.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg> Start Vibration Capture';
                    btnStartVib.title = 'Start live vibration capture from ESP32 ADXL345 node';
                }
            }
        } else {
            // ESP32 is OFFLINE
            window.latestEsp32Accel = null;
            if (dot) {
                dot.style.background = '#ef4444';
                dot.style.boxShadow = 'none';
                dot.style.animation = 'none';
            }
            if (stTxt) stTxt.textContent = 'ESP32 OFFLINE';
            if (stPill) {
                stPill.classList.add('is-offline');
                stPill.style.background = 'rgba(239,68,68,0.1)';
                stPill.style.borderColor = 'rgba(239,68,68,0.25)';
                stPill.style.color = '#f87171';
            }
            if (busBdg) busBdg.textContent = 'HARDWARE NODE OFFLINE';

            // Gate live capture buttons if still in IDLE state
            if (captureState === 'IDLE') {
                if (btnStartRec) {
                    btnStartRec.classList.add('h-diag-btn-gated');
                    btnStartRec.title = 'ESP32 DAQ Node is offline. Connect ESP32 hardware to enable live capture, or click Upload Audio below.';
                    if (btnStartRecTxt) btnStartRecTxt.innerHTML = '<span style="font-size:0.8rem;margin-right:2px;">🔒</span> Live Capture (ESP32 Offline)';
                }
                if (overlay && !overlay.dataset.hasCustomContent) {
                    overlay.innerHTML = 'ESP32 DAQ Node: <strong style="color:#f87171;">OFFLINE</strong> · Connect ESP32 for live capture, or click <strong>"Upload Audio"</strong> (.wav/.mp3)';
                }
            } else if (captureState === 'AUDIO_DONE') {
                if (btnStartVib) {
                    btnStartVib.classList.add('h-diag-btn-gated');
                    btnStartVib.innerHTML = '<span style="font-size:0.8rem;margin-right:2px;">🔒</span> Live Vibration (ESP32 Offline)';
                    btnStartVib.title = 'ESP32 DAQ is offline. Import a vibration CSV or skip vibration for audio-only analysis.';
                }
            }
        }
    }

    function checkLiveDaq() {
        const sel = document.getElementById('vehicle-switcher');
        const vid = (sel && sel.value) ? sel.value : '{{ $selectedVehicle?->id ?? '' }}';
        if (!vid) return;
        fetch('/api/v1/telemetry/latest?vehicle_id=' + encodeURIComponent(vid), { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                if (data.online) {
                    updateEsp32ConnectionState(true, data);

                    if (captureState === 'RECORDING_VIBRATION') {
                        ingestLiveVibrationPacket(data);
                    }

                    // Update gauges and workbench only if user hasn't chosen to skip
                    if (!window._userSkippedAuxHardware) {
                        if (data.temperature_c !== null && data.temperature_c !== undefined) {
                            const tempStr = Number(data.temperature_c).toFixed(1) + '°C';
                            const pT = document.getElementById('probe-temp-val');
                            if (pT) pT.textContent = tempStr;
                            const gTemp = document.getElementById('gauge-temp-num');
                            if (gTemp) gTemp.textContent = tempStr;
                            const wbTemp = document.getElementById('wb-val-temp');
                            if (wbTemp) wbTemp.textContent = tempStr;
                            document.getElementById('form_temperature_c').value = data.temperature_c;
                        }
                        if (data.rpm !== null && data.rpm !== undefined) {
                            const pR = document.getElementById('probe-rpm-val');
                            if (pR) pR.textContent = data.rpm + ' RPM';
                            const gR = document.getElementById('gauge-rpm-num');
                            if (gR) gR.textContent = data.rpm;
                            const wbRpm = document.getElementById('wb-val-rpm');
                            if (wbRpm) wbRpm.textContent = data.rpm;
                            const formRpm = document.getElementById('form_rpm');
                            if (formRpm) {
                                formRpm.value = data.rpm;
                                formRpm.dataset.liveLocked = 'true';
                            }
                        }
                        if (data.voltage_v !== null && data.voltage_v !== undefined) {
                            const voltStr = Number(data.voltage_v).toFixed(2) + 'V';
                            const pV = document.getElementById('probe-volt-val');
                            if (pV) pV.textContent = voltStr;
                            const gV = document.getElementById('gauge-voltage-num');
                            if (gV) gV.textContent = Number(data.voltage_v).toFixed(2);
                            const wbVolt = document.getElementById('wb-val-volt');
                            if (wbVolt) wbVolt.textContent = voltStr;
                            document.getElementById('form_voltage_v').value = data.voltage_v;
                        }
                        if (data.current_ma !== null && data.current_ma !== undefined) {
                            const gCurr = document.getElementById('gauge-curr-num');
                            if (gCurr) gCurr.textContent = Math.round(data.current_ma) + ' mA';
                            document.getElementById('form_current_ma').value = data.current_ma;
                        }

                        const tag = document.getElementById('wb-status-tag');
                        if (tag && (data.temperature_c !== null || data.rpm > 0 || data.voltage_v !== null)) {
                            tag.textContent = '🟢 ESP32 LIVE';
                            tag.style.background = 'rgba(16,185,129,0.15)';
                            tag.style.color = '#34d399';
                            tag.style.borderColor = 'rgba(16,185,129,0.3)';
                            const btnClear = document.getElementById('btn-clear-hardware');
                            if (btnClear) btnClear.style.display = 'inline-flex';
                            const tickAux = document.getElementById('tick-aux');
                            if (tickAux) tickAux.classList.add('completed');
                        }
                    }

                    if (data.peak_g !== null && data.peak_g !== undefined) {
                        const fmtG = Number(data.peak_g).toFixed(3);
                        const darkPeak = document.getElementById('dark-peak-val');
                        if (darkPeak) darkPeak.innerHTML = `${fmtG} <span style="font-size:1rem;color:#94a3b8;">g</span>`;
                        const pVib = document.getElementById('probe-vib-val');
                        if (pVib) pVib.textContent = fmtG + ' g';
                    }
                } else {
                    updateEsp32ConnectionState(false, data);
                    if (!window._userSkippedAuxHardware) {
                        const tag = document.getElementById('wb-status-tag');
                        if (tag) {
                            tag.textContent = 'ESP32 OFFLINE (OPTIONAL)';
                            tag.style.background = 'rgba(100,116,139,0.18)';
                            tag.style.color = '#94a3b8';
                            tag.style.borderColor = 'rgba(100,116,139,0.3)';
                        }
                    }
                }
            })
            .catch(() => {
                updateEsp32ConnectionState(false, null);
            });
    }

    window.captureLiveTelemetrySnapshot = async function(interactive = false) {
        const sel = document.getElementById('vehicle-switcher');
        const vid = (sel && sel.value) ? sel.value : '{{ $selectedVehicle?->id ?? '' }}';
        if (!vid) return false;

        window._userSkippedAuxHardware = false;

        try {
            const res = await fetch('/api/v1/telemetry/latest?vehicle_id=' + encodeURIComponent(vid), { cache: 'no-store' });
            if (res.ok) {
                const data = await res.json();
                if (data.online) {
                    let capturedCount = 0;
                    if (data.temperature_c !== null && data.temperature_c !== undefined) {
                        const tempStr = Number(data.temperature_c).toFixed(1) + '°C';
                        document.getElementById('wb-val-temp').textContent = tempStr;
                        document.getElementById('probe-temp-val').textContent = tempStr;
                        document.getElementById('form_temperature_c').value = data.temperature_c;
                        capturedCount++;
                    }
                    if (data.rpm !== null && data.rpm !== undefined) {
                        document.getElementById('wb-val-rpm').textContent = data.rpm;
                        document.getElementById('probe-rpm-val').textContent = data.rpm + ' RPM';
                        document.getElementById('form_rpm').value = data.rpm;
                        capturedCount++;
                    }
                    if (data.voltage_v !== null && data.voltage_v !== undefined) {
                        const voltStr = Number(data.voltage_v).toFixed(2) + 'V';
                        document.getElementById('wb-val-volt').textContent = voltStr;
                        document.getElementById('probe-volt-val').textContent = voltStr;
                        document.getElementById('form_voltage_v').value = data.voltage_v;
                        capturedCount++;
                    }
                    if (data.current_ma !== null && data.current_ma !== undefined) {
                        document.getElementById('form_current_ma').value = data.current_ma;
                    }

                    const tag = document.getElementById('wb-status-tag');
                    if (tag) {
                        tag.textContent = '🟢 ESP32 LIVE';
                        tag.style.background = 'rgba(16,185,129,0.15)';
                        tag.style.color = '#34d399';
                        tag.style.borderColor = 'rgba(16,185,129,0.3)';
                    }

                    const btnClear = document.getElementById('btn-clear-hardware');
                    if (btnClear) btnClear.style.display = 'inline-flex';

                    const tickAux = document.getElementById('tick-aux');
                    if (tickAux) tickAux.classList.add('completed');

                    if (interactive) {
                        alert('✓ Captured Live Hardware DAQ from ESP32:\n• Engine Speed: ' + (data.rpm ?? '--') + ' RPM\n• Coolant Temp: ' + (data.temperature_c !== null ? (data.temperature_c + '°C') : 'Unplugged') + '\n• DC Bus Voltage: ' + (data.voltage_v !== null ? (data.voltage_v + 'V') : 'Unplugged') + '\n\n(Contextual metadata captured with diagnosis; excluded from ML feature vector).');
                    }
                    return true;
                }
            }
        } catch (_) {}

        if (interactive) {
            alert('ℹ️ ESP32 Hardware DAQ is currently offline or in standby.\n\nAuxiliary sensors (RPM, Coolant Temp, Voltage) remain uncaptured (null).\n\nYou can proceed with your acoustic/vibration diagnosis without them (they are optional), or connect your ESP32 node.');
        }
        return false;
    };
    checkLiveDaqInterval = setInterval(checkLiveDaq, 3000);
    checkLiveDaq();

})();
</script>

</x-app-layout>
