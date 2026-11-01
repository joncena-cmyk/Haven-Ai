<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="description" content="HAVEN AI — Intelligent Automotive Diagnostics and Real-Time Multi-Sensor Telemetry.">
    <title>HAVEN AI — Intelligent Vehicle Diagnostics</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <style>
        :root {
            --h-bg: #07090e;
            --h-surface: #0d111a;
            --h-surface-2: #131a27;
            --h-border: #1e293b;
            --h-border-subtle: rgba(255, 255, 255, 0.08);
            --h-blue: #0284c7;
            --h-blue-glow: #38bdf8;
            --h-blue-hover: #0369a1;
            --h-white: #ffffff;
            --h-text: #f8fafc;
            --h-muted: #94a3b8;
            --h-mono: 'JetBrains Mono', monospace;
            --h-sans: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            border-radius: 0 !important;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: var(--h-bg);
            color: var(--h-text);
            font-family: var(--h-sans);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        /* ── Action Buttons ── */
        .haven-btn-solid {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: var(--h-white);
            color: #000000;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 0.85rem 1.85rem;
            border: 1px solid var(--h-white);
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .haven-btn-solid:hover {
            background: var(--h-blue);
            border-color: var(--h-blue);
            color: var(--h-white);
        }

        .haven-btn-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: transparent;
            color: var(--h-white);
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 0.85rem 1.85rem;
            border: 1px solid rgba(255, 255, 255, 0.28);
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .haven-btn-ghost:hover {
            border-color: var(--h-white);
            background: rgba(255, 255, 255, 0.06);
            color: var(--h-white);
        }

        /* ── Nav Links ── */
        .haven-nav-link {
            position: relative;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--h-muted);
            text-decoration: none;
            padding: 0.4rem 0;
            transition: color 0.2s ease;
        }
        .haven-nav-link:hover {
            color: var(--h-white);
        }
        .haven-nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0%;
            height: 2px;
            background: var(--h-blue);
            transition: width 0.2s ease;
        }
        .haven-nav-link:hover::after {
            width: 100%;
        }

        /* ── Filter Tabs ── */
        .haven-tab {
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            color: var(--h-muted);
            font-size: 0.76rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.65rem 1.1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .haven-tab:hover {
            color: var(--h-white);
        }
        .haven-tab.active {
            color: var(--h-white);
            border-bottom-color: var(--h-blue);
        }

        /* ── Cards ── */
        .haven-card {
            background: var(--h-surface);
            border: 1px solid var(--h-border);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2rem;
            transition: all 0.2s ease;
        }
        .haven-card:hover {
            border-color: var(--h-blue);
            transform: translateY(-3px);
            background: var(--h-surface-2);
        }

        .haven-pill {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--h-blue-glow);
            margin-bottom: 0.6rem;
        }

        .h-mono {
            font-family: var(--h-mono);
        }

        .live-dot {
            width: 7px;
            height: 7px;
            background: #16a34a;
            box-shadow: 0 0 6px #16a34a;
            display: inline-block;
        }

        /* ── Cinematic Slow Pop-Up Entrance Animations ── */
        @keyframes cinematicPop {
            0% {
                opacity: 0;
                transform: translateY(44px) scale(0.96);
                filter: blur(8px);
            }
            60% {
                opacity: 0.95;
                filter: blur(0px);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
                filter: blur(0px);
            }
        }

        @keyframes cinematicFade {
            0% {
                opacity: 0;
                transform: translateY(30px);
                filter: blur(6px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
                filter: blur(0px);
            }
        }

        /* Staggered deliberate pacing for cinematic feel */
        .hero-pop-1 { animation: cinematicPop 1.3s cubic-bezier(0.16, 1, 0.3, 1) 0.15s both; }
        .hero-pop-2 { animation: cinematicPop 1.6s cubic-bezier(0.16, 1, 0.3, 1) 0.45s both; }
        .hero-pop-3 { animation: cinematicFade 1.6s cubic-bezier(0.16, 1, 0.3, 1) 0.8s both; }
        .hero-pop-4 { animation: cinematicPop 1.5s cubic-bezier(0.16, 1, 0.3, 1) 1.15s both; }
        .hero-pop-5 { animation: cinematicFade 1.7s cubic-bezier(0.16, 1, 0.3, 1) 1.5s both; }

        /* Scroll triggered cinematic pop-up for cards and sections */
        .scroll-pop {
            opacity: 0;
            transform: translateY(40px) scale(0.96);
            filter: blur(6px);
            transition: opacity 1.25s cubic-bezier(0.16, 1, 0.3, 1), transform 1.25s cubic-bezier(0.16, 1, 0.3, 1), filter 1.25s ease;
            will-change: opacity, transform, filter;
        }
        .scroll-pop.is-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            filter: blur(0px);
        }

        @media (prefers-reduced-motion: reduce) {
            .hero-pop-1, .hero-pop-2, .hero-pop-3, .hero-pop-4, .hero-pop-5, .scroll-pop {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
                filter: none !important;
            }
        }

        /* Mobile Viewport Responsiveness */
        @media (max-width: 768px) {
            header > div {
                padding: 0 1rem !important;
                gap: 0.5rem !important;
            }
            .hero-pop-4 {
                flex-direction: column !important;
                align-items: stretch !important;
            }
            .hero-pop-4 a {
                width: 100% !important;
                justify-content: center !important;
                min-height: 44px;
            }
            .hero-pop-5 {
                grid-template-columns: repeat(2, 1fr) !important;
                padding: 1rem !important;
                gap: 1rem !important;
            }
            #modules, #standards {
                padding: 3rem 1rem !important;
            }
            #telemetry {
                padding: 3rem 1rem !important;
            }
            #cards-grid {
                grid-template-columns: 1fr !important;
            }
            footer {
                padding: 2.5rem 1rem 1.5rem !important;
            }
        }

        @media (max-width: 480px) {
            header #top-status-text, header .h-mono {
                display: none !important;
            }
            .hero-pop-5 {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════════════
     HEADER
     ═══════════════════════════════════════════════════════════════ --}}
<header style="position:sticky;top:0;z-index:9000;background:rgba(7,9,14,0.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--h-border);">
    <div style="max-width:1380px;margin:0 auto;padding:0 2rem;height:64px;display:flex;align-items:center;justify-content:space-between;gap:2rem;">

        {{-- Brand Logo --}}
        <div style="display:flex;align-items:center;gap:2rem;">
            <a href="{{ url('/') }}" style="display:flex;align-items:center;gap:0.75rem;text-decoration:none;">
                <div style="width:26px;height:26px;display:flex;align-items:center;justify-content:center;background:rgba(2,132,199,0.12);border:1px solid rgba(2,132,199,0.4);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--h-blue)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <span style="font-weight:900;font-size:1.1rem;letter-spacing:0.16em;color:var(--h-white);text-transform:uppercase;">
                    HAVEN <span style="color:var(--h-blue-glow);">AI</span>
                </span>
            </a>

            <nav style="display:none;align-items:center;gap:1.75rem;" class="nav-links">
                <a href="#modules" class="haven-nav-link">Modules</a>
                <a href="#telemetry" class="haven-nav-link">Telemetry</a>
                <a href="#standards" class="haven-nav-link">Standards</a>
            </nav>
            <style>
                @media (min-width: 992px) { .nav-links { display: flex !important; } }
            </style>
        </div>

        {{-- Actions --}}
        <div style="display:flex;align-items:center;gap:1rem;">
            <div style="display:flex;align-items:center;gap:0.45rem;padding:0.35rem 0.75rem;background:var(--h-surface);border:1px solid var(--h-border);font-size:0.68rem;text-transform:uppercase;letter-spacing:0.08em;color:var(--h-muted);">
                <span class="live-dot" id="top-dot" style="background:#64748b;box-shadow:none;"></span>
                <span id="top-status-text" class="h-mono">Checking...</span>
            </div>

            @auth
                <a href="{{ url('/dashboard') }}" class="haven-btn-solid" style="padding:0.5rem 1.25rem;font-size:0.72rem;">
                    <span>Dashboard</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            @else
                <a href="{{ route('login') }}" class="haven-btn-ghost" style="padding:0.5rem 1.1rem;font-size:0.72rem;">
                    Sign In
                </a>
                @if(Route::has('register'))
                    <a href="{{ route('register') }}" class="haven-btn-solid" style="padding:0.5rem 1.25rem;font-size:0.72rem;">
                        <span>Get Started</span>
                    </a>
                @endif
            @endauth
        </div>

    </div>
</header>


{{-- ═══════════════════════════════════════════════════════════════
     HERO SECTION
     ═══════════════════════════════════════════════════════════════ --}}
<section style="position:relative;width:100%;min-height:85vh;display:flex;flex-direction:column;justify-content:flex-end;overflow:hidden;background:#05070a;">

    {{-- Dark Automotive Backdrop --}}
    <div style="position:absolute;inset:0;z-index:1;background-image:url('{{ asset('images/hero.jpg') }}');background-size:cover;background-position:center right;filter:brightness(0.75) contrast(1.05);"></div>
    <div style="position:absolute;inset:0;z-index:2;background:linear-gradient(180deg, rgba(7,9,14,0.15) 0%, rgba(7,9,14,0.85) 85%, #07090e 100%), linear-gradient(90deg, rgba(7,9,14,0.88) 0%, rgba(7,9,14,0.5) 50%, rgba(7,9,14,0.1) 100%);"></div>

    {{-- Hero Content --}}
    <div style="position:relative;z-index:3;max-width:1380px;width:100%;margin:0 auto;padding:4rem 2rem 2.5rem;">

        <div class="hero-pop-1" style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1.25rem;">
            <span style="width:20px;height:2px;background:var(--h-blue);display:inline-block;"></span>
            <span style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.16em;color:var(--h-blue-glow);">
                HAVEN AI &bull; Precision Telemetry
            </span>
        </div>

        <h1 class="hero-pop-2" style="font-size:clamp(2.2rem, 5.2vw, 4.4rem);font-weight:900;line-height:1.05;letter-spacing:-0.03em;margin:0 0 1.25rem;text-transform:uppercase;max-width:920px;">
            Decode the Machine.<br>
            <span style="color:var(--h-blue-glow);">Master the Drive.</span>
        </h1>

        <p class="hero-pop-3" style="font-size:1.05rem;color:var(--h-muted);line-height:1.7;max-width:560px;margin:0 0 2.25rem;">
            Autonomous ESP32 multi-sensor edge streaming and machine learning diagnostics for internal combustion engines.
        </p>

        <div class="hero-pop-4" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:3.5rem;">
            @auth
                <a href="{{ url('/dashboard') }}" class="haven-btn-solid">
                    <span>Open Dashboard</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            @else
                <a href="{{ route('register') }}" class="haven-btn-solid">
                    <span>Launch Platform</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
                <a href="#modules" class="haven-btn-ghost">
                    <span>View Architecture</span>
                </a>
            @endauth
        </div>

        {{-- Live Telemetry Strip --}}
        <div class="hero-pop-5" style="background:rgba(13,17,26,0.9);backdrop-filter:blur(12px);border:1px solid var(--h-border);padding:1.25rem 1.75rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1.5rem;align-items:center;">
            <div>
                <div style="font-size:0.65rem;color:var(--h-muted);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:0.2rem;">Coolant Temp</div>
                <div id="h-temp" class="h-mono" style="font-size:1.35rem;font-weight:700;color:var(--h-white);">--.- °C</div>
            </div>
            <div>
                <div style="font-size:0.65rem;color:var(--h-muted);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:0.2rem;">Engine Speed</div>
                <div id="h-rpm" class="h-mono" style="font-size:1.35rem;font-weight:700;color:var(--h-white);">0 <span style="font-size:0.75rem;color:var(--h-muted);">RPM</span></div>
            </div>
            <div>
                <div style="font-size:0.65rem;color:var(--h-muted);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:0.2rem;">Vibration Peak</div>
                <div id="h-vib" class="h-mono" style="font-size:1.35rem;font-weight:700;color:var(--h-white);">--.- <span style="font-size:0.75rem;color:var(--h-muted);">m/s²</span></div>
            </div>
            <div>
                <div style="font-size:0.65rem;color:var(--h-muted);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:0.2rem;">Bus Voltage</div>
                <div id="h-volt" class="h-mono" style="font-size:1.35rem;font-weight:700;color:var(--h-white);">--.- <span style="font-size:0.75rem;color:var(--h-muted);">V</span></div>
            </div>
            <div>
                <div style="font-size:0.65rem;color:var(--h-muted);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:0.2rem;">Acoustic Level</div>
                <div id="h-audio" class="h-mono" style="font-size:1.35rem;font-weight:700;color:var(--h-white);">-- <span style="font-size:0.75rem;color:var(--h-muted);">RMS</span></div>
            </div>
        </div>

    </div>
</section>


{{-- ═══════════════════════════════════════════════════════════════
     ARCHITECTURE & MODULES
     ═══════════════════════════════════════════════════════════════ --}}
<section id="modules" style="max-width:1380px;margin:0 auto;padding:5rem 2rem 4rem;">

    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1.5rem;margin-bottom:2.5rem;border-bottom:1px solid var(--h-border);padding-bottom:1.25rem;">
        <div>
            <div class="haven-pill">System Components</div>
            <h2 style="font-size:clamp(1.6rem, 3vw, 2.4rem);font-weight:800;letter-spacing:-0.02em;margin:0;text-transform:uppercase;">
                Diagnostic Modules
            </h2>
        </div>

        <div style="display:flex;gap:0.25rem;flex-wrap:wrap;">
            <button class="haven-tab active" onclick="filterCards('all', this)">All</button>
            <button class="haven-tab" onclick="filterCards('hardware', this)">Hardware</button>
            <button class="haven-tab" onclick="filterCards('acoustic', this)">Acoustics</button>
            <button class="haven-tab" onclick="filterCards('ai', this)">AI Diagnostics</button>
        </div>
    </div>

    <div id="cards-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:1.5rem;">

        {{-- Card 1: ESP32 DAQ --}}
        <div class="haven-card scroll-pop" data-cat="hardware">
            <div>
                <span class="haven-pill">Edge DAQ</span>
                <h3 style="font-size:1.15rem;font-weight:700;text-transform:uppercase;margin:0 0 0.6rem;">ESP32 Node</h3>
                <p style="font-size:0.85rem;color:var(--h-muted);line-height:1.6;margin:0 0 1.25rem;">
                    Dual-core microcontroller aggregating 5 sensors over Wi-Fi with sub-15ms ingestion.
                </p>
            </div>
            <div style="border-top:1px solid var(--h-border);padding-top:0.85rem;display:flex;justify-content:space-between;align-items:center;">
                <span class="h-mono" style="font-size:0.72rem;color:var(--h-muted);">2.4GHz Wi-Fi</span>
                <a href="{{ url('/dashboard') }}" style="color:var(--h-white);font-size:0.75rem;font-weight:700;text-decoration:none;letter-spacing:0.06em;">Live View &rarr;</a>
            </div>
        </div>

        {{-- Card 2: INMP441 Acoustic --}}
        <div class="haven-card scroll-pop" data-cat="acoustic">
            <div>
                <span class="haven-pill">Acoustics</span>
                <h3 style="font-size:1.15rem;font-weight:700;text-transform:uppercase;margin:0 0 0.6rem;">INMP441 MEMS Mic</h3>
                <p style="font-size:0.85rem;color:var(--h-muted);line-height:1.6;margin:0 0 1.25rem;">
                    I2S digital audio capture sampling combustion harmonics, belt squeal, and valve tap.
                </p>
            </div>
            <div style="border-top:1px solid var(--h-border);padding-top:0.85rem;display:flex;justify-content:space-between;align-items:center;">
                <span class="h-mono" style="font-size:0.72rem;color:var(--h-muted);">44.1 kHz I2S</span>
                <a href="{{ url('/diagnostics') }}" style="color:var(--h-white);font-size:0.75rem;font-weight:700;text-decoration:none;letter-spacing:0.06em;">Spectrogram &rarr;</a>
            </div>
        </div>

        {{-- Card 3: MPU6050 Vibration --}}
        <div class="haven-card scroll-pop" data-cat="acoustic">
            <div>
                <span class="haven-pill">Vibration</span>
                <h3 style="font-size:1.15rem;font-weight:700;text-transform:uppercase;margin:0 0 0.6rem;">MPU6050 Accelerometer</h3>
                <p style="font-size:0.85rem;color:var(--h-muted);line-height:1.6;margin:0 0 1.25rem;">
                    3-axis block acceleration monitoring engine balance and misfire vibrations.
                </p>
            </div>
            <div style="border-top:1px solid var(--h-border);padding-top:0.85rem;display:flex;justify-content:space-between;align-items:center;">
                <span class="h-mono" style="font-size:0.72rem;color:var(--h-muted);">I2C 0x68</span>
                <a href="{{ url('/diagnostics') }}" style="color:var(--h-white);font-size:0.75rem;font-weight:700;text-decoration:none;letter-spacing:0.06em;">Waveforms &rarr;</a>
            </div>
        </div>

        {{-- Card 4: INA219 + DS18B20 --}}
        <div class="haven-card scroll-pop" data-cat="hardware">
            <div>
                <span class="haven-pill">Power &amp; Thermal</span>
                <h3 style="font-size:1.15rem;font-weight:700;text-transform:uppercase;margin:0 0 0.6rem;">INA219 &amp; DS18B20</h3>
                <p style="font-size:0.85rem;color:var(--h-muted);line-height:1.6;margin:0 0 1.25rem;">
                    Real-time battery bus voltage, charging ripple, and coolant operating temperatures.
                </p>
            </div>
            <div style="border-top:1px solid var(--h-border);padding-top:0.85rem;display:flex;justify-content:space-between;align-items:center;">
                <span class="h-mono" style="font-size:0.72rem;color:var(--h-muted);">1-Wire / I2C</span>
                <a href="{{ url('/dashboard') }}" style="color:var(--h-white);font-size:0.75rem;font-weight:700;text-decoration:none;letter-spacing:0.06em;">Status &rarr;</a>
            </div>
        </div>

        {{-- Card 5: AI Diagnostics & DTC --}}
        <div class="haven-card scroll-pop" data-cat="ai">
            <div>
                <span class="haven-pill">Intelligence</span>
                <h3 style="font-size:1.15rem;font-weight:700;text-transform:uppercase;margin:0 0 0.6rem;">AI Diagnostic Copilot</h3>
                <p style="font-size:0.85rem;color:var(--h-muted);line-height:1.6;margin:0 0 1.25rem;">
                    Machine learning model mapping anomalies directly to SAE J1979 Diagnostic Trouble Codes.
                </p>
            </div>
            <div style="border-top:1px solid var(--h-border);padding-top:0.85rem;display:flex;justify-content:space-between;align-items:center;">
                <span class="h-mono" style="font-size:0.72rem;color:var(--h-muted);">SAE J1979 DTC</span>
                <a href="{{ url('/learning') }}" style="color:var(--h-white);font-size:0.75rem;font-weight:700;text-decoration:none;letter-spacing:0.06em;">DTC Hub &rarr;</a>
            </div>
        </div>

    </div>
</section>


{{-- ═══════════════════════════════════════════════════════════════
     LIVE TELEMETRY OVERVIEW
     ═══════════════════════════════════════════════════════════════ --}}
<section id="telemetry" style="background:var(--h-surface);border-top:1px solid var(--h-border);border-bottom:1px solid var(--h-border);padding:5rem 2rem;">
    <div style="max-width:1380px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:3.5rem;align-items:center;" class="split-grid">

        {{-- Left: Concise Features --}}
        <div>
            <div class="haven-pill">Live Ingestion</div>
            <h2 style="font-size:clamp(1.8rem, 3.5vw, 2.6rem);font-weight:800;letter-spacing:-0.02em;text-transform:uppercase;margin:0 0 1rem;">
                Real-Time Stream Engine
            </h2>
            <p style="font-size:0.95rem;color:var(--h-muted);line-height:1.7;margin:0 0 2rem;">
                Continuous edge sampling delivers live diagnostic data without database congestion.
            </p>

            <div style="display:flex;flex-direction:column;gap:1.25rem;margin-bottom:2.5rem;">
                <div style="display:flex;gap:1rem;align-items:flex-start;">
                    <div style="color:var(--h-blue-glow);font-weight:700;font-size:0.85rem;" class="h-mono">01</div>
                    <div>
                        <div style="font-weight:700;font-size:0.9rem;text-transform:uppercase;color:var(--h-white);">Auto-Detect Sensor Bus</div>
                        <div style="font-size:0.8rem;color:var(--h-muted);margin-top:0.2rem;">Dynamic I2C/OneWire probe detects sensor connections and disconnects live.</div>
                    </div>
                </div>

                <div style="display:flex;gap:1rem;align-items:flex-start;">
                    <div style="color:var(--h-blue-glow);font-weight:700;font-size:0.85rem;" class="h-mono">02</div>
                    <div>
                        <div style="font-weight:700;font-size:0.9rem;text-transform:uppercase;color:var(--h-white);">Volatile Live Test Bench</div>
                        <div style="font-size:0.8rem;color:var(--h-muted);margin-top:0.2rem;">15-second TTL cache lets you inspect hardware health without creating database sessions.</div>
                    </div>
                </div>

                <div style="display:flex;gap:1rem;align-items:flex-start;">
                    <div style="color:var(--h-blue-glow);font-weight:700;font-size:0.85rem;" class="h-mono">03</div>
                    <div>
                        <div style="font-weight:700;font-size:0.9rem;text-transform:uppercase;color:var(--h-white);">ISO/SAE 21434 Key Protection</div>
                        <div style="font-size:0.8rem;color:var(--h-muted);margin-top:0.2rem;">32-character authenticated tokens protect ingest endpoints per vehicle.</div>
                    </div>
                </div>
            </div>

            <a href="{{ url('/dashboard') }}" class="haven-btn-solid">
                <span>Open Live Bench</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>

        {{-- Right: Minimal Cockpit Mockup --}}
        <div style="background:var(--h-bg);border:1px solid var(--h-border);padding:2rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--h-border);padding-bottom:0.75rem;margin-bottom:1.5rem;">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span class="live-dot"></span>
                    <span style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--h-white);">Live Ingestion Stream</span>
                </div>
                <span class="h-mono" style="font-size:0.68rem;color:var(--h-muted);">NODE: ESP32-5SENSOR</span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem;">
                <div style="background:var(--h-surface);border:1px solid var(--h-border);padding:1.25rem;text-align:center;">
                    <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--h-muted);margin-bottom:0.35rem;">RPM</div>
                    <div id="c-rpm" class="h-mono" style="font-size:2rem;font-weight:800;color:var(--h-white);line-height:1;">0</div>
                    <div style="font-size:0.68rem;color:var(--h-blue-glow);font-weight:700;letter-spacing:0.08em;margin-top:0.35rem;">HALL SENSOR</div>
                </div>
                <div style="background:var(--h-surface);border:1px solid var(--h-border);padding:1.25rem;text-align:center;">
                    <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--h-muted);margin-bottom:0.35rem;">BLOCK TEMP</div>
                    <div id="c-temp" class="h-mono" style="font-size:2rem;font-weight:800;color:var(--h-white);line-height:1;">--.-°</div>
                    <div style="font-size:0.68rem;color:#16a34a;font-weight:700;letter-spacing:0.08em;margin-top:0.35rem;">DS18B20</div>
                </div>
            </div>

            <div style="background:var(--h-surface);border:1px solid var(--h-border);padding:1rem;">
                <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;">
                    <span style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.08em;color:var(--h-muted);">Acoustic Wave</span>
                    <span class="h-mono" style="font-size:0.65rem;color:var(--h-blue-glow);">44.1 kHz</span>
                </div>
                <svg width="100%" height="40" viewBox="0 0 300 40" preserveAspectRatio="none" style="display:block;">
                    <path d="M0 20 Q 25 5, 50 20 T 100 20 T 150 8 T 200 32 T 250 20 T 300 20" fill="none" stroke="var(--h-blue)" stroke-width="1.8"/>
                    <path d="M0 20 Q 30 14, 60 20 T 120 20 T 180 12 T 240 28 T 300 20" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="1"/>
                </svg>
            </div>
        </div>

    </div>
</section>
<style>
    @media (max-width: 900px) {
        .split-grid { grid-template-columns: 1fr !important; gap: 2.5rem !important; }
    }
</style>


{{-- ═══════════════════════════════════════════════════════════════
     STANDARDS & COMPLIANCE
     ═══════════════════════════════════════════════════════════════ --}}
<section id="standards" style="max-width:1380px;margin:0 auto;padding:5rem 2rem;">

    <div style="margin-bottom:2.5rem;">
        <div class="haven-pill">Architecture</div>
        <h2 style="font-size:clamp(1.6rem, 3vw, 2.2rem);font-weight:800;letter-spacing:-0.02em;text-transform:uppercase;margin:0;">
            Standards &amp; Security
        </h2>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;">

        <div class="scroll-pop" style="background:var(--h-surface);border:1px solid var(--h-border);padding:1.75rem;">
            <div style="font-size:0.7rem;font-weight:700;color:var(--h-blue-glow);letter-spacing:0.1em;text-transform:uppercase;margin-bottom:0.5rem;">
                ISO/SAE 21434
            </div>
            <h3 style="font-size:1.05rem;font-weight:700;text-transform:uppercase;margin:0 0 0.5rem;">Cybersecurity</h3>
            <p style="font-size:0.85rem;color:var(--h-muted);line-height:1.6;margin:0;">
                Cryptographic 32-character tokens authenticate each device transmission.
            </p>
        </div>

        <div class="scroll-pop" style="background:var(--h-surface);border:1px solid var(--h-border);padding:1.75rem;">
            <div style="font-size:0.7rem;font-weight:700;color:var(--h-blue-glow);letter-spacing:0.1em;text-transform:uppercase;margin-bottom:0.5rem;">
                SAE J1979 / OBD-II
            </div>
            <h3 style="font-size:1.05rem;font-weight:700;text-transform:uppercase;margin:0 0 0.5rem;">DTC Standard</h3>
            <p style="font-size:0.85rem;color:var(--h-muted);line-height:1.6;margin:0;">
                Harmonized powertrain trouble codes mapped to real-time sensor anomalies.
            </p>
        </div>

        <div class="scroll-pop" style="background:var(--h-surface);border:1px solid var(--h-border);padding:1.75rem;">
            <div style="font-size:0.7rem;font-weight:700;color:var(--h-blue-glow);letter-spacing:0.1em;text-transform:uppercase;margin-bottom:0.5rem;">
                ISO/IEC 27001
            </div>
            <h3 style="font-size:1.05rem;font-weight:700;text-transform:uppercase;margin:0 0 0.5rem;">Audit Trail</h3>
            <p style="font-size:0.85rem;color:var(--h-muted);line-height:1.6;margin:0;">
                Immutable logging of all logins, telemetry transmissions, and diagnostics.
            </p>
        </div>

    </div>
</section>


{{-- ═══════════════════════════════════════════════════════════════
     FOOTER
     ═══════════════════════════════════════════════════════════════ --}}
<footer style="background:#05070a;border-top:1px solid var(--h-border);padding:3.5rem 2rem 2.5rem;">
    <div style="max-width:1380px;margin:0 auto;">

        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:2rem;margin-bottom:2.5rem;padding-bottom:2rem;border-bottom:1px solid var(--h-border);">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div style="width:24px;height:24px;display:flex;align-items:center;justify-content:center;background:rgba(2,132,199,0.12);border:1px solid rgba(2,132,199,0.4);">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--h-blue)" stroke-width="2.5">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <span style="font-weight:800;font-size:1rem;letter-spacing:0.15em;text-transform:uppercase;">
                    HAVEN <span style="color:var(--h-blue-glow);">AI</span>
                </span>
            </div>

            <div style="display:flex;align-items:center;gap:1.5rem;font-size:0.8rem;">
                <a href="{{ url('/dashboard') }}" style="color:var(--h-muted);text-decoration:none;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='var(--h-muted)'">Dashboard</a>
                <a href="{{ url('/diagnostics') }}" style="color:var(--h-muted);text-decoration:none;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='var(--h-muted)'">Diagnostics</a>
                <a href="{{ url('/vehicles') }}" style="color:var(--h-muted);text-decoration:none;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='var(--h-muted)'">Vehicles</a>
                <a href="{{ url('/learning') }}" style="color:var(--h-muted);text-decoration:none;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='var(--h-muted)'">Learning Hub</a>
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;font-size:0.75rem;color:var(--h-muted);">
            <div>&copy; {{ date('Y') }} HAVEN AI. Capstone Research Project &bull; Group 3.</div>
            <a href="#" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;" style="color:var(--h-white);text-decoration:none;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;">
                Top &uarr;
            </a>
        </div>

    </div>
</footer>


{{-- ═══════════════════════════════════════════════════════════════
     SCRIPT
     ═══════════════════════════════════════════════════════════════ --}}
<script>
function filterCards(cat, btn) {
    document.querySelectorAll('.haven-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('#cards-grid .haven-card').forEach(card => {
        if (cat === 'all' || card.dataset.cat === cat) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

function pollTelemetry() {
    fetch('/api/v1/telemetry/latest', { cache: 'no-store' })
        .then(res => res.json())
        .then(data => {
            const dot  = document.getElementById('top-dot');
            const stat = document.getElementById('top-status-text');

            if (data.online) {
                if (dot) {
                    dot.style.background = '#16a34a';
                    dot.style.boxShadow  = '0 0 6px #16a34a';
                }
                if (stat) stat.textContent = 'Live Streaming';

                const hTemp = document.getElementById('h-temp');
                const hRpm  = document.getElementById('h-rpm');
                const hVib  = document.getElementById('h-vib');
                const hVolt = document.getElementById('h-volt');
                const hAud  = document.getElementById('h-audio');

                if (hTemp && data.temperature_c !== null) hTemp.textContent = Number(data.temperature_c).toFixed(1) + ' °C';
                if (hRpm  && data.rpm !== null) hRpm.innerHTML = Number(data.rpm).toLocaleString() + ' <span style="font-size:0.75rem;color:var(--h-muted);">RPM</span>';
                if (hVib  && data.peak_g !== null) hVib.innerHTML = Number(data.peak_g).toFixed(2) + ' <span style="font-size:0.75rem;color:var(--h-muted);">m/s²</span>';
                if (hVolt && data.voltage_v !== null) hVolt.innerHTML = Number(data.voltage_v).toFixed(2) + ' <span style="font-size:0.75rem;color:var(--h-muted);">V</span>';
                if (hAud  && data.audio_rms !== null) hAud.innerHTML = Math.round(data.audio_rms).toLocaleString() + ' <span style="font-size:0.75rem;color:var(--h-muted);">RMS</span>';

                const cRpm  = document.getElementById('c-rpm');
                const cTemp = document.getElementById('c-temp');
                if (cRpm && data.rpm !== null) cRpm.textContent = Number(data.rpm).toLocaleString();
                if (cTemp && data.temperature_c !== null) cTemp.textContent = Number(data.temperature_c).toFixed(1) + '°';
            } else {
                if (dot) {
                    dot.style.background = '#64748b';
                    dot.style.boxShadow  = 'none';
                }
                if (stat) stat.textContent = 'Standby';
            }
        })
        .catch(() => {});
}

pollTelemetry();
setInterval(pollTelemetry, 3000);

// Intersection Observer for scroll-triggered pop-up animation
const popObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            popObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.15, rootMargin: '0px 0px -30px 0px' });

document.querySelectorAll('.scroll-pop').forEach((el, index) => {
    el.style.transitionDelay = (index % 3 * 0.18) + 's';
    popObserver.observe(el);
});
</script>

</body>
</html>
