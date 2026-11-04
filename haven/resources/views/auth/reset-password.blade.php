<x-guest-layout title="Set New Password">

    <h2 class="haven-auth-title">Set a new password</h2>
    <p class="haven-auth-desc">Choose a strong password to secure your HAVEN account.</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        {{-- Password Reset Token --}}
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        {{-- Email Address --}}
        <div style="margin-bottom:1.25rem;">
            <label class="haven-label" for="email">{{ __('Email') }}</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', $request->email) }}"
                required
                autofocus
                autocomplete="username"
                class="haven-input {{ $errors->has('email') ? 'haven-input-error' : '' }}"
            >
            @error('email')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div style="margin-bottom:1.25rem;">
            <label class="haven-label" for="password">{{ __('Password') }}</label>
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
                <button type="button" id="toggle-reset-pw"
                    onclick="toggleResetPw('password','toggle-reset-pw')"
                    style="position:absolute;right:0.6rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--h-text3);padding:4px;display:flex;align-items:center;"
                    title="Show/hide password">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror
        </div>

        {{-- Confirm Password --}}
        <div style="margin-bottom:1.5rem;">
            <label class="haven-label" for="password_confirmation">{{ __('Confirm Password') }}</label>
            <div style="position:relative;">
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="haven-input {{ $errors->has('password_confirmation') ? 'haven-input-error' : '' }}"
                    placeholder="Re-enter new password"
                    style="padding-right:2.75rem;"
                >
                <button type="button" id="toggle-reset-pw-confirm"
                    onclick="toggleResetPw('password_confirmation','toggle-reset-pw-confirm')"
                    style="position:absolute;right:0.6rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--h-text3);padding:4px;display:flex;align-items:center;"
                    title="Show/hide password">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
            @error('password_confirmation')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="haven-btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <span>{{ __('Reset Password') }}</span>
        </button>
    </form>

<script>
function toggleResetPw(fieldId, btnId) {
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
