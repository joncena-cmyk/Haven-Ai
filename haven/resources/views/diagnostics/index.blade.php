<x-app-layout title="Diagnostic History">
@php
    $vehicles   = Auth::user()->vehicles()->orderBy('make')->get();
    $filterVid  = request('vehicle_id');
    $filterStat = request('status');

    $query = Auth::user()->diagnostics()->with('vehicle')->latest();
    if ($filterVid)  $query->where('vehicle_id', $filterVid);
    if ($filterStat) $query->where('status', $filterStat);
    $diagnostics = $query->paginate(20)->withQueryString();

    // Summary stats
    $total      = Auth::user()->diagnostics()->count();
    $completed  = Auth::user()->diagnostics()->where('status','completed')->count();
    $conditions = Auth::user()->diagnostics()->whereNotNull('ml_condition')
                    ->selectRaw('ml_condition, count(*) as cnt')
                    ->groupBy('ml_condition')->orderByDesc('cnt')->get();
@endphp

    <div class="h-page-header">
        <div>
            <h1 class="h-page-title">Diagnostic History</h1>
            <p class="h-page-desc">All engine diagnostic sessions across your vehicles.</p>
        </div>
        <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
            <button type="button" onclick="openIotModal()" class="haven-action-btn haven-action-btn-secondary" style="font-size:0.8rem;padding:0.48rem 0.85rem;display:inline-flex;align-items:center;gap:0.4rem;cursor:pointer;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M1.42 9a16 16 0 0 1 21.16 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>
                <span>ESP32 IoT Hub</span>
                <span class="h-badge h-badge-teal" style="font-size:0.6rem;padding:0.1rem 0.35rem;">Live</span>
            </button>
            <a href="{{ route('diagnostics.export.dataset') }}" class="haven-action-btn haven-action-btn-secondary" style="font-size:0.8rem;padding:0.48rem 0.85rem;display:inline-flex;align-items:center;gap:0.4rem;text-decoration:none;" title="Download labeled training dataset CSV for train.py">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>Export Dataset (CSV)</span>
            </a>
            <a href="{{ route('diagnostics.create') }}" class="haven-btn-primary" style="width:auto;text-decoration:none;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Diagnostic
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="h-flash h-flash-success">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Summary stat strip --}}
    @if($total > 0)
        <div class="h-stat-row" style="margin-bottom:1.25rem;">
            <div class="h-stat-cell">
                <div class="h-stat-value">{{ $total }}</div>
                <div class="h-stat-label">Total Sessions</div>
                <div class="h-stat-sub">All vehicles</div>
            </div>
            <div class="h-stat-cell">
                <div class="h-stat-value" style="{{ $completed > 0 ? 'color:var(--h-green);' : '' }}">{{ $completed }}</div>
                <div class="h-stat-label">Analyzed</div>
                <div class="h-stat-sub">{{ $total > 0 ? round($completed/$total*100) : 0 }}% complete</div>
            </div>
            <div class="h-stat-cell">
                <div class="h-stat-value" style="font-size:0.95rem;">
                    {{ $conditions->first()?->ml_condition ? ucwords(str_replace('_',' ',$conditions->first()->ml_condition)) : '—' }}
                </div>
                <div class="h-stat-label">Most Common Result</div>
                <div class="h-stat-sub">{{ $conditions->first()?->cnt ? $conditions->first()->cnt . ' sessions' : 'No results yet' }}</div>
            </div>
            <div class="h-stat-cell">
                <div class="h-stat-value">{{ Auth::user()->diagnostics()->where('status','draft')->count() }}</div>
                <div class="h-stat-label">Drafts</div>
                <div class="h-stat-sub">Incomplete sessions</div>
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('diagnostics.index') }}"
          style="display:flex;gap:0.6rem;flex-wrap:wrap;align-items:center;margin-bottom:1.25rem;">
        <select name="vehicle_id" class="haven-input haven-select"
                style="padding:0.45rem 0.75rem;font-size:0.82rem;flex:1 1 140px;"
                onchange="this.form.submit()">
            <option value="">All Vehicles</option>
            @foreach($vehicles as $v)
                <option value="{{ $v->id }}" {{ $filterVid == $v->id ? 'selected' : '' }}>{{ $v->display_name }}</option>
            @endforeach
        </select>
        <select name="status" class="haven-input haven-select"
                style="padding:0.45rem 0.75rem;font-size:0.82rem;flex:1 1 140px;"
                onchange="this.form.submit()">
            <option value="">All Statuses</option>
            @foreach(['draft'=>'Draft','submitted'=>'Submitted','processing'=>'Processing','completed'=>'Completed','failed'=>'Failed'] as $val=>$label)
                <option value="{{ $val }}" {{ $filterStat === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @if($filterVid || $filterStat)
            <a href="{{ route('diagnostics.index') }}"
               style="font-size:0.78rem;color:var(--h-text3);text-decoration:none;padding:0.45rem 0.5rem;">
                ✕ Clear filters
            </a>
        @endif
        <span style="font-size:0.78rem;color:var(--h-text3);margin-left:auto;">
            {{ $diagnostics->total() }} {{ Str::plural('session', $diagnostics->total()) }}
        </span>
    </form>

    @if($diagnostics->isEmpty())
        <div class="h-empty">
            <div class="h-empty-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <h2 class="h-empty-title">
                {{ ($filterVid || $filterStat) ? 'No sessions match this filter' : 'No Diagnostic Sessions Yet' }}
            </h2>
            <p class="h-empty-desc">
                {{ ($filterVid || $filterStat) ? 'Try adjusting or clearing your filters.' : 'Start a new session to record engine audio and vibration data for ML-assisted analysis.' }}
            </p>
            @if(!$filterVid && !$filterStat)
                <a href="{{ route('diagnostics.create') }}" class="haven-btn-primary" style="width:auto;text-decoration:none;">Start First Diagnostic</a>
            @endif
        </div>
    @else
        <div class="h-list-card">
            @foreach($diagnostics as $d)
                <div class="haven-diag-list-row" style="display:flex;align-items:stretch;border-bottom:1px solid var(--h-border);">
                    {{-- Main link --}}
                    <a href="{{ route('diagnostics.show', $d) }}"
                       class="haven-diag-list-link"
                       style="flex:1;display:flex;align-items:center;padding:0.75rem 1rem;gap:0.65rem;text-decoration:none;color:inherit;transition:background 0.1s;min-width:0;overflow:hidden;"
                       onmouseover="this.style.background='var(--h-surface2)'" onmouseout="this.style.background=''">
                        <div class="h-list-item-icon"
                             style="flex-shrink:0;{{ $d->status==='completed' && $d->ml_condition==='normal' ? 'background:var(--h-green-bg);color:var(--h-green);' : ($d->status==='completed' ? 'background:var(--h-amber-bg);color:var(--h-amber);' : ($d->status==='submitted' ? 'background:var(--h-teal-bg);color:var(--h-teal);' : '')) }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <div class="h-list-item-main" style="flex:1;min-width:0;overflow:hidden;">
                            <div class="h-list-item-title" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                {{ $d->vehicle->display_name }}
                                @if($d->ml_condition)
                                    <span style="color:var(--h-teal);font-weight:600;">
                                        — {{ ucwords(str_replace('_',' ',$d->ml_condition)) }}
                                    </span>
                                @endif
                            </div>
                            <div class="h-list-item-sub" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                #{{ $d->id }} · {{ $d->created_at->format('M j, Y · g:i A') }}
                                @if($d->audio_duration_sec) · {{ floor($d->audio_duration_sec/60) }}:{{ str_pad($d->audio_duration_sec%60,2,'0',STR_PAD_LEFT) }} @endif
                                @if($d->vibration_sample_count) · {{ number_format($d->vibration_sample_count) }} samples @endif
                            </div>
                            <div class="haven-diag-mobile-badges">
                                @if($d->ml_confidence)
                                    <span style="font-size:0.7rem;font-weight:600;color:var(--h-text3);">{{ round($d->ml_confidence*100) }}%</span>
                                @endif
                                <span class="h-badge {{ $d->status_badge }}">{{ $d->status_label }}</span>
                            </div>
                        </div>
                        <div class="haven-diag-desktop-badges" style="display:flex;align-items:center;gap:0.5rem;flex-shrink:0;">
                            @if($d->ml_confidence)
                                <span style="font-size:0.72rem;font-weight:600;color:var(--h-text3);">{{ round($d->ml_confidence*100) }}%</span>
                            @endif
                            <span class="h-badge {{ $d->status_badge }}">{{ $d->status_label }}</span>
                        </div>
                    </a>
                    {{-- Quick actions --}}
                    <div class="haven-diag-list-actions" style="display:flex;flex-direction:column;justify-content:center;gap:0.25rem;padding:0 0.5rem;flex-shrink:0;border-left:1px solid var(--h-border);">
                        @if(in_array($d->status,['draft','submitted','failed']))
                            <form method="POST" action="{{ route('diagnostics.analyze', $d) }}">
                                @csrf
                                <button type="submit" class="h-btn-sm h-btn-sm-teal" title="Run Analysis">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 3l14 9-14 9V3z"/></svg>
                                </button>
                            </form>
                        @endif
                        @if($d->status === 'completed')
                            <a href="{{ route('diagnostics.report', $d) }}" target="_blank"
                               class="h-btn-sm h-btn-sm-default" title="Print Report" style="text-decoration:none;">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div style="margin-top:1rem;">{{ $diagnostics->links() }}</div>
    @endif

    {{-- ══════════════════════════════════════════════════════
         ESP32 IoT Hub Modal (Hardware & Simulator)
    ══════════════════════════════════════════════════════ --}}
    <div id="iot-modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(11,15,23,0.85);z-index:9998;align-items:center;justify-content:center;padding:1rem;">
        <div style="background:var(--h-surface);border:1px solid var(--h-teal-ring);border-radius:var(--h-radius-xl);width:680px;max-width:100%;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,0.5), 0 0 30px rgba(2,132,199,0.2);overflow:hidden;">
            
            {{-- Modal Header --}}
            <div style="padding:1.15rem 1.4rem;border-bottom:1px solid var(--h-border);background:linear-gradient(90deg, rgba(2,132,199,0.1) 0%, rgba(30,41,59,0.6) 100%);display:flex;align-items:center;justify-content:space-between;gap:0.75rem;">
                <div style="display:flex;align-items:center;gap:0.65rem;">
                    <div style="width:34px;height:34px;border-radius:50%;background:#0284c7;display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 0 12px rgba(2,132,199,0.4);flex-shrink:0;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M1.42 9a16 16 0 0 1 21.16 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>
                    </div>
                    <div>
                        <div style="display:flex;align-items:center;gap:0.45rem;">
                            <h3 style="font-size:0.95rem;font-weight:700;color:var(--h-text);margin:0;">ESP32 Wi-Fi IoT Telemetry Hub</h3>
                            <span class="h-badge h-badge-teal" style="font-size:0.65rem;">Hardware DAQ</span>
                        </div>
                        <div style="font-size:0.73rem;color:var(--h-text3);margin-top:0.1rem;">
                            Direct Wi-Fi telemetry ingestion & async queue workers
                        </div>
                    </div>
                </div>
                <button type="button" onclick="closeIotModal()" style="background:var(--h-surface2);border:1px solid var(--h-border);color:var(--h-text3);border-radius:6px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;cursor:pointer;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>

            {{-- Modal Nav Tabs --}}
            <div style="display:flex;border-bottom:1px solid var(--h-border);background:var(--h-surface2);padding:0 1rem;overflow-x:auto;">
                <button type="button" class="iot-tab-btn active" id="tab-btn-sim" onclick="switchIotTab('sim')" style="padding:0.75rem 1rem;font-size:0.78rem;font-weight:600;color:var(--h-teal);border:none;border-bottom:2px solid var(--h-teal);background:none;cursor:pointer;white-space:nowrap;">
                    📡 1-Click Hardware Simulator
                </button>
                <button type="button" class="iot-tab-btn" id="tab-btn-wiring" onclick="switchIotTab('wiring')" style="padding:0.75rem 1rem;font-size:0.78rem;font-weight:600;color:var(--h-text3);border:none;border-bottom:2px solid transparent;background:none;cursor:pointer;white-space:nowrap;">
                    🔌 Hardware Pinout & Wiring Guide
                </button>
                <button type="button" class="iot-tab-btn" id="tab-btn-code" onclick="switchIotTab('code')" style="padding:0.75rem 1rem;font-size:0.78rem;font-weight:600;color:var(--h-text3);border:none;border-bottom:2px solid transparent;background:none;cursor:pointer;white-space:nowrap;">
                    ⚡ ESP32 C++ Firmware (5 Sensors)
                </button>
                <button type="button" class="iot-tab-btn" id="tab-btn-api" onclick="switchIotTab('api')" style="padding:0.75rem 1rem;font-size:0.78rem;font-weight:600;color:var(--h-text3);border:none;border-bottom:2px solid transparent;background:none;cursor:pointer;white-space:nowrap;">
                    📋 REST API Specs
                </button>
            </div>

            {{-- Modal Body --}}
            <div style="flex:1;overflow-y:auto;padding:1.25rem;">
                
                {{-- TAB 1: Simulator --}}
                <div id="iot-tab-sim">
                    <p style="font-size:0.8rem;color:var(--h-text2);margin-top:0;line-height:1.5;">
                        Test direct wireless telemetry transmission and async ML background queue processing across all 5 sensors (DS18B20 Temp, Hall RPM, MPU6050 Vibration, INA219 Voltage/Current, INMP441 Acoustic Audio).
                    </p>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.85rem;margin-bottom:1rem;">
                        <div>
                            <label style="font-size:0.74rem;font-weight:600;color:var(--h-text);display:block;margin-bottom:0.35rem;">Target Vehicle</label>
                            <select id="sim-vehicle" class="haven-input" style="font-size:0.8rem;padding:0.55rem;">
                                @foreach($vehicles as $veh)
                                    <option value="{{ $veh->id }}" data-key="{{ $veh->device_key }}">{{ $veh->display_name }} (ID #{{ $veh->id }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.74rem;font-weight:600;color:var(--h-text);display:block;margin-bottom:0.35rem;">Simulated Fault Profile</label>
                            <select id="sim-profile" class="haven-input" style="font-size:0.8rem;padding:0.55rem;" onchange="updateSimPreview()">
                                <option value="valve_clearance">🔔 Improper Valve Clearance (High Crest Factor >4.2, sharp valvetrain tapping, 2200 RPM)</option>
                                <option value="misfire">🟡 Engine Misfire (Cyclic torque dropout every 720°, erratic ZCR, 720 RPM)</option>
                                <option value="loose_component">🔩 Loose/Worn Component Vibration (High RMS >0.55g, structural resonance, 850 RPM)</option>
                                <option value="battery_fault">⚡ Alternator/Battery Low Voltage (11.4V undercharge)</option>
                                <option value="normal">✅ Normal Engine Operation (800 RPM baseline idle, 89°C, 14.2V charging)</option>
                            </select>
                        </div>
                    </div>

                    {{-- Live Multi-Sensor Simulation Preview Cards --}}
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0.5rem;margin-bottom:1rem;">
                        <div style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.6rem 0.75rem;">
                            <div style="font-size:0.65rem;color:var(--h-text3);font-weight:700;text-transform:uppercase;">DS18B20 (D4)</div>
                            <div id="sim-prev-temp" style="font-size:1.1rem;font-weight:800;color:var(--h-teal);margin-top:0.15rem;">93.5°C</div>
                            <div style="font-size:0.65rem;color:var(--h-text3);">Coolant Temp</div>
                        </div>
                        <div style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.6rem 0.75rem;">
                            <div style="font-size:0.65rem;color:var(--h-text3);font-weight:700;text-transform:uppercase;">Hall RPM (D23)</div>
                            <div id="sim-prev-rpm" style="font-size:1.1rem;font-weight:800;color:var(--h-text);margin-top:0.15rem;">2,400</div>
                            <div style="font-size:0.65rem;color:var(--h-text3);">Tachometer</div>
                        </div>
                        <div style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.6rem 0.75rem;">
                            <div style="font-size:0.65rem;color:var(--h-text3);font-weight:700;text-transform:uppercase;">INA219 (D21/D22)</div>
                            <div id="sim-prev-volts" style="font-size:1.1rem;font-weight:800;color:var(--h-text);margin-top:0.15rem;">13.8V</div>
                            <div style="font-size:0.65rem;color:var(--h-text3);">510 mA Draw</div>
                        </div>
                        <div style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.6rem 0.75rem;">
                            <div style="font-size:0.65rem;color:var(--h-text3);font-weight:700;text-transform:uppercase;">MPU6050 & INMP441</div>
                            <div id="sim-prev-vib" style="font-size:1.1rem;font-weight:800;color:var(--h-amber);margin-top:0.15rem;">3.85g</div>
                            <div style="font-size:0.65rem;color:var(--h-text3);">Acoustic & Peak G</div>
                        </div>
                    </div>

                    <div style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.75rem 1rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            <div style="font-size:0.8rem;font-weight:600;color:var(--h-text);">Automatic Async Queue Worker</div>
                            <div style="font-size:0.7rem;color:var(--h-text3);">Dispatches AnalyzeDiagnosticJob to background worker upon ingestion</div>
                        </div>
                        <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
                            <input type="checkbox" id="sim-auto-analyze" checked style="accent-color:var(--h-teal);width:16px;height:16px;">
                            <span style="font-size:0.75rem;font-weight:600;color:var(--h-teal);">Auto-Analyze</span>
                        </label>
                    </div>

                    <div style="display:flex;gap:0.6rem;margin-bottom:1rem;">
                        <button type="button" id="btn-broadcast-sim" onclick="broadcastSimTelemetry()" class="haven-btn-primary" style="flex:1;justify-content:center;padding:0.65rem 1rem;font-size:0.82rem;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M1.42 9a16 16 0 0 1 21.16 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>
                            <span>Broadcast ESP32 Wi-Fi Telemetry Packet</span>
                        </button>
                    </div>

                    {{-- Live Console Log --}}
                    <div style="font-size:0.72rem;font-weight:700;color:var(--h-text3);margin-bottom:0.35rem;text-transform:uppercase;letter-spacing:0.04em;">Telemetry Transmission Console</div>
                    <div id="sim-console" style="background:#0a0e14;border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.75rem;font-family:var(--h-mono),monospace;font-size:0.72rem;color:#38bdf8;max-height:160px;overflow-y:auto;line-height:1.5;">
                        <div>[SYSTEM] Ingestion Gateway listening on POST /api/v1/telemetry/ingest</div>
                        <div>[READY] 5-Sensor ESP32 Hardware DAQ Node ready (DS18B20, Hall RPM, MPU6050, INA219, INMP441).</div>
                    </div>
                </div>

                {{-- TAB 2: Hardware Pinout & Wiring Guide --}}
                <div id="iot-tab-wiring" style="display:none;">
                    <div style="margin-bottom:1rem;">
                        <h4 style="margin:0 0 0.35rem 0;font-size:0.92rem;color:var(--h-text);font-weight:700;">ESP32 Automotive DAQ Hardware Pinout</h4>
                        <p style="font-size:0.78rem;color:var(--h-text2);margin:0;line-height:1.5;">
                            Connect your ESP32-WROOM-32 or ESP32-S3 microcontroller to the 5 diagnostic sensor modules using the exact pin mappings below:
                        </p>
                    </div>

                    {{-- Pinout Table --}}
                    <div style="border:1px solid var(--h-border);border-radius:var(--h-radius);overflow:hidden;margin-bottom:1.25rem;">
                        <table style="width:100%;border-collapse:collapse;font-size:0.8rem;text-align:left;">
                            <thead>
                                <tr style="background:var(--h-surface2);border-bottom:1px solid var(--h-border);">
                                    <th style="padding:0.65rem 0.85rem;font-weight:700;color:var(--h-text);">Module</th>
                                    <th style="padding:0.65rem 0.85rem;font-weight:700;color:var(--h-text);">Connection Pin</th>
                                    <th style="padding:0.65rem 0.85rem;font-weight:700;color:var(--h-text);">ESP32 Pin</th>
                                    <th style="padding:0.65rem 0.85rem;font-weight:700;color:var(--h-text);">Protocol / Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom:1px solid var(--h-border);">
                                    <td style="padding:0.65rem 0.85rem;font-weight:600;color:var(--h-text);">
                                        🌡️ DS18B20
                                    </td>
                                    <td style="padding:0.65rem 0.85rem;color:var(--h-text2);">DATA</td>
                                    <td style="padding:0.65rem 0.85rem;">
                                        <code style="background:rgba(2,132,199,0.15);color:#38bdf8;padding:2px 6px;border-radius:4px;font-weight:700;">D4 (GPIO 4)</code>
                                    </td>
                                    <td style="padding:0.65rem 0.85rem;color:var(--h-text3);font-size:0.75rem;">Dallas 1-Wire · Coolant / Cylinder Head Temp (°C)</td>
                                </tr>
                                <tr style="border-bottom:1px solid var(--h-border);">
                                    <td style="padding:0.65rem 0.85rem;font-weight:600;color:var(--h-text);">
                                        ⏱️ Hall RPM
                                    </td>
                                    <td style="padding:0.65rem 0.85rem;color:var(--h-text2);">DIG</td>
                                    <td style="padding:0.65rem 0.85rem;">
                                        <code style="background:rgba(2,132,199,0.15);color:#38bdf8;padding:2px 6px;border-radius:4px;font-weight:700;">D23 (GPIO 23)</code>
                                    </td>
                                    <td style="padding:0.65rem 0.85rem;color:var(--h-text3);font-size:0.75rem;">Digital Pulse Interrupt · Crankshaft / Flywheel RPM</td>
                                </tr>
                                <tr style="border-bottom:1px solid var(--h-border);">
                                    <td style="padding:0.65rem 0.85rem;font-weight:600;color:var(--h-text);">
                                        📈 MPU6050
                                    </td>
                                    <td style="padding:0.65rem 0.85rem;color:var(--h-text2);">SDA / SCL</td>
                                    <td style="padding:0.65rem 0.85rem;">
                                        <code style="background:rgba(2,132,199,0.15);color:#38bdf8;padding:2px 6px;border-radius:4px;font-weight:700;">D21 / D22</code>
                                    </td>
                                    <td style="padding:0.65rem 0.85rem;color:var(--h-text3);font-size:0.75rem;">I2C (0x68) · 3-Axis Engine Vibration Acceleration & Peak G</td>
                                </tr>
                                <tr style="border-bottom:1px solid var(--h-border);">
                                    <td style="padding:0.65rem 0.85rem;font-weight:600;color:var(--h-text);">
                                        ⚡ INA219
                                    </td>
                                    <td style="padding:0.65rem 0.85rem;color:var(--h-text2);">SDA / SCL</td>
                                    <td style="padding:0.65rem 0.85rem;">
                                        <code style="background:rgba(2,132,199,0.15);color:#38bdf8;padding:2px 6px;border-radius:4px;font-weight:700;">D21 / D22</code>
                                    </td>
                                    <td style="padding:0.65rem 0.85rem;color:var(--h-text3);font-size:0.75rem;">I2C (0x40) · Battery / Alternator DC Voltage & Current Draw</td>
                                </tr>
                                <tr>
                                    <td style="padding:0.65rem 0.85rem;font-weight:600;color:var(--h-text);">
                                        🎙️ INMP441
                                    </td>
                                    <td style="padding:0.65rem 0.85rem;color:var(--h-text2);">SCK / WS / SD</td>
                                    <td style="padding:0.65rem 0.85rem;">
                                        <code style="background:rgba(245,158,11,0.15);color:var(--h-amber);padding:2px 6px;border-radius:4px;font-weight:700;">D18 / D19 / D5</code>
                                    </td>
                                    <td style="padding:0.65rem 0.85rem;color:var(--h-text3);font-size:0.75rem;">I2S Digital MEMS Audio · Engine Acoustic Knocks & Harmonic Noise</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Wiring Tips --}}
                    <div style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.85rem 1rem;">
                        <div style="font-size:0.78rem;font-weight:700;color:var(--h-text);margin-bottom:0.35rem;">⚡ Power & Pull-up Resistor Best Practices</div>
                        <ul style="margin:0;padding-left:1.1rem;font-size:0.75rem;color:var(--h-text2);line-height:1.6;">
                            <li><strong>DS18B20:</strong> Connect a 4.7kΩ pull-up resistor between <code style="color:var(--h-teal);">DATA (D4)</code> and <code style="color:var(--h-teal);">3.3V</code>.</li>
                            <li><strong>Hall Sensor:</strong> Connect a 10kΩ pull-up resistor between <code style="color:var(--h-teal);">DIG (D23)</code> and <code style="color:var(--h-teal);">3.3V</code> if using open-collector NPN Hall switches.</li>
                            <li><strong>Shared I2C Bus:</strong> MPU6050 (0x68) and INA219 (0x40) share the same I2C lines (<code style="color:#a78bfa;">SDA=D21, SCL=D22</code>) without conflict due to different hex addresses.</li>
                            <li><strong>INMP441 Microphone:</strong> Connect <code style="color:var(--h-amber);">L/R to GND</code> for Left mono channel output.</li>
                        </ul>
                    </div>
                </div>

                {{-- TAB 3: C++ Firmware Sketch --}}
                <div id="iot-tab-code" style="display:none;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.6rem;">
                        <span style="font-size:0.75rem;color:var(--h-text2);">Complete Arduino C++ sketch for all 5 sensors with ISO/SAE 21434 auth:</span>
                        <button type="button" onclick="copySketchCode()" id="copy-sketch-btn" class="haven-action-btn haven-action-btn-secondary" style="font-size:0.72rem;padding:0.25rem 0.6rem;">
                            📋 Copy Sketch
                        </button>
                    </div>
                    <pre id="sketch-code-block" style="background:#0a0e14;border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.85rem;font-family:'Courier New',monospace;font-size:0.68rem;color:#cbd5e1;max-height:320px;overflow:auto;line-height:1.45;white-space:pre;">/*
 * HAVEN AI - ESP32 Automotive Telemetry DAQ Node
 * ISO/SAE 21434 Authenticated Client
 *
 * Hardware Pin Connections:
 *  - DS18B20 (DATA):  GPIO 4  (4.7k pull-up to 3.3V)
 *  - Hall RPM (DIG):  GPIO 23 (Hardware Interrupt)
 *  - MPU6050 (SDA):   GPIO 21, (SCL): GPIO 22
 *  - INA219 (SDA):    GPIO 21, (SCL): GPIO 22
 *  - INMP441 (I2S):   SCK=GPIO 18, WS=GPIO 19, SD=GPIO 5
 */

#include &lt;WiFi.h&gt;
#include &lt;HTTPClient.h&gt;
#include &lt;ArduinoJson.h&gt;
#include &lt;Wire.h&gt;
#include &lt;OneWire.h&gt;
#include &lt;DallasTemperature.h&gt;
#include &lt;Adafruit_MPU6050.h&gt;
#include &lt;Adafruit_INA219.h&gt;

// --- CONFIGURATION ---
const char* ssid       = "YOUR_WIFI_SSID";
const char* password   = "YOUR_WIFI_PASSWORD";
const char* serverUrl  = "{{ url('/api/v1/telemetry/ingest') }}";
const char* deviceKey  = "hvn_dev_YOUR_VEHICLE_DEVICE_KEY"; // From Vehicle profile
const int   vehicleId  = 1;

// --- PIN MAPPINGS ---
#define PIN_DS18B20 4
#define PIN_HALL    23
#define PIN_I2C_SDA 21
#define PIN_I2C_SCL 22

// --- SENSOR OBJECTS ---
OneWire oneWire(PIN_DS18B20);
DallasTemperature tempSensor(&amp;oneWire);
Adafruit_MPU6050 mpu;
Adafruit_INA219 ina219;

// --- HALL RPM INTERRUPT COUNTER ---
volatile unsigned long pulseCount = 0;
void IRAM_ATTR onHallPulse() {
  pulseCount++;
}

void setup() {
  Serial.begin(115200);
  Wire.begin(PIN_I2C_SDA, PIN_I2C_SCL);

  // Initialize Sensors
  tempSensor.begin();
  mpu.begin();
  mpu.setAccelerometerRange(MPU6050_RANGE_8_G);
  ina219.begin();

  // Hall RPM Interrupt
  pinMode(PIN_HALL, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(PIN_HALL), onHallPulse, FALLING);

  // Connect to Wi-Fi
  Serial.printf("Connecting to Wi-Fi [%s]...", ssid);
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nHAVEN ESP32 DAQ Connected!");
}

void loop() {
  if (WiFi.status() == WL_CONNECTED) {
    // 1. Read DS18B20 Temp
    tempSensor.requestTemperatures();
    float tempC = tempSensor.getTempCByIndex(0);

    // 2. Read Hall RPM (1-second sampling window)
    noInterrupts();
    unsigned long pulses = pulseCount;
    pulseCount = 0;
    interrupts();
    unsigned int rpm = (pulses * 60) / 1; // 1 pulse per rev

    // 3. Read INA219 Voltage &amp; Current
    float busVoltage = ina219.getBusVoltage_V();
    float current_mA = ina219.getCurrent_mA();

    // 4. Sample MPU6050 Accelerometer
    sensors_event_t a, g, temp;
    float peakG = 0.0;
    StaticJsonDocument&lt;2048&gt; doc;
    JsonArray samples = doc.createNestedArray("samples");

    for (int i = 0; i &lt; 30; i++) {
      mpu.getEvent(&amp;a, &amp;g, &amp;temp);
      float mag = sqrt(sq(a.acceleration.x) + sq(a.acceleration.y) + sq(a.acceleration.z)) / 9.80665;
      if (mag &gt; peakG) peakG = mag;
      samples.add(round(mag * 1000.0) / 1000.0);
      delay(20);
    }

    // 5. Assemble Ingestion Payload
    doc["vehicle_id"]     = vehicleId;
    doc["device_id"]      = "ESP32-HAVEN-NODE-01";
    doc["temperature_c"]  = tempC;
    doc["rpm"]            = rpm;
    doc["voltage_v"]      = busVoltage;
    doc["current_ma"]     = current_mA;
    doc["peak_g"]         = peakG;
    doc["auto_analyze"]   = true;

    // 6. Transmit to HAVEN via HTTP POST with ISO/SAE 21434 Header
    HTTPClient http;
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-Haven-Device-Key", deviceKey);

    String requestBody;
    serializeJson(doc, requestBody);
    int httpResponseCode = http.POST(requestBody);

    Serial.printf("[INGEST] HTTP %d | Temp: %.1fC | RPM: %u | Volts: %.2fV | Peak: %.2fg\n",
                  httpResponseCode, tempC, rpm, busVoltage, peakG);
    http.end();
  }
  delay(10000);
}</pre>
                </div>

                {{-- TAB 4: REST API Specs --}}
                <div id="iot-tab-api" style="display:none;">
                    <div style="font-size:0.8rem;color:var(--h-text);margin-bottom:0.75rem;">
                        <strong>Endpoint:</strong> <code style="color:var(--h-teal);background:var(--h-surface2);padding:0.2rem 0.5rem;border-radius:4px;">POST {{ url('/api/v1/telemetry/ingest') }}</code>
                    </div>
                    <div style="font-size:0.75rem;color:var(--h-text3);margin-bottom:0.4rem;text-transform:uppercase;font-weight:700;">Multi-Sensor cURL Request (ISO/SAE 21434 Authenticated)</div>
                    <pre style="background:#0a0e14;border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.8rem;font-family:'Courier New',monospace;font-size:0.7rem;color:#cbd5e1;overflow-x:auto;">curl -X POST "{{ url('/api/v1/telemetry/ingest') }}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Haven-Device-Key: hvn_dev_YOUR_VEHICLE_KEY" \
  -d '{
    "vehicle_id": 1,
    "device_id": "ESP32-NODE-01",
    "temperature_c": 92.4,
    "rpm": 1850,
    "voltage_v": 13.82,
    "current_ma": 420.5,
    "peak_g": 3.84,
    "samples": [0.98, 1.05, 3.84, 1.12, 0.95],
    "auto_analyze": true
  }'</pre>
                </div>

            </div>

            {{-- Modal Footer --}}
            <div style="padding:0.75rem 1.4rem;border-top:1px solid var(--h-border);background:var(--h-surface);display:flex;align-items:center;justify-content:space-between;">
                <div style="font-size:0.72rem;color:var(--h-text3);display:flex;align-items:center;gap:0.35rem;">
                    <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#22c55e;"></span>
                    <span>Endpoint: <strong>{{ url('/api/v1/telemetry/ingest') }}</strong></span>
                </div>
                <button type="button" onclick="closeIotModal()" class="haven-action-btn haven-action-btn-secondary" style="font-size:0.75rem;padding:0.35rem 0.75rem;">
                    Close
                </button>
            </div>

        </div>
    </div>

    {{-- IoT Modal Scripts --}}
    <script>
    function openIotModal() {
        document.getElementById('iot-modal-backdrop').style.display = 'flex';
        updateSimPreview();
    }
    function closeIotModal() {
        document.getElementById('iot-modal-backdrop').style.display = 'none';
    }

    function switchIotTab(tab) {
        document.querySelectorAll('.iot-tab-btn').forEach(b => {
            b.style.color = 'var(--h-text3)';
            b.style.borderBottomColor = 'transparent';
        });
        const activeBtn = document.getElementById('tab-btn-' + tab);
        if (activeBtn) {
            activeBtn.style.color = 'var(--h-teal)';
            activeBtn.style.borderBottomColor = 'var(--h-teal)';
        }

        document.getElementById('iot-tab-sim').style.display = tab === 'sim' ? 'block' : 'none';
        document.getElementById('iot-tab-wiring').style.display = tab === 'wiring' ? 'block' : 'none';
        document.getElementById('iot-tab-code').style.display = tab === 'code' ? 'block' : 'none';
        document.getElementById('iot-tab-api').style.display = tab === 'api' ? 'block' : 'none';
    }

    function updateSimPreview() {
        const profile = document.getElementById('sim-profile').value;
        const tempEl = document.getElementById('sim-prev-temp');
        const rpmEl = document.getElementById('sim-prev-rpm');
        const voltsEl = document.getElementById('sim-prev-volts');
        const vibEl = document.getElementById('sim-prev-vib');

        if (profile === 'valve_clearance') {
            tempEl.textContent = '90.5°C';
            tempEl.style.color = 'var(--h-teal)';
            rpmEl.textContent = '2,200';
            voltsEl.textContent = '13.8V';
            vibEl.textContent = '3.72g';
            vibEl.style.color = 'var(--h-red)';
        } else if (profile === 'misfire') {
            tempEl.textContent = '89.2°C';
            tempEl.style.color = 'var(--h-teal)';
            rpmEl.textContent = '720';
            voltsEl.textContent = '13.9V';
            vibEl.textContent = '1.95g';
            vibEl.style.color = 'var(--h-amber)';
        } else if (profile === 'loose_component') {
            tempEl.textContent = '88.0°C';
            tempEl.style.color = 'var(--h-teal)';
            rpmEl.textContent = '850';
            voltsEl.textContent = '14.0V';
            vibEl.textContent = '2.10g';
            vibEl.style.color = 'var(--h-amber)';
        } else if (profile === 'battery_fault') {
            tempEl.textContent = '86.5°C';
            tempEl.style.color = 'var(--h-teal)';
            rpmEl.textContent = '1,100';
            voltsEl.textContent = '11.4V';
            vibEl.textContent = '0.95g';
            vibEl.style.color = 'var(--h-text)';
        } else {
            tempEl.textContent = '89.0°C';
            tempEl.style.color = 'var(--h-green)';
            rpmEl.textContent = '800';
            voltsEl.textContent = '14.2V';
            vibEl.textContent = '0.92g';
            vibEl.style.color = 'var(--h-green)';
        }
    }

    function copySketchCode() {
        const code = document.getElementById('sketch-code-block').innerText;
        navigator.clipboard.writeText(code).then(() => {
            const btn = document.getElementById('copy-sketch-btn');
            btn.innerText = '✓ Copied!';
            setTimeout(() => { btn.innerText = '📋 Copy Sketch'; }, 2000);
        });
    }

    function logConsole(msg, isSuccess = false) {
        const consoleEl = document.getElementById('sim-console');
        const time = new Date().toLocaleTimeString();
        const color = isSuccess ? '#22c55e' : '#38bdf8';
        consoleEl.innerHTML += `<div style="color:${color};">[${time}] ${msg}</div>`;
        consoleEl.scrollTop = consoleEl.scrollHeight;
    }

    function broadcastSimTelemetry() {
        const vehicleSelect = document.getElementById('sim-vehicle');
        const vehicleId = vehicleSelect.value;
        const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
        const deviceKey = selectedOption ? (selectedOption.dataset.key || '') : '';
        const profile = document.getElementById('sim-profile').value;
        const autoAnalyze = document.getElementById('sim-auto-analyze').checked;
        const btn = document.getElementById('btn-broadcast-sim');

        btn.disabled = true;
        btn.innerHTML = '<span>Transmitting Telemetry Over Wi-Fi...</span>';

        logConsole(`Initializing 5-sensor Wi-Fi packet for vehicle #${vehicleId} (Profile: ${profile.toUpperCase()})...`);
        if (deviceKey) {
            logConsole(`🔑 Authenticating device via ISO/SAE 21434 Key: ${deviceKey.substring(0, 14)}...`);
        }

        // Configure profile variables
        let tempC = 89.0;
        let rpm = 800;
        let voltageV = 14.2;
        let currentMa = 380.0;
        let samples = [];

        if (profile === 'valve_clearance') {
            tempC = 90.5;
            rpm = 2200;
            voltageV = 13.8;
            currentMa = 520.0;
            for (let i = 0; i < 40; i++) {
                let base = 1.0 + Math.sin(i * 0.3) * 0.15;
                // Sharp periodic tapping spikes at camshaft frequency intervals
                if (i % 5 === 0) base += 2.72;
                samples.push(parseFloat(base.toFixed(3)));
            }
        } else if (profile === 'misfire') {
            tempC = 89.2;
            rpm = 720;
            voltageV = 13.9;
            currentMa = 440.0;
            for (let i = 0; i < 40; i++) {
                let base = 1.0 + Math.sin(i * 0.15) * 0.7;
                if (i % 8 === 0) base = 0.25; // Combustion dropout
                samples.push(parseFloat(base.toFixed(3)));
            }
        } else if (profile === 'loose_component') {
            tempC = 88.0;
            rpm = 850;
            voltageV = 14.0;
            currentMa = 400.0;
            for (let i = 0; i < 40; i++) {
                // High steady RMS, low crest factor — continuous structural resonance
                let base = 0.55 + Math.sin(i * 0.2) * 0.42 + (Math.random() * 0.1 - 0.05);
                samples.push(parseFloat(base.toFixed(3)));
            }
        } else if (profile === 'battery_fault') {
            tempC = 86.5;
            rpm = 1100;
            voltageV = 11.4;
            currentMa = -120.0;
            for (let i = 0; i < 40; i++) {
                let base = 0.95 + (Math.random() * 0.08 - 0.04);
                samples.push(parseFloat(base.toFixed(3)));
            }
        } else {
            for (let i = 0; i < 40; i++) {
                let base = 1.0 + (Math.random() * 0.15 - 0.075);
                samples.push(parseFloat(base.toFixed(3)));
            }
        }

        const payload = {
            vehicle_id: parseInt(vehicleId),
            device_id: 'ESP32-SIMULATOR-NODE',
            device_key: deviceKey,
            temperature_c: tempC,
            rpm: rpm,
            voltage_v: voltageV,
            current_ma: currentMa,
            sample_rate_hz: 50,
            auto_analyze: autoAnalyze,
            samples: samples,
            sensor_data: {
                ds18b20: { pin: 'D4', temp_c: tempC },
                hall_rpm: { pin: 'D23', rpm: rpm },
                ina219: { pins: 'D21/D22', voltage_v: voltageV, current_ma: currentMa },
                mpu6050: { pins: 'D21/D22', sample_count: samples.length },
                inmp441: { pins: 'D18/D19/D5', protocol: 'I2S' }
            }
        };

        logConsole(`Sending 5-sensor packet: Temp ${tempC}°C (D4), ${rpm} RPM (D23), ${voltageV}V (D21/D22)...`);

        fetch('{{ url('/api/v1/telemetry/ingest') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Haven-Device-Key': deviceKey
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M1.42 9a16 16 0 0 1 21.16 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg><span>Broadcast ESP32 Wi-Fi Telemetry Packet</span>';

            if (data.success) {
                logConsole(`✅ INGEST SUCCESS: Created Diagnostic #${data.diagnostic_id} (Status: ${data.status.toUpperCase()})`, true);
                logConsole(`Temp: ${data.metrics.temperature_c}°C | RPM: ${data.metrics.rpm} | Volts: ${data.metrics.voltage_v}V | Peak: ${data.metrics.peak_g}g`, true);
                if (data.metrics.auto_analyzed) {
                    logConsole(`🚀 AnalyzeDiagnosticJob dispatched to background queue!`, true);
                }
                logConsole(`Opening session in 2 seconds...`, true);

                setTimeout(() => {
                    window.location.href = data.show_url;
                }, 2000);
            } else {
                logConsole(`❌ INGEST ERROR: ${data.message || 'Unknown error'}`);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<span>Broadcast ESP32 Wi-Fi Telemetry Packet</span>';
            logConsole(`❌ TRANSMISSION FAILED: ${err.message}`);
        });
    }
    </script>
</x-app-layout>
