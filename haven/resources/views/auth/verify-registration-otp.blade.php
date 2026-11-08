<x-guest-layout title="Verify Your Account">

    {{-- Back to Registration link --}}
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('register') }}" style="display:inline-flex;align-items:center;gap:0.45rem;font-size:0.8rem;color:var(--h-text3);text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='var(--h-teal)'" onmouseout="this.style.color='var(--h-text3)'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
            </svg>
            <span>Back to registration</span>
        </a>
    </div>

    {{-- Icon & Heading --}}
    <div style="text-align:center;margin-bottom:1.5rem;">
        <div style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%;background:rgba(20,184,166,0.1);border:1px solid rgba(20,184,166,0.25);margin-bottom:1rem;color:var(--h-teal);">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
            </svg>
        </div>
        <h2 class="haven-auth-title" style="margin-bottom:0.4rem;">Verify your email</h2>
        <p class="haven-auth-desc" style="margin-bottom:0.25rem;">
            We sent a 6-digit confirmation code to:
        </p>
        <span style="font-weight:600;color:var(--h-teal);font-size:0.9rem;word-break:break-all;">
            {{ $email }}
        </span>
    </div>

    {{-- Success Status (e.g. Resent code) --}}
    @if (session('status'))
        <div class="haven-alert-success" role="alert" style="margin-bottom:1.25rem;">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('register.verify-otp.store') }}">
        @csrf

        {{-- 6-Digit Code Input --}}
        <div style="margin-bottom:1.5rem;">
            <label class="haven-label" for="code" style="text-align:center;display:block;">
                Enter 6-Digit Code
            </label>
            <input
                id="code"
                type="text"
                name="code"
                maxlength="6"
                pattern="[0-9]{6}"
                inputmode="numeric"
                required
                autofocus
                autocomplete="one-time-code"
                class="haven-input {{ $errors->has('code') ? 'haven-input-error' : '' }}"
                placeholder="000000"
                style="text-align:center;font-size:1.75rem;letter-spacing:0.5rem;font-weight:700;font-family:monospace;"
            >
            @error('code')
                <p class="haven-error-msg" style="text-align:center;margin-top:0.4rem;">{{ $message }}</p>
            @enderror
            <p style="text-align:center;font-size:0.75rem;color:var(--h-text3);margin-top:0.4rem;">
                Code is valid for 10 minutes.
            </p>
        </div>

        <button type="submit" class="haven-btn-primary" style="width:100%;margin-bottom:1.25rem;">
            Verify and Complete Sign Up
        </button>
    </form>

    {{-- Resend Code Form --}}
    <div style="text-align:center;padding-top:1rem;border-top:1px solid var(--h-border);">
        <p style="font-size:0.8rem;color:var(--h-text3);margin-bottom:0.5rem;">Didn't receive the email?</p>
        <form method="POST" action="{{ route('register.resend-otp') }}">
            @csrf
            <button type="submit" class="haven-link" style="background:none;border:none;cursor:pointer;font-size:0.85rem;font-weight:600;">
                Resend verification code
            </button>
        </form>
    </div>

</x-guest-layout>

