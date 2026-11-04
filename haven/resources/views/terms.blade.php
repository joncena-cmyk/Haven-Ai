<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Terms and Conditions of Use for HAVEN AI — Automotive Acoustic & Vibration Diagnostics Platform.">
    <title>Terms and Conditions — HAVEN AI</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <style>
        :root {
            --h-bg: #07090e;
            --h-surface: #0d111a;
            --h-surface-2: #131a27;
            --h-surface-3: #1a2334;
            --h-border: #1e293b;
            --h-border-subtle: rgba(255, 255, 255, 0.08);
            --h-blue: #0284c7;
            --h-blue-glow: #38bdf8;
            --h-blue-bg: rgba(2, 132, 199, 0.12);
            --h-white: #ffffff;
            --h-text: #f8fafc;
            --h-text2: #cbd5e1;
            --h-muted: #94a3b8;
            --h-mono: 'JetBrains Mono', monospace;
            --h-sans: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --h-radius: 8px;
        }

        html[data-theme="light"] {
            --h-bg: #f8fafc;
            --h-surface: #ffffff;
            --h-surface-2: #f1f5f9;
            --h-surface-3: #e2e8f0;
            --h-border: #cbd5e1;
            --h-border-subtle: rgba(0, 0, 0, 0.08);
            --h-blue: #0284c7;
            --h-blue-glow: #0369a1;
            --h-blue-bg: rgba(2, 132, 199, 0.08);
            --h-white: #0f172a;
            --h-text: #0f172a;
            --h-text2: #334155;
            --h-muted: #64748b;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background: var(--h-bg);
            color: var(--h-text);
            font-family: var(--h-sans);
            -webkit-font-smoothing: antialiased;
            line-height: 1.6;
        }

        .terms-header {
            position: sticky;
            top: 0;
            z-index: 900;
            background: rgba(7, 9, 14, 0.88);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--h-border);
        }
        html[data-theme="light"] .terms-header {
            background: rgba(255, 255, 255, 0.92);
        }

        .terms-nav-wrap {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
        }

        .terms-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--h-text);
            font-weight: 800;
            font-size: 1.05rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .terms-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 3rem 1.5rem 5rem;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 3rem;
            align-items: start;
        }

        @media (max-width: 900px) {
            .terms-container {
                grid-template-columns: 1fr;
                gap: 2rem;
                padding-top: 2rem;
            }
            .terms-sidebar {
                position: static !important;
                display: none;
            }
        }

        .terms-sidebar {
            position: sticky;
            top: 96px;
            background: var(--h-surface);
            border: 1px solid var(--h-border);
            border-radius: var(--h-radius);
            padding: 1.5rem;
        }

        .terms-toc-link {
            display: block;
            padding: 0.45rem 0.6rem;
            font-size: 0.8rem;
            color: var(--h-muted);
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.15s ease;
            margin-bottom: 0.2rem;
        }
        .terms-toc-link:hover {
            color: var(--h-text);
            background: var(--h-surface-2);
            padding-left: 0.85rem;
        }

        .terms-card {
            background: var(--h-surface);
            border: 1px solid var(--h-border);
            border-radius: var(--h-radius);
            padding: 2.25rem 2.5rem;
            margin-bottom: 2rem;
        }

        .terms-card h2 {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            margin-top: 0;
            margin-bottom: 1rem;
            color: var(--h-text);
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .terms-badge {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.2rem 0.55rem;
            border-radius: 4px;
            background: var(--h-blue-bg);
            color: var(--h-blue-glow);
            border: 1px solid rgba(2, 132, 199, 0.3);
            display: inline-block;
        }

        .terms-alert {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.28);
            border-left: 4px solid #ef4444;
            padding: 1.25rem 1.5rem;
            border-radius: 4px;
            margin: 1.5rem 0;
            font-size: 0.88rem;
            color: var(--h-text2);
        }

        .terms-alert-title {
            font-weight: 700;
            color: #ef4444;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-size: 0.78rem;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .terms-info-box {
            background: var(--h-surface-2);
            border: 1px solid var(--h-border);
            padding: 1.25rem 1.5rem;
            border-radius: 6px;
            margin: 1.25rem 0;
            font-size: 0.86rem;
            color: var(--h-text2);
        }

        .terms-list {
            padding-left: 1.35rem;
            margin: 0.75rem 0 1rem;
            color: var(--h-text2);
            font-size: 0.9rem;
        }
        .terms-list li {
            margin-bottom: 0.5rem;
        }

        .haven-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.55rem 1.15rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            text-decoration: none;
            border-radius: var(--h-radius);
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .haven-btn-primary {
            background: var(--h-blue);
            color: #ffffff;
            border: 1px solid var(--h-blue);
        }
        .haven-btn-primary:hover {
            background: #0369a1;
            border-color: #0369a1;
        }
        .haven-btn-ghost {
            background: transparent;
            color: var(--h-text);
            border: 1px solid var(--h-border);
        }
        .haven-btn-ghost:hover {
            background: var(--h-surface-2);
        }
    </style>
</head>
<body>

    {{-- Topbar --}}
    <header class="terms-header">
        <div class="terms-nav-wrap">
            <a href="{{ url('/') }}" class="terms-brand">
                <div style="width:26px;height:26px;display:flex;align-items:center;justify-content:center;background:var(--h-blue-bg);border:1px solid rgba(2,132,199,0.4);border-radius:4px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--h-blue)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <span>HAVEN <span style="color:var(--h-blue-glow);">AI</span></span>
            </a>

            <div style="display:flex;align-items:center;gap:1rem;">
                {{-- Theme Switcher --}}
                <button onclick="toggleTheme()" type="button" aria-label="Toggle theme" title="Switch theme"
                    style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;background:var(--h-surface);border:1px solid var(--h-border);border-radius:var(--h-radius);cursor:pointer;color:var(--h-text);transition:all 0.15s;">
                    <svg id="theme-icon-sun" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                        <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                    <svg id="theme-icon-moon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>

                @auth
                    <a href="{{ route('dashboard') }}" class="haven-btn haven-btn-primary">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="haven-btn haven-btn-ghost">Sign In</a>
                    <a href="{{ route('register') }}" class="haven-btn haven-btn-primary">Register</a>
                @endauth
            </div>
        </div>
    </header>

    {{-- Main Content --}}
    <main class="terms-container">

        {{-- Sidebar Table of Contents --}}
        <aside class="terms-sidebar">
            <div style="font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:0.12em;color:var(--h-muted);margin-bottom:1rem;">
                Table of Contents
            </div>
            <nav>
                <a href="#acceptance" class="terms-toc-link">1. Acceptance of Terms</a>
                <a href="#automotive-disclaimer" class="terms-toc-link" style="color:#ef4444;font-weight:600;">2. Automotive Safety Disclaimer</a>
                <a href="#capstone-context" class="terms-toc-link">3. Academic & Capstone Scope</a>
                <a href="#accounts-keys" class="terms-toc-link">4. User Accounts & IoT Keys</a>
                <a href="#telemetry-storage" class="terms-toc-link">5. Audio & Sensor Telemetry</a>
                <a href="#ai-assistant" class="terms-toc-link">6. AI Assistant & LLM Redaction</a>
                <a href="#acceptable-use" class="terms-toc-link">7. Acceptable Use & Rate Limits</a>
                <a href="#liability" class="terms-toc-link">8. Limitation of Liability</a>
                <a href="#contact" class="terms-toc-link">9. Inquiries & Revisions</a>
            </nav>

            <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--h-border);font-size:0.75rem;color:var(--h-muted);">
                <div>Effective Date: <strong>November 2026</strong></div>
                <div>Version: <strong>2.5 (ISO/SAE Compliant)</strong></div>
            </div>
        </aside>

        {{-- Main Article Panels --}}
        <article>

            {{-- Title Hero --}}
            <div style="margin-bottom:2.5rem;">
                <div class="terms-badge" style="margin-bottom:0.75rem;">Legal Notice & Operational Agreement</div>
                <h1 style="font-size:2.25rem;font-weight:900;letter-spacing:-0.03em;margin:0 0 0.5rem;color:var(--h-text);">
                    Terms and Conditions of Use
                </h1>
                <p style="font-size:1rem;color:var(--h-muted);margin:0;">
                    Please read these Terms and Conditions carefully prior to registering an account, mounting IoT sensor hardware, or submitting acoustic and vibration telemetry to the HAVEN AI platform.
                </p>
            </div>

            {{-- Section 1: Acceptance --}}
            <section id="acceptance" class="terms-card">
                <h2>
                    <span style="color:var(--h-blue-glow);">01.</span>
                    Acceptance of Terms
                </h2>
                <p style="color:var(--h-text2);font-size:0.92rem;margin-top:0;">
                    By creating an account, accessing this website, utilizing our API endpoints (including <code>/api/v1/telemetry/ingest</code>), or querying the HAVEN AI Copilot, you expressly acknowledge and agree to be bound by these Terms and Conditions and all applicable laws and regulations. If you do not agree to every provision contained herein, you must immediately discontinue use of the platform and disconnect all ESP32 IoT DAQ devices.
                </p>
            </section>

            {{-- Section 2: Critical Automotive Safety Disclaimer --}}
            <section id="automotive-disclaimer" class="terms-card" style="border-color:rgba(239, 68, 68, 0.4);">
                <h2>
                    <span style="color:#ef4444;">02.</span>
                    Critical Automotive Safety Disclaimer
                </h2>

                <div class="terms-alert">
                    <div class="terms-alert-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Mandatory Safety Notice · Not a Replacement for a Certified Mechanic
                    </div>
                    HAVEN AI is an automated statistical classification and decision-support tool. It does <strong>NOT</strong> perform hands-on mechanical repairs, component torque validation, certified automotive safety inspections, or direct ECU flash overrides. All vehicle diagnostics, maintenance, and driving decisions remain the sole responsibility of the vehicle owner or certified technician.
                </div>

                <p style="color:var(--h-text2);font-size:0.92rem;">
                    When utilizing HAVEN AI for acoustic recording or IoT accelerometer telemetry, users must observe the following vehicle safety precautions:
                </p>

                <ul class="terms-list">
                    <li><strong>Stationary Operation Only:</strong> Never hold mobile recording devices or manipulate ESP32 microcontroller hardware while driving on public highways or operating a moving motor vehicle. Telemetry recordings must only be gathered with the vehicle safely in Park (or Neutral) on level ground with the parking brake securely engaged.</li>
                    <li><strong>Moving Powertrain Hazards:</strong> Keep hands, loose clothing, hair, tools, and sensor wires completely clear of rotating accessory drive pulleys, serpentine belts, electric radiator cooling fan blades, and alternator components.</li>
                    <li><strong>Extreme Thermal Hazards:</strong> Exhaust manifolds, turbocharger housings, and catalytic converters reach temperatures exceeding 400°C to 900°C. Never touch or affix accelerometers, sensors, or smartphone cases to hot exhaust components.</li>
                    <li><strong>High-Pressure Cooling Hazard:</strong> Never open a radiator pressure cap or coolant expansion tank on a hot or overheating engine. Scalding pressurized coolant can cause severe burn trauma.</li>
                    <li><strong>Ventilation & Carbon Monoxide:</strong> Never run an internal combustion engine in an enclosed garage or space without commercial exhaust extraction. Carbon monoxide gas is odorless, colorless, and lethal.</li>
                </ul>
            </section>

            {{-- Section 3: Capstone Scope --}}
            <section id="capstone-context" class="terms-card">
                <h2>
                    <span style="color:var(--h-blue-glow);">03.</span>
                    Academic Research & Capstone Project Scope
                </h2>
                <p style="color:var(--h-text2);font-size:0.92rem;margin-top:0;">
                    HAVEN AI is developed as a University Capstone Research Project (Group 3). The core objective is investigating dual-tier machine learning classification (Mel-Frequency Cepstral Coefficients, spectral centroids, Peak G, RMS G, and Crest Factor impulsiveness) applied to internal combustion engine anomalies.
                </p>
                <div class="terms-info-box">
                    <strong>Validated Distribution:</strong> HAVEN's acoustic model was trained specifically on 4-cylinder four-stroke gasoline internal combustion engines under baseline idling and controlled revving conditions. Diagnostics applied to diesel engines, rotary engines, electric vehicles, or two-stroke machinery fall outside the primary validated training distribution and should be interpreted with caution.
                </div>
            </section>

            {{-- Section 4: Accounts & IoT Keys --}}
            <section id="accounts-keys" class="terms-card">
                <h2>
                    <span style="color:var(--h-blue-glow);">04.</span>
                    User Accounts & Hardware IoT Authentication
                </h2>
                <p style="color:var(--h-text2);font-size:0.92rem;margin-top:0;">
                    To use garage vehicle tracking and IoT telemetry, you must create an account. You agree to provide accurate vehicle information and maintain the confidentiality of your credentials.
                </p>
                <ul class="terms-list">
                    <li><strong>Cryptographic Device Keys:</strong> Every registered vehicle is assigned an ESP32 device key formatted as <code>hvn_dev_[32-hex]</code>. In compliance with <strong>ISO/SAE 21434 §9</strong> (Automotive Cybersecurity Hardware Authentication), this key validates telemetry streams. You are responsible for safeguarding device keys. If you suspect an unauthorized party possesses your device key, you must regenerate it immediately via your vehicle profile.</li>
                    <li><strong>Account Security:</strong> You are responsible for all activities conducted under your user account and IoT device keys. HAVEN implements ISO/IEC 27001 audit logging of vehicle registrations, telemetry submissions, and key regenerations.</li>
                </ul>
            </section>

            {{-- Section 5: Telemetry & Private Storage --}}
            <section id="telemetry-storage" class="terms-card">
                <h2>
                    <span style="color:var(--h-blue-glow);">05.</span>
                    Audio Recordings & Sensor Telemetry Storage
                </h2>
                <p style="color:var(--h-text2);font-size:0.92rem;margin-top:0;">
                    When you record engine audio or stream accelerometer data, HAVEN processes the binary payload solely for Fourier transform, feature extraction, and ML condition inference.
                </p>
                <ul class="terms-list">
                    <li><strong>Private Disk Isolation:</strong> In accordance with data privacy and zero-trust storage standards, all uploaded customer engine audio (<code>.wav</code>, <code>.webm</code>) and vibration telemetry (<code>.csv</code>, <code>.json</code>) are stored exclusively on protected private disks, not public storage symlinks.</li>
                    <li><strong>Owner-Checked Access:</strong> Audio streaming and vibration file downloads are strictly protected behind authenticated and owner-authorized routes. Unauthorized users or unauthenticated actors cannot access your garage audio or telemetry files.</li>
                    <li><strong>Right to Deletion:</strong> Deleting a diagnostic session or vehicle permanently deletes the associated audio, vibration telemetry, and database records from our storage disks.</li>
                </ul>
            </section>

            {{-- Section 6: AI Copilot & Privacy --}}
            <section id="ai-assistant" class="terms-card">
                <h2>
                    <span style="color:var(--h-blue-glow);">06.</span>
                    AI Assistant Copilot & Third-Party LLM Redaction
                </h2>
                <p style="color:var(--h-text2);font-size:0.92rem;margin-top:0;">
                    HAVEN AI provides an interactive mechanical assistant. You acknowledge that:
                </p>
                <ul class="terms-list">
                    <li><strong>Automatic PII Redaction:</strong> When interacting with the AI Assistant, our backend executes automated redaction to strip device keys (<code>hvn_dev_...</code>), email addresses, telephone numbers, and 17-character VINs before prompts are forwarded to external LLM providers (e.g., Groq, Google Gemini, OpenAI).</li>
                    <li><strong>Informational Guidance:</strong> Answers provided by the AI Assistant are generative informational suggestions based on SAE diagnostic principles. They do not constitute formal mechanical repair estimates, warranty guarantees, or structural engineering sign-offs.</li>
                </ul>
            </section>

            {{-- Section 7: Acceptable Use --}}
            <section id="acceptable-use" class="terms-card">
                <h2>
                    <span style="color:var(--h-blue-glow);">07.</span>
                    Acceptable Use, Abuse Prevention & Rate Limiting
                </h2>
                <p style="color:var(--h-text2);font-size:0.92rem;margin-top:0;">
                    To protect infrastructure and fellow users from denial-of-service and disk-exhaustion vulnerabilities, you agree not to:
                </p>
                <ul class="terms-list">
                    <li>Exceed API rate limits (telemetry ingest is restricted to 30 requests/minute per vehicle/IP, AI questions to 15 requests/minute per user).</li>
                    <li>Submit corrupted or oversized payloads exceeding 5 MB decoded audio or 512 KB vibration CSV.</li>
                    <li>Attempt to probe, scrape, reverse-engineer, or bypass authentication barriers or vehicle ownership authorization controls.</li>
                    <li>Upload audio recordings containing non-automotive eavesdropped conversations, confidential communications, or unlawful material.</li>
                </ul>
            </section>

            {{-- Section 8: Limitation of Liability --}}
            <section id="liability" class="terms-card">
                <h2>
                    <span style="color:var(--h-blue-glow);">08.</span>
                    Limitation of Liability & "As-Is" Warranty Disclaimer
                </h2>
                <p style="color:var(--h-text2);font-size:0.92rem;margin-top:0;text-transform:uppercase;font-size:0.82rem;letter-spacing:0.04em;color:var(--h-muted);">
                    THE HAVEN AI PLATFORM, HARDWARE SCHEMATICS, FIRMWARE SKETCHES, ML MODELS, AND DIAGNOSTIC REPORTS ARE PROVIDED ON AN "AS-IS" AND "AS-AVAILABLE" BASIS WITHOUT WARRANTIES OF ANY KIND, EXPRESS OR IMPLIED.
                </p>
                <p style="color:var(--h-text2);font-size:0.92rem;">
                    To the maximum extent permitted by applicable law, the authors, developers, university instructors, and Group 3 capstone researchers shall not be liable for any direct, indirect, incidental, punitive, or consequential damages, including but not limited to:
                </p>
                <ul class="terms-list">
                    <li>Engine breakdown, thrown connecting rods, valvetrain damage, seized bearings, or melted catalytic converters;</li>
                    <li>Vehicle towing, rental car, diagnostic shop labor, or parts replacement expenses;</li>
                    <li>Physical injury, burns, electrical shock, or property damage resulting from improperly mounted IoT hardware or improper engine bay inspection;</li>
                    <li>Erroneous, false-positive, or false-negative machine learning diagnostic classifications.</li>
                </ul>
            </section>

            {{-- Section 9: Inquiries --}}
            <section id="contact" class="terms-card">
                <h2>
                    <span style="color:var(--h-blue-glow);">09.</span>
                    Inquiries, Governing Law & Revisions
                </h2>
                <p style="color:var(--h-text2);font-size:0.92rem;margin-top:0;">
                    We reserve the right to revise or update these Terms and Conditions at any time. Continued use of the platform following the posting of modifications constitutes your acceptance of the revised Terms.
                </p>
                <p style="color:var(--h-text2);font-size:0.92rem;">
                    If you have questions regarding these Terms, privacy protections, or research collaboration, please contact the project team through your institutional portal or GitHub repository.
                </p>
                <div style="margin-top:1.5rem;display:flex;gap:1rem;flex-wrap:wrap;">
                    <a href="{{ url('/') }}" class="haven-btn haven-btn-primary">Return to Homepage</a>
                    <a href="{{ route('privacy') }}" class="haven-btn haven-btn-ghost">View Privacy Policy</a>
                </div>
            </section>

        </article>
    </main>

    {{-- Footer --}}
    <footer style="background:var(--h-surface);border-top:1px solid var(--h-border);padding:2.5rem 1.5rem;text-align:center;font-size:0.8rem;color:var(--h-muted);">
        <div style="max-width:1280px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div>&copy; {{ date('Y') }} HAVEN AI. Capstone Research Project &bull; Group 3.</div>
            <div style="display:flex;gap:1.5rem;">
                <a href="{{ route('terms') }}" style="color:var(--h-text);text-decoration:none;font-weight:600;">Terms & Conditions</a>
                <a href="{{ route('privacy') }}" style="color:var(--h-muted);text-decoration:none;">Privacy Policy</a>
                <a href="{{ url('/learning') }}" style="color:var(--h-muted);text-decoration:none;">Learning Hub</a>
            </div>
        </div>
    </footer>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme') || 'dark';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcons(newTheme);
        }

        function updateThemeIcons(theme) {
            const sun = document.getElementById('theme-icon-sun');
            const moon = document.getElementById('theme-icon-moon');
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
            updateThemeIcons(t);
        })();
    </script>
</body>
</html>

