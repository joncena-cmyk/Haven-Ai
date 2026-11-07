<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HAVEN — Diagnostic Report #{{ $diagnostic->id }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            color: #111;
            background: #fff;
            line-height: 1.55;
        }

        /* ── Layout ── */
        .page { max-width: 820px; margin: 0 auto; padding: 2.5rem; }

        /* ── Header ── */
        .report-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding-bottom: 1.25rem;
            border-bottom: 2px solid #111;
            margin-bottom: 1.5rem;
        }
        .report-logo {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #111;
        }
        .report-logo span { color: #0284c7; }
        .report-meta { text-align: right; font-size: 0.75rem; color: #555; }
        .report-meta strong { color: #111; }

        /* ── Section titles ── */
        .section-title {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #888;
            margin-bottom: 0.75rem;
            padding-bottom: 0.35rem;
            border-bottom: 1px solid #e5e7eb;
        }

        /* ── Result banner ── */
        .result-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .result-banner.normal          { background: #f0fdf4; border: 1.5px solid #86efac; }
        .result-banner.valve_clearance { background: #fef2f2; border: 1.5px solid #fca5a5; }
        .result-banner.misfire         { background: #fffbeb; border: 1.5px solid #fcd34d; }
        .result-banner.loose_component { background: #fffbeb; border: 1.5px solid #fcd34d; }
        .result-banner.ambient_noise   { background: #fffbeb; border: 1.5px solid #fcd34d; }
        /* Legacy fallback classes */
        .result-banner.knocking        { background: #fef2f2; border: 1.5px solid #fca5a5; }
        .result-banner.misfiring       { background: #fffbeb; border: 1.5px solid #fcd34d; }
        .result-banner.idling_rough    { background: #fffbeb; border: 1.5px solid #fcd34d; }
        .result-banner.overheating     { background: #fef2f2; border: 1.5px solid #fca5a5; }
        .result-banner.pending         { background: #f0f9ff; border: 1.5px solid #7dd3fc; }

        .result-condition {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #111;
        }
        .result-label { font-size: 0.7rem; color: #777; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.2rem; }
        .result-confidence { font-size: 1.4rem; font-weight: 700; }
        .confidence-bar-bg { height: 5px; background: #e5e7eb; border-radius: 3px; margin-top: 0.5rem; width: 120px; overflow: hidden; }
        .confidence-bar    { height: 5px; border-radius: 3px; background: #0284c7; }

        /* ── Data grid ── */
        .data-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .data-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .data-card-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #555;
            padding: 0.5rem 0.875rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0.875rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.8rem;
        }
        .data-row:last-child { border-bottom: none; }
        .data-label { color: #777; }
        .data-value { font-weight: 600; color: #111; text-align: right; }

        /* ── Top classes ── */
        .top-classes { margin-bottom: 1.5rem; }
        .tc-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.45rem;
            font-size: 0.8rem;
        }
        .tc-label { width: 130px; font-weight: 500; color: #333; }
        .tc-bar-bg { flex: 1; height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; }
        .tc-bar { height: 6px; border-radius: 3px; background: #0284c7; }
        .tc-pct { width: 40px; text-align: right; font-weight: 600; color: #111; }

        /* ── Notes ── */
        .notes-box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.875rem;
            font-size: 0.82rem;
            color: #444;
            line-height: 1.65;
            margin-bottom: 1.5rem;
        }

        /* ── Disclaimer ── */
        .disclaimer {
            padding: 0.875rem 1rem;
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            font-size: 0.75rem;
            color: #78350f;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        /* ── Footer ── */
        .report-footer {
            padding-top: 0.875rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.72rem;
            color: #aaa;
        }

        /* ── Print button (screen only) ── */
        .print-btn {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 0.6rem 1.1rem;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .back-btn {
            position: fixed;
            top: 1.25rem;
            right: 8.5rem;
            background: #f9fafb;
            color: #555;
            border: 1px solid #e5e7eb;
            padding: 0.6rem 1rem;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
        }

        @media (max-width: 640px) {
            .page { padding: 4.5rem 1rem 2rem !important; }
            .print-btn { top: 0.75rem; right: 0.75rem; padding: 0.45rem 0.8rem; font-size: 0.75rem; }
            .back-btn { top: 0.75rem; left: 0.75rem; right: auto; padding: 0.45rem 0.8rem; font-size: 0.75rem; }
            .report-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
            .report-meta { text-align: left; }
            .result-banner { flex-direction: column; align-items: flex-start; gap: 0.65rem; }
            .data-grid { grid-template-columns: 1fr !important; }
        }

        @media print {
            .print-btn, .back-btn { display: none; }
            .page { padding: 1.5rem; }
            body { font-size: 12px; }
        }
    </style>
</head>
<body>

<a href="{{ route('diagnostics.show', $diagnostic) }}" class="back-btn">← Back</a>
<button onclick="window.print()" class="print-btn">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
    Print / Save PDF
</button>

<div class="page">

    {{-- Header --}}
    <div class="report-header">
        <div>
            <div class="report-logo">HA<span>VEN</span></div>
            <div style="font-size:0.75rem;color:#777;margin-top:0.2rem;">Engine Diagnostic Report</div>
        </div>
        <div class="report-meta">
            <strong>Report #{{ $diagnostic->id }}</strong><br>
            Generated: {{ now()->format('F j, Y · g:i A') }}<br>
            Vehicle: {{ $diagnostic->vehicle->display_name }}<br>
            {{ $diagnostic->vehicle->plate_number ? 'Plate: ' . $diagnostic->vehicle->plate_number : '' }}
        </div>
    </div>

    {{-- ML Result Banner --}}
    @if($diagnostic->status === 'completed' && $diagnostic->ml_condition)
        @php
            $topClasses = $diagnostic->ml_result_raw['top_classes'] ?? [];
            $isMock = str_contains($diagnostic->ml_result_raw['mode'] ?? '', 'mock');
        @endphp
        <div class="result-banner {{ $diagnostic->ml_condition }}">
            <div>
                <div class="result-label">Detected Condition</div>
                <div class="result-condition">{{ $diagnostic->condition_label ?? ucwords(str_replace('_',' ',$diagnostic->ml_condition)) }}</div>
                @if($isMock)
                    <div style="font-size:0.72rem;color:#f59e0b;margin-top:0.2rem;">⚠ Simulated result — ML server was offline</div>
                @endif
            </div>
            @if($diagnostic->ml_confidence !== null)
                <div style="text-align:right;">
                    <div class="result-label">Model Confidence</div>
                    <div class="result-confidence">{{ $diagnostic->confidence_percent }}</div>
                    <div class="confidence-bar-bg">
                        <div class="confidence-bar" style="width:{{ round($diagnostic->ml_confidence*100) }}%;"></div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Top classes breakdown --}}
        @if(!empty($topClasses))
            <div class="top-classes">
                <div class="section-title">Classification Breakdown</div>
                @foreach($topClasses as $tc)
                    <div class="tc-row">
                        <div class="tc-label">{{ ucwords(str_replace('_',' ',$tc['condition'])) }}</div>
                        <div class="tc-bar-bg"><div class="tc-bar" style="width:{{ round(($tc['confidence']??0)*100) }}%;"></div></div>
                        <div class="tc-pct">{{ round(($tc['confidence']??0)*100,1) }}%</div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        <div class="result-banner pending">
            <div>
                <div class="result-label">Analysis Status</div>
                <div class="result-condition" style="font-size:1.2rem;">{{ $diagnostic->status_label }}</div>
            </div>
        </div>
    @endif

    {{-- Data grid --}}
    <div class="data-grid">

        {{-- Vehicle info --}}
        <div class="data-card">
            <div class="data-card-title">Vehicle</div>
            <div class="data-row"><span class="data-label">Name</span><span class="data-value">{{ $diagnostic->vehicle->display_name }}</span></div>
            <div class="data-row"><span class="data-label">Scope</span><span class="data-value" style="color:{{ $diagnostic->vehicle->isScopeCompliant() ? '#0284c7' : '#f59e0b' }};">{{ $diagnostic->vehicle->scope_meta['label'] }}</span></div>
            @if($diagnostic->vehicle->plate_number)
                <div class="data-row"><span class="data-label">Plate</span><span class="data-value">{{ $diagnostic->vehicle->plate_number }}</span></div>
            @endif
            @if($diagnostic->vehicle->engine_type)
                <div class="data-row"><span class="data-label">Engine</span><span class="data-value">{{ $diagnostic->vehicle->engine_type }}</span></div>
            @endif
            @if($diagnostic->vehicle->transmission)
                <div class="data-row"><span class="data-label">Transmission</span><span class="data-value">{{ $diagnostic->vehicle->transmission }}</span></div>
            @endif
            @if($diagnostic->vehicle->mileage_km)
                <div class="data-row"><span class="data-label">Mileage</span><span class="data-value">{{ number_format($diagnostic->vehicle->mileage_km) }} km</span></div>
            @endif
        </div>

        {{-- Session info --}}
        <div class="data-card">
            <div class="data-card-title">Session</div>
            <div class="data-row"><span class="data-label">Session ID</span><span class="data-value">#{{ $diagnostic->id }}</span></div>
            <div class="data-row"><span class="data-label">Engine Mode</span><span class="data-value">{{ $diagnostic->recording_mode_label }}</span></div>
            <div class="data-row"><span class="data-label">Recorded</span><span class="data-value">{{ $diagnostic->created_at->format('M j, Y · g:i A') }}</span></div>
            @if($diagnostic->ml_processed_at)
                <div class="data-row"><span class="data-label">Analyzed</span><span class="data-value">{{ $diagnostic->ml_processed_at->format('M j, Y · g:i A') }}</span></div>
            @endif
            <div class="data-row"><span class="data-label">Status</span><span class="data-value">{{ $diagnostic->status_label }}</span></div>
        </div>

        {{-- Audio --}}
        <div class="data-card">
            <div class="data-card-title">Engine Audio</div>
            @if($diagnostic->hasAudio())
                <div class="data-row"><span class="data-label">Status</span><span class="data-value" style="color:#0284c7;">Captured</span></div>
                @if($diagnostic->audio_original_name)
                    <div class="data-row"><span class="data-label">File</span><span class="data-value" style="font-size:0.72rem;">{{ $diagnostic->audio_original_name }}</span></div>
                @endif
                @if($diagnostic->audio_duration_sec)
                    <div class="data-row">
                        <span class="data-label">Duration</span>
                        <span class="data-value">{{ floor($diagnostic->audio_duration_sec/60) }}:{{ str_pad($diagnostic->audio_duration_sec%60,2,'0',STR_PAD_LEFT) }}</span>
                    </div>
                @endif
            @else
                <div class="data-row"><span class="data-label">Status</span><span class="data-value" style="color:#aaa;">Not recorded</span></div>
            @endif
        </div>

        {{-- Vibration --}}
        <div class="data-card">
            <div class="data-card-title">Vibration Data (ESP32)</div>
            @if($diagnostic->hasVibration())
                <div class="data-row"><span class="data-label">Status</span><span class="data-value" style="color:#0284c7;">Captured</span></div>
                @if($diagnostic->vibration_sample_count)
                    <div class="data-row"><span class="data-label">Samples</span><span class="data-value">{{ number_format($diagnostic->vibration_sample_count) }}</span></div>
                @endif
                @if($diagnostic->vibration_peak_g !== null)
                    <div class="data-row"><span class="data-label">Peak Acceleration</span><span class="data-value">{{ $diagnostic->vibration_peak_g }} g</span></div>
                @endif
                @if($diagnostic->vibration_rms_g !== null)
                    <div class="data-row"><span class="data-label">RMS Acceleration</span><span class="data-value">{{ $diagnostic->vibration_rms_g }} g</span></div>
                @endif
                @if($diagnostic->vibration_peak_g && $diagnostic->vibration_rms_g)
                    <div class="data-row">
                        <span class="data-label">Crest Factor</span>
                        <span class="data-value">{{ $diagnostic->vibration_rms_g > 0 ? round($diagnostic->vibration_peak_g / $diagnostic->vibration_rms_g, 2) : '—' }}</span>
                    </div>
                @endif
            @else
                <div class="data-row"><span class="data-label">Status</span><span class="data-value" style="color:#aaa;">Not uploaded</span></div>
            @endif
        </div>

        {{-- Multi-Sensor Telemetry (ESP32 DAQ) --}}
        @if($diagnostic->hasMultiSensor() || $diagnostic->temperature_c !== null || $diagnostic->rpm !== null || $diagnostic->voltage_v !== null)
            <div class="data-card" style="grid-column: 1 / -1;">
                <div class="data-card-title">Multi-Sensor Telemetry (ESP32 DAQ · DS18B20 · Hall · INA219)</div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:1px;background:#e5e7eb;">
                    <div style="background:#fff;padding:0.6rem 0.875rem;">
                        <div style="font-size:0.68rem;color:#777;text-transform:uppercase;letter-spacing:0.04em;">Engine Temp</div>
                        <div style="font-size:0.95rem;font-weight:700;color:{{ ($diagnostic->temperature_c && $diagnostic->temperature_c > 105) ? '#ef4444' : '#111' }};">
                            {{ $diagnostic->temperature_c !== null ? number_format($diagnostic->temperature_c, 1) . ' °C' : '—' }}
                        </div>
                    </div>
                    <div style="background:#fff;padding:0.6rem 0.875rem;">
                        <div style="font-size:0.68rem;color:#777;text-transform:uppercase;letter-spacing:0.04em;">Crankshaft RPM</div>
                        <div style="font-size:0.95rem;font-weight:700;color:#111;">
                            {{ $diagnostic->rpm !== null ? number_format($diagnostic->rpm) . ' RPM' : '—' }}
                        </div>
                    </div>
                    <div style="background:#fff;padding:0.6rem 0.875rem;">
                        <div style="font-size:0.68rem;color:#777;text-transform:uppercase;letter-spacing:0.04em;">Bus Voltage</div>
                        <div style="font-size:0.95rem;font-weight:700;color:{{ ($diagnostic->voltage_v && ($diagnostic->voltage_v < 12.0 || $diagnostic->voltage_v > 15.0)) ? '#ef4444' : '#111' }};">
                            {{ $diagnostic->voltage_v !== null ? number_format($diagnostic->voltage_v, 2) . ' V' : '—' }}
                        </div>
                    </div>
                    <div style="background:#fff;padding:0.6rem 0.875rem;">
                        <div style="font-size:0.68rem;color:#777;text-transform:uppercase;letter-spacing:0.04em;">Sensor Current</div>
                        <div style="font-size:0.95rem;font-weight:700;color:#111;">
                            {{ $diagnostic->current_ma !== null ? number_format($diagnostic->current_ma, 1) . ' mA' : '—' }}
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>

    {{-- Notes --}}
    @if($diagnostic->notes)
        <div class="section-title">Session Notes</div>
        <div class="notes-box">{{ $diagnostic->notes }}</div>
    @endif

    {{-- Disclaimer --}}
    <div class="disclaimer">
        <strong>⚠ Important Disclaimer</strong><br>
        This report is generated by the HAVEN engine diagnostic system as part of an academic capstone project (CINS).
        The ML classifier provides a probabilistic estimate based on audio and vibration features —
        it is <strong>not a certified mechanical diagnosis</strong>.
        Model confidence indicates classifier certainty, not the probability of mechanical failure.
        Always consult a licensed automotive technician before taking mechanical action.
    </div>

    {{-- Footer --}}
    <div class="report-footer">
        <span>HAVEN AI · Academic Capstone · CINS</span>
        <span>Session #{{ $diagnostic->id }} · {{ $diagnostic->created_at->format('Y-m-d') }}</span>
    </div>

</div>
</body>
</html>
