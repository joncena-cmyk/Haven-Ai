@php
    $is2faEnabled = (bool) old('two_factor_enabled', $user->two_factor_enabled ? '1' : '0');
    if ($errors->has('phone') || session('two_factor_test_status')) {
        $is2faEnabled = true;
    }
    $selectedChannel = old('two_factor_channel', $user->two_factor_channel ?? 'email');
    if ($errors->has('phone')) {
        $selectedChannel = 'sms';
    }
    $profileFirebaseConfig = [
        'apiKey' => config('services.firebase.api_key'),
        'authDomain' => config('services.firebase.auth_domain'),
        'projectId' => config('services.firebase.project_id'),
        'appId' => config('services.firebase.app_id'),
    ];
@endphp

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

    @if (session('two_factor_test_status'))
        <div style="background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);border-radius:var(--h-radius);padding:0.65rem 1rem;margin-bottom:1.25rem;font-size:0.8rem;color:var(--h-teal);display:flex;align-items:center;gap:0.5rem;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            {{ session('two_factor_test_status') }}
        </div>
    @endif

    <div id="profile-firebase-test-banner" style="display:none;border-radius:var(--h-radius);padding:0.65rem 1rem;margin-bottom:1.25rem;font-size:0.8rem;"></div>
    <div id="profile-recaptcha-wrapper" style="display:flex;justify-content:flex-start;margin-bottom:1rem;">
        <div id="profile-recaptcha-container"></div>
    </div>

    <form method="post" action="{{ route('profile.two-factor.update') }}" class="space-y-6" id="two-factor-settings-form">
        @csrf
        @method('put')

        {{-- Enable / Disable Switch --}}
        <div>
            <label class="haven-label" style="margin-bottom:0.6rem;">{{ __('2FA Status on Sign In') }}</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:0.75rem;">
                <label id="card-2fa-disabled" style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem;border-radius:var(--h-radius);border:1px solid {{ ! $is2faEnabled ? 'var(--h-teal-ring)' : 'var(--h-border)' }};background:{{ ! $is2faEnabled ? 'rgba(20,184,166,0.05)' : 'var(--h-surface2)' }};cursor:pointer;transition:all 0.15s ease;">
                    <input type="radio" name="two_factor_enabled" value="0" {{ ! $is2faEnabled ? 'checked' : '' }} style="margin-top:0.2rem;accent-color:var(--h-teal);" onchange="toggle2faOptions(this.value)">
                    <div>
                        <span style="display:block;font-size:0.875rem;font-weight:600;color:var(--h-text);">{{ __('Disabled') }}</span>
                        <span style="display:block;font-size:0.75rem;color:var(--h-text3);">{{ __('Standard sign-in using email and password only.') }}</span>
                    </div>
                </label>

                <label id="card-2fa-enabled" style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem;border-radius:var(--h-radius);border:1px solid {{ $is2faEnabled ? 'var(--h-teal-ring)' : 'var(--h-border)' }};background:{{ $is2faEnabled ? 'rgba(20,184,166,0.05)' : 'var(--h-surface2)' }};cursor:pointer;transition:all 0.15s ease;">
                    <input type="radio" name="two_factor_enabled" value="1" {{ $is2faEnabled ? 'checked' : '' }} style="margin-top:0.2rem;accent-color:var(--h-teal);" onchange="toggle2faOptions(this.value)">
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
        <div id="channel-container" style="display:{{ $is2faEnabled ? 'block' : 'none' }};">
            <label class="haven-label" style="margin-bottom:0.6rem;">{{ __('Choose Your Preferred 2FA Channel') }}</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:0.75rem;margin-bottom:1rem;">
                {{-- Email Option (Brevo) --}}
                <label id="card-channel-email" style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem;border-radius:var(--h-radius);border:1px solid {{ $selectedChannel === 'email' ? 'var(--h-teal-ring)' : 'var(--h-border)' }};background:{{ $selectedChannel === 'email' ? 'rgba(20,184,166,0.05)' : 'var(--h-surface2)' }};cursor:pointer;transition:all 0.15s ease;">
                    <input type="radio" name="two_factor_channel" value="email" {{ $selectedChannel === 'email' ? 'checked' : '' }} style="margin-top:0.2rem;accent-color:var(--h-teal);" onchange="toggleChannel(this.value)">
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

                {{-- SMS Option (Firebase / Brevo) --}}
                <label id="card-channel-sms" style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem;border-radius:var(--h-radius);border:1px solid {{ $selectedChannel === 'sms' ? 'var(--h-teal-ring)' : 'var(--h-border)' }};background:{{ $selectedChannel === 'sms' ? 'rgba(20,184,166,0.05)' : 'var(--h-surface2)' }};cursor:pointer;transition:all 0.15s ease;">
                    <input type="radio" name="two_factor_channel" value="sms" {{ $selectedChannel === 'sms' ? 'checked' : '' }} style="margin-top:0.2rem;accent-color:var(--h-teal);" onchange="toggleChannel(this.value)">
                    <div>
                        <div style="display:flex;align-items:center;gap:0.4rem;">
                            <span style="font-size:0.875rem;font-weight:600;color:var(--h-text);">{{ __('SMS to Phone') }}</span>
                            <span class="h-badge h-badge-muted" style="font-size:0.6rem;">Firebase / SMS</span>
                        </div>
                        <span style="display:block;font-size:0.75rem;color:var(--h-text3);margin-top:0.2rem;">
                            {{ __('Instant 6-digit text message sent to your mobile.') }}
                        </span>
                    </div>
                </label>
            </div>

            {{-- Phone Number Input (Visible if SMS selected or phone already saved/errored) --}}
            <div id="phone-input-group" style="display:{{ ($selectedChannel === 'sms' || $errors->has('phone') || ! empty($user->phone)) ? 'block' : 'none' }};margin-top:1rem;padding:1rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid {{ $errors->has('phone') ? 'var(--h-red)' : 'var(--h-border)' }};">
                <label class="haven-label" for="phone" style="margin-bottom:0.35rem;">
                    {{ __('Mobile Phone Number') }}
                    <span id="phone-required-star" style="color:var(--h-red);display:{{ $selectedChannel === 'sms' ? 'inline' : 'none' }};">*</span>
                </label>
                <div style="display:flex;align-items:center;gap:0.65rem;flex-wrap:wrap;">
                    <input
                        id="phone"
                        name="phone"
                        type="tel"
                        value="{{ old('phone', $user->phone) }}"
                        class="haven-input {{ $errors->has('phone') ? 'haven-input-error' : '' }}"
                        placeholder="+639171234567 or 09171234567"
                        style="max-width:320px;"
                    >
                </div>
                <p style="font-size:0.75rem;color:var(--h-text3);margin-top:0.4rem;">
                    {{ __('Accepts +639171234567 or local PH format 09171234567 (automatically formatted to +63).') }}
                </p>
                @error('phone')
                    <p class="haven-error-msg" style="margin-top:0.35rem;">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;padding-top:0.5rem;">
            <button type="submit" class="haven-btn-primary">
                {{ __('Save Security Settings') }}
            </button>
            <button
                type="button"
                onclick="submitTest2fa()"
                class="haven-action-btn haven-action-btn-secondary"
                style="padding:0.62rem 1.1rem;font-size:0.84rem;cursor:pointer;display:inline-flex;align-items:center;gap:0.4rem;"
            >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                {{ __('Send Test 2FA Code') }}
            </button>
        </div>
    </form>

    <form id="test-2fa-form" method="POST" action="{{ route('profile.two-factor.test') }}" style="display:none;">
        @csrf
        <input type="hidden" name="two_factor_channel" id="test_two_factor_channel" value="{{ $selectedChannel }}">
        <input type="hidden" name="phone" id="test_two_factor_phone" value="{{ old('phone', $user->phone) }}">
    </form>

    <script>
        function toggle2faOptions(val) {
            const container = document.getElementById('channel-container');
            const cardDisabled = document.getElementById('card-2fa-disabled');
            const cardEnabled = document.getElementById('card-2fa-enabled');
            const enabled = String(val) === '1';

            if (container) {
                container.style.display = enabled ? 'block' : 'none';
            }
            if (cardDisabled) {
                cardDisabled.style.borderColor = !enabled ? 'var(--h-teal-ring)' : 'var(--h-border)';
                cardDisabled.style.background = !enabled ? 'rgba(20,184,166,0.05)' : 'var(--h-surface2)';
            }
            if (cardEnabled) {
                cardEnabled.style.borderColor = enabled ? 'var(--h-teal-ring)' : 'var(--h-border)';
                cardEnabled.style.background = enabled ? 'rgba(20,184,166,0.05)' : 'var(--h-surface2)';
            }
        }

        function toggleChannel(channel) {
            const phoneGroup = document.getElementById('phone-input-group');
            const reqStar = document.getElementById('phone-required-star');
            const cardEmail = document.getElementById('card-channel-email');
            const cardSms = document.getElementById('card-channel-sms');
            const isSms = channel === 'sms';

            if (phoneGroup) {
                const phoneInput = document.getElementById('phone');
                const hasPhoneVal = phoneInput && phoneInput.value.trim() !== '';
                phoneGroup.style.display = (isSms || hasPhoneVal) ? 'block' : 'none';
            }
            if (reqStar) {
                reqStar.style.display = isSms ? 'inline' : 'none';
            }
            if (cardEmail) {
                cardEmail.style.borderColor = !isSms ? 'var(--h-teal-ring)' : 'var(--h-border)';
                cardEmail.style.background = !isSms ? 'rgba(20,184,166,0.05)' : 'var(--h-surface2)';
            }
            if (cardSms) {
                cardSms.style.borderColor = isSms ? 'var(--h-teal-ring)' : 'var(--h-border)';
                cardSms.style.background = isSms ? 'rgba(20,184,166,0.05)' : 'var(--h-surface2)';
            }
        }

        function normalizePhoneJs(raw) {
            let s = (raw || '').replace(/[\s\-\(\)\.]+/g, '').trim();
            if (/^09\d{9}$/.test(s)) return '+63' + s.slice(1);
            if (/^9\d{9}$/.test(s)) return '+63' + s;
            if (/^639\d{9}$/.test(s)) return '+' + s;
            return s;
        }

        function showFirebaseTestBanner(msg, isError) {
            const box = document.getElementById('profile-firebase-test-banner');
            if (!box) return;
            box.style.display = 'block';
            box.style.background = isError ? 'var(--h-red-bg)' : 'var(--h-teal-bg)';
            box.style.border = '1px solid ' + (isError ? 'var(--h-red)' : 'var(--h-teal-ring)');
            box.style.color = isError ? 'var(--h-red)' : 'var(--h-teal)';
            box.textContent = msg;
        }

        let profileRecaptchaVerifier = null;
        let profileRecaptchaMode = 'invisible';
        let profileSmsInFlight = false;

        function initProfileRecaptchaVerifier(sizeMode, targetE164) {
            if (profileRecaptchaVerifier && profileRecaptchaMode === sizeMode) {
                return profileRecaptchaVerifier;
            }
            if (profileRecaptchaVerifier) {
                try {
                    profileRecaptchaVerifier.clear();
                } catch (e) {}
                profileRecaptchaVerifier = null;
            }
            const wrapper = document.getElementById('profile-recaptcha-wrapper');
            if (wrapper) {
                wrapper.innerHTML = '<div id="profile-recaptcha-container"></div>';
            }
            profileRecaptchaMode = sizeMode;
            profileRecaptchaVerifier = new firebase.auth.RecaptchaVerifier('profile-recaptcha-container', {
                size: sizeMode,
                theme: 'dark',
                callback: function() {
                    if (sizeMode === 'normal' && !profileSmsInFlight) {
                        executeProfileSmsTest(targetE164);
                    }
                }
            });
            return profileRecaptchaVerifier;
        }

        function executeProfileSmsTest(e164) {
            if (profileSmsInFlight) return;
            profileSmsInFlight = true;
            showFirebaseTestBanner('Requesting test SMS from Google Firebase to ' + e164 + '...', false);

            firebase.auth().signInWithPhoneNumber(e164, profileRecaptchaVerifier)
                .then(function() {
                    profileSmsInFlight = false;
                    showFirebaseTestBanner('Firebase SMS dispatched to ' + e164 + '! Check your phone for the 6-digit verification code.', false);
                })
                .catch(function(err) {
                    profileSmsInFlight = false;
                    const code = (err && err.code) ? err.code : '';
                    const msg = (err && err.message) ? err.message : 'Unknown Firebase error';

                    if ((code === 'auth/invalid-app-credential' || code === 'auth/captcha-check-failed' || msg.includes('invalid-app-credential')) && profileRecaptchaMode === 'invisible') {
                        showFirebaseTestBanner('Localhost security check required: Please check the "I\'m not a robot" box below to send your test SMS.', false);
                        const normalVerifier = initProfileRecaptchaVerifier('normal', e164);
                        normalVerifier.render();
                        return;
                    }

                    if (msg.includes('OPERATION_NOT_ALLOWED') || msg.includes('region')) {
                        showFirebaseTestBanner('Firebase error: Philippines (+63) is not enabled in Firebase Console → Authentication → Settings → SMS region policy. Enable Philippines (+63) there and try again.', true);
                    } else {
                        showFirebaseTestBanner('Firebase SMS error: ' + msg, true);
                    }
                });
        }

        function submitTest2fa() {
            const checkedChannel = document.querySelector('input[name="two_factor_channel"]:checked');
            const channel = checkedChannel ? checkedChannel.value : 'email';
            const phoneInput = document.getElementById('phone');

            // If Email channel selected, dispatch via Brevo backend
            if (channel !== 'sms') {
                document.getElementById('test_two_factor_channel').value = 'email';
                document.getElementById('test_two_factor_phone').value = phoneInput ? phoneInput.value : '';
                document.getElementById('test-2fa-form').submit();
                return;
            }

            // If SMS channel selected, dispatch directly via Firebase Phone Auth in browser
            const e164 = normalizePhoneJs(phoneInput ? phoneInput.value : '');
            if (!/^\+[1-9]\d{7,14}$/.test(e164)) {
                showFirebaseTestBanner('Please enter a valid mobile number (e.g. +639158148706 or 09158148706) before testing Firebase SMS.', true);
                return;
            }
            if (phoneInput) phoneInput.value = e164;

            const runFirebaseTest = function() {
                const cfg = @json($profileFirebaseConfig);
                if (!firebase.apps.length) {
                    firebase.initializeApp(cfg);
                }
                initProfileRecaptchaVerifier(profileRecaptchaMode, e164);
                if (profileRecaptchaMode === 'normal') {
                    profileRecaptchaVerifier.render().then(function() {
                        showFirebaseTestBanner('Please check the "I\'m not a robot" box below to send your test SMS to ' + e164 + '.', false);
                    });
                } else {
                    executeProfileSmsTest(e164);
                }
            };

            if (typeof firebase !== 'undefined' && firebase.auth) {
                runFirebaseTest();
            } else {
                const s1 = document.createElement('script');
                s1.src = 'https://www.gstatic.com/firebasejs/11.0.2/firebase-app-compat.js';
                s1.onload = function() {
                    const s2 = document.createElement('script');
                    s2.src = 'https://www.gstatic.com/firebasejs/11.0.2/firebase-auth-compat.js';
                    s2.onload = runFirebaseTest;
                    document.head.appendChild(s2);
                };
                document.head.appendChild(s1);
            }
        }

        window.addEventListener('DOMContentLoaded', function() {
            const checkedEnabled = document.querySelector('input[name="two_factor_enabled"]:checked');
            if (checkedEnabled) {
                toggle2faOptions(checkedEnabled.value);
            }
            const checkedChannel = document.querySelector('input[name="two_factor_channel"]:checked');
            if (checkedChannel) {
                toggleChannel(checkedChannel.value);
            }
        });
    </script>
</section>
