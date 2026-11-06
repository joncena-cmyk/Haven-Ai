<x-guest-layout title="Sign In">

    {{-- Back to Home link --}}
    <div style="margin-bottom:1.5rem;">
        <a href="{{ url('/') }}" style="display:inline-flex;align-items:center;gap:0.45rem;font-size:0.8rem;color:var(--h-text3);text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='var(--h-teal)'" onmouseout="this.style.color='var(--h-text3)'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
            </svg>
            <span>Back to homepage</span>
        </a>
    </div>

    <h2 class="haven-auth-title">Welcome back</h2>
    <p class="haven-auth-desc">Sign in to your HAVEN account to continue.</p>

    {{-- Session Status (e.g. password reset link sent) --}}
    @if (session('status'))
        <div class="haven-alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" id="login-form">
        @csrf

        {{-- Email --}}
        <div style="margin-bottom:1.25rem;">
            <label class="haven-label" for="email">Email address</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                class="haven-input {{ $errors->has('email') ? 'haven-input-error' : '' }}"
                placeholder="you@example.com"
            >
            @error('email')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div style="margin-bottom:1rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem;">
                <label class="haven-label" for="password" style="margin-bottom:0;">Password</label>
                @if (Route::has('password.request'))
                    <a class="haven-link" href="{{ route('password.request') }}" style="font-size:0.8rem;">
                        Forgot password?
                    </a>
                @endif
            </div>
            <div style="position:relative;">
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="haven-input {{ $errors->has('password') ? 'haven-input-error' : '' }}"
                    placeholder="••••••••"
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
            {{-- Caps Lock warning --}}
            <div id="caps-lock-warning" style="display:none;margin-top:0.45rem;font-size:0.75rem;color:#f59e0b;align-items:center;gap:0.35rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>Caps Lock is ON</span>
            </div>
            @error('password')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror
        </div>

        {{-- Remember me --}}
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.5rem;">
            <input id="remember_me" type="checkbox" name="remember" class="haven-checkbox">
            <label for="remember_me" style="font-size:0.875rem;color:var(--h-text2);cursor:pointer;">
                Remember me on this device
            </label>
        </div>

        {{-- Submit with Loading State --}}
        <button type="submit" class="haven-btn-primary" id="login-submit">
            <span id="login-submit-icon" style="display:inline-flex;align-items:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    <polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                </svg>
            </span>
            <span id="login-submit-text">Sign In</span>
        </button>

        {{-- Register link --}}
        <p style="text-align:center;margin-top:1.5rem;font-size:0.875rem;color:var(--h-text2);">
            Don't have an account?
            <a class="haven-link" href="{{ route('register') }}">Create one</a>
        </p>

        {{-- Terms & Privacy Notice --}}
        <p style="text-align:center;margin-top:1.25rem;font-size:0.75rem;color:var(--h-text3);line-height:1.45;">
            By signing in, you agree to our
            <a class="haven-link" href="{{ route('terms') }}" target="_blank" style="text-decoration:underline;">Terms &amp; Conditions</a>
            and
            <a class="haven-link" href="{{ route('privacy') }}" target="_blank" style="text-decoration:underline;">Privacy Policy</a>.
        </p>

    </form>

<style>
    @keyframes havenSpin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>

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

// Caps Lock Warning
const pwField = document.getElementById('password');
const capsWarning = document.getElementById('caps-lock-warning');
if (pwField && capsWarning) {
    pwField.addEventListener('keyup', function(e) {
        if (e.getModifierState && e.getModifierState('CapsLock')) {
            capsWarning.style.display = 'inline-flex';
        } else {
            capsWarning.style.display = 'none';
        }
    });
    pwField.addEventListener('keydown', function(e) {
        if (e.getModifierState && e.getModifierState('CapsLock')) {
            capsWarning.style.display = 'inline-flex';
        } else {
            capsWarning.style.display = 'none';
        }
    });
}

// Form Submit Loading Feedback & Device Persistence
const loginForm = document.getElementById('login-form');
const loginBtn = document.getElementById('login-submit');
const loginIcon = document.getElementById('login-submit-icon');
const loginText = document.getElementById('login-submit-text');
const emailField = document.getElementById('email');
const rememberBox = document.getElementById('remember_me');

// Pre-fill remembered email
try {
    const savedEmail = localStorage.getItem('haven_remember_email');
    const shouldRemember = localStorage.getItem('haven_remember_device') === '1';
    if (savedEmail && emailField && !emailField.value) {
        emailField.value = savedEmail;
    }
    if (rememberBox && shouldRemember) {
        rememberBox.checked = true;
    }
} catch (e) {}

if (loginForm && loginBtn) {
    loginForm.addEventListener('submit', function() {
        try {
            if (rememberBox && rememberBox.checked && emailField && emailField.value.trim()) {
                localStorage.setItem('haven_remember_device', '1');
                localStorage.setItem('haven_remember_email', emailField.value.trim());
            } else {
                localStorage.removeItem('haven_remember_device');
                localStorage.removeItem('haven_remember_email');
            }
        } catch (e) {}

        loginBtn.disabled = true;
        loginBtn.style.opacity = '0.8';
        loginBtn.style.cursor = 'wait';
        if (loginIcon) {
            loginIcon.innerHTML = `
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:havenSpin 0.9s linear infinite;margin-right:0.4rem;">
                    <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                </svg>
            `;
        }
        if (loginText) {
            loginText.textContent = 'Authenticating...';
        }
    });
}
</script>

</x-guest-layout>
