<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="HAVEN — ML-Based Internal Combustion Engine Diagnostic Support System">
    <title>{{ config('app.name', 'HAVEN') }} — {{ $title ?? 'Sign In' }}</title>

    {{-- Apply saved theme before any paint to avoid flash --}}
    <script>
        (function () {
            var t = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div id="haven-top-laser" class="haven-top-laser"></div>

<div class="haven-auth-shell">

    {{-- Left visual panel --}}
    <div class="haven-auth-panel">
        <div class="haven-auth-panel-grid"></div>

        <div class="haven-auth-panel-content">

            <a href="{{ url('/') }}" title="Return to HAVEN Homepage" style="text-decoration:none;display:inline-block;">
                <div class="haven-brand-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--accent-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/>
                    </svg>
                </div>
            </a>

            <h1 class="haven-auth-tagline">
                HAVEN<br><span>Diagnostic System</span>
            </h1>
            <p class="haven-auth-subtitle">
                ML-based engine diagnostic support for internal combustion engines.
                Analyzes audio and vibration data to identify possible conditions.
            </p>

            {{-- Instrument-style info panel --}}
            <div class="haven-instr-panel" style="position:relative;overflow:hidden;">
                <div>
                    <div class="haven-instr-value" style="color:var(--h-telemetry-cyan);">Audio</div>
                    <div class="haven-instr-label">Signal Analysis</div>
                </div>
                <div class="haven-instr-divider"></div>
                <div>
                    <div class="haven-instr-value" style="color:var(--h-status-warn);">Vibration</div>
                    <div class="haven-instr-label">Sensor Data</div>
                </div>
                <div class="haven-instr-divider"></div>
                <div>
                    <div class="haven-instr-value" style="color:var(--h-status-ok);">ML</div>
                    <div class="haven-instr-label">Classification</div>
                </div>
            </div>

            {{-- Live Acoustic Waveform Decorative Strip --}}
            <div style="margin-top:1rem;padding:0.65rem 1rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius-lg);display:flex;align-items:center;justify-content:space-between;gap:0.75rem;">
                <div style="display:flex;align-items:center;gap:0.45rem;font-size:0.7rem;font-weight:600;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.06em;">
                    <span style="width:7px;height:7px;border-radius:50%;background:var(--h-status-ok);box-shadow:0 0 8px var(--h-status-ok);"></span>
                    <span>DSP Pipeline Ready</span>
                </div>
                <svg width="120" height="24" viewBox="0 0 120 24" fill="none" aria-hidden="true">
                    <path d="M2 12 L18 12 L24 4 L30 20 L36 2 L42 22 L48 7 L54 17 L60 9 L66 15 L72 5 L78 19 L84 12 L118 12" stroke="var(--h-teal)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>

            {{-- Features --}}
            <div class="haven-auth-features">
                <div class="haven-auth-feature">
                    <div class="haven-auth-feature-dot"></div>
                    Record or upload engine audio for signal analysis
                </div>
                <div class="haven-auth-feature">
                    <div class="haven-auth-feature-dot" style="background:#f59e0b;"></div>
                    Capture ESP32 accelerometer vibration data
                </div>
                <div class="haven-auth-feature">
                    <div class="haven-auth-feature-dot" style="background:#22c55e;"></div>
                    ML model classifies possible engine conditions
                </div>
                <div class="haven-auth-feature">
                    <div class="haven-auth-feature-dot" style="background:var(--text-secondary);"></div>
                    University capstone research project · CINS Group 3
                </div>
            </div>

        </div>
    </div>

    {{-- Right form panel --}}
    <div class="haven-auth-form-panel" style="position:relative;">

        {{-- Theme toggle in top corner of form panel --}}
        <div style="position:absolute;top:1.25rem;right:1.25rem;z-index:10;">
            <button onclick="toggleTheme()" type="button" aria-label="Toggle theme" title="Switch theme"
                style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);cursor:pointer;color:var(--h-text2);transition:all 0.2s;"
                onmouseover="this.style.background='var(--h-surface3)';this.style.color='var(--h-text)'"
                onmouseout="this.style.background='var(--h-surface2)';this.style.color='var(--h-text2)'">
                <svg id="guest-theme-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                    <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
                <svg id="guest-theme-moon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>
        </div>

        <div class="haven-auth-card">

            {{-- Mobile logo --}}
            <div class="haven-auth-logo-mobile">
                <a href="{{ url('/') }}" style="display:flex;align-items:center;gap:0.5rem;text-decoration:none;color:inherit;">
                    <div class="haven-logo-mark">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/>
                        </svg>
                    </div>
                    <span class="haven-logo-text">HA<span>VEN</span></span>
                </a>
            </div>

            {{ $slot }}

        </div>
    </div>

</div>

<script>
    function toggleTheme() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme') || 'dark';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateGuestThemeIcons(newTheme);
    }

    function updateGuestThemeIcons(theme) {
        const sun = document.getElementById('guest-theme-sun');
        const moon = document.getElementById('guest-theme-moon');
        if (sun && moon) {
            if (theme === 'light') {
                sun.style.display = 'none';
                moon.style.display = 'block';
            } else {
                sun.style.display = 'block';
                moon.style.display = 'none';
            }
        }
    }

    (function() {
        const t = localStorage.getItem('theme') || 'dark';
        updateGuestThemeIcons(t);
    })();
</script>

</body>
</html>
