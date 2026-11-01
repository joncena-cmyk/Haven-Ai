<x-guest-layout title="Sign In">

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

        {{-- Submit --}}
        <button type="submit" class="haven-btn-primary" id="login-submit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                <polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
            Sign In
        </button>

        {{-- Register link --}}
        <p style="text-align:center;margin-top:1.5rem;font-size:0.875rem;color:var(--h-text2);">
            Don't have an account?
            <a class="haven-link" href="{{ route('register') }}">Create one</a>
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
</script>

</x-guest-layout>
