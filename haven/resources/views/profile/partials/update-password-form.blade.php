<section>
    <header style="margin-bottom:1.25rem;">
        <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.35rem;">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <h2 style="font-size:1.05rem;font-weight:700;color:var(--h-text);margin:0;">
                {{ __('Change Password') }}
            </h2>
        </div>

        <p style="font-size:0.8rem;color:var(--h-text3);margin:0;line-height:1.5;">
            {{ __('Your password should be at least 8 characters and include a combination of numbers, letters, and special characters.') }}
        </p>
    </header>

    @if (session('status') === 'password-updated')
        <div style="background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);border-radius:var(--h-radius);padding:0.7rem 1rem;margin-bottom:1.25rem;font-size:0.82rem;color:var(--h-teal);display:flex;align-items:center;gap:0.5rem;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            {{ __('Your password has been updated.') }}
        </div>
    @endif

    <form method="post" action="{{ route('password.update') }}" style="display:flex;flex-direction:column;gap:1.1rem;">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="haven-label">{{ __('Current Password') }}</label>
            <input
                id="update_password_current_password"
                name="current_password"
                type="password"
                class="haven-input {{ $errors->updatePassword->has('current_password') ? 'haven-input-error' : '' }}"
                autocomplete="current-password"
                placeholder="Enter current password"
            />
            @if ($errors->updatePassword->has('current_password'))
                <p class="haven-error-msg">{{ $errors->updatePassword->first('current_password') }}</p>
            @endif
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1rem;">
            <div>
                <label for="update_password_password" class="haven-label">{{ __('New Password') }}</label>
                <input
                    id="update_password_password"
                    name="password"
                    type="password"
                    class="haven-input {{ $errors->updatePassword->has('password') ? 'haven-input-error' : '' }}"
                    autocomplete="new-password"
                    placeholder="Enter new password"
                />
                @if ($errors->updatePassword->has('password'))
                    <p class="haven-error-msg">{{ $errors->updatePassword->first('password') }}</p>
                @endif
            </div>

            <div>
                <label for="update_password_password_confirmation" class="haven-label">{{ __('Re-type New Password') }}</label>
                <input
                    id="update_password_password_confirmation"
                    name="password_confirmation"
                    type="password"
                    class="haven-input {{ $errors->updatePassword->has('password_confirmation') ? 'haven-input-error' : '' }}"
                    autocomplete="new-password"
                    placeholder="Confirm new password"
                />
                @if ($errors->updatePassword->has('password_confirmation'))
                    <p class="haven-error-msg">{{ $errors->updatePassword->first('password_confirmation') }}</p>
                @endif
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.75rem;padding-top:0.5rem;">
            <button type="submit" class="haven-btn-primary" style="padding:0.6rem 1.4rem;font-size:0.85rem;">
                {{ __('Update Password') }}
            </button>
        </div>
    </form>
</section>
