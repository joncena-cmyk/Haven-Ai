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

        {{-- Invisible reCAPTCHA container for Firebase --}}
        <div id="recaptcha-container"></div>

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
                <button type="button" onclick="sendFirebaseSms()" class="haven-link" style="background:none;border:none;cursor:pointer;font-size:0.85rem;font-weight:600;">
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

    {{-- Firebase Phone Auth Integration Script --}}
    @if ($channel === 'sms' && ! empty($userPhone))
        <script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
        <script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-auth-compat.js"></script>
        <script>
            const firebaseConfig = @json($firebaseConfig);
            if (!firebase.apps.length) {
                firebase.initializeApp(firebaseConfig);
            }

            let confirmationResult = null;
            let recaptchaVerifier = null;
            const targetPhone = @json($userPhone);

            function showStatus(msg, isError = false) {
                const box = document.getElementById('firebase-status');
                const txt = document.getElementById('firebase-status-text');
                box.style.display = 'block';
                txt.textContent = msg;
                txt.style.color = isError ? 'var(--h-red)' : 'var(--h-text2)';
            }

            function sendFirebaseSms() {
                showStatus('Requesting SMS verification code from Google...');
                
                if (!recaptchaVerifier) {
                    recaptchaVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', {
                        'size': 'invisible',
                        'callback': function(response) {
                            // reCAPTCHA solved
                        }
                    });
                }

                firebase.auth().signInWithPhoneNumber(targetPhone, recaptchaVerifier)
                    .then(function(result) {
                        confirmationResult = result;
                        showStatus('SMS code sent! Check your phone for the 6-digit text.');
                    })
                    .catch(function(error) {
                        console.error('Firebase SMS error:', error);
                        showStatus('Failed to send SMS: ' + (error.message || 'Check connection'), true);
                    });
            }

            // Auto-trigger SMS on page load
            window.addEventListener('DOMContentLoaded', function() {
                sendFirebaseSms();
            });

            // Intercept form submission to confirm code with Firebase
            document.getElementById('two-factor-form').addEventListener('submit', function(e) {
                const code = document.getElementById('code').value.trim();
                const errBox = document.getElementById('client-error');
                errBox.style.display = 'none';

                if (confirmationResult && code.length === 6) {
                    e.preventDefault();
                    document.getElementById('submit-btn').disabled = true;
                    showStatus('Verifying code with Google...');

                    confirmationResult.confirm(code)
                        .then(function(userCredential) {
                            return userCredential.user.getIdToken();
                        })
                        .then(function(idToken) {
                            document.getElementById('firebase_token').value = idToken;
                            document.getElementById('two-factor-form').submit();
                        })
                        .catch(function(error) {
                            console.error('Code confirmation error:', error);
                            document.getElementById('submit-btn').disabled = false;
                            errBox.textContent = 'Invalid verification code. Please check the code and try again.';
                            errBox.style.display = 'block';
                            showStatus('Invalid code.', true);
                        });
                }
            });
        </script>
    @endif

</x-guest-layout>
