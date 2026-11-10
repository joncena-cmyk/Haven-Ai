<x-guest-layout title="Two-Factor Authentication">

    {{-- Back to Sign In link --}}
    <div style="margin-bottom:1.5rem;">
        <a href="{{ route('login') }}" style="display:inline-flex;align-items:center;gap:0.45rem;font-size:0.8rem;color:var(--h-text3);text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='var(--h-teal)'" onmouseout="this.style.color='var(--h-text3)'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
            </svg>
            <span>Back to sign in</span>
        </a>
    </div>

    {{-- Icon & Heading --}}
    <div style="text-align:center;margin-bottom:1.5rem;">
        <div style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%;background:rgba(20,184,166,0.1);border:1px solid rgba(20,184,166,0.25);margin-bottom:1rem;color:var(--h-teal);">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>
        <h2 class="haven-auth-title" style="margin-bottom:0.4rem;">Two-Factor Authentication</h2>
        <p class="haven-auth-desc" style="margin-bottom:0.25rem;">
            Security verification code via
            <strong style="color:var(--h-teal);">{{ strtoupper($channel) }}</strong> to:
        </p>
        <span style="font-weight:600;color:var(--h-teal);font-size:0.95rem;">
            {{ $target }}
        </span>
    </div>

    {{-- Success Status / Flash Messages --}}
    @if (session('status'))
        <div class="haven-alert-success" role="alert" style="margin-bottom:1.25rem;">
            {{ session('status') }}
        </div>
    @endif

    {{-- Status Banner for Firebase SMS Sending --}}
    <div id="firebase-status" style="display:none;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.65rem 1rem;margin-bottom:1.25rem;font-size:0.8rem;color:var(--h-text2);text-align:center;">
        <span id="firebase-status-text">Preparing SMS delivery...</span>
    </div>

    <form method="POST" action="{{ route('two-factor.challenge.store') }}" id="two-factor-form">
        @csrf
        <input type="hidden" name="firebase_token" id="firebase_token">

        {{-- 6-Digit Code Input --}}
        <div style="margin-bottom:1.5rem;">
            <label class="haven-label" for="code" style="text-align:center;display:block;">
                Security Verification Code
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
            <p id="client-error" class="haven-error-msg" style="display:none;text-align:center;margin-top:0.4rem;"></p>
            @error('code')
                <p class="haven-error-msg" style="text-align:center;margin-top:0.4rem;">{{ $message }}</p>
            @enderror
            <p style="text-align:center;font-size:0.75rem;color:var(--h-text3);margin-top:0.4rem;">
                Code is valid for 10 minutes.
            </p>
        </div>

        {{-- reCAPTCHA container for Firebase (Invisible first, auto-switches to Visible checkbox if localhost requires human check) --}}
        <div id="recaptcha-wrapper" style="display:flex;justify-content:center;margin-bottom:1rem;">
            <div id="recaptcha-container"></div>
        </div>

        <button type="submit" id="submit-btn" class="haven-btn-primary" style="width:100%;margin-bottom:1.25rem;">
            Authenticate &amp; Continue
        </button>
    </form>

    {{-- Fallback Actions & Channel Switcher --}}
    <div style="text-align:center;padding-top:1rem;border-top:1px solid var(--h-border);display:flex;flex-direction:column;gap:0.75rem;">
        {{-- Resend Code --}}
        @if ($channel === 'email')
            <form method="POST" action="{{ route('two-factor.challenge.resend') }}">
                @csrf
                <p style="font-size:0.8rem;color:var(--h-text3);margin:0 0 0.25rem 0;">Didn't get the email?</p>
                <button type="submit" class="haven-link" style="background:none;border:none;cursor:pointer;font-size:0.85rem;font-weight:600;">
                    Resend email code
                </button>
            </form>
        @else
            <div>
                <p style="font-size:0.8rem;color:var(--h-text3);margin:0 0 0.25rem 0;">Didn't receive the SMS?</p>
                <button type="button" onclick="sendFirebaseSms(true)" class="haven-link" style="background:none;border:none;cursor:pointer;font-size:0.85rem;font-weight:600;">
                    Resend SMS code
                </button>
            </div>
        @endif

        {{-- Switch to alternative channel --}}
        @if ($channel === 'sms')
            <form method="POST" action="{{ route('two-factor.challenge.switch') }}" style="margin-top:0.25rem;">
                @csrf
                <button type="submit" style="background:none;border:none;cursor:pointer;font-size:0.78rem;color:var(--h-teal);text-decoration:underline;">
                    Can't receive SMS? Switch to Email verification
                </button>
            </form>
        @elseif ($hasPhone)
            <form method="POST" action="{{ route('two-factor.challenge.switch') }}" style="margin-top:0.25rem;">
                @csrf
                <button type="submit" style="background:none;border:none;cursor:pointer;font-size:0.78rem;color:var(--h-teal);text-decoration:underline;">
                    Prefer SMS? Switch to Phone verification
                </button>
            </form>
        @endif
    </div>

    {{-- Firebase Phone Auth Integration Script (v11.0.2 with Identity Platform support) --}}
    @if ($channel === 'sms' && ! empty($userPhone))
        <script src="https://www.gstatic.com/firebasejs/11.0.2/firebase-app-compat.js"></script>
        <script src="https://www.gstatic.com/firebasejs/11.0.2/firebase-auth-compat.js"></script>
        <script>
            const firebaseConfig = @json($firebaseConfig);
            if (!firebase.apps.length) {
                firebase.initializeApp(firebaseConfig);
            }

            let confirmationResult = null;
            let recaptchaVerifier = null;
            let recaptchaMode = 'invisible';
            let smsDispatchInFlight = false;
            const targetPhone = @json($userPhone);

            function showStatus(msg, isError = false) {
                const box = document.getElementById('firebase-status');
                const txt = document.getElementById('firebase-status-text');
                box.style.display = 'block';
                txt.textContent = msg;
                txt.style.color = isError ? 'var(--h-red)' : 'var(--h-text2)';
            }

            function initRecaptchaVerifier(sizeMode) {
                if (recaptchaVerifier && recaptchaMode === sizeMode) {
                    return recaptchaVerifier;
                }
                if (recaptchaVerifier) {
                    try {
                        recaptchaVerifier.clear();
                    } catch (e) {}
                    recaptchaVerifier = null;
                }
                const wrapper = document.getElementById('recaptcha-wrapper');
                if (wrapper) {
                    wrapper.innerHTML = '<div id="recaptcha-container"></div>';
                }
                recaptchaMode = sizeMode;
                recaptchaVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', {
                    'size': sizeMode,
                    'theme': 'dark',
                    'callback': function() {
                        if (sizeMode === 'normal' && !smsDispatchInFlight) {
                            executePhoneSignIn();
                        }
                    },
                    'expired-callback': function() {
                        showStatus('Security check expired. Please click the checkbox or Resend SMS code again.', true);
                    }
                });
                return recaptchaVerifier;
            }

            const hasServerError = @json($errors->any());
            const smsStorageKey = 'haven_sms_sent_at_' + targetPhone;

            function executePhoneSignIn() {
                if (smsDispatchInFlight) return;
                smsDispatchInFlight = true;
                showStatus('Requesting SMS verification code from Google Firebase...');

                firebase.auth().signInWithPhoneNumber(targetPhone, recaptchaVerifier)
                    .then(function(result) {
                        smsDispatchInFlight = false;
                        confirmationResult = result;
                        sessionStorage.setItem(smsStorageKey, String(Date.now()));
                        showStatus('SMS code sent via Firebase! Check your phone (' + targetPhone + ') for the 6-digit text.');
                    })
                    .catch(function(error) {
                        smsDispatchInFlight = false;
                        console.error('Firebase SMS error:', error);
                        const code = (error && error.code) ? error.code : '';
                        const msg = (error && error.message) ? error.message : 'Unable to send SMS';

                        if ((code === 'auth/invalid-app-credential' || code === 'auth/captcha-check-failed' || msg.includes('invalid-app-credential')) && recaptchaMode === 'invisible') {
                            showStatus('Localhost security check required: Please check the "I\'m not a robot" box below to send your SMS code.', false);
                            const normalVerifier = initRecaptchaVerifier('normal');
                            normalVerifier.render();
                            return;
                        }

                        if (msg.includes('OPERATION_NOT_ALLOWED') || msg.includes('region')) {
                            showStatus('Firebase SMS blocked: Philippines (+63) is not enabled in Firebase Console → Authentication → Settings → SMS region policy.', true);
                        } else if (msg.includes('too-many-requests') || code === 'auth/too-many-requests') {
                            showStatus('Firebase rate limit reached (auth/too-many-requests): Too many SMS requests were sent to ' + targetPhone + ' in a short time. Wait ~15–30 minutes for Google\'s cooldown, or click "Switch to Email verification" below.', true);
                        } else {
                            showStatus('Firebase SMS error: ' + msg, true);
                        }
                    });
            }

            function sendFirebaseSms(isManualResend = false) {
                const lastSent = parseInt(sessionStorage.getItem(smsStorageKey) || '0', 10);
                const elapsedSec = Math.floor((Date.now() - lastSent) / 1000);
                if (!isManualResend && lastSent > 0 && elapsedSec < 60) {
                    showStatus('SMS code was already requested ' + elapsedSec + 's ago. Check your phone or click "Resend SMS code" below.', false);
                    return;
                }

                initRecaptchaVerifier(recaptchaMode);
                if (recaptchaMode === 'normal') {
                    recaptchaVerifier.render().then(function() {
                        showStatus('Please check the "I\'m not a robot" box above to send your SMS code.');
                    });
                } else {
                    executePhoneSignIn();
                }
            }

            // Wait for full window load (including scripts) before initializing Firebase reCAPTCHA
            window.addEventListener('load', function() {
                if (!hasServerError) {
                    sendFirebaseSms(false);
                }
            });

            // Intercept form submission to confirm code with Firebase
            document.getElementById('two-factor-form').addEventListener('submit', function(e) {
                const form = this;
                const code = document.getElementById('code').value.trim();
                const errBox = document.getElementById('client-error');
                errBox.style.display = 'none';

                if (confirmationResult && code.length === 6) {
                    e.preventDefault();
                    document.getElementById('submit-btn').disabled = true;
                    showStatus('Verifying SMS code with Google Firebase...');

                    confirmationResult.confirm(code)
                        .then(function(userCredential) {
                            return userCredential.user.getIdToken();
                        })
                        .then(function(idToken) {
                            document.getElementById('firebase_token').value = idToken;
                            form.submit();
                        })
                        .catch(function(error) {
                            console.error('Code confirmation error:', error);
                            document.getElementById('submit-btn').disabled = false;
                            errBox.textContent = 'Invalid SMS verification code. Please check the 6-digit code and try again.';
                            errBox.style.display = 'block';
                            showStatus('Invalid verification code.', true);
                        });
                }
            });
        </script>
    @endif

</x-guest-layout>
