<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="HAVEN — ML-Based ICE Diagnostic Support System">
    <title>{{ config('app.name', 'HAVEN') }} — {{ $title ?? 'Dashboard' }}</title>
    
    <!-- Prevent flash of unstyled content - Load theme IMMEDIATELY -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    {{-- High-Tech Top Progress Laser Beam (Instant Visual Feedback on Page Moves) --}}
    <div id="haven-top-laser" class="haven-top-laser"></div>

    {{-- Modern Topbar Navigation --}}
    @include('layouts.navigation')

    {{-- Main Content Canvas (Expansive SaaS Layout) --}}
    <main class="haven-main-canvas" id="haven-main-canvas">
        <div class="haven-content-container">
            {{ $slot }}
        </div>
    </main>

    {{-- Professional Enterprise Automotive Footer --}}
    <footer class="haven-footer" style="border-top:1px solid var(--h-border);background:var(--h-surface);padding:1.25rem 0;">
        <div class="haven-footer-inner" style="max-width:1360px;margin:0 auto;padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;font-size:0.75rem;color:var(--h-text3);">
            {{-- Brand & System Descriptor --}}
            <div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <div style="width:20px;height:20px;display:flex;align-items:center;justify-content:center;background:rgba(2,132,199,0.12);border:1px solid rgba(2,132,199,0.35);">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                        </svg>
                    </div>
                    <span style="font-weight:800;letter-spacing:0.12em;color:var(--h-text);text-transform:uppercase;font-size:0.8rem;">
                        HAVEN <span style="color:var(--h-teal);">AI</span>
                    </span>
                </div>
                <span style="color:var(--h-border);">|</span>
                <span>&copy; {{ date('Y') }} Haven AI Technologies. Intelligent Powertrain Diagnostics.</span>
            </div>

            {{-- Legal & Policy Links --}}
            <div style="display:flex;align-items:center;gap:1.25rem;font-size:0.75rem;">
                <a href="{{ route('terms') }}" style="color:var(--h-text3);text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='var(--h-text)'" onmouseout="this.style.color='var(--h-text3)'">Terms & Conditions</a>
                <span style="color:var(--h-border);">·</span>
                <a href="{{ route('privacy') }}" style="color:var(--h-text3);text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='var(--h-text)'" onmouseout="this.style.color='var(--h-text3)'">Privacy Policy</a>
                <span style="color:var(--h-border);">·</span>
                <a href="{{ route('learning.index') }}" style="color:var(--h-text3);text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='var(--h-text)'" onmouseout="this.style.color='var(--h-text3)'">Knowledge Hub</a>
            </div>
        </div>
    </footer>

    {{-- Core Application Script --}}
    <script>
        // Theme Synchronization
        function applyThemeUI(theme) {
            document.documentElement.setAttribute('data-theme', theme);

            const topMoon = document.getElementById('topbar-theme-moon');
            const topSun = document.getElementById('topbar-theme-sun');
            if (topMoon && topSun) {
                topMoon.style.display = theme === 'light' ? 'block' : 'none';
                topSun.style.display = theme === 'light' ? 'none' : 'block';
            }
        }

        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            localStorage.setItem('theme', newTheme);
            applyThemeUI(newTheme);
        }

        (function initTheme() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            applyThemeUI(savedTheme);
        })();

        window.addEventListener('storage', function(e) {
            if (e.key === 'theme' && e.newValue) {
                applyThemeUI(e.newValue);
            }
        });

        // User Dropdown Menu
        function toggleUserDropdown() {
            const menu = document.getElementById('haven-user-dropdown');
            if (menu) {
                menu.classList.toggle('show');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const userMenu = document.getElementById('haven-user-menu');
            const dropdown = document.getElementById('haven-user-dropdown');
            if (userMenu && dropdown && !userMenu.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Mobile Drawer Toggle
        function toggleMobileDrawer() {
            const drawer = document.getElementById('haven-mobile-drawer');
            const backdrop = document.getElementById('haven-mobile-backdrop');
            if (drawer) {
                drawer.classList.toggle('open');
            }
            if (backdrop) {
                backdrop.classList.toggle('open');
            }
            document.body.style.overflow = drawer && drawer.classList.contains('open') ? 'hidden' : '';
        }

        // Close drawer on desktop resize or Escape key
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                const drawer = document.getElementById('haven-mobile-drawer');
                const backdrop = document.getElementById('haven-mobile-backdrop');
                if (drawer) drawer.classList.remove('open');
                if (backdrop) backdrop.classList.remove('open');
                document.body.style.overflow = '';
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const drawer = document.getElementById('haven-mobile-drawer');
                const backdrop = document.getElementById('haven-mobile-backdrop');
                if (drawer && drawer.classList.contains('open')) {
                    drawer.classList.remove('open');
                    if (backdrop) backdrop.classList.remove('open');
                    document.body.style.overflow = '';
                }
            }
        });
    </script>

    {{-- Automatic Inactivity Timeout (30 Minutes Idle Auto-Logout) --}}
    @auth
    <script>
        (function() {
            const INACTIVITY_LIMIT_MS = 30 * 60 * 1000; // 30 minutes in milliseconds
            let idleTimer = null;
            let lastKeepAlivePing = Date.now();

            function triggerAutoLogout() {
                // If a logout form is present on page, submit it cleanly
                const logoutForm = document.querySelector('form[action="{{ route('logout') }}"]');
                if (logoutForm) {
                    logoutForm.submit();
                } else {
                    // Fallback: POST directly with CSRF header
                    fetch('{{ route('logout') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        }
                    }).finally(() => {
                        window.location.href = '/';
                    });
                }
            }

            function resetIdleTimer() {
                if (idleTimer) clearTimeout(idleTimer);
                idleTimer = setTimeout(triggerAutoLogout, INACTIVITY_LIMIT_MS);

                // Keep session and CSRF in sync if user is active (throttled to every 2.5 min)
                const now = Date.now();
                if (now - lastKeepAlivePing > 2.5 * 60 * 1000) {
                    lastKeepAlivePing = now;
                    fetch('{{ route('keep-alive') }}', { credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(d => {
                            if (d && d.csrf_token) {
                                document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', d.csrf_token);
                            }
                        })
                        .catch(() => {});
                }
            }

            // User activity events: mousemove, keydown, click, scroll, touch
            const activityEvents = ['mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll'];
            activityEvents.forEach(evt => {
                window.addEventListener(evt, resetIdleTimer, { passive: true });
            });

            // Start timer immediately upon page load
            resetIdleTimer();
        })();
    </script>
    @endauth

    {{-- Floating Siri / Gigi Assistant Orb for the Website (Authenticated Users) --}}
    @auth
        <x-ai-orb />
    @endauth

    @guest
        <x-auth-modal />
    @endguest

</body>
</html>
