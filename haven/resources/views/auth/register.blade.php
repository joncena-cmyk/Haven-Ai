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
            {{-- Password strength & Caps Lock hint --}}
            <div id="pw-strength-wrap" style="display:none;margin-top:0.45rem;">
                <div style="display:flex;gap:4px;height:4px;margin-bottom:0.3rem;">
                    <div id="pw-bar-1" style="flex:1;border-radius:99px;background:var(--h-border);transition:background 0.2s;"></div>
                    <div id="pw-bar-2" style="flex:1;border-radius:99px;background:var(--h-border);transition:background 0.2s;"></div>
                    <div id="pw-bar-3" style="flex:1;border-radius:99px;background:var(--h-border);transition:background 0.2s;"></div>
                    <div id="pw-bar-4" style="flex:1;border-radius:99px;background:var(--h-border);transition:background 0.2s;"></div>
                </div>
                <div id="pw-strength-label" style="font-size:0.72rem;color:var(--h-text3);">At least 8 characters recommended</div>
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
    const strengthWrap = document.getElementById('pw-strength-wrap');
    const strengthLabel = document.getElementById('pw-strength-label');
    const bars = [
        document.getElementById('pw-bar-1'),
        document.getElementById('pw-bar-2'),
        document.getElementById('pw-bar-3'),
        document.getElementById('pw-bar-4'),
    ];

    function checkCaps(e) {
        if (!capsWarn || typeof e.getModifierState !== 'function') return;
        capsWarn.style.display = e.getModifierState('CapsLock') ? 'flex' : 'none';
    }

    if (pwInput) {
        pwInput.addEventListener('keyup', checkCaps);
        pwInput.addEventListener('keydown', checkCaps);
        pwInput.addEventListener('input', function() {
            const val = pwInput.value;
            if (!val) {
                strengthWrap.style.display = 'none';
                return;
            }
            strengthWrap.style.display = 'block';
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
            if (/\d/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val) || val.length >= 12) score++;

            const colors = ['var(--h-red)', 'var(--h-amber)', 'var(--h-teal)', 'var(--h-green)'];
            const labels = ['Weak — use at least 8 characters', 'Fair — add uppercase or numbers', 'Good password strength', 'Strong password'];
            const activeColor = colors[Math.max(0, score - 1)];

            bars.forEach((b, idx) => {
                b.style.background = idx < score ? activeColor : 'var(--h-border)';
            });
            strengthLabel.textContent = labels[Math.max(0, score - 1)];
            strengthLabel.style.color = activeColor;
        });
    }
    if (pwConfirm) {
        pwConfirm.addEventListener('keyup', checkCaps);
        pwConfirm.addEventListener('keydown', checkCaps);
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
