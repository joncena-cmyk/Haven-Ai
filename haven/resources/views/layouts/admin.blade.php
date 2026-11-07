<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name', 'HAVEN') }} Admin — {{ $title ?? 'Console' }}</title>

    <script>
        (function() {
            document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'dark');
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="haven-top-laser" class="haven-top-laser"></div>

    @php $admin = Auth::guard('admin')->user(); @endphp

    {{-- Dedicated Administrator Navigation (no driver features) --}}
    <header class="haven-navbar" id="haven-navbar">
        <div class="haven-navbar-inner">
            <div style="display:flex;align-items:center;gap:1.25rem;min-width:0;flex-wrap:wrap;">
                <a href="{{ route('admin.dashboard') }}" class="haven-brand" style="text-decoration:none;">
                    <div class="haven-brand-mark">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </div>
                    <span class="haven-brand-title">HAVEN <span>ADMIN</span></span>
                </a>

                <nav class="haven-nav-links" aria-label="Admin Navigation" style="display:flex;">
                    <a href="{{ route('admin.dashboard') }}" class="haven-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><span>Overview</span></a>
                    <a href="{{ route('admin.users.index') }}" class="haven-nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><span>Users</span></a>
                    <a href="{{ route('admin.ml.index') }}" class="haven-nav-link {{ request()->routeIs('admin.ml.*') ? 'active' : '' }}"><span>ML &amp; Datasets</span></a>
                    <a href="{{ route('admin.security.index') }}" class="haven-nav-link {{ request()->routeIs('admin.security.*') ? 'active' : '' }}"><span>Security</span></a>
                    <a href="{{ route('admin.audit.index') }}" class="haven-nav-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}"><span>Audit Log</span></a>
                </nav>
            </div>

            <div class="haven-nav-actions">
                <button onclick="toggleTheme()" type="button" aria-label="Toggle theme" title="Switch theme" class="haven-nav-theme-btn">
                    <svg id="topbar-theme-sun" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                        <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                    <svg id="topbar-theme-moon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>

                @if($admin)
                    <div class="haven-user-menu" id="haven-user-menu">
                        <button type="button" class="haven-user-btn" onclick="toggleUserDropdown()" aria-label="Administrator menu">
                            <div class="haven-user-avatar">{{ strtoupper(substr($admin->name, 0, 1)) }}</div>
                            <span class="haven-user-btn-name">{{ $admin->name }}</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div class="haven-user-dropdown" id="haven-user-dropdown">
                            <div class="haven-user-dropdown-header">
                                <div class="haven-user-dropdown-name">{{ $admin->name }}</div>
                                <div class="haven-user-dropdown-email">{{ $admin->email }}</div>
                                <div style="margin-top:0.35rem;"><span class="h-badge h-badge-teal" style="font-size:0.6rem;">ADMINISTRATOR</span></div>
                            </div>
                            <div style="height:1px;background:var(--h-border);margin:0.35rem 0;"></div>
                            <a href="{{ route('admin.security.index') }}" class="haven-user-dropdown-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <span>Security &amp; Password</span>
                            </a>
                            <form method="POST" action="{{ route('admin.logout') }}" style="margin:0;">
                                @csrf
                                <button type="submit" class="haven-user-dropdown-item danger">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                    <span>Sign Out</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </header>

    <main class="haven-main-canvas" id="haven-main-canvas">
        <div class="haven-content-container">
            {{ $slot }}
        </div>
    </main>

    <footer class="haven-footer" style="border-top:1px solid var(--h-border);background:var(--h-surface);padding:1rem 0;">
        <div style="max-width:1360px;margin:0 auto;padding:0 1.5rem;font-size:0.75rem;color:var(--h-text3);display:flex;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
            <span>&copy; {{ date('Y') }} HAVEN AI · Administrator Console · All actions are audited (ISO/IEC 27001)</span>
        </div>
    </footer>

    <script>
        function applyThemeUI(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            const moon = document.getElementById('topbar-theme-moon');
            const sun = document.getElementById('topbar-theme-sun');
            if (moon && sun) {
                moon.style.display = theme === 'light' ? 'block' : 'none';
                sun.style.display = theme === 'light' ? 'none' : 'block';
            }
        }
        function toggleTheme() {
            const next = (document.documentElement.getAttribute('data-theme') || 'dark') === 'light' ? 'dark' : 'light';
            localStorage.setItem('theme', next);
            applyThemeUI(next);
        }
        applyThemeUI(localStorage.getItem('theme') || 'dark');

        function toggleUserDropdown() {
            const menu = document.getElementById('haven-user-dropdown');
            if (menu) menu.classList.toggle('show');
        }
        document.addEventListener('click', function (e) {
            const userMenu = document.getElementById('haven-user-menu');
            const dropdown = document.getElementById('haven-user-dropdown');
            if (userMenu && dropdown && !userMenu.contains(e.target)) dropdown.classList.remove('show');
        });
    </script>

    @if($admin)
    <script>
        (function() {
            const INACTIVITY_LIMIT_MS = 30 * 60 * 1000; // 30 minutes
            let idleTimer = null;

            function triggerAdminAutoLogout() {
                const logoutForm = document.querySelector('form[action="{{ route('admin.logout') }}"]');
                if (logoutForm) {
                    logoutForm.submit();
                } else {
                    window.location.href = '{{ route('admin.login') }}';
                }
            }

            function resetIdleTimer() {
                if (idleTimer) clearTimeout(idleTimer);
                idleTimer = setTimeout(triggerAdminAutoLogout, INACTIVITY_LIMIT_MS);
            }

            ['mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll'].forEach(evt => {
                window.addEventListener(evt, resetIdleTimer, { passive: true });
            });
            resetIdleTimer();
        })();
    </script>
    @endif
</body>
</html>

