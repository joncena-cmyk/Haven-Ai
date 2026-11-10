<x-app-layout title="Settings & Privacy">
    @php
        // Determine initial active tab based on session flashes or validation error bags
        $initialTab = 'personal';
        if (
            $errors->updatePassword->isNotEmpty() ||
            $errors->has('phone') ||
            $errors->has('two_factor_enabled') ||
            $errors->has('two_factor_channel') ||
            session('two_factor_test_status') ||
            in_array(session('status'), ['password-updated', 'two-factor-updated'], true)
        ) {
            $initialTab = 'security';
        } elseif (session('key_regenerated') || session('thresholds_saved')) {
            $initialTab = 'hardware';
        } elseif ($errors->userDeletion->isNotEmpty()) {
            $initialTab = 'ownership';
        }

        // User initials for avatar
        $nameParts = preg_split('/\s+/', trim($user->name ?: 'User'));
        $initials = strtoupper(substr($nameParts[0] ?? 'U', 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
    @endphp

    <style>
        .haven-settings-shell {
            max-width: 1240px;
            margin: 0 auto;
            padding: 1.75rem 1.25rem 3rem;
        }
        .haven-settings-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1.75rem;
            align-items: start;
        }
        @media (max-width: 900px) {
            .haven-settings-grid {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }
        }
        .haven-settings-sidebar {
            background: var(--h-surface);
            border: 1px solid var(--h-border);
            border-radius: var(--h-radius-xl);
            overflow: hidden;
            position: sticky;
            top: 84px;
        }
        @media (max-width: 900px) {
            .haven-settings-sidebar {
                position: static;
            }
        }
        .haven-settings-nav-btn {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.78rem 1rem;
            background: transparent;
            border: none;
            border-left: 3px solid transparent;
            text-align: left;
            cursor: pointer;
            transition: all 0.16s ease;
            color: var(--h-text2);
            border-radius: 0 var(--h-radius) var(--h-radius) 0;
        }
        .haven-settings-nav-btn:hover {
            background: var(--h-surface2);
            color: var(--h-text);
        }
        .haven-settings-nav-btn.active {
            background: var(--h-teal-bg);
            border-left-color: var(--h-teal);
            color: var(--h-text);
        }
        .haven-settings-nav-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--h-surface2);
            border: 1px solid var(--h-border);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: var(--h-text2);
            transition: all 0.16s ease;
        }
        .haven-settings-nav-btn.active .haven-settings-nav-icon {
            background: rgba(2, 132, 199, 0.16);
            border-color: var(--h-teal-ring);
            color: var(--h-teal);
        }
        .haven-settings-card {
            background: var(--h-surface);
            border: 1px solid var(--h-border);
            border-radius: var(--h-radius-xl);
            padding: 1.75rem;
        }
        @media (max-width: 640px) {
            .haven-settings-card {
                padding: 1.25rem;
            }
        }
        .haven-settings-pane {
            display: none;
            animation: havenFadeIn 0.18s ease-out;
        }
        .haven-settings-pane.active {
            display: block;
        }
        @keyframes havenFadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <div class="haven-settings-shell">
        {{-- Top Page Title Bar --}}
        <div style="margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div>
                <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--h-teal);margin-bottom:0.2rem;">
                    Accounts Center
                </div>
                <h1 style="font-size:1.55rem;font-weight:800;color:var(--h-text);margin:0;letter-spacing:-0.02em;">
                    Settings &amp; Privacy
                </h1>
            </div>
            <a href="{{ route('dashboard') }}" class="haven-action-btn haven-action-btn-secondary" style="font-size:0.8rem;padding:0.5rem 0.95rem;text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to Dashboard
            </a>
        </div>

        @if (session('error'))
            <div style="background:var(--h-red-bg);border:1px solid var(--h-red);border-radius:var(--h-radius);padding:0.75rem 1rem;margin-bottom:1.25rem;font-size:0.82rem;color:var(--h-red);display:flex;align-items:center;gap:0.5rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="haven-settings-grid">
            {{-- ════════════════════════════════════════════════════════════════
                 LEFT SIDEBAR — Facebook / Meta Accounts Center Navigation
                 ════════════════════════════════════════════════════════════════ --}}
            <aside class="haven-settings-sidebar" aria-label="Settings Navigation">
                {{-- User Profile Mini-Banner --}}
                <div style="padding:1.25rem;border-bottom:1px solid var(--h-border);background:linear-gradient(180deg, rgba(2,132,199,0.08) 0%, transparent 100%);">
                    <div style="display:flex;align-items:center;gap:0.85rem;">
                        <div style="position:relative;width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg, #0284c7, #0369a1);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1rem;color:#fff;flex-shrink:0;box-shadow:0 4px 12px rgba(2,132,199,0.35);">
                            {{ $initials }}
                            <span style="position:absolute;bottom:1px;right:1px;width:11px;height:11px;border-radius:50%;background:var(--h-green);border:2px solid var(--h-surface);"></span>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:0.95rem;font-weight:700;color:var(--h-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                {{ $user->name }}
                            </div>
                            <div style="font-size:0.75rem;color:var(--h-text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                {{ $user->email }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Navigation Groups --}}
                <div style="padding:0.75rem 0.65rem 1rem 0;">
                    <div style="padding:0.35rem 1rem 0.4rem;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--h-text3);">
                        Account Settings
                    </div>

                    {{-- 1. Personal Details --}}
                    <button type="button" onclick="switchSettingsTab('personal')" id="nav-btn-personal" class="haven-settings-nav-btn {{ $initialTab === 'personal' ? 'active' : '' }}">
                        <div class="haven-settings-nav-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:0.86rem;font-weight:600;line-height:1.25;">Personal Details</div>
                            <div style="font-size:0.72rem;color:var(--h-text3);margin-top:0.1rem;">Name, email &amp; identity</div>
                        </div>
                    </button>

                    {{-- 2. Password & Security --}}
                    <button type="button" onclick="switchSettingsTab('security')" id="nav-btn-security" class="haven-settings-nav-btn {{ $initialTab === 'security' ? 'active' : '' }}">
                        <div class="haven-settings-nav-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:0.4rem;">
                                <span style="font-size:0.86rem;font-weight:600;line-height:1.25;">Password &amp; Security</span>
                                <span class="h-badge {{ $user->two_factor_enabled ? 'h-badge-teal' : 'h-badge-gray' }}" style="font-size:0.58rem;padding:0.1rem 0.4rem;">
                                    {{ $user->two_factor_enabled ? '2FA ON' : '2FA OFF' }}
                                </span>
                            </div>
                            <div style="font-size:0.72rem;color:var(--h-text3);margin-top:0.1rem;">Password, Email &amp; SMS 2FA</div>
                        </div>
                    </button>

                    <div style="margin:0.65rem 0 0.35rem;padding:0.45rem 1rem 0.35rem;border-top:1px solid var(--h-border);font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--h-text3);">
                        Connected Systems
                    </div>

                    {{-- 3. IoT Hardware & Fleet Keys --}}
                    <button type="button" onclick="switchSettingsTab('hardware')" id="nav-btn-hardware" class="haven-settings-nav-btn {{ $initialTab === 'hardware' ? 'active' : '' }}">
                        <div class="haven-settings-nav-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="3" height="10" rx="1"/><rect x="19" y="7" width="3" height="10" rx="1"/>
                                <rect x="7" y="2" width="10" height="3" rx="1"/><rect x="7" y="19" width="10" height="3" rx="1"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:0.4rem;">
                                <span style="font-size:0.86rem;font-weight:600;line-height:1.25;">IoT Hardware Keys</span>
                                <span class="h-badge h-badge-gray" style="font-size:0.6rem;padding:0.1rem 0.45rem;">{{ $vehicles->count() }}</span>
                            </div>
                            <div style="font-size:0.72rem;color:var(--h-text3);margin-top:0.1rem;">ESP32 tokens &amp; thresholds</div>
                        </div>
                    </button>

                    {{-- 4. Activity & Security Audit Log --}}
                    <button type="button" onclick="switchSettingsTab('audit')" id="nav-btn-audit" class="haven-settings-nav-btn {{ $initialTab === 'audit' ? 'active' : '' }}">
                        <div class="haven-settings-nav-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                            </svg>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:0.86rem;font-weight:600;line-height:1.25;">Activity &amp; Audit Log</div>
                            <div style="font-size:0.72rem;color:var(--h-text3);margin-top:0.1rem;">Sign-in &amp; security history</div>
                        </div>
                    </button>

                    <div style="margin:0.65rem 0 0.35rem;padding:0.45rem 1rem 0.35rem;border-top:1px solid var(--h-border);font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--h-text3);">
                        Account Control
                    </div>

                    {{-- 5. Account Ownership & Deletion --}}
                    <button type="button" onclick="switchSettingsTab('ownership')" id="nav-btn-ownership" class="haven-settings-nav-btn {{ $initialTab === 'ownership' ? 'active' : '' }}">
                        <div class="haven-settings-nav-icon" style="color:var(--h-red);">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:0.86rem;font-weight:600;line-height:1.25;">Account Ownership</div>
                            <div style="font-size:0.72rem;color:var(--h-text3);margin-top:0.1rem;">Permanent data removal</div>
                        </div>
                    </button>
                </div>
            </aside>

            {{-- ════════════════════════════════════════════════════════════════
                 RIGHT CONTENT AREA — Tabbed Settings Panes
                 ════════════════════════════════════════════════════════════════ --}}
            <div>
                {{-- TAB 1: PERSONAL DETAILS --}}
                <div id="pane-personal" class="haven-settings-pane {{ $initialTab === 'personal' ? 'active' : '' }}">
                    <div class="haven-settings-card">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                {{-- TAB 2: PASSWORD & SECURITY (Password + 2FA) --}}
                <div id="pane-security" class="haven-settings-pane {{ $initialTab === 'security' ? 'active' : '' }}">
                    {{-- Facebook Security Checkup Banner --}}
                    <div style="background:var(--h-surface);border:1px solid var(--h-border);border-radius:var(--h-radius-xl);padding:1.25rem 1.5rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
                        <div style="display:flex;align-items:center;gap:0.9rem;">
                            <div style="width:42px;height:42px;border-radius:12px;background:{{ $user->two_factor_enabled ? 'var(--h-green-bg)' : 'var(--h-amber-bg)' }};border:1px solid {{ $user->two_factor_enabled ? 'var(--h-green)' : 'var(--h-amber)' }};display:flex;align-items:center;justify-content:center;color:{{ $user->two_factor_enabled ? 'var(--h-green)' : 'var(--h-amber)' }};flex-shrink:0;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <div>
                                <div style="font-size:0.95rem;font-weight:700;color:var(--h-text);">
                                    Security Checkup: {{ $user->two_factor_enabled ? 'Protected with 2FA' : 'Two-Factor Authentication is Off' }}
                                </div>
                                <div style="font-size:0.78rem;color:var(--h-text3);margin-top:0.1rem;">
                                    {{ $user->two_factor_enabled
                                        ? 'Your account requires a 6-digit verification code via ' . strtoupper($user->two_factor_channel ?? 'EMAIL') . ' on sign-in.'
                                        : 'Enable 2FA below to protect your account with Brevo Email or Firebase SMS codes.' }}
                                </div>
                            </div>
                        </div>
                        <span class="h-badge {{ $user->two_factor_enabled ? 'h-badge-green' : 'h-badge-amber' }}" style="font-size:0.68rem;padding:0.25rem 0.65rem;">
                            {{ $user->two_factor_enabled ? 'Strong Protection' : 'Recommended Action' }}
                        </span>
                    </div>

                    {{-- Two-Factor Authentication Card --}}
                    <div class="haven-settings-card" style="margin-bottom:1.25rem;">
                        @include('profile.partials.two-factor-authentication-form')
                    </div>

                    {{-- Change Password Card --}}
                    <div class="haven-settings-card">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>

                {{-- TAB 3: IOT HARDWARE & DEVICE KEYS --}}
                <div id="pane-hardware" class="haven-settings-pane {{ $initialTab === 'hardware' ? 'active' : '' }}">
                    <div class="haven-settings-card">
                        {{-- Section Header --}}
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.6rem;margin-bottom:0.4rem;padding-bottom:1rem;border-bottom:1px solid var(--h-border);">
                            <div>
                                <div style="display:flex;align-items:center;gap:0.6rem;">
                                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                                        <rect x="2" y="7" width="3" height="10" rx="1"/><rect x="19" y="7" width="3" height="10" rx="1"/>
                                        <rect x="7" y="2" width="10" height="3" rx="1"/><rect x="7" y="19" width="10" height="3" rx="1"/>
                                        <circle cx="12" cy="12" r="3"/><line x1="5" y1="12" x2="9" y2="12"/><line x1="15" y1="12" x2="19" y2="12"/>
                                        <line x1="12" y1="5" x2="12" y2="9"/><line x1="12" y1="15" x2="12" y2="19"/>
                                    </svg>
                                    <h2 style="font-size:1.15rem;font-weight:700;color:var(--h-text);margin:0;">ESP32 IoT Hardware &amp; Device Keys</h2>
                                </div>
                                <p style="font-size:0.8rem;color:var(--h-text3);margin:0.3rem 0 0 0;">
                                    Each vehicle uses a unique 32-character hardware key to authenticate ESP32 wireless telemetry. Copy it into your firmware or regenerate if compromised.
                                </p>
                            </div>
                            <span class="h-badge h-badge-teal" style="font-size:0.65rem;">ISO/SAE 21434 §9</span>
                        </div>

                        {{-- Flash: key regenerated / thresholds saved --}}
                        @if(session('key_regenerated'))
                            <div style="background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);border-radius:var(--h-radius);padding:0.7rem 1rem;margin:1rem 0;font-size:0.8rem;color:var(--h-teal);display:flex;align-items:center;gap:0.5rem;">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                {{ session('key_regenerated') }}
                            </div>
                        @endif
                        @if(session('thresholds_saved'))
                            <div style="background:var(--h-green-bg);border:1px solid var(--h-green);border-radius:var(--h-radius);padding:0.7rem 1rem;margin:1rem 0;font-size:0.8rem;color:var(--h-green);display:flex;align-items:center;gap:0.5rem;">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                {{ session('thresholds_saved') }}
                            </div>
                        @endif

                        @if($vehicles->isEmpty())
                            <div style="text-align:center;padding:2.5rem 1rem;background:var(--h-surface2);border:1px dashed var(--h-border);border-radius:var(--h-radius-lg);margin-top:1rem;">
                                <p style="font-size:0.85rem;color:var(--h-text2);margin:0 0 0.75rem 0;">No vehicles registered in your fleet yet.</p>
                                <a href="{{ route('vehicles.create') }}" class="haven-btn-primary" style="display:inline-flex;align-items:center;gap:0.4rem;font-size:0.8rem;padding:0.55rem 1.1rem;text-decoration:none;">
                                    + Register a Vehicle
                                </a>
                            </div>
                        @else
                            <div style="display:flex;flex-direction:column;gap:1.15rem;margin-top:1.15rem;">
                                @foreach($vehicles as $veh)
                                    @php
                                        $thresholds = $veh->alert_thresholds ?? [];
                                        $isHighlighted = session('key_vehicle_id') == $veh->id;
                                    @endphp
                                    <div data-vehicle-id="{{ $veh->id }}"
                                         style="background:var(--h-surface2);border:1px solid {{ $isHighlighted ? 'rgba(2,132,199,0.5)' : 'var(--h-border)' }};border-radius:var(--h-radius-lg);overflow:hidden;">

                                        {{-- Vehicle Header Bar --}}
                                        <div style="padding:0.8rem 1.1rem;border-bottom:1px solid var(--h-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
                                            <div style="display:flex;align-items:center;gap:0.6rem;">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v9a2 2 0 0 1-2 2h-3"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>
                                                </svg>
                                                <span style="font-size:0.88rem;font-weight:700;color:var(--h-text);">{{ $veh->display_name }}</span>
                                                <span style="font-size:0.72rem;color:var(--h-text3);">ID #{{ $veh->id }}</span>
                                                @if($veh->plate_number)
                                                    <span class="h-badge h-badge-gray" style="font-size:0.62rem;">{{ $veh->plate_number }}</span>
                                                @endif
                                            </div>
                                            {{-- Live ESP32 Status dot --}}
                                            @php
                                                $vehTelemetry = \Illuminate\Support\Facades\Cache::get('telemetry:live:' . $veh->id)
                                                    ?? ((($latestTelemetry['vehicle_id'] ?? null) == $veh->id) ? $latestTelemetry : null);
                                                $vehTs = $vehTelemetry['timestamp'] ?? $vehTelemetry['received_at'] ?? null;
                                                $isOnline = !empty($vehTelemetry['online'])
                                                    && !empty($vehTs)
                                                    && abs(\Illuminate\Support\Carbon::parse($vehTs)->diffInSeconds(now())) <= 20;
                                                $rawKey = (string) ($veh->device_key ?? '');
                                                $maskedKey = strlen($rawKey) > 12
                                                    ? substr($rawKey, 0, 12) . str_repeat('•', 16)
                                                    : ($rawKey ?: '(none)');
                                            @endphp
                                            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.15rem;">
                                                <div style="display:flex;align-items:center;gap:0.4rem;">
                                                    <span class="vehicle-status-dot" style="width:8px;height:8px;border-radius:50%;background:{{ $isOnline ? 'var(--h-green)' : 'var(--h-text3)' }};box-shadow:{{ $isOnline ? '0 0 6px var(--h-green)' : 'none' }};display:inline-block;flex-shrink:0;transition:background 0.3s;"></span>
                                                    <span class="vehicle-status-label" style="font-size:0.72rem;color:{{ $isOnline ? 'var(--h-green)' : 'var(--h-text3)' }};font-family:monospace;">{{ $isOnline ? 'Live (Streaming)' : ($vehTelemetry['last_seen_human'] ?? 'Standby / Offline') }}</span>
                                                </div>
                                                @if($isOnline && !empty($vehTelemetry['device_id']))
                                                    <div style="font-size:0.65rem;color:var(--h-text3);display:flex;align-items:center;gap:0.3rem;">
                                                        <span style="color:var(--h-teal);font-family:monospace;">{{ $vehTelemetry['device_id'] }}</span>
                                                        <span>•</span>
                                                        <span>5 Sensors Active</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <div style="padding:1rem 1.1rem;display:flex;flex-direction:column;gap:1rem;">
                                            {{-- Device Key Row --}}
                                            <div>
                                                <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.4rem;font-weight:600;">ESP32 Device Key</div>
                                                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                                                    <code id="dkey-{{ $veh->id }}"
                                                          data-raw="{{ $rawKey }}"
                                                          data-masked="{{ $maskedKey }}"
                                                          data-visible="false"
                                                          style="background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);color:var(--h-teal);padding:0.35rem 0.75rem;border-radius:6px;font-size:0.76rem;font-family:monospace;letter-spacing:0.03em;word-break:break-all;">{{ $maskedKey }}</code>
                                                    <button type="button"
                                                            onclick="havenToggleKey('{{ $veh->id }}')"
                                                            id="toggle-btn-{{ $veh->id }}"
                                                            class="haven-action-btn haven-action-btn-secondary"
                                                            style="font-size:0.74rem;padding:0.32rem 0.7rem;cursor:pointer;display:flex;align-items:center;gap:0.3rem;">
                                                        Show
                                                    </button>
                                                    <button type="button"
                                                            onclick="havenCopyKey('{{ $veh->id }}')"
                                                            id="copy-btn-{{ $veh->id }}"
                                                            class="haven-action-btn haven-action-btn-secondary"
                                                            style="font-size:0.74rem;padding:0.32rem 0.7rem;cursor:pointer;display:flex;align-items:center;gap:0.3rem;">
                                                        <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                        Copy Key
                                                    </button>
                                                    <button type="button"
                                                            onclick="havenConfirmRegen({{ $veh->id }},'{{ addslashes($veh->display_name) }}')"
                                                            class="haven-action-btn haven-action-btn-danger"
                                                            style="font-size:0.74rem;padding:0.32rem 0.7rem;cursor:pointer;display:flex;align-items:center;gap:0.3rem;">
                                                        <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.5 15a9 9 0 1 1-2.7-6.4L23 10"/></svg>
                                                        Regenerate
                                                    </button>
                                                    <form id="regen-form-{{ $veh->id }}" method="POST" action="{{ route('vehicles.regenerate-key', $veh) }}" style="display:none;">
                                                        @csrf
                                                    </form>
                                                </div>
                                                <p style="font-size:0.7rem;color:var(--h-text3);margin-top:0.35rem;">
                                                    ⚠ Regenerating will immediately disconnect your ESP32 until you flash the new key into its firmware.
                                                </p>
                                            </div>

                                            {{-- Sensor Alert Thresholds --}}
                                            <div style="padding-top:0.75rem;border-top:1px solid var(--h-border);">
                                                <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.55rem;font-weight:600;display:flex;align-items:center;gap:0.4rem;">
                                                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                                    Sensor Alert Thresholds
                                                </div>
                                                <form method="POST" action="{{ route('vehicles.thresholds', $veh) }}">
                                                    @csrf
                                                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.65rem;margin-bottom:0.75rem;">
                                                        <label style="display:flex;flex-direction:column;gap:0.25rem;">
                                                            <span style="font-size:0.7rem;color:var(--h-text3);">Max Temp (°C)</span>
                                                            <input type="number" name="temp_max_c" min="50" max="150" step="1"
                                                                   value="{{ $thresholds['temp_max_c'] ?? '' }}"
                                                                   placeholder="e.g. 95"
                                                                   class="haven-input" style="padding:0.38rem 0.6rem;font-size:0.78rem;">
                                                        </label>
                                                        <label style="display:flex;flex-direction:column;gap:0.25rem;">
                                                            <span style="font-size:0.7rem;color:var(--h-text3);">Max RPM</span>
                                                            <input type="number" name="rpm_max" min="500" max="10000" step="100"
                                                                   value="{{ $thresholds['rpm_max'] ?? '' }}"
                                                                   placeholder="e.g. 5000"
                                                                   class="haven-input" style="padding:0.38rem 0.6rem;font-size:0.78rem;">
                                                        </label>
                                                        <label style="display:flex;flex-direction:column;gap:0.25rem;">
                                                            <span style="font-size:0.7rem;color:var(--h-text3);">Min Voltage (V)</span>
                                                            <input type="number" name="voltage_min_v" min="8" max="16" step="0.1"
                                                                   value="{{ $thresholds['voltage_min_v'] ?? '' }}"
                                                                   placeholder="e.g. 11.5"
                                                                   class="haven-input" style="padding:0.38rem 0.6rem;font-size:0.78rem;">
                                                        </label>
                                                        <label style="display:flex;flex-direction:column;gap:0.25rem;">
                                                            <span style="font-size:0.7rem;color:var(--h-text3);">Max Vibration (g)</span>
                                                            <input type="number" name="vibration_max" min="0.1" max="50" step="0.1"
                                                                   value="{{ $thresholds['vibration_max'] ?? '' }}"
                                                                   placeholder="e.g. 1.5"
                                                                   class="haven-input" style="padding:0.38rem 0.6rem;font-size:0.78rem;">
                                                        </label>
                                                    </div>
                                                    <button type="submit" class="haven-action-btn haven-action-btn-secondary" style="font-size:0.75rem;padding:0.35rem 0.85rem;cursor:pointer;display:inline-flex;align-items:center;gap:0.35rem;">
                                                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                                        Save Thresholds
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- TAB 4: ACTIVITY & SECURITY AUDIT LOG --}}
                <div id="pane-audit" class="haven-settings-pane {{ $initialTab === 'audit' ? 'active' : '' }}">
                    <div class="haven-settings-card">
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid var(--h-border);">
                            <div>
                                <div style="display:flex;align-items:center;gap:0.5rem;">
                                    <h2 style="font-size:1.15rem;font-weight:700;color:var(--h-text);margin:0;">Security &amp; Activity Audit Trail</h2>
                                    <span class="h-badge h-badge-gray" style="font-size:0.62rem;">ISO/IEC 27001 A.8.15</span>
                                </div>
                                <p style="font-size:0.8rem;color:var(--h-text3);margin:0.25rem 0 0 0;">
                                    Immutable forensic log of recent authentication, vehicle management, and IoT sensor transmissions.
                                </p>
                            </div>
                        </div>

                        @if($auditLogs->isEmpty())
                            <p style="font-size:0.82rem;color:var(--h-text3);padding:1.5rem 0;text-align:center;">No audit events recorded yet.</p>
                        @else
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;font-size:0.8rem;text-align:left;">
                                    <thead>
                                        <tr style="border-bottom:1px solid var(--h-border);color:var(--h-text3);font-size:0.7rem;text-transform:uppercase;letter-spacing:0.05em;">
                                            <th style="padding:0.65rem 0.75rem;">Event</th>
                                            <th style="padding:0.65rem 0.75rem;">Status</th>
                                            <th style="padding:0.65rem 0.75rem;">IP Address</th>
                                            <th style="padding:0.65rem 0.75rem;">Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($auditLogs as $log)
                                            <tr style="border-bottom:1px solid var(--h-border);">
                                                <td style="padding:0.7rem 0.75rem;font-weight:600;color:var(--h-text);">
                                                    <code style="color:var(--h-teal);font-size:0.76rem;">{{ $log->event }}</code>
                                                </td>
                                                <td style="padding:0.7rem 0.75rem;">
                                                    @if($log->status === 'success')
                                                        <span class="h-badge h-badge-green" style="font-size:0.62rem;padding:0.15rem 0.5rem;">Success</span>
                                                    @elseif($log->status === 'failure')
                                                        <span class="h-badge h-badge-red" style="font-size:0.62rem;padding:0.15rem 0.5rem;">Failed</span>
                                                    @else
                                                        <span class="h-badge h-badge-amber" style="font-size:0.62rem;padding:0.15rem 0.5rem;">{{ ucfirst($log->status) }}</span>
                                                    @endif
                                                </td>
                                                <td style="padding:0.7rem 0.75rem;color:var(--h-text3);font-family:monospace;font-size:0.74rem;">
                                                    {{ $log->ip_address ?? '—' }}
                                                </td>
                                                <td style="padding:0.7rem 0.75rem;color:var(--h-text3);white-space:nowrap;">
                                                    {{ $log->created_at->diffForHumans() }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- TAB 5: ACCOUNT OWNERSHIP & DELETION --}}
                <div id="pane-ownership" class="haven-settings-pane {{ $initialTab === 'ownership' ? 'active' : '' }}">
                    <div class="haven-settings-card">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ════ Regen Confirm Modal ════ --}}
    <div id="haven-regen-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.7);backdrop-filter:blur(2px);align-items:center;justify-content:center;">
        <div style="background:var(--h-surface2);border:1px solid rgba(239,68,68,0.4);border-radius:var(--h-radius-xl);padding:1.5rem 2rem;max-width:420px;width:90%;box-shadow:0 8px 40px rgba(0,0,0,0.6);">
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                <svg width="18" height="18" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span style="font-size:0.9rem;font-weight:700;color:var(--h-text);">Regenerate Device Key?</span>
            </div>
            <p id="haven-regen-msg" style="font-size:0.8rem;color:var(--h-text3);line-height:1.5;margin-bottom:1rem;"></p>
            <div style="background:var(--h-red-bg);border:1px solid var(--h-red);border-radius:var(--h-radius-sm);padding:0.5rem 0.75rem;font-size:0.74rem;color:var(--h-red);margin-bottom:1.1rem;">
                ⚠ Your ESP32 will immediately lose connection. You must flash the new key into the firmware before it can reconnect.
            </div>
            <div style="display:flex;gap:0.6rem;justify-content:flex-end;">
                <button onclick="havenCloseModal()" class="haven-action-btn haven-action-btn-secondary" style="font-size:0.78rem;cursor:pointer;">Cancel</button>
                <button id="haven-regen-confirm-btn" class="haven-action-btn haven-action-btn-danger" style="font-size:0.78rem;cursor:pointer;">Yes, Regenerate Key</button>
            </div>
        </div>
    </div>

    <script>
    // ── Facebook-Style Tab Switching ─────────────────────────────────────────
    function switchSettingsTab(tabName) {
        const tabs = ['personal', 'security', 'hardware', 'audit', 'ownership'];
        tabs.forEach(t => {
            const pane = document.getElementById('pane-' + t);
            const btn  = document.getElementById('nav-btn-' + t);
            if (pane) pane.classList.toggle('active', t === tabName);
            if (btn)  btn.classList.toggle('active', t === tabName);
        });
        if (history.replaceState) {
            history.replaceState(null, null, '#' + tabName);
        }
    }

    // Check URL hash on load (e.g. #security-2fa, #security, #hardware, #audit, #ownership)
    window.addEventListener('DOMContentLoaded', function() {
        const hash = (window.location.hash || '').replace('#', '');
        if (hash === 'security' || hash === 'security-2fa') {
            switchSettingsTab('security');
        } else if (['personal', 'hardware', 'audit', 'ownership'].includes(hash)) {
            switchSettingsTab(hash);
        }
    });

    // ── Toggle device key visibility ─────────────────────────────────────────
    function havenToggleKey(vid) {
        const el  = document.getElementById('dkey-' + vid);
        const btn = document.getElementById('toggle-btn-' + vid);
        if (!el || !btn) return;
        const vis = el.dataset.visible === 'true';
        el.textContent    = vis ? el.dataset.masked : el.dataset.raw;
        el.dataset.visible = vis ? 'false' : 'true';
        btn.textContent   = vis ? 'Show' : 'Hide';
    }

    // ── Copy device key to clipboard ──────────────────────────────────────────
    function havenCopyKey(vid) {
        const el  = document.getElementById('dkey-' + vid);
        const key = el ? el.dataset.raw : '';
        if (!key) return;
        navigator.clipboard.writeText(key).then(() => {
            const btn = document.getElementById('copy-btn-' + vid);
            if (btn) {
                btn.innerHTML = '<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
                setTimeout(() => {
                    btn.innerHTML = '<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy Key';
                }, 2000);
            }
        });
    }

    // ── Regenerate key confirmation modal ────────────────────────────────────
    function havenConfirmRegen(vehicleId, vehicleName) {
        const modal = document.getElementById('haven-regen-modal');
        const msg   = document.getElementById('haven-regen-msg');
        const btn   = document.getElementById('haven-regen-confirm-btn');
        if (modal && modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }
        msg.textContent = 'You are about to regenerate the ESP32 device key for "' + vehicleName + '". This action cannot be undone.';
        btn.onclick = () => {
            document.getElementById('regen-form-' + vehicleId).submit();
        };
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function havenCloseModal() {
        document.getElementById('haven-regen-modal').style.display = 'none';
        document.body.style.overflow = '';
    }
    document.getElementById('haven-regen-modal').addEventListener('click', function(e) {
        if (e.target === this) havenCloseModal();
    });

    // ── Live ESP32 status per vehicle card (one-shot on page load) ───────────
    document.querySelectorAll('[data-vehicle-id]').forEach(card => {
        const vid   = card.dataset.vehicleId;
        const dot   = card.querySelector('.vehicle-status-dot');
        const label = card.querySelector('.vehicle-status-label');
        fetch('/api/v1/telemetry/latest?vehicle_id=' + encodeURIComponent(vid), {
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(r => r.json())
            .then(data => {
                if (data.online) {
                    dot.style.background  = 'var(--h-green)';
                    dot.style.boxShadow   = '0 0 6px var(--h-green)';
                    label.style.color     = 'var(--h-green)';
                    label.textContent     = 'Live (Streaming)';
                } else {
                    dot.style.background  = 'var(--h-text3)';
                    dot.style.boxShadow   = 'none';
                    label.style.color     = 'var(--h-text3)';
                    label.textContent     = data.last_seen_human ?? 'Standby / Offline';
                }
            })
            .catch(() => {
                label.textContent = 'Unable to check';
            });
    });
    </script>
</x-app-layout>
