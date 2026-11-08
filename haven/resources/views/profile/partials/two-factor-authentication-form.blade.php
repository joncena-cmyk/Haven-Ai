<section id="security-2fa">
    <header style="margin-bottom:1.25rem;">
        <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.35rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            <h2 style="font-size:1.05rem;font-weight:700;color:var(--h-text);margin:0;">
                {{ __('Two-Factor Authentication (2FA)') }}
            </h2>
            <span class="h-badge {{ $user->two_factor_enabled ? 'h-badge-teal' : 'h-badge-muted' }}" style="font-size:0.68rem;">
                {{ $user->two_factor_enabled ? 'ACTIVE (' . strtoupper($user->two_factor_channel ?? 'EMAIL') . ')' : 'DISABLED' }}
            </span>
        </div>

        <p style="font-size:0.8rem;color:var(--h-text3);margin:0;line-height:1.5;">
            {{ __('Add an extra layer of protection to your Haven AI account. When enabled, signing in requires a 6-digit one-time code sent via your preferred channel.') }}
        </p>
    </header>

    @if (session('status') === 'two-factor-updated')
        <div style="background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);border-radius:var(--h-radius);padding:0.65rem 1rem;margin-bottom:1.25rem;font-size:0.8rem;color:var(--h-teal);display:flex;align-items:center;gap:0.5rem;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            {{ __('Two-factor authentication settings have been updated successfully.') }}
        </div>
    @endif

    <form method="post" action="{{ route('profile.two-factor.update') }}" class="space-y-6">
        @csrf
        @method('put')

        {{-- Enable / Disable Switch --}}
        <div>
            <label class="haven-label" style="margin-bottom:0.6rem;">{{ __('2FA Status on Sign In') }}</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:0.75rem;">
                <label style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem;border-radius:var(--h-radius);border:1px solid var(--h-border);background:var(--h-surface2);cursor:pointer;">
                    <input type="radio" name="two_factor_enabled" value="0" {{ ! $user->two_factor_enabled ? 'checked' : '' }} style="margin-top:0.2rem;accent-color:var(--h-teal);" onchange="toggle2faOptions(this.value)">
                    <div>
                        <span style="display:block;font-size:0.875rem;font-weight:600;color:var(--h-text);">{{ __('Disabled') }}</span>
                        <span style="display:block;font-size:0.75rem;color:var(--h-text3);">{{ __('Standard sign-in using email and password only.') }}</span>
                    </div>
                </label>

                <label style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem;border-radius:var(--h-radius);border:1px solid var(--h-teal-ring);background:rgba(20,184,166,0.05);cursor:pointer;">
                    <input type="radio" name="two_factor_enabled" value="1" {{ $user->two_factor_enabled ? 'checked' : '' }} style="margin-top:0.2rem;accent-color:var(--h-teal);" onchange="toggle2faOptions(this.value)">
                    <div>
                        <span style="display:block;font-size:0.875rem;font-weight:600;color:var(--h-teal);">{{ __('Enabled (Multi-Factor)') }}</span>
                        <span style="display:block;font-size:0.75rem;color:var(--h-text3);">{{ __('Require a 6-digit verification code on every login.') }}</span>
                    </div>
                </label>
            </div>
            @error('two_factor_enabled')
                <p class="haven-error-msg">{{ $message }}</p>
            @enderror
        </div>

        {{-- Channel Options Container --}}
        <div id="channel-container" style="display:{{ $user->two_factor_enabled ? 'block' : 'none' }};">
            <label class="haven-label" style="margin-bottom:0.6rem;">{{ __('Choose Your Preferred 2FA Channel') }}</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:0.75rem;margin-bottom:1rem;">
                {{-- Email Option (Brevo) --}}
                <label style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem;border-radius:var(--h-radius);border:1px solid var(--h-border);background:var(--h-surface2);cursor:pointer;">
                    <input type="radio" name="two_factor_channel" value="email" {{ ($user->two_factor_channel ?? 'email') === 'email' ? 'checked' : '' }} style="margin-top:0.2rem;accent-color:var(--h-teal);" onchange="toggleChannel(this.value)">
                    <div>
                        <div style="display:flex;align-items:center;gap:0.4rem;">
                            <span style="font-size:0.875rem;font-weight:600;color:var(--h-text);">{{ __('Email OTP') }}</span>
                            <span class="h-badge h-badge-teal" style="font-size:0.6rem;">Brevo</span>
                        </div>
                        <span style="display:block;font-size:0.75rem;color:var(--h-text3);margin-top:0.2rem;">
                            {{ __('Code emailed to:') }} <strong style="color:var(--h-text2);">{{ $user->email }}</strong>
                        </span>
                    </div>
                </label>

                {{-- SMS Option (Firebase) --}}
                <label style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem;border-radius:var(--h-radius);border:1px solid var(--h-border);background:var(--h-surface2);cursor:pointer;">
                    <input type="radio" name="two_factor_channel" value="sms" {{ ($user->two_factor_channel ?? 'email') === 'sms' ? 'checked' : '' }} style="margin-top:0.2rem;accent-color:var(--h-teal);" onchange="toggleChannel(this.value)">
                    <div>
                        <div style="display:flex;align-items:center;gap:0.4rem;">
                            <span style="font-size:0.875rem;font-weight:600;color:var(--h-text);">{{ __('SMS to Phone') }}</span>
                            <span class="h-badge h-badge-muted" style="font-size:0.6rem;">Firebase</span>
                        </div>
                        <span style="display:block;font-size:0.75rem;color:var(--h-text3);margin-top:0.2rem;">
                            {{ __('Instant 6-digit text message sent to your mobile.') }}
                        </span>
                    </div>
                </label>
            </div>

            {{-- Phone Number Input (Visible if SMS selected) --}}
            <div id="phone-input-group" style="display:{{ ($user->two_factor_channel ?? 'email') === 'sms' ? 'block' : 'none' }};margin-top:1rem;padding:1rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid var(--h-border);">
                <label class="haven-label" for="phone" style="margin-bottom:0.35rem;">
                    {{ __('Mobile Phone Number (with Country Code)') }} <span style="color:var(--h-red);">*</span>
                </label>
                <input
                    id="phone"
                    name="phone"
                    type="tel"
                    value="{{ old('phone', $user->phone) }}"
                    class="haven-input {{ $errors->has('phone') ? 'haven-input-error' : '' }}"
                    placeholder="+639171234567"
                    style="max-width:320px;"
                >
                <p style="font-size:0.75rem;color:var(--h-text3);margin-top:0.4rem;">
                    {{ __('Must include your country code starting with + (e.g., +63 for Philippines, +1 for US/Canada).') }}
                </p>
                @error('phone')
                    <p class="haven-error-msg">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:1rem;padding-top:0.5rem;">
            <button type="submit" class="haven-btn-primary">
                {{ __('Save Security Settings') }}
            </button>
        </div>
    </form>

    <script>
        function toggle2faOptions(val) {
            const container = document.getElementById('channel-container');
            if (val === '1') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }

        function toggleChannel(channel) {
            const phoneGroup = document.getElementById('phone-input-group');
            if (channel === 'sms') {
                phoneGroup.style.display = 'block';
            } else {
                phoneGroup.style.display = 'none';
            }
        }
    </script>
</section>
