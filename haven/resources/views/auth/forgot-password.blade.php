<x-guest-layout title="Reset Password">

    {{-- Back to Sign In link --}}
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('login') }}" style="display:inline-flex;align-items:center;gap:0.45rem;font-size:0.8rem;color:var(--h-text3);text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='var(--h-teal)'" onmouseout="this.style.color='var(--h-text3)'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
            </svg>
            <span>Back to sign in</span>
        </a>
    </div>

    <h2 class="haven-auth-title">Reset your password</h2>
    <p class="haven-auth-desc">
        {{ __('Enter the email address associated with your HAVEN account and we will send you a secure password reset link.') }}
    </p>

    {{-- Session Status --}}
    @if (session('status'))
        <div class="haven-alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" id="forgot-pw-form">
        @csrf

        {{-- Email Address --}}
        <div style="margin-bottom:1.5rem;">
            <label class="haven-label" for="email">{{ __('Email address') }}</label>
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

        <button type="submit" class="haven-btn-primary" id="forgot-pw-submit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
            </svg>
            <span>{{ __('Email Password Reset Link') }}</span>
        </button>

        <p style="text-align:center;margin-top:1.5rem;font-size:0.875rem;color:var(--h-text3);">
            Remembered your password?
            <a class="haven-link" href="{{ route('login') }}">Sign in</a>
        </p>
    </form>

</x-guest-layout>
