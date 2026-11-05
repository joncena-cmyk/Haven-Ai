<x-guest-layout title="Create Account">

    {{-- Back to Home link --}}
    <div style="margin-bottom:1.5rem;">
        <a href="{{ url('/') }}" style="display:inline-flex;align-items:center;gap:0.45rem;font-size:0.8rem;color:var(--h-text3);text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='var(--h-teal)'" onmouseout="this.style.color='var(--h-text3)'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
            </svg>
            <span>Back to homepage</span>
        </a>
    </div>

    <h2 class="haven-auth-title">Create your account</h2>
    <p class="haven-auth-desc">Join HAVEN to start diagnosing your engine.</p>

    <form method="POST" action="{{ route('register') }}" id="register-form">
        @csrf

        {{-- Name --}}
        <div style="margin-bottom:1.25rem;">
            <label class="haven-label" for="name">Full name</label>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                required
                autofocus
                autocomplete="name"
                class="haven-input {{ $errors->has('name') ? 'haven-input-error' : '' }}"
                placeholder="Juan dela Cruz"
            >
            @error('name')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror
        </div>

        {{-- Email --}}
        <div style="margin-bottom:1.25rem;">
            <label class="haven-label" for="email">Email address</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autocomplete="username"
                class="haven-input {{ $errors->has('email') ? 'haven-input-error' : '' }}"
                placeholder="you@example.com"
            >
            @error('email')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div style="margin-bottom:1.25rem;">
            <label class="haven-label" for="password">Password</label>
            <div style="position:relative;">
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="haven-input {{ $errors->has('password') ? 'haven-input-error' : '' }}"
                    placeholder="Min. 8 characters"
                    style="padding-right:2.75rem;"
                >
                <button type="button" id="toggle-password"
                    onclick="togglePw('password','toggle-password')"
                    style="position:absolute;right:0.6rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--h-text3);padding:4px;display:flex;align-items:center;"
                    title="Show/hide password">
                    <svg id="toggle-password-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
            {{-- Interactive Password Security Guide & Strength Meter --}}
            <div id="pw-guide-wrap" style="margin-top:0.65rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:10px;padding:0.75rem 0.85rem;transition:all 0.2s ease;">
                {{-- Header Row: Label & Dynamic Strength Badge --}}
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.45rem;">
                    <span style="font-size:0.72rem;font-weight:700;color:var(--h-text2);text-transform:uppercase;letter-spacing:0.04em;display:flex;align-items:center;gap:0.35rem;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Password Strength Guide
                    </span>
                    <span id="pw-strength-badge" style="font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:999px;background:var(--h-border);color:var(--h-text3);transition:all 0.2s;">
                        Enter Password
                    </span>
                </div>

                {{-- Segmented Visual Meter --}}
                <div style="display:flex;gap:4px;height:4px;margin-bottom:0.65rem;">
                    <div id="pw-bar-1" style="flex:1;border-radius:99px;background:var(--h-border);transition:background 0.25s;"></div>
                    <div id="pw-bar-2" style="flex:1;border-radius:99px;background:var(--h-border);transition:background 0.25s;"></div>
                    <div id="pw-bar-3" style="flex:1;border-radius:99px;background:var(--h-border);transition:background 0.25s;"></div>
                    <div id="pw-bar-4" style="flex:1;border-radius:99px;background:var(--h-border);transition:background 0.25s;"></div>
                </div>

                {{-- Dynamic Requirements Checklist --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.35rem 0.5rem;font-size:0.72rem;">
                    <div id="crit-len" style="display:flex;align-items:center;gap:0.35rem;color:var(--h-text3);transition:color 0.2s;">
                        <span class="crit-icon" style="font-weight:700;min-width:12px;">○</span>
                        <span>8+ characters</span>
                    </div>
                    <div id="crit-case" style="display:flex;align-items:center;gap:0.35rem;color:var(--h-text3);transition:color 0.2s;">
                        <span class="crit-icon" style="font-weight:700;min-width:12px;">○</span>
                        <span>Upper &amp; lower case</span>
                    </div>
                    <div id="crit-num" style="display:flex;align-items:center;gap:0.35rem;color:var(--h-text3);transition:color 0.2s;">
                        <span class="crit-icon" style="font-weight:700;min-width:12px;">○</span>
                        <span>At least 1 number</span>
                    </div>
                    <div id="crit-sym" style="display:flex;align-items:center;gap:0.35rem;color:var(--h-text3);transition:color 0.2s;">
                        <span class="crit-icon" style="font-weight:700;min-width:12px;">○</span>
                        <span>Special symbol (!@#$)</span>
                    </div>
                </div>
            </div>
            <div id="caps-lock-warning" style="display:none;margin-top:0.4rem;font-size:0.75rem;color:var(--h-amber);align-items:center;gap:0.35rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>Caps Lock is ON</span>
            </div>
            @error('password')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror
        </div>

        {{-- Confirm Password --}}
        <div style="margin-bottom:1.75rem;">
            <label class="haven-label" for="password_confirmation">Confirm password</label>
            <div style="position:relative;">
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="haven-input {{ $errors->has('password_confirmation') ? 'haven-input-error' : '' }}"
                    placeholder="Re-enter your password"
                    style="padding-right:2.75rem;"
                >
                <button type="button" id="toggle-password-confirm"
                    onclick="togglePw('password_confirmation','toggle-password-confirm')"
                    style="position:absolute;right:0.6rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--h-text3);padding:4px;display:flex;align-items:center;"
                    title="Show/hide password">
                    <svg id="toggle-password-confirm-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
            <div id="pw-match-wrap" style="display:none;margin-top:0.45rem;font-size:0.75rem;align-items:center;gap:0.35rem;">
                <span id="pw-match-icon" style="font-weight:700;">✓</span>
                <span id="pw-match-text">Passwords match</span>
            </div>
            @error('password_confirmation')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror
        </div>

        {{-- Submit --}}
        <button type="submit" class="haven-btn-primary" id="register-submit">
            <span id="register-submit-icon" style="display:inline-flex;align-items:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
                </svg>
            </span>
            <span id="register-submit-text">Create Account</span>
        </button>

        {{-- Terms & Privacy Notice --}}
        <p style="text-align:center;margin-top:1rem;font-size:0.75rem;color:var(--h-text3);line-height:1.45;">
            By creating an account, you agree to our
            <a class="haven-link" href="{{ route('terms') }}" target="_blank" style="text-decoration:underline;">Terms &amp; Conditions</a>
            and
            <a class="haven-link" href="{{ route('privacy') }}" target="_blank" style="text-decoration:underline;">Privacy Policy</a>.
        </p>

        {{-- Login link --}}
        <p style="text-align:center;margin-top:1.5rem;font-size:0.875rem;color:var(--h-text3);">
            Already have an account?
            <a class="haven-link" href="{{ route('login') }}">Sign in</a>
        </p>

    </form>

<script>
function togglePw(fieldId, btnId) {
    const inp = document.getElementById(fieldId);
    const btn = document.getElementById(btnId);
    const showing = inp.type === 'text';
    inp.type = showing ? 'password' : 'text';
    btn.style.color = showing ? 'var(--h-text3)' : 'var(--h-teal)';
    btn.querySelector('svg').innerHTML = showing
        ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
        : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
}

(function() {
    const pwInput = document.getElementById('password');
    const pwConfirm = document.getElementById('password_confirmation');
    const capsWarn = document.getElementById('caps-lock-warning');
    const badge = document.getElementById('pw-strength-badge');
    const matchWrap = document.getElementById('pw-match-wrap');
    const matchIcon = document.getElementById('pw-match-icon');
    const matchText = document.getElementById('pw-match-text');

    const bars = [
        document.getElementById('pw-bar-1'),
        document.getElementById('pw-bar-2'),
        document.getElementById('pw-bar-3'),
        document.getElementById('pw-bar-4'),
    ];

    const critLen = document.getElementById('crit-len');
    const critCase = document.getElementById('crit-case');
    const critNum = document.getElementById('crit-num');
    const critSym = document.getElementById('crit-sym');

    function checkCaps(e) {
        if (!capsWarn || typeof e.getModifierState !== 'function') return;
        capsWarn.style.display = e.getModifierState('CapsLock') ? 'flex' : 'none';
    }

    function setCrit(el, passed) {
        if (!el) return;
        const icon = el.querySelector('.crit-icon');
        if (passed) {
            el.style.color = 'var(--h-green)';
            el.style.fontWeight = '600';
            if (icon) {
                icon.textContent = '✓';
                icon.style.color = 'var(--h-green)';
            }
        } else {
            el.style.color = 'var(--h-text3)';
            el.style.fontWeight = '400';
            if (icon) {
                icon.textContent = '○';
                icon.style.color = 'var(--h-text3)';
            }
        }
    }

    function updateMatch() {
        if (!pwConfirm || !matchWrap) return;
        const p1 = pwInput ? pwInput.value : '';
        const p2 = pwConfirm.value;
        if (!p2) {
            matchWrap.style.display = 'none';
            return;
        }
        matchWrap.style.display = 'flex';
        if (p1 === p2) {
            matchWrap.style.color = 'var(--h-green)';
            if (matchIcon) matchIcon.textContent = '✓';
            if (matchText) matchText.textContent = 'Passwords match';
        } else {
            matchWrap.style.color = 'var(--h-red)';
            if (matchIcon) matchIcon.textContent = '✕';
            if (matchText) matchText.textContent = 'Passwords do not match';
        }
    }

    if (pwInput) {
        pwInput.addEventListener('keyup', checkCaps);
        pwInput.addEventListener('keydown', checkCaps);
        pwInput.addEventListener('input', function() {
            const val = pwInput.value;
            if (!val) {
                if (badge) {
                    badge.textContent = 'Enter Password';
                    badge.style.background = 'var(--h-border)';
                    badge.style.color = 'var(--h-text3)';
                }
                bars.forEach(b => { if (b) b.style.background = 'var(--h-border)'; });
                setCrit(critLen, false);
                setCrit(critCase, false);
                setCrit(critNum, false);
                setCrit(critSym, false);
                updateMatch();
                return;
            }

            const hasLen = val.length >= 8;
            const hasCase = /[a-z]/.test(val) && /[A-Z]/.test(val);
            const hasNum = /\d/.test(val);
            const hasSym = /[^A-Za-z0-9]/.test(val);

            setCrit(critLen, hasLen);
            setCrit(critCase, hasCase);
            setCrit(critNum, hasNum);
            setCrit(critSym, hasSym);

            const passedCount = (hasLen ? 1 : 0) + (hasCase ? 1 : 0) + (hasNum ? 1 : 0) + (hasSym ? 1 : 0);

            let score = 0;
            let label = 'Weak';
            let color = 'var(--h-red)';
            let bgColor = 'var(--h-red-bg)';

            if (!hasLen || passedCount <= 1) {
                // Weak: less than 8 chars or single criterion
                score = 1;
                label = 'Weak';
                color = 'var(--h-red)';
                bgColor = 'var(--h-red-bg)';
            } else if (passedCount === 2) {
                // Fair / Moderate
                score = 2;
                label = 'Fair';
                color = 'var(--h-amber)';
                bgColor = 'var(--h-amber-bg)';
            } else if (passedCount === 3) {
                // Medium / Good
                score = 3;
                label = 'Medium';
                color = 'var(--h-amber)';
                bgColor = 'var(--h-amber-bg)';
            } else {
                // Strong / Very Strong (all criteria met)
                score = 4;
                label = (val.length >= 12) ? 'Very Strong' : 'Strong';
                color = 'var(--h-green)';
                bgColor = 'var(--h-green-bg)';
            }

            bars.forEach((b, idx) => {
                if (b) b.style.background = idx < score ? color : 'var(--h-border)';
            });

            if (badge) {
                badge.textContent = label;
                badge.style.color = color;
                badge.style.background = bgColor;
            }

            updateMatch();
        });
    }

    if (pwConfirm) {
        pwConfirm.addEventListener('keyup', checkCaps);
        pwConfirm.addEventListener('keydown', checkCaps);
        pwConfirm.addEventListener('input', updateMatch);
    }

    const form = document.getElementById('register-form');
    const btn = document.getElementById('register-submit');
    const txt = document.getElementById('register-submit-text');
    if (form && btn && txt) {
        form.addEventListener('submit', function() {
            btn.disabled = true;
            btn.style.opacity = '0.75';
            txt.textContent = 'Creating Account...';
        });
    }
})();
</script>

</x-guest-layout>
