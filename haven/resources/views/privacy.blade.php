<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Privacy Policy for HAVEN AI — Data Privacy, Private Telemetry Disks & Zero-PII AI Protections.">
    <title>Privacy Policy — HAVEN AI</title>

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
            --h-teal: #14b8a6;
            --h-teal-bg: rgba(20, 184, 166, 0.12);
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
            --h-teal: #0f766e;
            --h-teal-bg: rgba(15, 118, 110, 0.08);
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

        .privacy-header {
            position: sticky;
            top: 0;
            z-index: 900;
            background: rgba(7, 9, 14, 0.88);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--h-border);
        }
        html[data-theme="light"] .privacy-header {
            background: rgba(255, 255, 255, 0.92);
        }

        .privacy-nav-wrap {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
        }

        .privacy-brand {
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

        .privacy-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 3rem 1.5rem 5rem;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 3rem;
            align-items: start;
        }

        @media (max-width: 900px) {
            .privacy-container {
                grid-template-columns: 1fr;
                gap: 2rem;
                padding-top: 2rem;
            }
            .privacy-sidebar {
                display: none;
            }
        }

        .privacy-sidebar {
            position: sticky;
            top: 96px;
            background: var(--h-surface);
            border: 1px solid var(--h-border);
            border-radius: var(--h-radius);
            padding: 1.5rem;
        }

        .privacy-toc-link {
            display: block;
            padding: 0.45rem 0.6rem;
            font-size: 0.8rem;
            color: var(--h-muted);
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.15s ease;
            margin-bottom: 0.2rem;
        }
        .privacy-toc-link:hover {
            color: var(--h-text);
            background: var(--h-surface-2);
            padding-left: 0.85rem;
        }

        .privacy-card {
            background: var(--h-surface);
            border: 1px solid var(--h-border);
            border-radius: var(--h-radius);
            padding: 2.25rem 2.5rem;
            margin-bottom: 2rem;
        }

        .privacy-card h2 {
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

        .privacy-badge {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.2rem 0.55rem;
            border-radius: 4px;
            background: var(--h-teal-bg);
            color: var(--h-teal);
            border: 1px solid rgba(20, 184, 166, 0.3);
            display: inline-block;
        }

        .privacy-list {
            padding-left: 1.35rem;
            margin: 0.75rem 0 1rem;
            color: var(--h-text2);
            font-size: 0.9rem;
        }
        .privacy-list li {
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

    <header class="privacy-header">
        <div class="privacy-nav-wrap">
            <a href="{{ url('/') }}" class="privacy-brand">
                <div style="width:26px;height:26px;display:flex;align-items:center;justify-content:center;background:var(--h-blue-bg);border:1px solid rgba(2,132,199,0.4);border-radius:4px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--h-blue)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <span>HAVEN <span style="color:var(--h-blue-glow);">AI</span></span>
            </a>

            <div style="display:flex;align-items:center;gap:1rem;">
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

    <main class="privacy-container">

        <aside class="privacy-sidebar">
            <div style="font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:0.12em;color:var(--h-muted);margin-bottom:1rem;">
                Privacy Outline
            </div>
            <nav>
                <a href="#data-collection" class="privacy-toc-link">1. Data We Collect</a>
                <a href="#audio-telemetry" class="privacy-toc-link">2. Audio & Private Disks</a>
                <a href="#llm-redaction" class="privacy-toc-link">3. LLM Zero-PII Policy</a>
                <a href="#iso-audit" class="privacy-toc-link">4. ISO 27001 Audit Logs</a>
                <a href="#rights" class="privacy-toc-link">5. Your Data Rights</a>
            </nav>

            <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--h-border);font-size:0.75rem;color:var(--h-muted);">
                <div>Effective Date: <strong>November 2026</strong></div>
                <div>Standard: <strong>ISO/IEC 27001:2022</strong></div>
            </div>
        </aside>

        <article>
            <div style="margin-bottom:2.5rem;">
                <div class="privacy-badge" style="margin-bottom:0.75rem;">Data Protection & Cybersecurity Policy</div>
                <h1 style="font-size:2.25rem;font-weight:900;letter-spacing:-0.03em;margin:0 0 0.5rem;color:var(--h-text);">
                    Privacy & Data Protection Policy
                </h1>
                <p style="font-size:1rem;color:var(--h-muted);margin:0;">
                    How HAVEN AI safeguards your garage records, IoT device credentials, uploaded engine acoustics, and vibration data.
                </p>
            </div>

            <section id="data-collection" class="privacy-card">
                <h2>
                    <span style="color:var(--h-teal);">01.</span>
                    Information We Collect
                </h2>
                <p style="color:var(--h-text2);font-size:0.92rem;margin-top:0;">
                    We collect only the technical data necessary to provide acoustic signal analysis, vibration feature extraction, and vehicle health trending:
                </p>
                <ul class="privacy-list">
                    <li><strong>Account Credentials:</strong> Name, email address, and securely hashed passwords (Bcrypt).</li>
                    <li><strong>Garage Metadata:</strong> Vehicle make, model, year, engine configuration, transmission, and optional odometer mileage.</li>
                    <li><strong>Hardware DAQ Telemetry:</strong> ESP32 device ID, 3-axis accelerometer readings (Peak G, RMS G), temperature readings (DS18B20), engine RPM (Hall sensor), and bus voltage (INA219).</li>
                </ul>
            </section>

            <section id="audio-telemetry" class="privacy-card">
                <h2>
                    <span style="color:var(--h-teal);">02.</span>
                    Private Storage Disks & Owner-Controlled Media
                </h2>
                <p style="color:var(--h-text2);font-size:0.92rem;margin-top:0;">
                    Customer audio and vibration files are treated as confidential diagnostic assets:
                </p>
                <ul class="privacy-list">
                    <li><strong>Zero Public Exposure:</strong> Engine recordings (<code>.wav</code>, <code>.webm</code>) and raw sensor files (<code>.csv</code>, <code>.json</code>) are stored exclusively in <code>storage/app/private</code>. No public URL symlinks exist for customer media.</li>
                    <li><strong>Owner-Authorized Streaming:</strong> Audio playback and CSV downloads are routed through authenticated controller endpoints that verify the user owns the diagnostic session.</li>
                </ul>
            </section>

            <section id="llm-redaction" class="privacy-card">
                <h2>
                    <span style="color:var(--h-teal);">03.</span>
                    Zero-PII Transmission to Generative AI Providers
                </h2>
                <p style="color:var(--h-text2);font-size:0.92rem;margin-top:0;">
                    HAVEN AI integrates external generative models (Groq, Google Gemini, OpenAI) strictly for conversational natural-language assistance. Before any prompt leaves our servers, the <code>GlobalAiAssistantService::sanitizePromptText</code> pipeline scrubs:
                </p>
                <ul class="privacy-list">
                    <li>Hardware device keys (<code>hvn_dev_...</code>)</li>
                    <li>Email addresses and phone numbers</li>
                    <li>17-character Vehicle Identification Numbers (VINs)</li>
                    <li>Private garage notes and secret tokens</li>
                </ul>
            </section>

            <section id="iso-audit" class="privacy-card">
                <h2>
                    <span style="color:var(--h-teal);">04.</span>
                    ISO/IEC 27001 Audit Trail & Security
                </h2>
                <p style="color:var(--h-text2);font-size:0.92rem;margin-top:0;">
                    All security-critical operations (including vehicle creation, device key regeneration, failed IoT authentication attempts, and telemetry ingestion) are logged in an immutable audit trail recording the timestamp, IP address, and user agent.
                </p>
            </section>

            <section id="rights" class="privacy-card">
                <h2>
                    <span style="color:var(--h-teal);">05.</span>
                    User Control & Deletion Rights
                </h2>
                <p style="color:var(--h-text2);font-size:0.92rem;margin-top:0;">
                    You maintain complete ownership of your vehicle and diagnostic data. You may delete individual diagnostic runs, purge entire vehicles, or delete your user account at any time from your Account Profile. Deletion immediately removes all associated database rows, audit records, audio files, and vibration telemetry from our disks.
                </p>
                <div style="margin-top:1.5rem;display:flex;gap:1rem;flex-wrap:wrap;">
                    <a href="{{ route('terms') }}" class="haven-btn haven-btn-primary">View Terms & Conditions</a>
                    <a href="{{ url('/') }}" class="haven-btn haven-btn-ghost">Back to Home</a>
                </div>
            </section>
        </article>
    </main>

    <footer style="background:var(--h-surface);border-top:1px solid var(--h-border);padding:2.5rem 1.5rem;text-align:center;font-size:0.8rem;color:var(--h-muted);">
        <div style="max-width:1280px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div>&copy; {{ date('Y') }} HAVEN AI. Capstone Research Project &bull; Group 3.</div>
            <div style="display:flex;gap:1.5rem;">
                <a href="{{ route('terms') }}" style="color:var(--h-muted);text-decoration:none;">Terms & Conditions</a>
                <a href="{{ route('privacy') }}" style="color:var(--h-text);text-decoration:none;font-weight:600;">Privacy Policy</a>
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

