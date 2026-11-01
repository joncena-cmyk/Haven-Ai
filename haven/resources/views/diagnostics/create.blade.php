<x-app-layout title="New Diagnostic">

    <div class="h-page-header">
        <div>
            <h1 class="h-page-title">New Diagnostic Session</h1>
            <p class="h-page-desc">Capture engine audio and vibration data for ML analysis.</p>
        </div>
        <a href="{{ route('diagnostics.index') }}" class="haven-btn-ghost" style="text-decoration:none;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            History
        </a>
    </div>

    @if($vehicles->isEmpty())
        <div class="h-empty">
            <div class="h-empty-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <h2 class="h-empty-title">No Vehicles Registered</h2>
            <p class="h-empty-desc">Register a vehicle before starting a diagnostic session.</p>
            <a href="{{ route('vehicles.create') }}" class="haven-btn-primary" style="width:auto;text-decoration:none;">Register a Vehicle</a>
        </div>
    @else

    {{-- Step indicator --}}
    <div id="step-indicator" style="display:flex;align-items:center;gap:0;margin-bottom:1.5rem;background:var(--h-surface);border:1px solid var(--h-border);border-radius:var(--h-radius-xl);overflow:hidden;">
        @foreach([['01','Select Vehicle'],['02','Record Audio'],['03','Vibration Data'],['04','Review & Submit']] as $i => $step)
            <div class="diag-step-tab" id="step-tab-{{ $i+1 }}" style="flex:1;display:flex;align-items:center;justify-content:center;gap:0.5rem;padding:0.75rem 0.5rem;font-size:0.72rem;font-weight:600;color:var(--h-text3);cursor:pointer;transition:background 0.1s,color 0.1s;border-right:{{ $i < 3 ? '1px solid var(--h-border)' : 'none' }};text-align:center;" onclick="goStep({{ $i+1 }})">
                <span class="step-num" style="width:20px;height:20px;border-radius:50%;background:var(--h-surface3);border:1px solid var(--h-border);display:flex;align-items:center;justify-content:center;font-size:0.65rem;flex-shrink:0;">{{ $step[0] }}</span>
                <span class="step-label" style="display:none;">{{ $step[1] }}</span>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('diagnostics.store') }}" enctype="multipart/form-data" id="diag-form">
        @csrf

        {{-- ══ STEP 1: Vehicle ══ --}}
        <div class="diag-step" id="step-1">
            <div class="haven-form-card">
                <div class="haven-form-card-header">
                    <div class="h-form-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div>
                    <div>
                        <h2 class="haven-form-title">Select Vehicle</h2>
                        <p class="haven-form-subtitle">Choose which vehicle to run this diagnostic session on.</p>
                    </div>
                </div>
                @if($vehicles->isEmpty())
                    <div class="h-notice" style="margin-bottom:1rem;background:var(--h-amber-bg);border-color:rgba(245,158,11,0.25);">
                        <svg class="h-notice-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span style="color:var(--h-text);">
                            No vehicles found in your garage. You must register at least one vehicle before recording diagnostics.
                            <a href="{{ route('vehicles.create') }}" style="color:var(--h-teal);font-weight:600;margin-left:0.3rem;">Register Vehicle →</a>
                        </span>
                    </div>
                @endif
                <div class="haven-form-group">
                    <label class="haven-label" for="vehicle_id">Vehicle <span class="haven-required">*</span></label>
                    <select id="vehicle_id" name="vehicle_id" class="haven-input haven-select @error('vehicle_id') haven-input-error @enderror" required {{ $vehicles->isEmpty() ? 'disabled' : '' }} onchange="updateVehicleScopeBadge()">
                        <option value="">— Select a vehicle —</option>
                        @foreach($vehicles as $v)
                            <option value="{{ $v->id }}" 
                                    data-scope-compliant="{{ $v->isScopeCompliant() ? '1' : '0' }}"
                                    data-scope-label="{{ $v->scope_meta['label'] }}"
                                    data-scope-css="{{ $v->scope_meta['badge_css'] }}"
                                    data-scope-notice="{{ $v->scope_meta['notice'] ?? '' }}"
                                    {{ (old('vehicle_id', $selectedVehicleId) == $v->id) ? 'selected' : '' }}>
                                {{ $v->display_name }}{{ $v->plate_number ? ' · ' . $v->plate_number : '' }}
                            </option>
                        @endforeach
                    </select>
                    <div id="vehicle-scope-badge" style="margin-top:0.4rem;display:none;"></div>
                    @error('vehicle_id')<p class="haven-error-msg">{{ $message }}</p>@enderror
                </div>
                <div class="haven-form-group" style="margin-top:1rem;">
                    <label class="haven-label" for="notes">Session Notes <span class="haven-optional">(optional)</span></label>
                    <textarea id="notes" name="notes" rows="2" class="haven-input" placeholder="Describe any symptoms noticed before this diagnostic…">{{ old('notes') }}</textarea>
                </div>
                <div class="haven-form-actions">
                    <button type="button" class="haven-btn-primary" style="width:auto;" onclick="goStep(2)">
                        Next: Record Audio
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ══ STEP 2: Audio ══ --}}
        <div class="diag-step" id="step-2" style="display:none;">
            <div class="haven-form-card">
                <div class="haven-form-card-header">
                    <div class="h-form-icon" style="background:rgba(245,158,11,0.08);border-color:rgba(245,158,11,0.2);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                    </div>
                    <div>
                        <h2 class="haven-form-title">Engine Audio</h2>
                        <p class="haven-form-subtitle">Record engine sound directly, or upload an existing audio file.</p>
                    </div>
                </div>

                {{-- Recording Mode Selector (Idle vs Revving per Thesis Scope) --}}
                <div style="margin-bottom:1.25rem;">
                    <label class="haven-label" style="display:block;margin-bottom:0.45rem;">
                        Engine Recording State <span class="haven-required">*</span>
                        <span style="font-weight:400;color:var(--h-text3);font-size:0.7rem;margin-left:0.3rem;">(Calibrated for thesis baseline classification)</span>
                    </label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(100%, 200px), 1fr));gap:0.75rem;">
                        <label id="mode-idle-card" style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.75rem 0.9rem;border:1.5px solid var(--h-teal);background:rgba(2,132,199,0.08);border-radius:var(--h-radius);cursor:pointer;transition:all 0.15s ease;">
                            <input type="radio" name="recording_mode" id="mode-idle-radio" value="idle" checked onchange="setRecordingMode('idle')" style="margin-top:0.2rem;accent-color:var(--h-teal);">
                            <div>
                                <div style="font-size:0.84rem;font-weight:700;color:var(--h-text);display:flex;align-items:center;gap:0.4rem;">
                                    Baseline Idle
                                    <span class="h-badge h-badge-teal" style="font-size:0.6rem;padding:0.1rem 0.35rem;">750–900 RPM</span>
                                </div>
                                <div style="font-size:0.7rem;color:var(--h-text3);margin-top:0.25rem;line-height:1.35;">
                                    Operating temp in Park/Neutral, A/C OFF. Evaluates combustion cadence &amp; structural mount vibration.
                                </div>
                            </div>
                        </label>
                        <label id="mode-revving-card" style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.75rem 0.9rem;border:1px solid var(--h-border);background:var(--h-surface2);border-radius:var(--h-radius);cursor:pointer;transition:all 0.15s ease;">
                            <input type="radio" name="recording_mode" id="mode-revving-radio" value="revving" onchange="setRecordingMode('revving')" style="margin-top:0.2rem;accent-color:var(--h-teal);">
                            <div>
                                <div style="font-size:0.84rem;font-weight:700;color:var(--h-text);display:flex;align-items:center;gap:0.4rem;">
                                    Baseline Revving
                                    <span class="h-badge h-badge-amber" style="font-size:0.6rem;padding:0.1rem 0.35rem;">2,000–2,500 RPM</span>
                                </div>
                                <div style="font-size:0.7rem;color:var(--h-text3);margin-top:0.25rem;line-height:1.35;">
                                    Steady throttle hold. Evaluates high-frequency valvetrain lash, valve clearance, and camshaft harmonics.
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Recording tabs --}}
                <div style="display:flex;border:1px solid var(--h-border);border-radius:var(--h-radius);overflow:hidden;margin-bottom:1.25rem;">
                    <button type="button" id="tab-record" onclick="setAudioTab('record')"
                        style="flex:1;padding:0.55rem;font-size:0.8rem;font-weight:600;font-family:inherit;background:var(--h-teal-bg);color:var(--h-teal);border:none;cursor:pointer;border-right:1px solid var(--h-border);">
                        Record Live
                    </button>
                    <button type="button" id="tab-upload" onclick="setAudioTab('upload')"
                        style="flex:1;padding:0.55rem;font-size:0.8rem;font-weight:500;font-family:inherit;background:var(--h-surface2);color:var(--h-text2);border:none;cursor:pointer;">
                        Upload File
                    </button>
                </div>

                {{-- Live Recording panel --}}
                <div id="panel-record">
                    {{-- Waveform canvas --}}
                    <canvas id="waveform" width="600" height="80"
                        style="width:100%;height:80px;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);display:block;margin-bottom:1rem;"></canvas>

                    {{-- Timer --}}
                    <div style="display:flex;align-items:center;justify-content:center;gap:1.25rem;margin-bottom:1rem;">
                        <div style="font-size:1.75rem;font-weight:700;color:var(--h-text);letter-spacing:0.05em;font-variant-numeric:tabular-nums;" id="rec-timer">0:00</div>
                        <div id="rec-dot" style="width:10px;height:10px;border-radius:50%;background:var(--h-border2);"></div>
                    </div>

                    {{-- Controls --}}
                    <div style="display:flex;justify-content:center;gap:0.75rem;margin-bottom:1rem;">
                        <button type="button" id="btn-start" onclick="startRecording()"
                            class="haven-btn-primary" style="width:auto;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
                            Start Recording
                        </button>
                        <button type="button" id="btn-stop" onclick="stopRecording()"
                            class="haven-btn-secondary" style="width:auto;display:none;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="6" y="6" width="12" height="12" fill="currentColor" rx="1"/></svg>
                            Stop
                        </button>
                    </div>

                    {{-- Playback --}}
                    <div id="playback-section" style="display:none;padding:0.875rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);">
                        <div style="font-size:0.72rem;font-weight:600;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.07em;margin-bottom:0.5rem;">Recording Captured</div>
                        <audio id="audio-playback" controls style="width:100%;margin-bottom:0.5rem;"></audio>
                        <div style="font-size:0.72rem;color:var(--h-text3);" id="rec-info"></div>
                    </div>

                    {{-- Hidden input carries the blob as a file --}}
                    <input type="file" id="audio_blob" name="audio_blob" style="display:none;" accept="audio/*">
                    <input type="hidden" id="audio_duration" name="audio_duration">
                </div>

                {{-- Upload panel --}}
                <div id="panel-upload" style="display:none;">
                    <label class="haven-label" for="audio_file">Audio File (WAV, MP3, WebM, M4A)</label>
                    <input id="audio_file" name="audio_file" type="file" accept=".wav,.mp3,.webm,.ogg,.m4a"
                           class="haven-input @error('audio_file') haven-input-error @enderror"
                           style="padding:0.5rem;">
                    @error('audio_file')<p class="haven-error-msg">{{ $message }}</p>@enderror
                    <div class="h-notice" style="margin-top:0.75rem;">
                        <svg class="h-notice-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>Record the engine at idle RPM for at least 10 seconds. Keep the microphone within 30 cm of the engine bay.</span>
                    </div>
                </div>

                <div class="haven-form-actions">
                    <button type="button" class="haven-btn-ghost" style="width:auto;" onclick="goStep(1)">Back</button>
                    <button type="button" class="haven-btn-primary" style="width:auto;" onclick="goStep(3)">
                        Next: Vibration Data
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ══ STEP 3: Vibration ══ --}}
        <div class="diag-step" id="step-3" style="display:none;">
            <div class="haven-form-card">
                <div class="haven-form-card-header">
                    <div class="h-form-icon" style="background:rgba(34,197,94,0.08);border-color:rgba(34,197,94,0.2);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </div>
                    <div>
                        <h2 class="haven-form-title">Vibration Data</h2>
                        <p class="haven-form-subtitle">Upload the CSV or JSON file exported from the ESP32 accelerometer.</p>
                    </div>
                </div>

                <div class="haven-form-group" style="margin-bottom:1rem;">
                    <label class="haven-label" for="vibration_file">ESP32 Data File (CSV or JSON)</label>
                    <input id="vibration_file" name="vibration_file" type="file" accept=".csv,.json,.txt"
                           class="haven-input @error('vibration_file') haven-input-error @enderror"
                           style="padding:0.5rem;"
                           onchange="previewVibration(this)">
                    @error('vibration_file')<p class="haven-error-msg">{{ $message }}</p>@enderror
                </div>

                {{-- Preview panel --}}
                <div id="vib-preview" style="display:none;margin-bottom:1rem;padding:1rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);">
                    <div style="font-size:0.72rem;font-weight:600;color:var(--h-green);text-transform:uppercase;letter-spacing:0.07em;margin-bottom:0.75rem;">File Preview</div>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--h-border);border:1px solid var(--h-border);border-radius:6px;overflow:hidden;">
                        <div style="background:var(--h-surface);padding:0.75rem;text-align:center;">
                            <div style="font-size:1.1rem;font-weight:700;color:var(--h-text);font-variant-numeric:tabular-nums;" id="prev-samples">—</div>
                            <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.06em;margin-top:0.2rem;">Samples</div>
                        </div>
                        <div style="background:var(--h-surface);padding:0.75rem;text-align:center;">
                            <div style="font-size:1.1rem;font-weight:700;color:var(--h-amber);font-variant-numeric:tabular-nums;" id="prev-peak">—</div>
                            <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.06em;margin-top:0.2rem;">Peak (g)</div>
                        </div>
                        <div style="background:var(--h-surface);padding:0.75rem;text-align:center;">
                            <div style="font-size:1.1rem;font-weight:700;color:var(--h-teal);font-variant-numeric:tabular-nums;" id="prev-rms">—</div>
                            <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.06em;margin-top:0.2rem;">RMS (g)</div>
                        </div>
                    </div>
                    <pre id="prev-rows" style="margin-top:0.75rem;font-size:0.72rem;color:var(--h-text3);background:var(--h-bg);border:1px solid var(--h-border);border-radius:4px;padding:0.5rem;overflow-x:auto;max-height:100px;"></pre>
                </div>

                {{-- Optional: Manual Multi-Sensor Telemetry Inputs --}}
                <div style="margin-top:1.25rem;padding:1rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                        <div style="font-size:0.75rem;font-weight:700;color:var(--h-text);text-transform:uppercase;letter-spacing:0.06em;display:flex;align-items:center;gap:0.4rem;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M1.42 9a16 16 0 0 1 21.16 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>
                            Optional Hardware Telemetry (Manual DAQ)
                        </div>
                        <span class="h-badge h-badge-teal" style="font-size:0.6rem;">5-Sensor</span>
                    </div>
                    <p style="font-size:0.72rem;color:var(--h-text3);margin:0 0 0.75rem;">
                        If testing without direct ESP32 Wi-Fi ingestion, enter gauge readings to include them in the ML inference &amp; report.
                    </p>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:0.75rem;">
                        <div>
                            <label class="haven-label" style="font-size:0.7rem;" for="temperature_c">Temp (°C · DS18B20)</label>
                            <input type="number" step="0.1" id="temperature_c" name="temperature_c" class="haven-input" placeholder="e.g. 88.5" value="{{ old('temperature_c') }}">
                        </div>
                        <div>
                            <label class="haven-label" style="font-size:0.7rem;" for="rpm">RPM (Hall Sensor)</label>
                            <input type="number" id="rpm" name="rpm" class="haven-input" placeholder="e.g. 850" value="{{ old('rpm') }}">
                        </div>
                        <div>
                            <label class="haven-label" style="font-size:0.7rem;" for="voltage_v">Voltage (V · INA219)</label>
                            <input type="number" step="0.01" id="voltage_v" name="voltage_v" class="haven-input" placeholder="e.g. 13.8" value="{{ old('voltage_v') }}">
                        </div>
                        <div>
                            <label class="haven-label" style="font-size:0.7rem;" for="current_ma">Current (mA)</label>
                            <input type="number" step="0.1" id="current_ma" name="current_ma" class="haven-input" placeholder="e.g. 420.0" value="{{ old('current_ma') }}">
                        </div>
                    </div>
                </div>

                {{-- ESP32 wiring hint --}}
                <div class="h-notice" style="margin-top:1rem;">
                    <svg class="h-notice-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>
                        Expected CSV format: <code style="color:var(--h-teal);font-size:0.72rem;">timestamp,x,y,z</code> or <code style="color:var(--h-teal);font-size:0.72rem;">timestamp,magnitude</code> (g-force values, 50–200 Hz sample rate).
                        Export from the ESP32 serial plotter or SD card log.
                    </span>
                </div>

                <div class="haven-form-actions">
                    <button type="button" class="haven-btn-ghost" style="width:auto;" onclick="goStep(2)">Back</button>
                    <button type="button" class="haven-btn-primary" style="width:auto;" onclick="goStep(4)">
                        Review & Submit
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ══ STEP 4: Review & Submit ══ --}}
        <div class="diag-step" id="step-4" style="display:none;">
            <div class="haven-form-card">
                <div class="haven-form-card-header">
                    <div class="h-form-icon" style="background:var(--h-teal-bg);border-color:var(--h-teal-ring);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div>
                        <h2 class="haven-form-title">Review & Submit</h2>
                        <p class="haven-form-subtitle">Confirm your captured data before sending for analysis.</p>
                    </div>
                </div>

                <div id="review-summary" style="margin-bottom:1.25rem;">
                    {{-- Filled by JS --}}
                </div>

                <div class="h-notice" style="margin-bottom:1.25rem;">
                    <svg class="h-notice-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span>
                        <strong style="color:var(--h-teal);">Dual-Tier ML Inference Ready.</strong>
                        Your telemetry will be evaluated via asynchronous background processing.
                        The system executes acoustic MFCC &amp; FFT spectral analysis alongside ESP32 vibration and multi-sensor heuristics.
                    </span>
                </div>

                <div class="haven-form-actions">
                    <button type="button" class="haven-btn-ghost" style="width:auto;" onclick="goStep(3)">Back</button>
                    <button type="submit" id="btn-submit" class="haven-btn-primary" style="width:auto;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
                        Submit Diagnostic
                    </button>
                </div>
            </div>
        </div>

    </form>
    @endif

<style>
.diag-step-tab.active { background: var(--h-teal-bg) !important; color: var(--h-teal) !important; }
.diag-step-tab.active .step-num { background: var(--h-teal) !important; color: #0a0e0d !important; border-color: var(--h-teal) !important; }
@media (min-width: 640px) { .step-label { display: inline !important; } }
</style>

<script>
/* ── Step navigation ── */
let currentStep = 1;
function goStep(n) {
    document.querySelectorAll('.diag-step').forEach(el => el.style.display = 'none');
    document.getElementById('step-' + n).style.display = 'block';
    document.querySelectorAll('.diag-step-tab').forEach((el, i) => {
        el.classList.toggle('active', i + 1 === n);
    });
    currentStep = n;
    if (n === 4) buildReview();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
goStep(1);

/* ── Audio tab toggle ── */
function setAudioTab(tab) {
    const isRecord = tab === 'record';
    document.getElementById('panel-record').style.display = isRecord ? 'block' : 'none';
    document.getElementById('panel-upload').style.display = isRecord ? 'none' : 'block';
    document.getElementById('tab-record').style.background = isRecord ? 'var(--h-teal-bg)' : 'var(--h-surface2)';
    document.getElementById('tab-record').style.color = isRecord ? 'var(--h-teal)' : 'var(--h-text2)';
    document.getElementById('tab-upload').style.background = isRecord ? 'var(--h-surface2)' : 'var(--h-teal-bg)';
    document.getElementById('tab-upload').style.color = isRecord ? 'var(--h-text2)' : 'var(--h-teal)';
}

/* ── Audio Recording ── */
let mediaRecorder, chunks = [], audioBlob, animFrame, analyser, startTime, timerInterval;

async function startRecording() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const source = ctx.createMediaStreamSource(stream);
        analyser = ctx.createAnalyser();
        analyser.fftSize = 256;
        source.connect(analyser);

        mediaRecorder = new MediaRecorder(stream);
        chunks = [];
        mediaRecorder.ondataavailable = e => chunks.push(e.data);
        mediaRecorder.onstop = () => finalizeRecording(stream);
        mediaRecorder.start(100);

        startTime = Date.now();
        document.getElementById('btn-start').style.display = 'none';
        document.getElementById('btn-stop').style.display = 'inline-flex';
        document.getElementById('rec-dot').style.background = 'var(--h-red)';
        drawWaveform();
        timerInterval = setInterval(updateTimer, 500);
    } catch (err) {
        alert('Microphone access denied. Please allow microphone access in your browser and try again.');
    }
}

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    cancelAnimationFrame(animFrame);
    clearInterval(timerInterval);
    document.getElementById('btn-stop').style.display = 'none';
    document.getElementById('rec-dot').style.background = 'var(--h-teal)';
}

function finalizeRecording(stream) {
    stream.getTracks().forEach(t => t.stop());
    audioBlob = new Blob(chunks, { type: 'audio/webm' });
    const url = URL.createObjectURL(audioBlob);
    document.getElementById('audio-playback').src = url;
    document.getElementById('playback-section').style.display = 'block';

    const sec = Math.round((Date.now() - startTime) / 1000);
    document.getElementById('audio_duration').value = sec;
    document.getElementById('rec-info').textContent =
        `Duration: ${Math.floor(sec/60)}:${String(sec%60).padStart(2,'0')} · Size: ${(audioBlob.size/1024).toFixed(1)} KB`;

    // Inject blob into file input via DataTransfer
    try {
        const file = new File([audioBlob], 'engine-recording.webm', { type: 'audio/webm' });
        const dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('audio_blob').files = dt.files;
    } catch(e) { /* Safari fallback — store blob reference */ }
}

function updateTimer() {
    const sec = Math.round((Date.now() - startTime) / 1000);
    document.getElementById('rec-timer').textContent =
        `${Math.floor(sec/60)}:${String(sec%60).padStart(2,'0')}`;
}

function drawWaveform() {
    const canvas = document.getElementById('waveform');
    const ctx = canvas.getContext('2d');
    const buf = new Uint8Array(analyser.frequencyBinCount);

    function draw() {
        animFrame = requestAnimationFrame(draw);
        analyser.getByteTimeDomainData(buf);
        ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--h-surface2').trim() || '#1e2024';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.lineWidth = 2;
        ctx.strokeStyle = '#0284c7';
        ctx.beginPath();
        const sliceWidth = canvas.width / buf.length;
        let x = 0;
        for (let i = 0; i < buf.length; i++) {
            const v = buf[i] / 128.0;
            const y = (v * canvas.height) / 2;
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            x += sliceWidth;
        }
        ctx.lineTo(canvas.width, canvas.height / 2);
        ctx.stroke();
    }
    draw();
}

/* ── Vibration file preview ── */
function previewVibration(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const text = e.target.result;
        const lines = text.trim().split('\n').filter(Boolean);
        const header = lines[0];
        const dataLines = lines.slice(1);
        let mags = [];
        dataLines.forEach(line => {
            const cols = line.split(',');
            if (cols.length >= 4) {
                const x = parseFloat(cols[1]), y = parseFloat(cols[2]), z = parseFloat(cols[3]);
                if (!isNaN(x) && !isNaN(y) && !isNaN(z)) mags.push(Math.sqrt(x*x + y*y + z*z));
            } else if (cols.length >= 2) {
                const v = parseFloat(cols[1]);
                if (!isNaN(v)) mags.push(Math.abs(v));
            }
        });
        if (mags.length) {
            const peak = Math.max(...mags).toFixed(3);
            const rms  = Math.sqrt(mags.reduce((a,v)=>a+v*v,0)/mags.length).toFixed(3);
            document.getElementById('prev-samples').textContent = mags.length.toLocaleString();
            document.getElementById('prev-peak').textContent = peak + ' g';
            document.getElementById('prev-rms').textContent  = rms  + ' g';
        }
        document.getElementById('prev-rows').textContent = lines.slice(0,6).join('\n');
        document.getElementById('vib-preview').style.display = 'block';
    };
    reader.readAsText(file);
}

/* ── Review summary ── */
function buildReview() {
    const vehicleSelect = document.getElementById('vehicle_id');
    const vehicleText = vehicleSelect.options[vehicleSelect.selectedIndex]?.text || '—';
    const hasRecorded = audioBlob != null;
    const hasUpload   = document.getElementById('audio_file')?.files.length > 0;
    const hasAudio    = hasRecorded || hasUpload;
    const hasVib      = document.getElementById('vibration_file')?.files.length > 0;
    const notes       = document.getElementById('notes')?.value;
    const modeLabel   = currentRecordingMode === 'revving' ? 'Baseline Revving (2,000–2,500 RPM)' : 'Baseline Idle (750–900 RPM)';

    document.getElementById('review-summary').innerHTML = `
        <div style="display:grid;gap:0.5rem;">
            ${reviewRow('Vehicle', vehicleText, true)}
            ${reviewRow('Engine Mode', modeLabel, true)}
            ${reviewRow('Engine Audio', hasRecorded ? 'Recorded (' + document.getElementById('rec-info').textContent + ')' : hasUpload ? document.getElementById('audio_file').files[0].name : 'Not provided', hasAudio)}
            ${reviewRow('Vibration Data', hasVib ? document.getElementById('vibration_file').files[0].name + ' · ' + document.getElementById('prev-samples').textContent + ' samples' : 'Not provided', hasVib)}
            ${notes ? reviewRow('Notes', notes, true) : ''}
        </div>
        ${(!hasAudio || !hasVib) ? '<div class="h-flash h-flash-warn" style="margin-top:0.875rem;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Missing ' + (!hasAudio && !hasVib ? 'audio and vibration data' : !hasAudio ? 'audio recording' : 'vibration data') + '. Session will be saved as draft.</div>' : '<div class="h-flash h-flash-success" style="margin-top:0.875rem;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Both data sources captured. Session will be submitted for analysis.</div>'}
    `;
}
function reviewRow(label, value, ok) {
    return `<div style="display:flex;justify-content:space-between;align-items:flex-start;padding:0.6rem 0.875rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);gap:1rem;">
        <span style="font-size:0.78rem;color:var(--h-text3);flex-shrink:0;">${label}</span>
        <span style="font-size:0.82rem;color:${ok ? 'var(--h-text2)' : 'var(--h-red)'};font-weight:500;text-align:right;">${value}</span>
    </div>`;
}

/* ── Recording Mode Selector ── */
let currentRecordingMode = 'idle';
function setRecordingMode(mode) {
    currentRecordingMode = mode;
    const idleCard = document.getElementById('mode-idle-card');
    const revCard = document.getElementById('mode-revving-card');
    const rpmInput = document.getElementById('rpm');

    if (mode === 'idle') {
        idleCard.style.border = '1.5px solid var(--h-teal)';
        idleCard.style.background = 'rgba(2,132,199,0.08)';
        revCard.style.border = '1px solid var(--h-border)';
        revCard.style.background = 'var(--h-surface2)';
        if (rpmInput && (!rpmInput.value || rpmInput.dataset.autoFilled === '1')) {
            rpmInput.value = '800';
            rpmInput.dataset.autoFilled = '1';
        }
    } else {
        revCard.style.border = '1.5px solid var(--h-amber)';
        revCard.style.background = 'rgba(245,158,11,0.08)';
        idleCard.style.border = '1px solid var(--h-border)';
        idleCard.style.background = 'var(--h-surface2)';
        if (rpmInput && (!rpmInput.value || rpmInput.dataset.autoFilled === '1')) {
            rpmInput.value = '2200';
            rpmInput.dataset.autoFilled = '1';
        }
    }
}

/* ── Vehicle Scope Indicator in Wizard ── */
function updateVehicleScopeBadge() {
    const sel = document.getElementById('vehicle_id');
    const badgeEl = document.getElementById('vehicle-scope-badge');
    if (!sel || !badgeEl) return;
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) {
        badgeEl.style.display = 'none';
        return;
    }
    const compliant = opt.dataset.scopeCompliant === '1';
    const label = opt.dataset.scopeLabel || '';
    const css = opt.dataset.scopeCss || 'h-badge-teal';
    const notice = opt.dataset.scopeNotice || '';

    badgeEl.innerHTML = `
        <div style="display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;margin-top:0.35rem;">
            <span class="h-badge ${css}" style="font-size:0.65rem;">${label}</span>
            ${!compliant ? `<span style="font-size:0.7rem;color:var(--h-amber);">${notice}</span>` : ''}
        </div>
    `;
    badgeEl.style.display = 'block';
}
updateVehicleScopeBadge();
</script>

</x-app-layout>
