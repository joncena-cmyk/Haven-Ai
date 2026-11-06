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
            
            {{-- WebSerial 1-Click Wi-Fi Config Button --}}
            <button type="button" id="btn-webserial-config" onclick="openEsp32WebSerialModal()" 
                    class="h-telemetry-info-btn" style="border-color:rgba(14,165,233,0.4);background:rgba(14,165,233,0.1);color:#38bdf8;" title="Pair & Configure ESP32 Wi-Fi over USB with 1-click">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>
                <span>Pair Wi-Fi (USB)</span>
            </button>

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
                <span style="font-size:0.75rem;color:var(--h-text3);font-weight:600;">g</span>
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
                <div style="font-size:0.68rem;color:var(--h-text2);" id="detail-hint-mpu6050">Dynamic vibration (gravity removed). ~0 g at rest; tap board to test.</div>
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

{{-- WebSerial 1-Click ESP32 Wi-Fi Setup Modal --}}
@php
    $userVehicles = auth()->check() ? auth()->user()->vehicles()->select('id', 'make', 'model', 'year', 'device_key')->get() : collect();
@endphp
<div id="esp32-webserial-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);backdrop-filter:blur(6px);z-index:99999;align-items:center;justify-content:center;padding:1.25rem;" onclick="if(event.target===this)closeEsp32WebSerialModal()">
    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:14px;max-width:540px;width:100%;padding:1.5rem;box-shadow:0 24px 48px rgba(0,0,0,0.6);color:#f1f5f9;position:relative;max-height:90vh;overflow-y:auto;">
        
        {{-- Close button --}}
        <button type="button" onclick="closeEsp32WebSerialModal()" style="position:absolute;top:1rem;right:1rem;background:none;border:none;color:#94a3b8;font-size:1.5rem;cursor:pointer;line-height:1;padding:0.2rem;" aria-label="Close">&times;</button>

        {{-- Header --}}
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(14,165,233,0.15);border:1px solid rgba(14,165,233,0.3);display:flex;align-items:center;justify-content:center;color:#38bdf8;flex-shrink:0;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>
            </div>
            <div>
                <h3 style="font-size:1.1rem;font-weight:700;color:#f8fafc;margin:0 0 0.15rem;">ESP32 Wi-Fi Auto-Provisioning</h3>
                <p style="font-size:0.75rem;color:#94a3b8;margin:0;">1-Click WebSerial USB link or Hotspot setup</p>
            </div>
        </div>

        {{-- Mode Switch / Tabs --}}
        <div style="display:flex;gap:0.4rem;background:#1e293b;padding:0.25rem;border-radius:8px;margin-bottom:1.2rem;">
            <button type="button" id="tab-webserial" onclick="switchProvisionTab('usb')" style="flex:1;padding:0.5rem;border-radius:6px;border:none;background:#0ea5e9;color:#fff;font-weight:600;font-size:0.8rem;cursor:pointer;">⚡ WebSerial USB (1-Click)</button>
            <button type="button" id="tab-hotspot" onclick="switchProvisionTab('hotspot')" style="flex:1;padding:0.5rem;border-radius:6px;border:none;background:transparent;color:#94a3b8;font-weight:600;font-size:0.8rem;cursor:pointer;">📶 Wi-Fi Hotspot (Solution A)</button>
        </div>

        {{-- Tab 1: WebSerial Form --}}
        <div id="view-webserial">
            <div id="webserial-browser-warn" style="display:none;background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);color:#fbbf24;padding:0.6rem 0.8rem;border-radius:8px;font-size:0.75rem;margin-bottom:1rem;">
                ⚠️ WebSerial is not supported in this browser. Please use <strong>Google Chrome</strong> or <strong>Microsoft Edge</strong>, or use the <strong>Wi-Fi Hotspot (Solution A)</strong> tab.
            </div>

            <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:1rem;line-height:1.4;">
                Plug your ESP32 into this laptop via USB cable, enter local Wi-Fi credentials, then click <strong>"Flash Wi-Fi to ESP32 over USB"</strong>.
            </div>

            <div style="display:flex;flex-direction:column;gap:0.85rem;">
                {{-- Vehicle selection --}}
                <div>
                    <label style="display:block;font-size:0.72rem;font-weight:700;color:#94a3b8;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Target Vehicle</label>
                    <select id="ws-vehicle-select" onchange="onWsVehicleChange(this)" style="width:100%;padding:0.6rem;background:#131c2e;border:1px solid #334155;border-radius:8px;color:#f8fafc;font-size:0.85rem;outline:none;">
                        @forelse($userVehicles as $v)
                            <option value="{{ $v->id }}" data-key="{{ $v->device_key }}">{{ $v->year }} {{ $v->make }} {{ $v->model }} (ID: {{ $v->id }})</option>
                        @empty
                            <option value="1" data-key="">Default Vehicle (ID: 1)</option>
                        @endforelse
                    </select>
                </div>

                {{-- Wi-Fi SSID --}}
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.3rem;">
                        <label style="font-size:0.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.04em;margin:0;">Wi-Fi Network (SSID)</label>
                        <button type="button" onclick="scanWifiOverUsb()" id="btn-scan-usb" style="background:none;border:none;color:#38bdf8;font-size:0.72rem;cursor:pointer;padding:0;text-decoration:underline;">Scan Nearby Networks (via USB)</button>
                    </div>
                    <input type="text" id="ws-wifi-ssid" placeholder="e.g. MyWiFi-2.4G" style="width:100%;padding:0.6rem;background:#131c2e;border:1px solid #334155;border-radius:8px;color:#f8fafc;font-size:0.85rem;box-sizing:border-box;outline:none;">
                    <select id="ws-scanned-select" style="display:none;width:100%;margin-top:0.35rem;padding:0.5rem;background:#131c2e;border:1px solid #0284c7;border-radius:8px;color:#38bdf8;font-size:0.8rem;outline:none;" onchange="if(this.value){document.getElementById('ws-wifi-ssid').value=this.value;}">
                        <option value="">-- Select from scanned networks --</option>
                    </select>
                </div>

                {{-- Wi-Fi Password --}}
                <div>
                    <label style="display:block;font-size:0.72rem;font-weight:700;color:#94a3b8;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Wi-Fi Password</label>
                    <div style="position:relative;">
                        <input type="password" id="ws-wifi-pass" placeholder="Wi-Fi Password (leave blank if open)" style="width:100%;padding:0.6rem;padding-right:3rem;background:#131c2e;border:1px solid #334155;border-radius:8px;color:#f8fafc;font-size:0.85rem;box-sizing:border-box;outline:none;">
                        <button type="button" onclick="togglePassVisibility('ws-wifi-pass', this)" style="position:absolute;right:0.6rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#64748b;cursor:pointer;font-size:0.72rem;font-weight:600;">SHOW</button>
                    </div>
                </div>

                {{-- Haven Ingest Server URL --}}
                <div>
                    <label style="display:block;font-size:0.72rem;font-weight:700;color:#94a3b8;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Haven Ingest Server URL</label>
                    <input type="text" id="ws-server-url" value="" style="width:100%;padding:0.6rem;background:#131c2e;border:1px solid #334155;border-radius:8px;color:#f8fafc;font-size:0.82rem;box-sizing:border-box;font-family:monospace;outline:none;">
                    <div id="ws-ip-tip" style="font-size:0.68rem;color:#f59e0b;margin-top:0.25rem;"></div>
                </div>

                {{-- Device Key --}}
                <div>
                    <label style="display:block;font-size:0.72rem;font-weight:700;color:#94a3b8;margin-bottom:0.3rem;text-transform:uppercase;letter-spacing:0.04em;">Device Key (X-Haven-Device-Key)</label>
                    <input type="text" id="ws-device-key" value="{{ $userVehicles->first()?->device_key ?? '' }}" style="width:100%;padding:0.6rem;background:#131c2e;border:1px solid #334155;border-radius:8px;color:#f8fafc;font-size:0.82rem;box-sizing:border-box;font-family:monospace;outline:none;">
                </div>

                {{-- Status / Logs Output --}}
                <div id="ws-status-box" style="display:none;padding:0.65rem 0.85rem;border-radius:8px;font-size:0.75rem;font-family:monospace;line-height:1.4;"></div>

                {{-- Action Button --}}
                <button type="button" id="btn-flash-usb" onclick="flashWebSerial()" style="width:100%;padding:0.8rem;background:linear-gradient(135deg,#0284c7,#0ea5e9);border:none;border-radius:8px;color:#fff;font-weight:700;font-size:0.9rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.5rem;box-shadow:0 4px 15px rgba(2,132,199,0.35);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    <span>Flash Wi-Fi to ESP32 over USB</span>
                </button>
            </div>
        </div>

        {{-- Tab 2: Hotspot (Solution A) Instructions --}}
        <div id="view-hotspot" style="display:none;">
            <div style="background:#131c2e;border:1px solid #1e293b;border-radius:10px;padding:1rem;margin-bottom:1rem;">
                <div style="font-weight:700;color:#38bdf8;font-size:0.85rem;margin-bottom:0.5rem;display:flex;align-items:center;gap:0.4rem;">
                    <span>📶</span> Solution A: Direct Mobile & Laptop Captive Hotspot
                </div>
                <p style="font-size:0.78rem;color:#cbd5e1;line-height:1.5;margin:0 0 0.8rem;">
                    If you don't have a USB cable or are using your smartphone, the ESP32 acts as an autonomous Wi-Fi Access Point with an automatic captive portal:
                </p>
                <ol style="font-size:0.75rem;color:#94a3b8;padding-left:1.2rem;margin:0;line-height:1.6;">
                    <li>Power your ESP32 (car 12V adapter, battery pack, or phone charger).</li>
                    <li>Open Wi-Fi settings on your phone or laptop and connect to:
                        <br><strong style="color:#f8fafc;font-family:monospace;">HAVEN_AI_LOCAL</strong> (No password needed).</li>
                    <li>A popup setup screen will open automatically. If not, open your browser to:
                        <br><a href="http://192.168.4.1/config" target="_blank" style="color:#38bdf8;font-family:monospace;">http://192.168.4.1/config</a></li>
                    <li>Select or type the new Wi-Fi name, enter the password, and click <strong>"Save & Reconnect"</strong>.</li>
                    <li>The ESP32 saves to internal NVS Flash, restarts, and links back to Haven AI!</li>
                </ol>
            </div>
            <div style="text-align:right;">
                <button type="button" onclick="closeEsp32WebSerialModal()" style="padding:0.5rem 1rem;background:#334155;border:none;border-radius:6px;color:#fff;font-size:0.8rem;cursor:pointer;">Close</button>
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

function openEsp32WebSerialModal() {
    const modal = document.getElementById('esp32-webserial-modal');
    if (!modal) return;
    modal.style.display = 'flex';

    // Auto-detect browser WebSerial support
    const warn = document.getElementById('webserial-browser-warn');
    const flashBtn = document.getElementById('btn-flash-usb');
    const scanBtn = document.getElementById('btn-scan-usb');
    if (!('serial' in navigator)) {
        if (warn) warn.style.display = 'block';
        if (flashBtn) flashBtn.disabled = true;
        if (scanBtn) scanBtn.disabled = true;
    } else {
        if (warn) warn.style.display = 'none';
        if (flashBtn) flashBtn.disabled = false;
        if (scanBtn) scanBtn.disabled = false;
    }

    // Auto-fill Server URL based on current host
    const serverInput = document.getElementById('ws-server-url');
    const tip = document.getElementById('ws-ip-tip');
    if (serverInput && !serverInput.value) {
        const host = window.location.hostname;
        const port = window.location.port ? ':' + window.location.port : '';
        serverInput.value = window.location.protocol + '//' + host + port + '/api/v1/telemetry/ingest';
        if (host === 'localhost' || host === '127.0.0.1') {
            if (tip) {
                tip.innerHTML = '⚠️ Note: Since ESP32 connects via Wi-Fi, replace <code>' + host + '</code> with your laptop\'s Wi-Fi IP (e.g. <code>http://192.168.1.50:8000/...</code>) so the ESP32 can reach it.';
            }
        }
    }
}

function closeEsp32WebSerialModal() {
    const modal = document.getElementById('esp32-webserial-modal');
    if (modal) modal.style.display = 'none';
}

function switchProvisionTab(mode) {
    const vUsb = document.getElementById('view-webserial');
    const vHotspot = document.getElementById('view-hotspot');
    const tUsb = document.getElementById('tab-webserial');
    const tHotspot = document.getElementById('tab-hotspot');

    if (mode === 'usb') {
        if (vUsb) vUsb.style.display = 'block';
        if (vHotspot) vHotspot.style.display = 'none';
        if (tUsb) { tUsb.style.background = '#0ea5e9'; tUsb.style.color = '#fff'; }
        if (tHotspot) { tHotspot.style.background = 'transparent'; tHotspot.style.color = '#94a3b8'; }
    } else {
        if (vUsb) vUsb.style.display = 'none';
        if (vHotspot) vHotspot.style.display = 'block';
        if (tHotspot) { tHotspot.style.background = '#0ea5e9'; tHotspot.style.color = '#fff'; }
        if (tUsb) { tUsb.style.background = 'transparent'; tUsb.style.color = '#94a3b8'; }
    }
}

function togglePassVisibility(id, btn) {
    const input = document.getElementById(id);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerText = 'HIDE';
    } else {
        input.type = 'password';
        btn.innerText = 'SHOW';
    }
}

function onWsVehicleChange(select) {
    const opt = select.options[select.selectedIndex];
    const key = opt.getAttribute('data-key') || '';
    const keyInput = document.getElementById('ws-device-key');
    if (keyInput) keyInput.value = key;
}

function setWsStatus(type, msg) {
    const box = document.getElementById('ws-status-box');
    if (!box) return;
    box.style.display = 'block';
    if (type === 'info') {
        box.style.background = 'rgba(14,165,233,0.12)';
        box.style.border = '1px solid rgba(14,165,233,0.3)';
        box.style.color = '#38bdf8';
    } else if (type === 'success') {
        box.style.background = 'rgba(34,197,94,0.12)';
        box.style.border = '1px solid rgba(34,197,94,0.3)';
        box.style.color = '#4ade80';
    } else if (type === 'error') {
        box.style.background = 'rgba(239,68,68,0.12)';
        box.style.border = '1px solid rgba(239,68,68,0.3)';
        box.style.color = '#f87171';
    }
    box.innerText = msg;
}

/**
 * Open the ESP32 serial port, wait out the auto-reset that most DevKits perform
 * when the port is opened (DTR/RTS toggle), send `command`, and read lines until
 * `matcher(line)` returns a truthy value or `timeoutMs` elapses.
 * The port is ALWAYS released and closed, even on timeout or error.
 */
async function esp32SerialTransaction(command, matcher, timeoutMs, onStatus) {
    const port = await navigator.serial.requestPort();
    await port.open({ baudRate: 115200 });

    const encoder = new TextEncoder();
    const decoder = new TextDecoder();
    let reader = null;
    let writer = null;
    let timer = null;

    try {
        // Release reset lines so the board can boot, then wait for setup() to start.
        try { await port.setSignals({ dataTerminalReady: false, requestToSend: false }); } catch (_) {}
        if (onStatus) onStatus('Waiting for ESP32 to boot...');
        await new Promise(r => setTimeout(r, 2500));

        writer = port.writable.getWriter();
        reader = port.readable.getReader();

        // Cancelling the reader is the only reliable way to unblock a pending read().
        timer = setTimeout(() => { try { reader.cancel(); } catch (_) {} }, timeoutMs);

        // PING first; resend the command once in case the first copy landed during boot.
        await writer.write(encoder.encode('\nPING\n' + command + '\n'));
        let resent = false;
        const startedAt = Date.now();

        let buffer = '';
        while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            if (!value) continue;
            buffer += decoder.decode(value, { stream: true });

            let nl;
            while ((nl = buffer.indexOf('\n')) >= 0) {
                const line = buffer.slice(0, nl).trim();
                buffer = buffer.slice(nl + 1);
                const result = matcher(line);
                if (result) return result;
            }

            if (!resent && Date.now() - startedAt > 3000) {
                resent = true;
                await writer.write(encoder.encode('\n' + command + '\n'));
            }
        }
        return null; // timed out
    } finally {
        if (timer) clearTimeout(timer);
        try { if (reader) { await reader.cancel().catch(() => {}); reader.releaseLock(); } } catch (_) {}
        try { if (writer) writer.releaseLock(); } catch (_) {}
        try { await port.close(); } catch (_) {}
    }
}

async function scanWifiOverUsb() {
    if (!('serial' in navigator)) {
        alert('WebSerial is not supported in this browser. Please use Chrome or Edge.');
        return;
    }

    const btn = document.getElementById('btn-scan-usb');
    const oldText = btn.innerText;
    btn.innerText = 'Connecting...';
    btn.disabled = true;
    setWsStatus('info', 'Select your ESP32 in the browser prompt...');

    try {
        const data = await esp32SerialTransaction(
            'SCAN_WIFI',
            line => {
                if (!line.startsWith('WIFI_LIST:')) return null;
                try { return JSON.parse(line.substring(10)); } catch (_) { return null; }
            },
            20000,
            msg => setWsStatus('info', msg)
        );

        const select = document.getElementById('ws-scanned-select');
        if (data && Array.isArray(data.networks) && data.networks.length && select) {
            select.innerHTML = '<option value="">-- Select from scanned networks --</option>';
            data.networks.forEach(n => {
                if (!n.ssid) return;
                const opt = document.createElement('option');
                opt.value = n.ssid;
                opt.textContent = n.ssid + ' (' + n.rssi + ' dBm)';
                select.appendChild(opt);
            });
            select.style.display = 'block';
            setWsStatus('success', 'Found ' + data.networks.length + ' Wi-Fi networks. Select one from the dropdown.');
        } else {
            setWsStatus('error', 'No scan result from ESP32. Make sure it runs the latest HAVEN firmware and the Arduino Serial Monitor is closed. You can still type the SSID manually.');
        }
    } catch (err) {
        if (err.name === 'NotFoundError') {
            setWsStatus('info', 'Connection cancelled.');
        } else {
            setWsStatus('error', 'Serial error: ' + (err.message || err) + ' (Close the Arduino Serial Monitor if it is open.)');
        }
    } finally {
        btn.innerText = oldText;
        btn.disabled = false;
    }
}

async function flashWebSerial() {
    if (!('serial' in navigator)) {
        alert('WebSerial is not supported in this browser. Please use Chrome or Edge.');
        return;
    }

    const ssid = document.getElementById('ws-wifi-ssid').value.trim();
    const pass = document.getElementById('ws-wifi-pass').value;
    const serverUrl = document.getElementById('ws-server-url').value.trim();
    const key = document.getElementById('ws-device-key').value.trim();
    const vehId = parseInt(document.getElementById('ws-vehicle-select').value, 10) || 1;

    if (!ssid) {
        alert('Please enter your Wi-Fi SSID.');
        return;
    }
    if (!serverUrl) {
        alert('Please enter the Haven Server Ingest URL.');
        return;
    }
    if (!key) {
        alert('Please enter your vehicle device key.');
        return;
    }

    const btn = document.getElementById('btn-flash-usb');
    btn.disabled = true;
    setWsStatus('info', 'Select your ESP32 in the browser prompt...');

    const payload = { ssid: ssid, pass: pass, server: serverUrl, key: key, vehicle_id: vehId };
    const command = 'SET_CONFIG:' + JSON.stringify(payload);
    if (command.length > 480) {
        setWsStatus('error', 'Configuration is too long for the ESP32 buffer. Shorten the server URL.');
        btn.disabled = false;
        return;
    }

    try {
        const result = await esp32SerialTransaction(
            command,
            line => (line.startsWith('OK:SAVED') ? 'saved' : (line.startsWith('ERR:') ? line : null)),
            20000,
            msg => setWsStatus('info', msg)
        );

        if (result === 'saved') {
            setWsStatus('success', '✓ Wi-Fi saved to ESP32 flash. It is rebooting and joining "' + ssid + '". Watch the live status bar.');
            setTimeout(closeEsp32WebSerialModal, 3000);
        } else if (result) {
            setWsStatus('error', 'ESP32 rejected the configuration: ' + result);
        } else {
            setWsStatus('error', 'ESP32 did not confirm the save. Make sure it runs the latest HAVEN firmware and the Arduino Serial Monitor is closed, then try again.');
        }
    } catch (err) {
        if (err.name === 'NotFoundError') {
            setWsStatus('info', 'Connection cancelled by user.');
        } else {
            setWsStatus('error', 'WebSerial Error: ' + (err.message || err));
        }
    } finally {
        btn.disabled = false;
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
                valVib.innerText = peak.toFixed(3);
                valVib.style.color = 'var(--h-text)';
                // Values are gravity-removed g; 2 g fills the bar.
                const vibPct = Math.min(100, Math.max(0, (peak / 2) * 100));
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
