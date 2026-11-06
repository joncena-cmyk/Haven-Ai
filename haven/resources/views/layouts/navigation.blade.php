@php
    $authUser = Auth::user();
    $authName = $authUser ? $authUser->name : 'User';
    $authEmail = $authUser ? $authUser->email : '';
    $authInitial = $authUser ? strtoupper(substr($authUser->name, 0, 1)) : 'U';
    $vc = $authUser ? $authUser->vehicles()->count() : 0;
    $dc = $authUser ? $authUser->diagnostics()->count() : 0;
@endphp

<header class="haven-navbar" id="haven-navbar">
    <div class="haven-navbar-inner">

        {{-- Left: Brand & Main Navigation --}}
        <div style="display:flex;align-items:center;gap:1.25rem;min-width:0;">
            {{-- Brand Logo --}}
            <a href="{{ $authUser ? route('dashboard') : url('/') }}" class="haven-brand" style="text-decoration:none;">
                <div class="haven-brand-mark">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <span class="haven-brand-title">HAVEN <span>AI</span></span>
            </a>

            {{-- Desktop Horizontal Navigation --}}
            <nav class="haven-nav-links" aria-label="Main Navigation">
                @auth
                    <a href="{{ route('dashboard') }}"
                       class="haven-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                        <span>Dashboard</span>
                    </a>

                    <a href="{{ route('vehicles.index') }}"
                       class="haven-nav-link {{ request()->routeIs('vehicles.*') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                            <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                        </svg>
                        <span>Vehicles</span>
                    </a>

                    <a href="{{ route('diagnostics.index') }}"
                       class="haven-nav-link {{ request()->routeIs('diagnostics.index') || request()->routeIs('diagnostics.show') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                        <span>Diagnostic History</span>
                    </a>
                @else
                    <a href="{{ url('/') }}"
                       class="haven-nav-link">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        <span>Home</span>
                    </a>
                @endauth

                <a href="{{ route('learning.index') }}"
                   class="haven-nav-link {{ request()->routeIs('learning.index', 'learning.topic', 'learning.show') ? 'active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                    </svg>
                    <span>Learning Hub</span>
                </a>

                @guest
                    <a href="{{ route('learning.dtc') }}"
                       class="haven-nav-link {{ request()->routeIs('learning.dtc') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <span>OBD-II DTC</span>
                    </a>

                    <a href="{{ route('learning.quiz') }}"
                       class="haven-nav-link {{ request()->routeIs('learning.quiz') ? 'active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        <span>Quiz</span>
                    </a>
                @endguest
            </nav>
        </div>

        {{-- Right: Actions & User Dropdown --}}
        <div class="haven-nav-actions">
            @auth
                {{-- Quick New Session CTA --}}
                <a href="{{ route('diagnostics.create') }}" class="haven-nav-cta {{ request()->routeIs('diagnostics.create') ? 'active' : '' }}" title="Start New Diagnostic Session">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    <span>Diagnose</span>
                </a>

                {{-- Stat Pill Context --}}
                <div class="haven-stat-pill" title="Live database counts">
                    <span class="haven-stat-pill-num">{{ $vc }}</span> {{ Str::plural('vehicle', $vc) }}
                    <span style="opacity:0.4;">·</span>
                    <span class="haven-stat-pill-num">{{ $dc }}</span> {{ Str::plural('diag', $dc) }}
                </div>
            @endauth

            {{-- Theme Toggle Button --}}
            <button onclick="toggleTheme()" type="button" aria-label="Toggle theme" title="Switch theme" class="haven-nav-theme-btn">
                <svg id="topbar-theme-sun" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                    <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
                <svg id="topbar-theme-moon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>

            @auth
                {{-- User Dropdown Menu --}}
                <div class="haven-user-menu" id="haven-user-menu">
                    <button type="button" class="haven-user-btn" onclick="toggleUserDropdown()" aria-label="User menu">
                        <div class="haven-user-avatar">
                            {{ $authInitial }}
                        </div>
                        <span class="haven-user-btn-name">{{ $authName }}</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>

                    <div class="haven-user-dropdown" id="haven-user-dropdown">
                        <div class="haven-user-dropdown-header">
                            <div class="haven-user-dropdown-name">{{ $authName }}</div>
                            <div class="haven-user-dropdown-email">{{ $authEmail }}</div>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="haven-user-dropdown-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <span>Account Profile</span>
                        </a>
                        <a href="{{ route('vehicles.create') }}" class="haven-user-dropdown-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            <span>Add Vehicle</span>
                        </a>
                        <div style="height:1px;background:var(--h-border);margin:0.35rem 0;"></div>
                        <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="haven-user-dropdown-item danger">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                    <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                                </svg>
                                <span>Sign Out</span>
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <button type="button" onclick="openAuthModal(event)" class="haven-nav-signin-btn" style="text-decoration:none;font-size:0.8rem;padding:0.4rem 0.85rem;border-radius:var(--h-radius);background:none;border:none;cursor:pointer;color:inherit;font-family:inherit;">
                    Sign In
                </button>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="haven-nav-cta" style="text-decoration:none;">
                        <span>Get Started</span>
                    </a>
                @endif
            @endauth

            {{-- Mobile Menu Hamburger --}}
            <button class="haven-menu-toggle" onclick="toggleMobileDrawer()" aria-label="Toggle mobile navigation">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
        </div>

    </div>

    {{-- Mobile Navigation Drawer Backdrop --}}
    <div class="haven-mobile-backdrop" id="haven-mobile-backdrop" onclick="toggleMobileDrawer()"></div>

    {{-- Mobile Navigation Drawer --}}
    <div class="haven-mobile-drawer" id="haven-mobile-drawer">
        <div style="display:flex;align-items:center;justify-content:space-between;padding-bottom:0.75rem;margin-bottom:0.5rem;border-bottom:1px solid var(--h-border);">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span style="font-size:0.88rem;font-weight:700;color:var(--h-text);">Navigation</span>
                <span class="haven-brand-chip">v2.5</span>
            </div>
            <button type="button" onclick="toggleMobileDrawer()" aria-label="Close menu" style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:6px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;color:var(--h-text3);cursor:pointer;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        @auth
            <a href="{{ route('dashboard') }}" class="haven-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" onclick="toggleMobileDrawer()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('vehicles.index') }}" class="haven-nav-link {{ request()->routeIs('vehicles.*') ? 'active' : '' }}" onclick="toggleMobileDrawer()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                <span>My Vehicles</span>
            </a>
            <a href="{{ route('diagnostics.index') }}" class="haven-nav-link {{ request()->routeIs('diagnostics.index') ? 'active' : '' }}" onclick="toggleMobileDrawer()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span>Diagnostic History</span>
            </a>
            <a href="{{ route('diagnostics.create') }}" class="haven-nav-link {{ request()->routeIs('diagnostics.create') ? 'active' : '' }}" onclick="toggleMobileDrawer()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
                <span>Diagnose</span>
            </a>
        @else
            <a href="{{ url('/') }}" class="haven-nav-link" onclick="toggleMobileDrawer()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Home</span>
            </a>
        @endauth
        <a href="{{ route('learning.index') }}" class="haven-nav-link {{ request()->routeIs('learning.*') ? 'active' : '' }}" onclick="toggleMobileDrawer()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            <span>Learning Modules</span>
        </a>

        <div style="height:1px;background:var(--h-border);margin:0.35rem 0;"></div>

        {{-- Mobile Theme Switch & Profile --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.45rem 0.75rem;background:var(--h-surface2);border-radius:var(--h-radius);margin-bottom:0.25rem;">
            <span style="font-size:0.8rem;color:var(--h-text2);">Theme</span>
            <button onclick="toggleTheme()" type="button" class="h-btn-sm h-btn-sm-default" style="font-size:0.75rem;padding:0.25rem 0.65rem;">
                Toggle Light/Dark
            </button>
        </div>

        @auth
            <a href="{{ route('profile.edit') }}" class="haven-nav-link" onclick="toggleMobileDrawer()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>My Profile ({{ $authName }})</span>
            </a>

            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="haven-nav-link" style="width:100%;background:none;border:none;cursor:pointer;color:var(--h-red);text-align:left;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <span>Sign Out</span>
                </button>
            </form>
        @else
            <button type="button" class="haven-nav-link" style="width:100%;background:none;border:none;cursor:pointer;text-align:left;font-family:inherit;" onclick="toggleMobileDrawer(); openAuthModal(event);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                <span>Sign In</span>
            </button>
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="haven-nav-link" style="color:var(--h-teal);font-weight:600;" onclick="toggleMobileDrawer()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    <span>Create Account</span>
                </a>
            @endif
        @endauth
    </div>
</header>
