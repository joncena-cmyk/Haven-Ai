<section>
    <header style="margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:1px solid var(--h-border);">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
            <div>
                <h2 style="font-size:1.15rem;font-weight:700;color:var(--h-text);margin:0;">
                    {{ __('Personal Details') }}
                </h2>
                <p style="font-size:0.82rem;color:var(--h-text3);margin:0.25rem 0 0 0;">
                    {{ __("Manage your display name, primary contact email, and identity verification.") }}
                </p>
            </div>
            @if ($user->email_verified_at)
                <span class="h-badge h-badge-green" style="font-size:0.7rem;padding:0.25rem 0.65rem;display:inline-flex;align-items:center;gap:0.35rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Verified Account
                </span>
            @else
                <span class="h-badge h-badge-amber" style="font-size:0.7rem;padding:0.25rem 0.65rem;">
                    Unverified Email
                </span>
            @endif
        </div>
    </header>

    {{-- Facebook-style Account Summary Rows --}}
    <div style="background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius-lg);overflow:hidden;margin-bottom:1.5rem;">
        <div style="padding:0.85rem 1.1rem;border-bottom:1px solid var(--h-border);display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <div>
                <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--h-text3);">Contact Info</div>
                <div style="font-size:0.88rem;font-weight:600;color:var(--h-text);margin-top:0.15rem;">{{ $user->email }}</div>
            </div>
            <span style="font-size:0.75rem;color:var(--h-text3);">Primary Email</span>
        </div>
        <div style="padding:0.85rem 1.1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <div>
                <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--h-text3);">Account Created</div>
                <div style="font-size:0.85rem;color:var(--h-text2);margin-top:0.15rem;">{{ $user->created_at ? $user->created_at->format('F j, Y') : 'Active Member' }}</div>
            </div>
            <span style="font-size:0.75rem;color:var(--h-teal);font-family:var(--h-mono);">ID #{{ $user->id }}</span>
        </div>
    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    @if (session('status') === 'profile-updated')
        <div style="background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);border-radius:var(--h-radius);padding:0.7rem 1rem;margin-bottom:1.25rem;font-size:0.82rem;color:var(--h-teal);display:flex;align-items:center;gap:0.5rem;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            {{ __('Your personal details have been saved.') }}
        </div>
    @endif

    <form method="post" action="{{ route('profile.update') }}" style="display:flex;flex-direction:column;gap:1.25rem;">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="haven-label">{{ __('Full Name') }}</label>
            <input
                id="name"
                name="name"
                type="text"
                class="haven-input {{ $errors->has('name') ? 'haven-input-error' : '' }}"
                value="{{ old('name', $user->name) }}"
                required
                autofocus
                autocomplete="name"
                placeholder="Your full name"
            />
            @error('name')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="haven-label">{{ __('Email Address') }}</label>
            <input
                id="email"
                name="email"
                type="email"
                class="haven-input {{ $errors->has('email') ? 'haven-input-error' : '' }}"
                value="{{ old('email', $user->email) }}"
                required
                autocomplete="username"
                placeholder="you@example.com"
            />
            @error('email')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div style="margin-top:0.75rem;padding:0.75rem 1rem;background:var(--h-amber-bg);border:1px solid var(--h-amber);border-radius:var(--h-radius);">
                    <p style="font-size:0.8rem;color:var(--h-text);margin:0;">
                        {{ __('Your email address is unverified.') }}
                        <button form="send-verification" style="background:none;border:none;color:var(--h-teal);text-decoration:underline;cursor:pointer;font-size:0.8rem;font-weight:600;padding:0;margin-left:0.25rem;">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p style="margin:0.4rem 0 0 0;font-weight:600;font-size:0.78rem;color:var(--h-green);">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.75rem;padding-top:0.5rem;border-top:1px solid var(--h-border);">
            <button type="submit" class="haven-btn-primary" style="padding:0.6rem 1.4rem;font-size:0.85rem;">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</section>
