{{-- Bosch OEM Industrial Live ESP32 Telemetry & Hardware Health Monitor --}}

{{-- Notification Toast Container --}}
<div id="haven-toast-container" style="position:fixed;top:1.2rem;right:1.2rem;z-index:9999;display:flex;flex-direction:column;gap:0.5rem;pointer-events:none;"></div>

<div id="esp32-live-widget" class="h-live-telemetry-panel" style="margin-bottom:1.5rem;background:var(--h-surface);border:1px solid var(--h-border);border-top:3px solid var(--h-teal);border-radius:var(--h-radius-lg);padding:1.1rem 1.25rem;position:relative;">
    
    {{-- Header Bar --}}
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:1px solid var(--h-border);">
        <div style="display:flex;align-items:center;gap:0.65rem;flex-wrap:wrap;">
            <div id="live-beacon-indicator" style="display:flex;align-items:center;gap:0.45rem;">
                <span class="live-ping-dot" id="live-dot" style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#64748b;"></span>
                <span id="live-status-text" class="h-mono" style="font-size:0.75rem;font-weight:700;letter-spacing:0.06em;color:var(--h-text2);text-transform:uppercase;">ESP32 DAQ NODE — CHECKING STATUS...</span>
            </div>
            <span id="live-mode-badge" class="h-badge" style="background:rgba(2,132,199,0.12);color:var(--h-teal);border:1px solid rgba(2,132,199,0.25);font-size:0.68rem;padding:0.18rem 0.5rem;font-family:var(--h-mono);">
                HARDWARE MONITOR
            </span>
            <span id="live-vehicle-tag" class="h-badge" style="background:var(--h-surface2);color:var(--h-text2);font-size:0.68rem;padding:0.18rem 0.5rem;">
                <span id="val-vehicle-name">Vehicle</span>
            </span>
        </div>

        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
            <span style="font-size:0.75rem;color:var(--h-text3);">Sync: <strong id="val-last-sync" class="h-mono" style="color:var(--h-text2);">Standby</strong></span>
            
            {{-- More Info / Component Diagnostic Toggle Button --}}
            <button type="button" id="btn-toggle-hardware-info" onclick="toggleHardwareInfo()" 
                    class="haven-action-btn haven-action-btn-secondary" 
                    style="font-size:0.72rem;padding:0.3rem 0.65rem;display:inline-flex;align-items:center;gap:0.35rem;cursor:pointer;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);color:var(--h-text);">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <span id="txt-toggle-info">More Info</span>
                <span id="icon-toggle-info" style="font-size:0.7rem;">▾</span>
            </button>
        </div>
    </div>

    {{-- Single Clean Row: 5 Live Instrument Meters --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(100%, 140px), 1fr));gap:0.75rem;">
        
        {{-- Sensor 1: Temperature (DS18B20) --}}
        <div id="meter-card-temp" style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.8rem 0.95rem;transition:border-color 0.3s ease;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
                <span style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:700;letter-spacing:0.05em;">Coolant / Temp</span>
                <span id="badge-temp-conn" class="h-badge" style="font-size:0.6rem;padding:0.08rem 0.3rem;background:#16a34a22;color:#4ade80;">OK</span>
            </div>
            <div style="display:flex;align-items:baseline;gap:0.25rem;">
                <span id="val-temp" class="h-mono" style="font-size:1.6rem;font-weight:700;color:#38bdf8;">--</span>
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
        <div id="meter-card-rpm" style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.8rem 0.95rem;transition:border-color 0.3s ease;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
                <span style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:700;letter-spacing:0.05em;">Engine Speed</span>
                <span id="badge-rpm-conn" class="h-badge" style="font-size:0.6rem;padding:0.08rem 0.3rem;background:#16a34a22;color:#4ade80;">OK</span>
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
        <div id="meter-card-vib" style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.8rem 0.95rem;transition:border-color 0.3s ease;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
                <span style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:700;letter-spacing:0.05em;">Vibration Peak</span>
                <span id="badge-vib-conn" class="h-badge" style="font-size:0.6rem;padding:0.08rem 0.3rem;background:#16a34a22;color:#4ade80;">OK</span>
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
        <div id="meter-card-power" style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.8rem 0.95rem;transition:border-color 0.3s ease;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
                <span style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:700;letter-spacing:0.05em;">Battery / Bus</span>
                <span id="badge-power-conn" class="h-badge" style="font-size:0.6rem;padding:0.08rem 0.3rem;background:#ea580c22;color:#fb923c;">NO LOAD</span>
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
        <div id="meter-card-audio" style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.8rem 0.95rem;transition:border-color 0.3s ease;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
                <span style="font-size:0.68rem;color:var(--h-text3);text-transform:uppercase;font-weight:700;letter-spacing:0.05em;">Acoustic Level</span>
                <span id="badge-audio-conn" class="h-badge" style="font-size:0.6rem;padding:0.08rem 0.3rem;background:#16a34a22;color:#4ade80;">OK</span>
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

    {{-- Expandable "More Info" Hardware Component Diagnostics Drawer --}}
    <div id="panel-hardware-details" style="display:none;margin-top:1.15rem;padding-top:1rem;border-top:1px dashed var(--h-border);animation:fadeIn 0.25s ease-in-out;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
            <div style="font-size:0.72rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:var(--h-text2);display:flex;align-items:center;gap:0.4rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span>Hardware Component Wiring & Diagnostics Details</span>
            </div>
            <span style="font-size:0.68rem;color:var(--h-text3);">Bench Test Auto-Diagnostics</span>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(100%, 180px), 1fr));gap:0.65rem;">
            
            {{-- Detail 1: DS18B20 --}}
            <div class="h-detail-card" style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.65rem 0.8rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;">
                    <span style="font-size:0.75rem;font-weight:600;color:var(--h-text);">DS18B20 Temp</span>
                    <span id="detail-badge-ds18b20" class="h-badge" style="font-size:0.62rem;padding:0.1rem 0.35rem;background:#16a34a22;color:#4ade80;">CONNECTED</span>
                </div>
                <div class="h-mono" style="font-size:0.7rem;color:var(--h-teal);margin-bottom:0.2rem;">Pin: D4 (OneWire)</div>
                <div style="font-size:0.68rem;color:var(--h-text2);" id="detail-hint-ds18b20">Reading room/ambient temp correctly.</div>
            </div>

            {{-- Detail 2: MPU6050 --}}
            <div class="h-detail-card" style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.65rem 0.8rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;">
                    <span style="font-size:0.75rem;font-weight:600;color:var(--h-text);">MPU6050 Accel</span>
                    <span id="detail-badge-mpu6050" class="h-badge" style="font-size:0.62rem;padding:0.1rem 0.35rem;background:#16a34a22;color:#4ade80;">CONNECTED</span>
                </div>
                <div class="h-mono" style="font-size:0.7rem;color:var(--h-teal);margin-bottom:0.2rem;">I2C: D21 (SDA) / D22 (SCL)</div>
                <div style="font-size:0.68rem;color:var(--h-text2);" id="detail-hint-mpu6050">Earth gravity detected (~9.8 m/s²). Tap board to test.</div>
            </div>

            {{-- Detail 3: INA219 --}}
            <div class="h-detail-card" style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.65rem 0.8rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;">
                    <span style="font-size:0.75rem;font-weight:600;color:var(--h-text);">INA219 Power</span>
                    <span id="detail-badge-ina219" class="h-badge" style="font-size:0.62rem;padding:0.1rem 0.35rem;background:#ea580c22;color:#fb923c;">ACTIVE (NO LOAD)</span>
                </div>
                <div class="h-mono" style="font-size:0.7rem;color:var(--h-teal);margin-bottom:0.2rem;">I2C: D21 (SDA) / D22 (SCL)</div>
                <div style="font-size:0.68rem;color:var(--h-text2);" id="detail-hint-ina219">Module active, but Vin+/- screw terminals have no battery hooked up.</div>
            </div>

            {{-- Detail 4: INMP441 --}}
            <div class="h-detail-card" style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.65rem 0.8rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;">
                    <span style="font-size:0.75rem;font-weight:600;color:var(--h-text);">INMP441 Mic</span>
                    <span id="detail-badge-inmp441" class="h-badge" style="font-size:0.62rem;padding:0.1rem 0.35rem;background:#16a34a22;color:#4ade80;">CONNECTED</span>
                </div>
                <div class="h-mono" style="font-size:0.7rem;color:var(--h-teal);margin-bottom:0.2rem;">I2S: D18 (BCLK), D19 (WS), D5 (SD)</div>
                <div style="font-size:0.68rem;color:var(--h-text2);" id="detail-hint-inmp441">Capturing ambient acoustic RMS audio stream.</div>
            </div>

            {{-- Detail 5: Hall RPM --}}
            <div class="h-detail-card" style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.65rem 0.8rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;">
                    <span style="font-size:0.75rem;font-weight:600;color:var(--h-text);">Hall Tachometer</span>
                    <span id="detail-badge-hall" class="h-badge" style="font-size:0.62rem;padding:0.1rem 0.35rem;background:#16a34a22;color:#4ade80;">READY</span>
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
    background: #0f2a1a;
    border: 1px solid #16a34a;
    color: #4ade80;
}
.haven-toast-warning {
    background: #2a1a0a;
    border: 1px solid #ea580c;
    color: #fb923c;
}
.haven-toast-info {
    background: #0a1a2a;
    border: 1px solid #0284c7;
    color: #38bdf8;
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
        dot.style.background = type === 'success' ? '#16a34a' : type === 'warning' ? '#ea580c' : '#0284c7';
        if (type === 'success') dot.style.boxShadow = '0 0 6px #16a34a';

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
                dot.style.background = '#16a34a';
                dot.style.boxShadow = '0 0 8px #16a34a';
                dot.classList.add('active');
                statusText.innerText = 'ESP32 DAQ NODE STREAMING LIVE';
                statusText.style.color = '#4ade80';
                const mb = document.getElementById('live-mode-badge');
                if (mb) {
                    mb.innerText = 'LIVE BENCH TEST';
                    mb.style.color = 'var(--h-teal)';
                    mb.style.borderColor = 'rgba(2,132,199,0.25)';
                }
            } else {
                dot.style.background = '#ea580c';
                dot.style.boxShadow = 'none';
                dot.classList.remove('active');
                statusText.innerText = 'ESP32 DAQ NODE — STANDBY / OFFLINE';
                statusText.style.color = '#fb923c';
                const mb = document.getElementById('live-mode-badge');
                if (mb) {
                    mb.innerText = 'STANDBY';
                    mb.style.color = '#94a3b8';
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
                badgeTemp.style.background = 'rgba(100,116,139,0.15)'; badgeTemp.style.color = '#94a3b8';
                cardTemp.style.borderColor = 'var(--h-border)';
                subTemp.innerText = 'Waiting for board'; subTemp.style.color = 'var(--h-text3)';

                // Hall Standby
                document.getElementById('val-rpm').innerText = '0';
                document.getElementById('val-rpm').style.color = 'var(--h-text3)';
                document.getElementById('bar-rpm').style.width = '0%';
                const bRpm = document.getElementById('badge-rpm-conn');
                bRpm.innerText = 'STANDBY';
                bRpm.style.background = 'rgba(100,116,139,0.15)'; bRpm.style.color = '#94a3b8';
                document.getElementById('sub-rpm').innerText = 'Standby';

                // MPU6050 Standby
                valVib.innerText = '--';
                valVib.style.color = 'var(--h-text3)';
                document.getElementById('bar-vib').style.width = '0%';
                badgeVib.innerText = 'STANDBY';
                badgeVib.style.background = 'rgba(100,116,139,0.15)'; badgeVib.style.color = '#94a3b8';
                cardVib.style.borderColor = 'var(--h-border)';
                document.getElementById('val-ax').innerText = '-';
                document.getElementById('val-ay').innerText = '-';
                document.getElementById('val-az').innerText = '-';

                // INA219 Standby
                valVoltage.innerText = '--';
                valVoltage.style.color = 'var(--h-text3)';
                document.getElementById('bar-power').style.width = '0%';
                badgePower.innerText = 'STANDBY';
                badgePower.style.background = 'rgba(100,116,139,0.15)'; badgePower.style.color = '#94a3b8';
                cardPower.style.borderColor = 'var(--h-border)';
                valCurrent.innerText = '--';

                // INMP441 Standby
                valAudio.innerText = '--';
                valAudio.style.color = 'var(--h-text3)';
                document.getElementById('bar-audio').style.width = '0%';
                badgeAudio.innerText = 'STANDBY';
                badgeAudio.style.background = 'rgba(100,116,139,0.15)'; badgeAudio.style.color = '#94a3b8';
                cardAudio.style.borderColor = 'var(--h-border)';

                // Hardware detail cards standby
                ['ds18b20', 'mpu6050', 'ina219', 'inmp441', 'hall'].forEach(k => {
                    const el = document.getElementById('detail-badge-' + k);
                    if (el) {
                        el.innerText = 'STANDBY';
                        el.style.background = 'rgba(100,116,139,0.15)';
                        el.style.color = '#94a3b8';
                    }
                });
                return;
            }

            const isTempConnected = (data.temperature_c !== null && data.temperature_c !== undefined) && 
                                    (!data.components_health || data.components_health.ds18b20?.connected !== false);

            if (isTempConnected) {
                const temp = parseFloat(data.temperature_c);
                valTemp.innerText = temp.toFixed(1);
                valTemp.style.color = '#38bdf8';
                const tempPct = Math.min(100, Math.max(0, (temp / 110) * 100));
                document.getElementById('bar-temp').style.width = tempPct + '%';
                badgeTemp.innerText = 'OK';
                badgeTemp.style.background = '#16a34a22'; badgeTemp.style.color = '#4ade80';
                cardTemp.style.borderColor = 'var(--h-border)';
                subTemp.innerText = 'Ambient';
                subTemp.style.color = 'var(--h-text3)';
            } else {
                valTemp.innerText = 'UNPLUGGED';
                valTemp.style.color = '#f87171';
                document.getElementById('bar-temp').style.width = '0%';
                badgeTemp.innerText = 'DISCONNECTED';
                badgeTemp.style.background = '#dc262622'; badgeTemp.style.color = '#f87171';
                cardTemp.style.borderColor = '#dc2626';
                subTemp.innerText = 'Check D4 Wire';
                subTemp.style.color = '#f87171';
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
                    b.style.background = '#0284c722'; b.style.color = '#38bdf8';
                } else {
                    b.innerText = 'READY';
                    b.style.background = '#16a34a22'; b.style.color = '#4ade80';
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
                badgeVib.style.background = '#16a34a22'; badgeVib.style.color = '#4ade80';
                cardVib.style.borderColor = 'var(--h-border)';
            } else {
                valVib.innerText = 'UNPLUGGED';
                valVib.style.color = '#f87171';
                document.getElementById('bar-vib').style.width = '0%';
                badgeVib.innerText = 'DISCONNECTED';
                badgeVib.style.background = '#dc262622'; badgeVib.style.color = '#f87171';
                cardVib.style.borderColor = '#dc2626';
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
                cardPower.style.borderColor = 'var(--h-border)';

                if (volt > 1.0) {
                    badgePower.innerText = 'OK';
                    badgePower.style.background = '#16a34a22'; badgePower.style.color = '#4ade80';
                } else {
                    badgePower.innerText = 'NO LOAD';
                    badgePower.style.background = '#ea580c22'; badgePower.style.color = '#fb923c';
                }
            } else {
                valVoltage.innerText = 'UNPLUGGED';
                valVoltage.style.color = '#f87171';
                document.getElementById('bar-power').style.width = '0%';
                badgePower.innerText = 'DISCONNECTED';
                badgePower.style.background = '#dc262622'; badgePower.style.color = '#f87171';
                cardPower.style.borderColor = '#dc2626';
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
                badgeAudio.style.background = '#16a34a22'; badgeAudio.style.color = '#4ade80';
                cardAudio.style.borderColor = 'var(--h-border)';
            } else {
                valAudio.innerText = 'UNPLUGGED';
                valAudio.style.color = '#f87171';
                document.getElementById('bar-audio').style.width = '0%';
                badgeAudio.innerText = 'DISCONNECTED';
                badgeAudio.style.background = '#dc262622'; badgeAudio.style.color = '#f87171';
                cardAudio.style.borderColor = '#dc2626';
            }

            // Update Expandable "More Info" Diagnostics Drawer if present
            if (data.components_health) {
                const ch = data.components_health;
                if (ch.ds18b20) {
                    const b = document.getElementById('detail-badge-ds18b20');
                    b.innerText = ch.ds18b20.connected ? 'CONNECTED' : 'DISCONNECTED';
                    b.style.background = ch.ds18b20.connected ? '#16a34a22' : '#dc262622';
                    b.style.color = ch.ds18b20.connected ? '#4ade80' : '#f87171';
                    document.getElementById('detail-hint-ds18b20').innerText = ch.ds18b20.hint;
                }
                if (ch.mpu6050) {
                    const b = document.getElementById('detail-badge-mpu6050');
                    b.innerText = ch.mpu6050.connected ? 'CONNECTED' : 'DISCONNECTED';
                    b.style.background = ch.mpu6050.connected ? '#16a34a22' : '#dc262622';
                    b.style.color = ch.mpu6050.connected ? '#4ade80' : '#f87171';
                    document.getElementById('detail-hint-mpu6050').innerText = ch.mpu6050.hint;
                }
                if (ch.ina219) {
                    const b = document.getElementById('detail-badge-ina219');
                    if (!ch.ina219.connected) {
                        b.innerText = 'DISCONNECTED';
                        b.style.background = '#dc262622'; b.style.color = '#f87171';
                    } else if (ch.ina219.status.includes('NO BATTERY')) {
                        b.innerText = 'ACTIVE (NO LOAD)';
                        b.style.background = '#ea580c22'; b.style.color = '#fb923c';
                    } else {
                        b.innerText = 'CONNECTED';
                        b.style.background = '#16a34a22'; b.style.color = '#4ade80';
                    }
                    document.getElementById('detail-hint-ina219').innerText = ch.ina219.hint;
                }
                if (ch.inmp441) {
                    const b = document.getElementById('detail-badge-inmp441');
                    b.innerText = ch.inmp441.connected ? 'CONNECTED' : 'DISCONNECTED';
                    b.style.background = ch.inmp441.connected ? '#16a34a22' : '#dc262622';
                    b.style.color = ch.inmp441.connected ? '#4ade80' : '#f87171';
                    document.getElementById('detail-hint-inmp441').innerText = ch.inmp441.hint;
                }
                if (ch.hall_rpm) {
                    document.getElementById('detail-hint-hall').innerText = ch.hall_rpm.hint;
                }
            }

        } catch (e) {
            console.warn('[Haven IoT] Polling error:', e);
        }
    }

    pollTelemetry();
    setInterval(pollTelemetry, 2000);
})();
</script>
