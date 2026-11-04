{{-- Bosch OEM Industrial Live ESP32 Telemetry & Hardware Health Monitor --}}

{{-- Notification Toast Container --}}
<div id="haven-toast-container" style="position:fixed;top:1.2rem;right:1.2rem;z-index:9999;display:flex;flex-direction:column;gap:0.5rem;pointer-events:none;"></div>

<div id="esp32-live-widget" class="h-live-telemetry-panel">
    
    {{-- Header Bar --}}
    <div class="h-telemetry-header">
        <div style="display:flex;align-items:center;gap:0.65rem;flex-wrap:wrap;">
            <div id="live-beacon-indicator" style="display:flex;align-items:center;gap:0.45rem;">
                <span class="live-ping-dot" id="live-dot" style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#64748b;"></span>
                <span id="live-status-text" class="h-mono" style="font-size:0.75rem;font-weight:700;letter-spacing:0.06em;color:var(--h-text2);text-transform:uppercase;">ESP32 DAQ NODE — CHECKING STATUS...</span>
            </div>
            <span id="live-mode-badge" class="h-badge" style="background:rgba(2,132,199,0.12);color:var(--h-teal);border:1px solid rgba(2,132,199,0.25);font-size:0.68rem;padding:0.18rem 0.5rem;font-family:var(--h-mono);">
                HARDWARE MONITOR
            </span>
            <span id="live-vehicle-tag" class="h-badge h-telemetry-vehicle-badge">
                <span id="val-vehicle-name">Vehicle</span>
            </span>
        </div>

        <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
            <span style="font-size:0.75rem;color:var(--h-text3);">Sync: <strong id="val-last-sync" class="h-mono" style="color:var(--h-text2);">Standby</strong></span>
            
            {{-- Live Rolling Waveform Toggle --}}
            <button type="button" id="btn-toggle-chart" onclick="toggleRollingChart()" class="h-telemetry-info-btn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span id="txt-toggle-chart">Live Waveform</span>
                <span id="icon-toggle-chart" style="font-size:0.7rem;">▾</span>
            </button>

            {{-- More Info / Component Diagnostic Toggle Button --}}
            <button type="button" id="btn-toggle-hardware-info" onclick="toggleHardwareInfo()" 
                    class="h-telemetry-info-btn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <span id="txt-toggle-info">More Info</span>
                <span id="icon-toggle-info" style="font-size:0.7rem;">▾</span>
            </button>
        </div>
    </div>

    {{-- Single Clean Row: 5 Live Instrument Meters --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(100%, 140px), 1fr));gap:0.75rem;">
        
        {{-- Sensor 1: Temperature (DS18B20) --}}
        <div id="meter-card-temp" class="h-meter-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
                <span style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:700;letter-spacing:0.05em;">Coolant / Temp</span>
                <span id="badge-temp-conn" class="h-badge" style="font-size:0.6rem;padding:0.08rem 0.3rem;background:var(--h-green-bg);color:var(--h-green);border:1px solid var(--h-green);">OK</span>
            </div>
            <div style="display:flex;align-items:baseline;gap:0.25rem;">
                <span id="val-temp" class="h-mono" style="font-size:1.6rem;font-weight:700;color:var(--h-telemetry-cyan);">--</span>
                <span style="font-size:0.75rem;color:var(--h-text3);font-weight:600;">°C</span>
            </div>
            <div style="height:4px;background:var(--h-surface3);border-radius:2px;margin-top:0.5rem;overflow:hidden;">
                <div id="bar-temp" style="height:100%;width:0%;background:#0284c7;transition:width 0.4s ease;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.62rem;color:var(--h-text3);margin-top:0.35rem;">
                <span>DS18B20 · D4</span>
                <span id="sub-temp">Ambient</span>
            </div>
        </div>

        {{-- Sensor 2: Tachometer / RPM (Hall Effect) --}}
        <div id="meter-card-rpm" class="h-meter-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
                <span style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:700;letter-spacing:0.05em;">Engine Speed</span>
                <span id="badge-rpm-conn" class="h-badge" style="font-size:0.6rem;padding:0.08rem 0.3rem;background:var(--h-green-bg);color:var(--h-green);border:1px solid var(--h-green);">OK</span>
            </div>
            <div style="display:flex;align-items:baseline;gap:0.25rem;">
                <span id="val-rpm" class="h-mono" style="font-size:1.6rem;font-weight:700;color:var(--h-text);">--</span>
                <span style="font-size:0.75rem;color:var(--h-text3);font-weight:600;">RPM</span>
            </div>
            <div style="height:4px;background:var(--h-surface3);border-radius:2px;margin-top:0.5rem;overflow:hidden;">
                <div id="bar-rpm" style="height:100%;width:0%;background:#16a34a;transition:width 0.4s ease;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.62rem;color:var(--h-text3);margin-top:0.35rem;">
                <span>HALL · D23</span>
                <span id="sub-rpm">Ready</span>
            </div>
        </div>

        {{-- Sensor 3: Vibration / Acceleration (MPU6050) --}}
        <div id="meter-card-vib" class="h-meter-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
                <span style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:700;letter-spacing:0.05em;">Vibration Peak</span>
                <span id="badge-vib-conn" class="h-badge" style="font-size:0.6rem;padding:0.08rem 0.3rem;background:var(--h-green-bg);color:var(--h-green);border:1px solid var(--h-green);">OK</span>
            </div>
            <div style="display:flex;align-items:baseline;gap:0.25rem;">
                <span id="val-vib" class="h-mono" style="font-size:1.6rem;font-weight:700;color:var(--h-text);">--</span>
                <span style="font-size:0.75rem;color:var(--h-text3);font-weight:600;">m/s²</span>
            </div>
            <div style="height:4px;background:var(--h-surface3);border-radius:2px;margin-top:0.5rem;overflow:hidden;">
                <div id="bar-vib" style="height:100%;width:0%;background:#0284c7;transition:width 0.3s ease;"></div>
            </div>
            <div class="h-mono" style="font-size:0.62rem;color:var(--h-text3);margin-top:0.35rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                X:<span id="val-ax">0.0</span> Y:<span id="val-ay">0.0</span> Z:<span id="val-az">0.0</span>
            </div>
        </div>

        {{-- Sensor 4: Bus Power (INA219) --}}
        <div id="meter-card-power" class="h-meter-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
                <span style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:700;letter-spacing:0.05em;">Battery / Bus</span>
                <span id="badge-power-conn" class="h-badge" style="font-size:0.6rem;padding:0.08rem 0.3rem;background:var(--h-amber-bg);color:var(--h-amber);border:1px solid var(--h-amber);">NO LOAD</span>
            </div>
            <div style="display:flex;align-items:baseline;gap:0.25rem;">
                <span id="val-voltage" class="h-mono" style="font-size:1.6rem;font-weight:700;color:var(--h-text);">--</span>
                <span style="font-size:0.75rem;color:var(--h-text3);font-weight:600;">V</span>
            </div>
            <div style="height:4px;background:var(--h-surface3);border-radius:2px;margin-top:0.5rem;overflow:hidden;">
                <div id="bar-power" style="height:100%;width:0%;background:#0284c7;transition:width 0.3s ease;"></div>
            </div>
            <div class="h-mono" style="display:flex;justify-content:space-between;font-size:0.62rem;color:var(--h-text3);margin-top:0.35rem;">
                <span>INA219</span>
                <span><span id="val-current">--</span> mA</span>
            </div>
        </div>

        {{-- Sensor 5: Audio Acoustic (INMP441) --}}
        <div id="meter-card-audio" class="h-meter-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
                <span style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:700;letter-spacing:0.05em;">Acoustic Level</span>
                <span id="badge-audio-conn" class="h-badge" style="font-size:0.6rem;padding:0.08rem 0.3rem;background:var(--h-green-bg);color:var(--h-green);border:1px solid var(--h-green);">OK</span>
            </div>
            <div style="display:flex;align-items:baseline;gap:0.25rem;">
                <span id="val-audio" class="h-mono" style="font-size:1.6rem;font-weight:700;color:var(--h-text);">--</span>
                <span style="font-size:0.75rem;color:var(--h-text3);font-weight:600;">RMS</span>
            </div>
            <div style="height:4px;background:var(--h-surface3);border-radius:2px;margin-top:0.5rem;overflow:hidden;">
                <div id="bar-audio" style="height:100%;width:0%;background:#ea580c;transition:width 0.25s ease;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.62rem;color:var(--h-text3);margin-top:0.35rem;">
                <span>INMP441 · I2S</span>
                <span>Audio Stream</span>
            </div>
        </div>

    </div>

    {{-- Expandable Live Rolling Telemetry Waveform Oscilloscope --}}
    <div id="panel-rolling-chart" style="display:none;margin-top:1.15rem;padding-top:1rem;border-top:1px dashed rgba(56,189,248,0.22);animation:fadeIn 0.25s ease-in-out;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.6rem;flex-wrap:wrap;gap:0.5rem;">
            <div style="font-size:0.72rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:var(--h-text2);display:flex;align-items:center;gap:0.4rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span>Live ESP32 Multi-Sensor Rolling Stream (Last 30 Packets)</span>
            </div>
            <div style="display:flex;align-items:center;gap:0.75rem;font-size:0.68rem;color:var(--h-text3);">
                <span style="display:flex;align-items:center;gap:0.25rem;"><strong style="color:#0284c7;">●</strong> Vibration Peak (g)</span>
                <span style="display:flex;align-items:center;gap:0.25rem;"><strong style="color:#16a34a;">●</strong> Engine RPM</span>
                <span style="display:flex;align-items:center;gap:0.25rem;"><strong style="color:#f59e0b;">●</strong> Coolant Temp (°C)</span>
            </div>
        </div>
        <div style="background:rgba(15,23,42,0.85);border:1px solid rgba(56,189,248,0.25);border-radius:10px;padding:0.6rem 0.8rem;position:relative;">
            <canvas id="telemetry-chart-canvas" width="760" height="100" style="width:100%;height:100px;display:block;"></canvas>
            <div id="chart-standby-msg" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:0.75rem;color:#64748b;font-family:var(--h-mono);">
                Waiting for incoming ESP32 live stream packets...
            </div>
        </div>
    </div>

    {{-- Expandable "More Info" Hardware Component Diagnostics Drawer --}}
    <div id="panel-hardware-details" style="display:none;margin-top:1.15rem;padding-top:1rem;border-top:1px dashed rgba(56,189,248,0.22);animation:fadeIn 0.25s ease-in-out;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
            <div style="font-size:0.72rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:var(--h-text2);display:flex;align-items:center;gap:0.4rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span>Hardware Component Wiring & Diagnostics Details</span>
            </div>
            <span style="font-size:0.68rem;color:var(--h-text3);">Bench Test Auto-Diagnostics</span>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(100%, 180px), 1fr));gap:0.65rem;">
            
            {{-- Detail 1: DS18B20 --}}
            <div class="h-detail-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;">
                    <span style="font-size:0.75rem;font-weight:600;color:var(--h-text);">DS18B20 Temp</span>
                    <span id="detail-badge-ds18b20" class="h-badge" style="font-size:0.62rem;padding:0.1rem 0.35rem;background:var(--h-green-bg);color:var(--h-green);border:1px solid var(--h-green);">CONNECTED</span>
                </div>
                <div class="h-mono" style="font-size:0.7rem;color:var(--h-teal);margin-bottom:0.2rem;">Pin: D4 (OneWire)</div>
                <div style="font-size:0.68rem;color:var(--h-text2);" id="detail-hint-ds18b20">Reading room/ambient temp correctly.</div>
            </div>

            {{-- Detail 2: MPU6050 --}}
            <div class="h-detail-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;">
                    <span style="font-size:0.75rem;font-weight:600;color:var(--h-text);">MPU6050 Accel</span>
                    <span id="detail-badge-mpu6050" class="h-badge" style="font-size:0.62rem;padding:0.1rem 0.35rem;background:var(--h-green-bg);color:var(--h-green);border:1px solid var(--h-green);">CONNECTED</span>
                </div>
                <div class="h-mono" style="font-size:0.7rem;color:var(--h-teal);margin-bottom:0.2rem;">I2C: D21 (SDA) / D22 (SCL)</div>
                <div style="font-size:0.68rem;color:var(--h-text2);" id="detail-hint-mpu6050">Earth gravity detected (~9.8 m/s²). Tap board to test.</div>
            </div>

            {{-- Detail 3: INA219 --}}
            <div class="h-detail-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;">
                    <span style="font-size:0.75rem;font-weight:600;color:var(--h-text);">INA219 Power</span>
                    <span id="detail-badge-ina219" class="h-badge" style="font-size:0.62rem;padding:0.1rem 0.35rem;background:var(--h-amber-bg);color:var(--h-amber);border:1px solid var(--h-amber);">ACTIVE (NO LOAD)</span>
                </div>
                <div class="h-mono" style="font-size:0.7rem;color:var(--h-teal);margin-bottom:0.2rem;">I2C: D21 (SDA) / D22 (SCL)</div>
                <div style="font-size:0.68rem;color:var(--h-text2);" id="detail-hint-ina219">Module active, but Vin+/- screw terminals have no battery hooked up.</div>
            </div>

            {{-- Detail 4: INMP441 --}}
            <div class="h-detail-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;">
                    <span style="font-size:0.75rem;font-weight:600;color:var(--h-text);">INMP441 Mic</span>
                    <span id="detail-badge-inmp441" class="h-badge" style="font-size:0.62rem;padding:0.1rem 0.35rem;background:var(--h-green-bg);color:var(--h-green);border:1px solid var(--h-green);">CONNECTED</span>
                </div>
                <div class="h-mono" style="font-size:0.7rem;color:var(--h-teal);margin-bottom:0.2rem;">I2S: D18 (BCLK), D19 (WS), D5 (SD)</div>
                <div style="font-size:0.68rem;color:var(--h-text2);" id="detail-hint-inmp441">Capturing ambient acoustic RMS audio stream.</div>
            </div>

            {{-- Detail 5: Hall RPM --}}
            <div class="h-detail-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;">
                    <span style="font-size:0.75rem;font-weight:600;color:var(--h-text);">Hall Tachometer</span>
                    <span id="detail-badge-hall" class="h-badge" style="font-size:0.62rem;padding:0.1rem 0.35rem;background:var(--h-green-bg);color:var(--h-green);border:1px solid var(--h-green);">READY</span>
                </div>
                <div class="h-mono" style="font-size:0.7rem;color:var(--h-teal);margin-bottom:0.2rem;">Pin: D23 (Digital Interrupt)</div>
                <div style="font-size:0.68rem;color:var(--h-text2);" id="detail-hint-hall">Ready for magnetic pass. If showing 40Hz noise, add 10kΩ pull-up.</div>
            </div>

        </div>
    </div>
</div>

<style>
@keyframes pulseDot {
    0% { transform: scale(0.95); opacity: 0.8; }
    50% { transform: scale(1.35); opacity: 1; }
    100% { transform: scale(0.95); opacity: 0.8; }
}
.live-ping-dot.active {
    animation: pulseDot 1.4s infinite ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes toastIn {
    from { opacity: 0; transform: translateX(60px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes toastOut {
    from { opacity: 1; transform: translateX(0); }
    to { opacity: 0; transform: translateX(60px); }
}
.haven-toast {
    pointer-events: auto;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.75rem 1.1rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    box-shadow: 0 4px 20px rgba(0,0,0,0.45);
    animation: toastIn 0.35s ease-out forwards;
    min-width: 280px;
    max-width: 420px;
    font-family: var(--h-mono), monospace;
}
.haven-toast.toast-exit {
    animation: toastOut 0.3s ease-in forwards;
}
.haven-toast-success {
    background: var(--h-surface);
    border: 1px solid var(--h-green);
    color: var(--h-green);
}
.haven-toast-warning {
    background: var(--h-surface);
    border: 1px solid var(--h-amber);
    color: var(--h-amber);
}
.haven-toast-info {
    background: var(--h-surface);
    border: 1px solid var(--h-teal);
    color: var(--h-teal);
}
.haven-toast-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.haven-toast-close {
    margin-left: auto;
    cursor: pointer;
    opacity: 0.5;
    font-size: 1rem;
    line-height: 1;
    padding: 0 0.2rem;
}
.haven-toast-close:hover {
    opacity: 1;
}
</style>

<script>
function toggleRollingChart() {
    const panel = document.getElementById('panel-rolling-chart');
    const txt = document.getElementById('txt-toggle-chart');
    const icon = document.getElementById('icon-toggle-chart');
    if (!panel) return;
    
    if (panel.style.display === 'none' || panel.style.display === '') {
        panel.style.display = 'block';
        txt.innerText = 'Hide Waveform';
        icon.innerText = '▴';
    } else {
        panel.style.display = 'none';
        txt.innerText = 'Live Waveform';
        icon.innerText = '▾';
    }
}

function toggleHardwareInfo() {
    const panel = document.getElementById('panel-hardware-details');
    const txt = document.getElementById('txt-toggle-info');
    const icon = document.getElementById('icon-toggle-info');
    if (!panel) return;
    
    if (panel.style.display === 'none' || panel.style.display === '') {
        panel.style.display = 'block';
        txt.innerText = 'Hide Details';
        icon.innerText = '▴';
    } else {
        panel.style.display = 'none';
        txt.innerText = 'More Info';
        icon.innerText = '▾';
    }
}

(function() {
    // Connection state tracking for notifications
    let wasOnline = null;        // null = first load, true/false = previous state
    let prevSensorState = {};    // track per-sensor connected state
    let toastQueue = [];

    function showToast(type, icon, message, duration) {
        duration = duration || 5000;
        const container = document.getElementById('haven-toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'haven-toast haven-toast-' + type;

        const dot = document.createElement('span');
        dot.className = 'haven-toast-dot';
        dot.style.background = type === 'success' ? 'var(--h-green)' : type === 'warning' ? 'var(--h-amber)' : 'var(--h-teal)';
        if (type === 'success') dot.style.boxShadow = '0 0 6px var(--h-green)';

        const text = document.createElement('span');
        text.innerText = message;

        const close = document.createElement('span');
        close.className = 'haven-toast-close';
        close.innerHTML = '&times;';
        close.onclick = function() { dismissToast(toast); };

        toast.appendChild(dot);
        toast.appendChild(text);
        toast.appendChild(close);
        container.appendChild(toast);

        setTimeout(function() { dismissToast(toast); }, duration);
    }

    function dismissToast(el) {
        if (!el || el.classList.contains('toast-exit')) return;
        el.classList.add('toast-exit');
        setTimeout(function() { el.remove(); }, 300);
    }

    // Sensor name map for human-readable notifications
    const sensorNames = {
        'ds18b20': 'Temperature Sensor (DS18B20)',
        'mpu6050': 'Vibration Sensor (MPU6050)',
        'ina219':  'Power Monitor (INA219)',
        'inmp441': 'Microphone (INMP441)',
        'hall_rpm': 'Hall Tachometer'
    };

    async function pollTelemetry() {
        try {
            const res = await fetch('/api/v1/telemetry/latest', { cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();

            const dot = document.getElementById('live-dot');
            const statusText = document.getElementById('live-status-text');

            // ── Connection State Change Notifications ──
            if (data.online && wasOnline === false) {
                // Just came online
                showToast('success', '●', '✓ ESP32 DAQ Node Connected — 5 sensors streaming live', 6000);
            } else if (data.online && wasOnline === null) {
                // First page load and already online
                showToast('info', '●', 'ESP32 DAQ Node detected — Live hardware stream active', 4000);
            } else if (!data.online && wasOnline === true) {
                // Just went offline
                showToast('warning', '●', '⚠ ESP32 DAQ Node disconnected — Check USB / Wi-Fi', 8000);
            }
            wasOnline = data.online;

            // ── Per-Sensor Connect/Disconnect Notifications ──
            if (data.components_health) {
                const ch = data.components_health;
                for (const [key, sensor] of Object.entries(ch)) {
                    const nowConnected = sensor.connected;
                    const wasConnected = prevSensorState[key];

                    if (wasConnected !== undefined && wasConnected !== nowConnected) {
                        if (nowConnected) {
                            showToast('success', '●', '✓ ' + sensorNames[key] + ' connected', 4000);
                        } else {
                            showToast('warning', '●', '⚠ ' + sensorNames[key] + ' disconnected — ' + sensor.hint, 7000);
                        }
                    }
                    prevSensorState[key] = nowConnected;
                }
            }

            if (data.online) {
                dot.style.background = 'var(--h-green)';
                dot.style.boxShadow = '0 0 8px var(--h-green)';
                dot.classList.add('active');
                statusText.innerText = 'ESP32 DAQ NODE STREAMING LIVE';
                statusText.style.color = 'var(--h-green)';
                const mb = document.getElementById('live-mode-badge');
                if (mb) {
                    mb.innerText = 'LIVE BENCH TEST';
                    mb.style.color = 'var(--h-teal)';
                    mb.style.borderColor = 'var(--h-teal-ring)';
                }
            } else {
                dot.style.background = 'var(--h-amber)';
                dot.style.boxShadow = 'none';
                dot.classList.remove('active');
                statusText.innerText = 'ESP32 DAQ NODE — STANDBY / OFFLINE';
                statusText.style.color = 'var(--h-amber)';
                const mb = document.getElementById('live-mode-badge');
                if (mb) {
                    mb.innerText = 'STANDBY';
                    mb.style.color = 'var(--h-text3)';
                    mb.style.borderColor = 'var(--h-border)';
                }
            }

            if (data.vehicle_name) {
                document.getElementById('val-vehicle-name').innerText = data.vehicle_name;
            }

            document.getElementById('val-last-sync').innerText = data.last_seen_human || 'Standby';

            // Common DOM element references
            const cardTemp = document.getElementById('meter-card-temp');
            const badgeTemp = document.getElementById('badge-temp-conn');
            const valTemp = document.getElementById('val-temp');
            const subTemp = document.getElementById('sub-temp');

            const cardVib = document.getElementById('meter-card-vib');
            const badgeVib = document.getElementById('badge-vib-conn');
            const valVib = document.getElementById('val-vib');

            const cardPower = document.getElementById('meter-card-power');
            const badgePower = document.getElementById('badge-power-conn');
            const valVoltage = document.getElementById('val-voltage');
            const valCurrent = document.getElementById('val-current');

            const cardAudio = document.getElementById('meter-card-audio');
            const badgeAudio = document.getElementById('badge-audio-conn');
            const valAudio = document.getElementById('val-audio');

            // ── WHEN HARDWARE IS OFFLINE (Clean Standby State) ──
            if (!data.online) {
                // DS18B20 Standby
                valTemp.innerText = '--';
                valTemp.style.color = 'var(--h-text3)';
                document.getElementById('bar-temp').style.width = '0%';
                badgeTemp.innerText = 'STANDBY';
                badgeTemp.style.background = 'var(--h-surface3)'; badgeTemp.style.color = 'var(--h-text3)';
                cardTemp.style.borderColor = '';
                subTemp.innerText = 'Waiting for board'; subTemp.style.color = 'var(--h-text3)';

                // Hall Standby
                document.getElementById('val-rpm').innerText = '0';
                document.getElementById('val-rpm').style.color = 'var(--h-text3)';
                document.getElementById('bar-rpm').style.width = '0%';
                const bRpm = document.getElementById('badge-rpm-conn');
                bRpm.innerText = 'STANDBY';
                bRpm.style.background = 'var(--h-surface3)'; bRpm.style.color = 'var(--h-text3)';
                document.getElementById('sub-rpm').innerText = 'Standby';

                // MPU6050 Standby
                valVib.innerText = '--';
                valVib.style.color = 'var(--h-text3)';
                document.getElementById('bar-vib').style.width = '0%';
                badgeVib.innerText = 'STANDBY';
                badgeVib.style.background = 'var(--h-surface3)'; badgeVib.style.color = 'var(--h-text3)';
                cardVib.style.borderColor = '';
                document.getElementById('val-ax').innerText = '-';
                document.getElementById('val-ay').innerText = '-';
                document.getElementById('val-az').innerText = '-';

                // INA219 Standby
                valVoltage.innerText = '--';
                valVoltage.style.color = 'var(--h-text3)';
                document.getElementById('bar-power').style.width = '0%';
                badgePower.innerText = 'STANDBY';
                badgePower.style.background = 'var(--h-surface3)'; badgePower.style.color = 'var(--h-text3)';
                cardPower.style.borderColor = '';
                valCurrent.innerText = '--';

                // INMP441 Standby
                valAudio.innerText = '--';
                valAudio.style.color = 'var(--h-text3)';
                document.getElementById('bar-audio').style.width = '0%';
                badgeAudio.innerText = 'STANDBY';
                badgeAudio.style.background = 'var(--h-surface3)'; badgeAudio.style.color = 'var(--h-text3)';
                cardAudio.style.borderColor = '';

                // Hardware detail cards standby
                ['ds18b20', 'mpu6050', 'ina219', 'inmp441', 'hall'].forEach(k => {
                    const el = document.getElementById('detail-badge-' + k);
                    if (el) {
                        el.innerText = 'STANDBY';
                        el.style.background = 'var(--h-surface3)';
                        el.style.color = 'var(--h-text3)';
                    }
                });
                return;
            }

            const isTempConnected = (data.temperature_c !== null && data.temperature_c !== undefined) && 
                                    (!data.components_health || data.components_health.ds18b20?.connected !== false);

            if (isTempConnected) {
                const temp = parseFloat(data.temperature_c);
                valTemp.innerText = temp.toFixed(1);
                valTemp.style.color = 'var(--h-telemetry-cyan)';
                const tempPct = Math.min(100, Math.max(0, (temp / 110) * 100));
                document.getElementById('bar-temp').style.width = tempPct + '%';
                badgeTemp.innerText = 'OK';
                badgeTemp.style.background = 'var(--h-green-bg)'; badgeTemp.style.color = 'var(--h-green)';
                cardTemp.style.borderColor = '';
                subTemp.innerText = 'Ambient';
                subTemp.style.color = 'var(--h-text3)';
            } else {
                valTemp.innerText = 'UNPLUGGED';
                valTemp.style.color = 'var(--h-red)';
                document.getElementById('bar-temp').style.width = '0%';
                badgeTemp.innerText = 'DISCONNECTED';
                badgeTemp.style.background = 'var(--h-red-bg)'; badgeTemp.style.color = 'var(--h-red)';
                cardTemp.style.borderColor = 'var(--h-red)';
                subTemp.innerText = 'Check D4 Wire';
                subTemp.style.color = 'var(--h-red)';
            }

            // 2. RPM (Hall)
            if (data.rpm !== null && data.rpm !== undefined) {
                const rpm = parseInt(data.rpm);
                document.getElementById('val-rpm').innerText = rpm.toLocaleString();
                const rpmPct = Math.min(100, Math.max(0, (rpm / 6000) * 100));
                document.getElementById('bar-rpm').style.width = rpmPct + '%';
                const b = document.getElementById('badge-rpm-conn');
                if (rpm > 0) {
                    b.innerText = 'PULSING';
                    b.style.background = 'var(--h-teal-bg)'; b.style.color = 'var(--h-teal)';
                } else {
                    b.innerText = 'READY';
                    b.style.background = 'var(--h-green-bg)'; b.style.color = 'var(--h-green)';
                }
            }

            // 3. Vibration (MPU6050)
            const isMpuConnected = (data.peak_g !== null && data.peak_g !== undefined) && 
                                   (!data.components_health || data.components_health.mpu6050?.connected !== false);

            if (isMpuConnected) {
                const peak = parseFloat(data.peak_g);
                valVib.innerText = peak.toFixed(2);
                valVib.style.color = 'var(--h-text)';
                const vibPct = Math.min(100, Math.max(0, (peak / 25) * 100));
                document.getElementById('bar-vib').style.width = vibPct + '%';
                badgeVib.innerText = 'OK';
                badgeVib.style.background = 'var(--h-green-bg)'; badgeVib.style.color = 'var(--h-green)';
                cardVib.style.borderColor = '';
            } else {
                valVib.innerText = 'UNPLUGGED';
                valVib.style.color = 'var(--h-red)';
                document.getElementById('bar-vib').style.width = '0%';
                badgeVib.innerText = 'DISCONNECTED';
                badgeVib.style.background = 'var(--h-red-bg)'; badgeVib.style.color = 'var(--h-red)';
                cardVib.style.borderColor = 'var(--h-red)';
            }
            if (data.accel_x !== null && data.accel_x !== undefined && isMpuConnected) {
                document.getElementById('val-ax').innerText = parseFloat(data.accel_x).toFixed(1);
                document.getElementById('val-ay').innerText = parseFloat(data.accel_y).toFixed(1);
                document.getElementById('val-az').innerText = parseFloat(data.accel_z).toFixed(1);
            } else {
                document.getElementById('val-ax').innerText = '-';
                document.getElementById('val-ay').innerText = '-';
                document.getElementById('val-az').innerText = '-';
            }

            // 4. Power (INA219)
            const isPowerConnected = (data.voltage_v !== null && data.voltage_v !== undefined) && 
                                     (!data.components_health || data.components_health.ina219?.connected !== false);

            if (isPowerConnected) {
                const volt = parseFloat(data.voltage_v);
                valVoltage.innerText = volt.toFixed(2);
                valVoltage.style.color = 'var(--h-text)';
                const voltPct = Math.min(100, Math.max(0, (volt / 15) * 100));
                document.getElementById('bar-power').style.width = voltPct + '%';
                cardPower.style.borderColor = '';

                if (volt > 1.0) {
                    badgePower.innerText = 'OK';
                    badgePower.style.background = 'var(--h-green-bg)'; badgePower.style.color = 'var(--h-green)';
                } else {
                    badgePower.innerText = 'NO LOAD';
                    badgePower.style.background = 'var(--h-amber-bg)'; badgePower.style.color = 'var(--h-amber)';
                }
            } else {
                valVoltage.innerText = 'UNPLUGGED';
                valVoltage.style.color = 'var(--h-red)';
                document.getElementById('bar-power').style.width = '0%';
                badgePower.innerText = 'DISCONNECTED';
                badgePower.style.background = 'var(--h-red-bg)'; badgePower.style.color = 'var(--h-red)';
                cardPower.style.borderColor = 'var(--h-red)';
            }
            if (data.current_ma !== null && data.current_ma !== undefined && isPowerConnected) {
                valCurrent.innerText = parseFloat(data.current_ma).toFixed(1);
            } else {
                valCurrent.innerText = '--';
            }

            // 5. Audio (INMP441)
            const isAudioConnected = (data.audio_rms !== null && data.audio_rms !== undefined) && 
                                     (!data.components_health || data.components_health.inmp441?.connected !== false);

            if (isAudioConnected) {
                const rms = Math.round(parseFloat(data.audio_rms));
                valAudio.innerText = rms.toLocaleString();
                valAudio.style.color = 'var(--h-text)';
                const audioPct = Math.min(100, Math.max(0, (rms / 75000) * 100));
                document.getElementById('bar-audio').style.width = audioPct + '%';
                badgeAudio.innerText = 'OK';
                badgeAudio.style.background = 'var(--h-green-bg)'; badgeAudio.style.color = 'var(--h-green)';
                cardAudio.style.borderColor = '';
            } else {
                valAudio.innerText = 'UNPLUGGED';
                valAudio.style.color = 'var(--h-red)';
                document.getElementById('bar-audio').style.width = '0%';
                badgeAudio.innerText = 'DISCONNECTED';
                badgeAudio.style.background = 'var(--h-red-bg)'; badgeAudio.style.color = 'var(--h-red)';
                cardAudio.style.borderColor = 'var(--h-red)';
            }

            // Update Expandable "More Info" Diagnostics Drawer if present
            if (data.components_health) {
                const ch = data.components_health;
                if (ch.ds18b20) {
                    const b = document.getElementById('detail-badge-ds18b20');
                    b.innerText = ch.ds18b20.connected ? 'CONNECTED' : 'DISCONNECTED';
                    b.style.background = ch.ds18b20.connected ? 'var(--h-green-bg)' : 'var(--h-red-bg)';
                    b.style.color = ch.ds18b20.connected ? 'var(--h-green)' : 'var(--h-red)';
                    document.getElementById('detail-hint-ds18b20').innerText = ch.ds18b20.hint;
                }
                if (ch.mpu6050) {
                    const b = document.getElementById('detail-badge-mpu6050');
                    b.innerText = ch.mpu6050.connected ? 'CONNECTED' : 'DISCONNECTED';
                    b.style.background = ch.mpu6050.connected ? 'var(--h-green-bg)' : 'var(--h-red-bg)';
                    b.style.color = ch.mpu6050.connected ? 'var(--h-green)' : 'var(--h-red)';
                    document.getElementById('detail-hint-mpu6050').innerText = ch.mpu6050.hint;
                }
                if (ch.ina219) {
                    const b = document.getElementById('detail-badge-ina219');
                    if (!ch.ina219.connected) {
                        b.innerText = 'DISCONNECTED';
                        b.style.background = 'var(--h-red-bg)'; b.style.color = 'var(--h-red)';
                    } else if (ch.ina219.status.includes('NO BATTERY')) {
                        b.innerText = 'ACTIVE (NO LOAD)';
                        b.style.background = 'var(--h-amber-bg)'; b.style.color = 'var(--h-amber)';
                    } else {
                        b.innerText = 'CONNECTED';
                        b.style.background = 'var(--h-green-bg)'; b.style.color = 'var(--h-green)';
                    }
                    document.getElementById('detail-hint-ina219').innerText = ch.ina219.hint;
                }
                if (ch.inmp441) {
                    const b = document.getElementById('detail-badge-inmp441');
                    b.innerText = ch.inmp441.connected ? 'CONNECTED' : 'DISCONNECTED';
                    b.style.background = ch.inmp441.connected ? 'var(--h-green-bg)' : 'var(--h-red-bg)';
                    b.style.color = ch.inmp441.connected ? 'var(--h-green)' : 'var(--h-red)';
                    document.getElementById('detail-hint-inmp441').innerText = ch.inmp441.hint;
                }
                if (ch.hall_rpm) {
                    document.getElementById('detail-hint-hall').innerText = ch.hall_rpm.hint;
                }
            }

            // Draw rolling history chart if history payload present
            if (data.history && Array.isArray(data.history)) {
                drawRollingChart(data.history);
            }

        } catch (e) {
            console.warn('[Haven IoT] Polling error:', e);
        }
    }

    function drawRollingChart(history) {
        const canvas = document.getElementById('telemetry-chart-canvas');
        const standbyMsg = document.getElementById('chart-standby-msg');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        if (!history || history.length < 2) {
            if (standbyMsg) standbyMsg.style.display = 'flex';
            return;
        }
        if (standbyMsg) standbyMsg.style.display = 'none';

        const w = canvas.width;
        const h = canvas.height;
        ctx.clearRect(0, 0, w, h);

        // Draw background grid lines
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.06)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(0, h * 0.25); ctx.lineTo(w, h * 0.25);
        ctx.moveTo(0, h * 0.50); ctx.lineTo(w, h * 0.50);
        ctx.moveTo(0, h * 0.75); ctx.lineTo(w, h * 0.75);
        ctx.stroke();

        const step = w / Math.max(1, history.length - 1);

        // 1. Draw Vibration Peak G (Sky Blue #0284c7) scaled to 0..3g
        ctx.strokeStyle = '#38bdf8';
        ctx.lineWidth = 2.2;
        ctx.beginPath();
        history.forEach((pt, i) => {
            const val = parseFloat(pt.peak) || 0;
            const y = h - Math.min(h - 5, Math.max(5, (val / 3.0) * (h - 10)));
            if (i === 0) ctx.moveTo(i * step, y);
            else ctx.lineTo(i * step, y);
        });
        ctx.stroke();

        // 2. Draw Engine RPM (Green #4ade80) scaled to 0..4000 RPM
        ctx.strokeStyle = '#4ade80';
        ctx.lineWidth = 1.8;
        ctx.setLineDash([4, 3]);
        ctx.beginPath();
        history.forEach((pt, i) => {
            const val = parseFloat(pt.rpm) || 0;
            const y = h - Math.min(h - 5, Math.max(5, (val / 4000.0) * (h - 10)));
            if (i === 0) ctx.moveTo(i * step, y);
            else ctx.lineTo(i * step, y);
        });
        ctx.stroke();
        ctx.setLineDash([]);

        // 3. Draw Coolant Temp (Amber #fb923c) scaled to 40..120°C
        ctx.strokeStyle = '#fb923c';
        ctx.lineWidth = 1.6;
        ctx.beginPath();
        history.forEach((pt, i) => {
            const val = parseFloat(pt.temp) || 0;
            const norm = (val - 40) / 80.0;
            const y = h - Math.min(h - 5, Math.max(5, norm * (h - 10)));
            if (i === 0) ctx.moveTo(i * step, y);
            else ctx.lineTo(i * step, y);
        });
        ctx.stroke();
    }

    // ── Real-Time Streaming (SSE) with Fallback Polling ──
    let sseActive = false;
    if (window.EventSource) {
        try {
            const es = new EventSource('/api/v1/telemetry/stream');
            es.addEventListener('telemetry', function(e) {
                try {
                    const data = JSON.parse(e.data);
                    renderTelemetryData(data);
                    sseActive = true;
                } catch (err) {}
            });
            es.onerror = function() {
                if (es.readyState === EventSource.CLOSED) {
                    es.close();
                }
            };
        } catch (e) {
            sseActive = false;
        }
    }

    // Always run initial poll and periodic polling fallback
    pollTelemetry();
    setInterval(pollTelemetry, 3000);
})();
</script>
