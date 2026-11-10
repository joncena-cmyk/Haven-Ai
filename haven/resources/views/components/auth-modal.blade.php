{{-- Haven AI — Modal Auth Popup Component with Smooth Entrance & Exit Animations --}}
<style>
    .haven-modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 99999;
        background: rgba(5, 7, 10, 0.78);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
        padding: 1rem;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.28s;
    }

    .haven-modal-backdrop.is-open {
        display: flex;
        opacity: 1;
        visibility: visible;
    }

    .haven-modal-card {
        position: relative;
        width: 100%;
        max-width: 440px;
        background: var(--h-surface, #0f172a);
        border: 1px solid var(--h-border, rgba(255, 255, 255, 0.12));
        border-radius: 24px !important;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
        padding: 2.25rem;
        box-sizing: border-box;
        transform: scale(0.92) translateY(24px);
        opacity: 0;
        transition: transform 0.32s cubic-bezier(0.175, 0.885, 0.32, 1.2), opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1);
        will-change: transform, opacity;
        overflow: hidden;
    }

    .haven-modal-card input[type="text"],
    .haven-modal-card input[type="email"],
    .haven-modal-card input[type="password"],
    .haven-modal-card button,
    .haven-modal-card .haven-btn-google,
    .haven-modal-card .haven-btn-solid {
        border-radius: 12px !important;
    }

    .haven-modal-card .haven-modal-badge-icon {
        border-radius: 8px !important;
    }

    /* Custom Accessible "Remember me on this device" Checkbox */
    .haven-remember-wrap {
        display: inline-flex;
        align-items: center;
        gap: 0.65rem;
        margin-bottom: 1.25rem;
        cursor: pointer;
        user-select: none;
        -webkit-user-select: none;
        position: relative;
    }

    .haven-remember-input {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        margin: 0;
        pointer-events: none;
    }

    .haven-remember-box {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
        background: var(--h-surface-2, rgba(255, 255, 255, 0.06));
        border: 1.5px solid var(--h-border, rgba(255, 255, 255, 0.22));
        border-radius: 5px !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        box-sizing: border-box;
    }

    [data-theme="light"] .haven-remember-box {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .haven-remember-check {
        opacity: 0;
        transform: scale(0.6);
        transition: opacity 0.16s ease, transform 0.16s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        color: #ffffff;
    }

    /* Checked state */
    .haven-remember-input:checked + .haven-remember-box {
        background: #0284c7 !important;
        border-color: #0284c7 !important;
        box-shadow: 0 0 0 1px rgba(56, 189, 248, 0.4), 0 2px 8px rgba(2, 132, 199, 0.35);
    }

    .haven-remember-input:checked + .haven-remember-box .haven-remember-check {
        opacity: 1;
        transform: scale(1);
    }

    /* Focus & Hover states */
    .haven-remember-wrap:hover .haven-remember-box {
        border-color: #0284c7;
    }

    .haven-remember-input:focus-visible + .haven-remember-box {
        outline: none;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.35);
        border-color: #38bdf8;
    }

    .haven-remember-text {
        font-size: 0.82rem;
        color: var(--h-muted, #94a3b8);
        transition: color 0.15s ease;
        line-height: 1.2;
    }

    [data-theme="light"] .haven-remember-text {
        color: #64748b;
    }

    .haven-remember-wrap:hover .haven-remember-text {
        color: var(--h-text, #ffffff);
    }

    [data-theme="light"] .haven-remember-wrap:hover .haven-remember-text {
        color: #0f172a;
    }

    @keyframes havenSpin {
        to { transform: rotate(360deg); }
    }

    .haven-modal-backdrop.is-open .haven-modal-card {
        transform: scale(1) translateY(0);
        opacity: 1;
    }

    .haven-modal-backdrop.is-closing {
        opacity: 0;
    }

    .haven-modal-backdrop.is-closing .haven-modal-card {
        transform: scale(0.93) translateY(18px);
        opacity: 0;
        transition: transform 0.22s cubic-bezier(0.4, 0, 1, 1), opacity 0.2s ease;
    }
</style>

@php
    $pending2faUserId = session('auth.two_factor_user_id');
    $pending2faUser = $pending2faUserId ? \App\Models\User::find($pending2faUserId) : null;
    $pending2faChannel = $pending2faUser
        ? session('auth.two_factor_active_channel', $pending2faUser->two_factor_channel ?? 'email')
        : 'email';
    $pending2faTarget = '';
    if ($pending2faUser) {
        $pending2faTarget = ($pending2faChannel === 'sms' && ! empty($pending2faUser->phone))
            ? substr($pending2faUser->phone, 0, 4).'****'.substr($pending2faUser->phone, -3)
            : substr($pending2faUser->email, 0, 2).'****@'.(explode('@', $pending2faUser->email)[1] ?? '***');
    }
    $modalFirebaseConfig = [
        'apiKey' => config('services.firebase.api_key'),
        'authDomain' => config('services.firebase.auth_domain'),
        'projectId' => config('services.firebase.project_id'),
        'appId' => config('services.firebase.app_id'),
    ];
    $shouldAutoOpen2fa = (bool) ($pending2faUser && (request()->routeIs('two-factor.challenge') || request()->boolean('two_factor') || ! empty($openTwoFactorModal)));
@endphp

<div id="haven-auth-modal" class="haven-modal-backdrop" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="haven-modal-card">
        
        {{-- Close Button --}}
        <button type="button" onclick="closeAuthModal()" aria-label="Close modal" style="position:absolute;top:1rem;right:1rem;background:transparent;border:none;color:var(--h-muted);cursor:pointer;padding:0.4rem;display:flex;align-items:center;justify-content:center;transition:color 0.15s;z-index:2;" onmouseover="this.style.color='var(--h-text)'" onmouseout="this.style.color='var(--h-muted)'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        {{-- ═══════════════════════════════════════════════════════════
             STEP 1: SIGN IN VIEW
             ═══════════════════════════════════════════════════════════ --}}
        <div id="modal-step-login" style="{{ $shouldAutoOpen2fa ? 'display:none;' : 'display:block;' }}">
            {{-- Header --}}
            <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.75rem;">
                <div class="haven-modal-badge-icon" style="width:26px;height:26px;display:flex;align-items:center;justify-content:center;background:rgba(2,132,199,0.12);border:1px solid rgba(2,132,199,0.4);">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--h-blue)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <span style="font-weight:900;font-size:0.95rem;letter-spacing:0.14em;color:var(--h-text);text-transform:uppercase;">
                    HAVEN <span style="color:var(--h-blue-glow);">AI</span>
                </span>
            </div>

            <h2 style="font-size:1.35rem;font-weight:800;color:var(--h-text);margin:0 0 0.3rem;letter-spacing:-0.02em;">Welcome Back</h2>
            <p style="font-size:0.82rem;color:var(--h-muted);margin:0 0 1.5rem;line-height:1.45;">Sign in to your account to monitor vehicle telemetry &amp; diagnostics.</p>

            @if(\App\Http\Controllers\Auth\GoogleAuthController::isConfigured())
            {{-- Google OAuth Button --}}
            <a href="{{ route('auth.google') }}" class="haven-btn-google" style="width:100%;box-sizing:border-box;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:0.75rem;padding:0.75rem 1rem;background:var(--h-surface-2, #ffffff);border:1px solid var(--h-border);color:var(--h-text);font-size:0.85rem;font-weight:600;cursor:pointer;transition:all 0.15s;margin-bottom:1.25rem;">
                <svg width="18" height="18" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                <span>Continue with Google</span>
            </a>

            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;">
                <div style="flex:1;height:1px;background:var(--h-border);"></div>
                <span style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.08em;color:var(--h-muted);">or email</span>
                <div style="flex:1;height:1px;background:var(--h-border);"></div>
            </div>
            @endif

            {{-- Standard Email & Password Form --}}
            <form method="POST" action="{{ route('login') }}" id="modal-login-form">
                @csrf

                {{-- Dynamic AJAX Error Alert --}}
                <div id="modal-error-alert" style="display:none;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.35);color:#f87171;border-radius:10px;padding:0.65rem 0.85rem;font-size:0.8rem;margin-bottom:1rem;line-height:1.4;align-items:center;gap:0.5rem;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span id="modal-error-text">These credentials do not match our records.</span>
                </div>

                @if (session('status') && ! $shouldAutoOpen2fa)
                    <div id="modal-status-alert" style="background:rgba(20,184,166,0.12);border:1px solid rgba(20,184,166,0.35);color:#2dd4bf;border-radius:10px;padding:0.65rem 0.85rem;font-size:0.8rem;margin-bottom:1rem;line-height:1.4;display:flex;align-items:center;gap:0.5rem;">
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any() && ! $shouldAutoOpen2fa)
                    <div id="modal-server-error" style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.35);color:#f87171;border-radius:10px;padding:0.65rem 0.85rem;font-size:0.8rem;margin-bottom:1rem;line-height:1.4;display:flex;align-items:center;gap:0.5rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <div style="margin-bottom:1rem;">
                    <label class="haven-label" for="modal_email" style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--h-muted);margin-bottom:0.35rem;display:block;">Email address</label>
                    <input id="modal_email" type="email" name="email" required autocomplete="username" placeholder="you@example.com" class="haven-input" style="width:100%;padding:0.65rem 0.85rem;background:var(--h-surface-2);border:1px solid var(--h-border);color:var(--h-text);font-size:0.875rem;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:1rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.35rem;">
                        <label class="haven-label" for="modal_password" style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--h-muted);margin:0;">Password</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" style="font-size:0.75rem;color:var(--h-blue-glow);text-decoration:none;">Forgot?</a>
                        @endif
                    </div>
                    <input id="modal_password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••" class="haven-input" style="width:100%;padding:0.65rem 0.85rem;background:var(--h-surface-2);border:1px solid var(--h-border);color:var(--h-text);font-size:0.875rem;box-sizing:border-box;">
                </div>

                {{-- Remember me on this device --}}
                <label class="haven-remember-wrap" for="modal_remember">
                    <input id="modal_remember" type="checkbox" name="remember" class="haven-remember-input" value="1">
                    <span class="haven-remember-box" aria-hidden="true">
                        <svg class="haven-remember-check" width="11" height="9" viewBox="0 0 11 9" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="1.5 4.5 4 7 9.5 1.5"/>
                        </svg>
                    </span>
                    <span class="haven-remember-text">Remember me on this device</span>
                </label>

                <button type="submit" id="modal-login-btn" class="haven-btn-solid" style="width:100%;padding:0.75rem 1rem;font-size:0.8rem;font-weight:700;letter-spacing:0.08em;background:var(--h-blue);color:#ffffff;border:1px solid var(--h-blue);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.5rem;">
                    <span>Sign In to HAVEN</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </form>

            <p style="text-align:center;margin:1.25rem 0 0;font-size:0.8rem;color:var(--h-muted);">
                Don't have an account?
                <a href="{{ route('register') }}" style="color:var(--h-blue-glow);font-weight:600;text-decoration:none;">Create one</a>
            </p>

            <p style="text-align:center;margin:0.85rem 0 0;font-size:0.72rem;color:var(--h-muted);line-height:1.45;">
                By signing in, you agree to our
                <a href="{{ route('terms') }}" target="_blank" style="color:var(--h-text2);text-decoration:underline;">Terms &amp; Conditions</a>
                and
                <a href="{{ route('privacy') }}" target="_blank" style="color:var(--h-text2);text-decoration:underline;">Privacy Policy</a>.
            </p>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             STEP 2: TWO-FACTOR AUTHENTICATION POP-UP VIEW
             ═══════════════════════════════════════════════════════════ --}}
        <div id="modal-step-2fa" style="{{ $shouldAutoOpen2fa ? 'display:block;' : 'display:none;' }}">
            {{-- Back to Sign In button --}}
            <div style="margin-bottom:1rem;">
                <button type="button" onclick="switchModalToLogin()" style="background:none;border:none;padding:0;cursor:pointer;display:inline-flex;align-items:center;gap:0.4rem;font-size:0.78rem;color:var(--h-muted);transition:color 0.15s;" onmouseover="this.style.color='var(--h-blue-glow)'" onmouseout="this.style.color='var(--h-muted)'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    <span>Back to sign in</span>
                </button>
            </div>

            {{-- Lock Icon & Heading --}}
            <div style="text-align:center;margin-bottom:1.25rem;">
                <div style="display:inline-flex;align-items:center;justify-content:center;width:52px;height:52px;border-radius:50%;background:rgba(2,132,199,0.12);border:1px solid rgba(56,189,248,0.3);margin-bottom:0.85rem;color:var(--h-blue-glow);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <h2 style="font-size:1.3rem;font-weight:800;color:var(--h-text);margin:0 0 0.35rem;letter-spacing:-0.02em;">Two-Factor Authentication</h2>
                <p style="font-size:0.82rem;color:var(--h-muted);margin:0 0 0.2rem;">
                    Security verification code via
                    <strong id="modal-2fa-channel-label" style="color:var(--h-blue-glow);">{{ strtoupper($pending2faChannel) }}</strong> to:
                </p>
                <span id="modal-2fa-target" style="font-weight:700;color:var(--h-blue-glow);font-size:0.92rem;">
                    {{ $pending2faTarget }}
                </span>
            </div>

            {{-- Status / Error Banner inside Pop-Up --}}
            <div id="modal-2fa-status" style="{{ (session('status') && $shouldAutoOpen2fa) || ($errors->has('code') && $shouldAutoOpen2fa) ? 'display:block;' : 'display:none;' }}background:var(--h-surface-2);border:1px solid {{ $errors->has('code') && $shouldAutoOpen2fa ? 'rgba(239,68,68,0.45)' : 'var(--h-border)' }};border-radius:12px;padding:0.65rem 0.9rem;margin-bottom:1.15rem;font-size:0.78rem;color:{{ $errors->has('code') && $shouldAutoOpen2fa ? '#f87171' : 'var(--h-muted)' }};text-align:center;line-height:1.45;">
                <span id="modal-2fa-status-text">
                    @if ($errors->has('code') && $shouldAutoOpen2fa)
                        {{ $errors->first('code') }}
                    @elseif (session('status') && $shouldAutoOpen2fa)
                        {{ session('status') }}
                    @else
                        Enter the 6-digit verification code sent to your device.
                    @endif
                </span>
            </div>

            {{-- 6-Digit Verification Form --}}
            <form method="POST" action="{{ route('two-factor.challenge.store') }}" id="modal-2fa-form">
                @csrf
                <input type="hidden" name="firebase_token" id="modal_firebase_token">

                <div style="margin-bottom:1.15rem;">
                    <label for="modal_2fa_code" style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--h-muted);margin-bottom:0.4rem;display:block;text-align:center;">
                        Security Verification Code
                    </label>
                    <input
                        id="modal_2fa_code"
                        type="text"
                        name="code"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        inputmode="numeric"
                        required
                        autocomplete="one-time-code"
                        placeholder="000000"
                        style="width:100%;padding:0.7rem 0.85rem;background:var(--h-surface-2);border:1px solid var(--h-border);color:var(--h-text);text-align:center;font-size:1.65rem;letter-spacing:0.48rem;font-weight:700;font-family:var(--h-mono, monospace);box-sizing:border-box;"
                    >
                    <p id="modal-2fa-error" style="display:none;color:#f87171;font-size:0.78rem;text-align:center;margin:0.4rem 0 0;"></p>
                    <p style="text-align:center;font-size:0.72rem;color:var(--h-muted);margin:0.4rem 0 0;">
                        Code is valid for 10 minutes.
                    </p>
                </div>

                {{-- reCAPTCHA container for Firebase Phone Auth --}}
                <div id="modal-recaptcha-wrapper" style="display:flex;justify-content:center;margin-bottom:0.9rem;">
                    <div id="modal-recaptcha-container"></div>
                </div>

                <button type="submit" id="modal-2fa-submit-btn" class="haven-btn-solid" style="width:100%;padding:0.75rem 1rem;font-size:0.8rem;font-weight:700;letter-spacing:0.08em;background:var(--h-blue);color:#ffffff;border:1px solid var(--h-blue);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.5rem;margin-bottom:1.1rem;">
                    <span>Authenticate &amp; Continue</span>
                </button>
            </form>

            {{-- Resend & Channel Switcher Actions --}}
            <div style="text-align:center;padding-top:0.9rem;border-top:1px solid var(--h-border);display:flex;flex-direction:column;gap:0.55rem;">
                <div>
                    <p id="modal-2fa-resend-prompt" style="font-size:0.76rem;color:var(--h-muted);margin:0 0 0.2rem;">
                        {{ $pending2faChannel === 'sms' ? "Didn't receive the SMS?" : "Didn't get the email?" }}
                    </p>
                    <button type="button" id="modal-2fa-resend-btn" onclick="handleModal2faResend()" style="background:none;border:none;cursor:pointer;font-size:0.82rem;font-weight:700;color:var(--h-blue-glow);">
                        {{ $pending2faChannel === 'sms' ? 'Resend SMS code' : 'Resend email code' }}
                    </button>
                </div>

                <div id="modal-2fa-switch-wrap" style="{{ ($pending2faChannel === 'sms' || ! empty($pending2faUser?->phone)) ? 'display:block;' : 'display:none;' }}margin-top:0.15rem;">
                    <button type="button" id="modal-2fa-switch-btn" onclick="handleModal2faSwitchChannel()" style="background:none;border:none;cursor:pointer;font-size:0.76rem;color:var(--h-blue-glow);text-decoration:underline;">
                        {{ $pending2faChannel === 'sms' ? "Can't receive SMS? Switch to Email verification" : 'Prefer SMS? Switch to Phone verification' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let isModalAnimating = false;
    let modal2faState = {
        channel: @json($pending2faChannel),
        target: @json($pending2faTarget),
        userPhone: @json($pending2faUser?->phone),
        hasPhone: @json(! empty($pending2faUser?->phone)),
        firebaseConfig: @json($modalFirebaseConfig),
        confirmationResult: null,
        recaptchaVerifier: null,
        recaptchaMode: 'invisible',
        smsInFlight: false
    };

    function showModal2faStatus(msg, isError = false) {
        const box = document.getElementById('modal-2fa-status');
        const txt = document.getElementById('modal-2fa-status-text');
        if (!box || !txt) return;
        box.style.display = 'block';
        box.style.borderColor = isError ? 'rgba(239,68,68,0.45)' : 'var(--h-border)';
        box.style.color = isError ? '#f87171' : 'var(--h-muted)';
        txt.textContent = msg;
    }

    function updateModal2faUI() {
        const chLabel = document.getElementById('modal-2fa-channel-label');
        const targetEl = document.getElementById('modal-2fa-target');
        const resendPrompt = document.getElementById('modal-2fa-resend-prompt');
        const resendBtn = document.getElementById('modal-2fa-resend-btn');
        const switchWrap = document.getElementById('modal-2fa-switch-wrap');
        const switchBtn = document.getElementById('modal-2fa-switch-btn');
        const isSms = modal2faState.channel === 'sms';

        if (chLabel) chLabel.textContent = isSms ? 'SMS' : 'EMAIL';
        if (targetEl) targetEl.textContent = modal2faState.target || '';
        if (resendPrompt) resendPrompt.textContent = isSms ? "Didn't receive the SMS?" : "Didn't get the email?";
        if (resendBtn) resendBtn.textContent = isSms ? 'Resend SMS code' : 'Resend email code';
        if (switchWrap) {
            switchWrap.style.display = (isSms || modal2faState.hasPhone) ? 'block' : 'none';
        }
        if (switchBtn) {
            switchBtn.textContent = isSms
                ? "Can't receive SMS? Switch to Email verification"
                : 'Prefer SMS? Switch to Phone verification';
        }
    }

    function switchModalToLogin() {
        const loginStep = document.getElementById('modal-step-login');
        const twoFactorStep = document.getElementById('modal-step-2fa');
        if (twoFactorStep) twoFactorStep.style.display = 'none';
        if (loginStep) loginStep.style.display = 'block';
    }

    function ensureFirebaseLoaded(callback) {
        if (typeof firebase !== 'undefined' && firebase.auth) {
            if (!firebase.apps.length) {
                firebase.initializeApp(modal2faState.firebaseConfig);
            }
            callback();
            return;
        }
        const s1 = document.createElement('script');
        s1.src = 'https://www.gstatic.com/firebasejs/11.0.2/firebase-app-compat.js';
        s1.onload = function() {
            const s2 = document.createElement('script');
            s2.src = 'https://www.gstatic.com/firebasejs/11.0.2/firebase-auth-compat.js';
            s2.onload = function() {
                if (!firebase.apps.length) {
                    firebase.initializeApp(modal2faState.firebaseConfig);
                }
                callback();
            };
            document.head.appendChild(s2);
        };
        document.head.appendChild(s1);
    }

    function initModalRecaptchaVerifier(sizeMode) {
        if (modal2faState.recaptchaVerifier && modal2faState.recaptchaMode === sizeMode) {
            return modal2faState.recaptchaVerifier;
        }
        if (modal2faState.recaptchaVerifier) {
            try {
                modal2faState.recaptchaVerifier.clear();
            } catch (e) {}
            modal2faState.recaptchaVerifier = null;
        }
        const wrapper = document.getElementById('modal-recaptcha-wrapper');
        if (wrapper) {
            wrapper.innerHTML = '<div id="modal-recaptcha-container"></div>';
        }
        modal2faState.recaptchaMode = sizeMode;
        modal2faState.recaptchaVerifier = new firebase.auth.RecaptchaVerifier('modal-recaptcha-container', {
            size: sizeMode,
            theme: 'dark',
            callback: function() {
                if (sizeMode === 'normal' && !modal2faState.smsInFlight) {
                    executeModalPhoneSignIn();
                }
            },
            'expired-callback': function() {
                showModal2faStatus('Security check expired. Please click Resend SMS code again.', true);
            }
        });
        return modal2faState.recaptchaVerifier;
    }

    function executeModalPhoneSignIn() {
        if (modal2faState.smsInFlight || !modal2faState.userPhone) return;
        modal2faState.smsInFlight = true;
        showModal2faStatus('Requesting SMS verification code from Google Firebase...');

        const smsStorageKey = 'haven_sms_sent_at_' + modal2faState.userPhone;

        firebase.auth().signInWithPhoneNumber(modal2faState.userPhone, modal2faState.recaptchaVerifier)
            .then(function(result) {
                modal2faState.smsInFlight = false;
                modal2faState.confirmationResult = result;
                sessionStorage.setItem(smsStorageKey, String(Date.now()));
                showModal2faStatus('SMS code sent via Firebase! Check your phone (' + modal2faState.target + ') for the 6-digit text.');
            })
            .catch(function(error) {
                modal2faState.smsInFlight = false;
                console.error('Firebase SMS error:', error);
                const code = (error && error.code) ? error.code : '';
                const msg = (error && error.message) ? error.message : 'Unable to send SMS';

                if ((code === 'auth/invalid-app-credential' || code === 'auth/captcha-check-failed' || msg.includes('invalid-app-credential')) && modal2faState.recaptchaMode === 'invisible') {
                    showModal2faStatus('Security check required: Please check the "I\'m not a robot" box below to send your SMS code.', false);
                    const normalVerifier = initModalRecaptchaVerifier('normal');
                    normalVerifier.render();
                    return;
                }

                if (msg.includes('OPERATION_NOT_ALLOWED') || msg.includes('region')) {
                    showModal2faStatus('Firebase SMS blocked: Philippines (+63) is not enabled in Firebase Console → Authentication → Settings → SMS region policy.', true);
                } else if (msg.includes('too-many-requests') || code === 'auth/too-many-requests') {
                    showModal2faStatus('Firebase rate limit reached (auth/too-many-requests): Too many SMS requests were sent to ' + modal2faState.userPhone + ' in a short time. Wait ~15–30 minutes for Google\'s cooldown, or click "Switch to Email verification" below.', true);
                } else {
                    showModal2faStatus('Firebase SMS error: ' + msg, true);
                }
            });
    }

    function triggerModalFirebaseSms(isManualResend = false) {
        if (modal2faState.channel !== 'sms' || !modal2faState.userPhone) return;
        const smsStorageKey = 'haven_sms_sent_at_' + modal2faState.userPhone;
        const lastSent = parseInt(sessionStorage.getItem(smsStorageKey) || '0', 10);
        const elapsedSec = Math.floor((Date.now() - lastSent) / 1000);
        if (!isManualResend && lastSent > 0 && elapsedSec < 60) {
            showModal2faStatus('SMS code was already requested ' + elapsedSec + 's ago. Check your phone or click "Resend SMS code" below.', false);
            return;
        }

        ensureFirebaseLoaded(function() {
            initModalRecaptchaVerifier(modal2faState.recaptchaMode);
            if (modal2faState.recaptchaMode === 'normal') {
                modal2faState.recaptchaVerifier.render().then(function() {
                    showModal2faStatus('Please check the "I\'m not a robot" box below to send your SMS code.', false);
                });
            } else {
                executeModalPhoneSignIn();
            }
        });
    }

    function openModalTwoFactorStep(data) {
        if (data) {
            if (data.channel) modal2faState.channel = data.channel;
            if (data.target) modal2faState.target = data.target;
            if (typeof data.user_phone !== 'undefined') modal2faState.userPhone = data.user_phone;
            if (typeof data.has_phone !== 'undefined') modal2faState.hasPhone = Boolean(data.has_phone);
        }

        updateModal2faUI();

        const loginStep = document.getElementById('modal-step-login');
        const twoFactorStep = document.getElementById('modal-step-2fa');
        if (loginStep) loginStep.style.display = 'none';
        if (twoFactorStep) twoFactorStep.style.display = 'block';

        const modal = document.getElementById('haven-auth-modal');
        if (modal && !modal.classList.contains('is-open')) {
            openAuthModal();
        }

        const codeInput = document.getElementById('modal_2fa_code');
        if (codeInput) {
            codeInput.value = '';
            setTimeout(() => codeInput.focus(), 120);
        }

        if (modal2faState.channel === 'sms' && modal2faState.userPhone) {
            triggerModalFirebaseSms(false);
        } else {
            showModal2faStatus('A 6-digit verification code has been sent to ' + modal2faState.target + '.');
        }
    }

    async function handleModal2faResend() {
        if (modal2faState.channel === 'sms') {
            triggerModalFirebaseSms(true);
            return;
        }

        showModal2faStatus('Sending a fresh 6-digit code to your email...');
        try {
            const csrfToken = document.querySelector('#modal-2fa-form input[name="_token"]')?.value || '';
            const res = await fetch('{{ route('two-factor.challenge.resend') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.status) {
                showModal2faStatus(data.status, false);
            } else {
                showModal2faStatus(data.message || 'Unable to resend code right now.', true);
            }
        } catch (err) {
            showModal2faStatus('Network error while resending email code.', true);
        }
    }

    async function handleModal2faSwitchChannel() {
        showModal2faStatus('Switching verification channel...');
        try {
            const csrfToken = document.querySelector('#modal-2fa-form input[name="_token"]')?.value || '';
            const res = await fetch('{{ route('two-factor.challenge.switch') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.channel) {
                modal2faState.channel = data.channel;
                modal2faState.target = data.target || modal2faState.target;
                if (typeof data.user_phone !== 'undefined') modal2faState.userPhone = data.user_phone;
                if (typeof data.has_phone !== 'undefined') modal2faState.hasPhone = Boolean(data.has_phone);
                updateModal2faUI();
                showModal2faStatus(data.status || 'Switched verification channel.', false);
                if (modal2faState.channel === 'sms') {
                    triggerModalFirebaseSms(false);
                }
            } else {
                showModal2faStatus(data.message || 'Unable to switch channel.', true);
            }
        } catch (err) {
            showModal2faStatus('Network error while switching channel.', true);
        }
    }

    function restoreRememberedDevice() {
        try {
            const savedEmail = localStorage.getItem('haven_remember_email');
            const shouldRemember = localStorage.getItem('haven_remember_device') === '1';
            const emailInput = document.getElementById('modal_email');
            const rememberCheckbox = document.getElementById('modal_remember');

            if (savedEmail && emailInput && !emailInput.value) {
                emailInput.value = savedEmail;
            }
            if (rememberCheckbox) {
                rememberCheckbox.checked = shouldRemember;
            }
        } catch (e) {
            // localStorage might be unavailable in restricted environments
        }
    }

    function openAuthModal(e) {
        if (e) e.preventDefault();
        const modal = document.getElementById('haven-auth-modal');
        if (!modal || isModalAnimating) return;

        restoreRememberedDevice();

        isModalAnimating = true;
        modal.classList.remove('is-closing');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Force browser reflow so transition triggers cleanly
        void modal.offsetWidth;

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        setTimeout(() => {
            isModalAnimating = false;
            const twoFactorStep = document.getElementById('modal-step-2fa');
            if (twoFactorStep && twoFactorStep.style.display !== 'none') {
                const codeInput = document.getElementById('modal_2fa_code');
                if (codeInput) codeInput.focus();
                return;
            }
            const emailInput = document.getElementById('modal_email');
            const passwordInput = document.getElementById('modal_password');
            if (emailInput && emailInput.value) {
                if (passwordInput) passwordInput.focus();
            } else if (emailInput) {
                emailInput.focus();
            }
        }, 320);
    }

    function closeAuthModal() {
        const modal = document.getElementById('haven-auth-modal');
        if (!modal || isModalAnimating || !modal.classList.contains('is-open')) return;

        isModalAnimating = true;
        modal.classList.add('is-closing');
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        setTimeout(() => {
            modal.style.display = 'none';
            modal.classList.remove('is-closing');
            document.body.style.overflow = '';
            isModalAnimating = false;
        }, 240);
    }

    // Close on backdrop click (outside card)
    document.addEventListener('DOMContentLoaded', function() {
        restoreRememberedDevice();

        @if ($shouldAutoOpen2fa)
            openAuthModal();
            @if ($pending2faChannel === 'sms' && ! empty($pending2faUser?->phone) && ! $errors->any())
                window.addEventListener('load', function() {
                    triggerModalFirebaseSms(false);
                });
            @endif
            try {
                if (window.location.pathname === '/two-factor-challenge') {
                    window.history.replaceState({}, '', '/');
                }
            } catch (e) {}
        @elseif ($errors->any() || session('status') || request()->boolean('login'))
            openAuthModal();
        @endif
        try {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('login') === '1' || urlParams.get('two_factor') === '1') {
                openAuthModal();
                urlParams.delete('login');
                urlParams.delete('two_factor');
                const cleanSearch = urlParams.toString();
                window.history.replaceState({}, '', window.location.pathname + (cleanSearch ? '?' + cleanSearch : '') + window.location.hash);
            }
        } catch (e) {}

        const modal = document.getElementById('haven-auth-modal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeAuthModal();
                }
            });
        }

        const modalForm = document.getElementById('modal-login-form');
        const submitBtn = document.getElementById('modal-login-btn');
        const errorAlert = document.getElementById('modal-error-alert');
        const errorText = document.getElementById('modal-error-text');

        if (modalForm && submitBtn) {
            modalForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Clear previous errors
                if (errorAlert) errorAlert.style.display = 'none';
                const serverErr = document.getElementById('modal-server-error');
                if (serverErr) serverErr.style.display = 'none';

                // Save remember me preference
                try {
                    const rememberCheckbox = document.getElementById('modal_remember');
                    const emailInput = document.getElementById('modal_email');
                    if (rememberCheckbox && rememberCheckbox.checked && emailInput && emailInput.value.trim()) {
                        localStorage.setItem('haven_remember_device', '1');
                        localStorage.setItem('haven_remember_email', emailInput.value.trim());
                    } else {
                        localStorage.removeItem('haven_remember_device');
                        localStorage.removeItem('haven_remember_email');
                    }
                } catch (err) {}

                // Button loading feedback
                const originalHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.85';
                submitBtn.style.cursor = 'wait';
                submitBtn.innerHTML = `
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:havenSpin 0.9s linear infinite;">
                        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                    </svg>
                    <span>Signing In...</span>
                `;

                try {
                    const formData = new FormData(modalForm);
                    const response = await fetch(modalForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (data.requires_2fa) {
                            openModalTwoFactorStep(data);
                            return;
                        }
                        window.location.href = data.redirect || '{{ route('dashboard', absolute: false) }}';
                        return;
                    }

                    // On error (422 or 401)
                    const data = await response.json().catch(() => null);
                    let message = 'These credentials do not match our records.';
                    if (data && data.errors) {
                        const firstKey = Object.keys(data.errors)[0];
                        if (firstKey && data.errors[firstKey][0]) {
                            message = data.errors[firstKey][0];
                        }
                    } else if (data && data.message) {
                        message = data.message;
                    }

                    if (errorAlert && errorText) {
                        errorText.textContent = message;
                        errorAlert.style.display = 'flex';
                    }

                    const pwInput = document.getElementById('modal_password');
                    if (pwInput) {
                        pwInput.value = '';
                        pwInput.focus();
                    }
                } catch (err) {
                    modalForm.submit();
                    return;
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                    submitBtn.innerHTML = originalHtml;
                }
            });
        }

        // 2FA Form Submission inside Pop-Up Modal
        const modal2faForm = document.getElementById('modal-2fa-form');
        const modal2faBtn = document.getElementById('modal-2fa-submit-btn');
        const modal2faErr = document.getElementById('modal-2fa-error');

        if (modal2faForm && modal2faBtn) {
            modal2faForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                if (modal2faErr) modal2faErr.style.display = 'none';

                const codeInput = document.getElementById('modal_2fa_code');
                const tokenInput = document.getElementById('modal_firebase_token');
                const code = codeInput ? codeInput.value.trim() : '';

                const originalHtml = modal2faBtn.innerHTML;
                modal2faBtn.disabled = true;
                modal2faBtn.style.opacity = '0.85';
                modal2faBtn.style.cursor = 'wait';
                modal2faBtn.innerHTML = `
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:havenSpin 0.9s linear infinite;">
                        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                    </svg>
                    <span>Verifying...</span>
                `;

                try {
                    // If Firebase SMS confirmationResult is active, verify code with Firebase first
                    if (modal2faState.channel === 'sms' && modal2faState.confirmationResult && code.length === 6) {
                        showModal2faStatus('Verifying SMS code with Google Firebase...');
                        try {
                            const userCredential = await modal2faState.confirmationResult.confirm(code);
                            const idToken = await userCredential.user.getIdToken();
                            if (tokenInput) tokenInput.value = idToken;
                        } catch (firebaseErr) {
                            console.error('Firebase confirmation error:', firebaseErr);
                            if (modal2faErr) {
                                modal2faErr.textContent = 'Invalid SMS verification code. Please check the 6-digit code and try again.';
                                modal2faErr.style.display = 'block';
                            }
                            showModal2faStatus('Invalid verification code.', true);
                            return;
                        }
                    }

                    const formData = new FormData(modal2faForm);
                    const response = await fetch(modal2faForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        window.location.href = data.redirect || '{{ route('dashboard', absolute: false) }}';
                        return;
                    }

                    const data = await response.json().catch(() => null);
                    let message = 'The verification code is invalid or has expired.';
                    if (data && data.errors && data.errors.code && data.errors.code[0]) {
                        message = data.errors.code[0];
                    } else if (data && data.message) {
                        message = data.message;
                    }
                    if (modal2faErr) {
                        modal2faErr.textContent = message;
                        modal2faErr.style.display = 'block';
                    }
                    showModal2faStatus(message, true);
                } catch (err) {
                    modal2faForm.submit();
                    return;
                } finally {
                    modal2faBtn.disabled = false;
                    modal2faBtn.style.opacity = '1';
                    modal2faBtn.style.cursor = 'pointer';
                    modal2faBtn.innerHTML = originalHtml;
                }
            });
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAuthModal();
        }
    });
</script>


