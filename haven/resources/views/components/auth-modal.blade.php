{{-- Haven AI — Modal Auth Popup Component with Smooth Entrance & Exit Animations --}}
<style>
    .haven-modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 99999;
        background: rgba(5, 7, 10, 0.78);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
        padding: 1rem;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.28s;
    }

    .haven-modal-backdrop.is-open {
        display: flex;
        opacity: 1;
        visibility: visible;
    }

    .haven-modal-card {
        position: relative;
        width: 100%;
        max-width: 440px;
        background: var(--h-surface, #0f172a);
        border: 1px solid var(--h-border, rgba(255, 255, 255, 0.12));
        border-radius: 24px !important;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
        padding: 2.25rem;
        box-sizing: border-box;
        transform: scale(0.92) translateY(24px);
        opacity: 0;
        transition: transform 0.32s cubic-bezier(0.175, 0.885, 0.32, 1.2), opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1);
        will-change: transform, opacity;
        overflow: hidden;
    }

    .haven-modal-card input[type="text"],
    .haven-modal-card input[type="email"],
    .haven-modal-card input[type="password"],
    .haven-modal-card button,
    .haven-modal-card .haven-btn-google,
    .haven-modal-card .haven-btn-solid {
        border-radius: 12px !important;
    }

    .haven-modal-card .haven-modal-badge-icon {
        border-radius: 8px !important;
    }

    /* Custom Accessible "Remember me on this device" Checkbox */
    .haven-remember-wrap {
        display: inline-flex;
        align-items: center;
        gap: 0.65rem;
        margin-bottom: 1.25rem;
        cursor: pointer;
        user-select: none;
        -webkit-user-select: none;
        position: relative;
    }

    .haven-remember-input {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        margin: 0;
        pointer-events: none;
    }

    .haven-remember-box {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
        background: var(--h-surface-2, rgba(255, 255, 255, 0.06));
        border: 1.5px solid var(--h-border, rgba(255, 255, 255, 0.22));
        border-radius: 5px !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        box-sizing: border-box;
    }

    [data-theme="light"] .haven-remember-box {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .haven-remember-check {
        opacity: 0;
        transform: scale(0.6);
        transition: opacity 0.16s ease, transform 0.16s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        color: #ffffff;
    }

    /* Checked state */
    .haven-remember-input:checked + .haven-remember-box {
        background: #0284c7 !important;
        border-color: #0284c7 !important;
        box-shadow: 0 0 0 1px rgba(56, 189, 248, 0.4), 0 2px 8px rgba(2, 132, 199, 0.35);
    }

    .haven-remember-input:checked + .haven-remember-box .haven-remember-check {
        opacity: 1;
        transform: scale(1);
    }

    /* Focus & Hover states */
    .haven-remember-wrap:hover .haven-remember-box {
        border-color: #0284c7;
    }

    .haven-remember-input:focus-visible + .haven-remember-box {
        outline: none;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.35);
        border-color: #38bdf8;
    }

    .haven-remember-text {
        font-size: 0.82rem;
        color: var(--h-muted, #94a3b8);
        transition: color 0.15s ease;
        line-height: 1.2;
    }

    [data-theme="light"] .haven-remember-text {
        color: #64748b;
    }

    .haven-remember-wrap:hover .haven-remember-text {
        color: var(--h-text, #ffffff);
    }

    [data-theme="light"] .haven-remember-wrap:hover .haven-remember-text {
        color: #0f172a;
    }

    @keyframes havenSpin {
        to { transform: rotate(360deg); }
    }

    .haven-modal-backdrop.is-open .haven-modal-card {
        transform: scale(1) translateY(0);
        opacity: 1;
    }

    .haven-modal-backdrop.is-closing {
        opacity: 0;
    }

    .haven-modal-backdrop.is-closing .haven-modal-card {
        transform: scale(0.93) translateY(18px);
        opacity: 0;
        transition: transform 0.22s cubic-bezier(0.4, 0, 1, 1), opacity 0.2s ease;
    }
</style>

<div id="haven-auth-modal" class="haven-modal-backdrop" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="haven-modal-card">
        
        {{-- Close Button --}}
        <button type="button" onclick="closeAuthModal()" aria-label="Close modal" style="position:absolute;top:1rem;right:1rem;background:transparent;border:none;color:var(--h-muted);cursor:pointer;padding:0.4rem;display:flex;align-items:center;justify-content:center;transition:color 0.15s;" onmouseover="this.style.color='var(--h-text)'" onmouseout="this.style.color='var(--h-muted)'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        {{-- Header --}}
        <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.75rem;">
            <div class="haven-modal-badge-icon" style="width:26px;height:26px;display:flex;align-items:center;justify-content:center;background:rgba(2,132,199,0.12);border:1px solid rgba(2,132,199,0.4);">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--h-blue)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
            </div>
            <span style="font-weight:900;font-size:0.95rem;letter-spacing:0.14em;color:var(--h-text);text-transform:uppercase;">
                HAVEN <span style="color:var(--h-blue-glow);">AI</span>
            </span>
        </div>

        <h2 style="font-size:1.35rem;font-weight:800;color:var(--h-text);margin:0 0 0.3rem;letter-spacing:-0.02em;">Welcome Back</h2>
        <p style="font-size:0.82rem;color:var(--h-muted);margin:0 0 1.5rem;line-height:1.45;">Sign in to your account to monitor vehicle telemetry & diagnostics.</p>

        {{-- Google OAuth Button (Future Ready) --}}
        <button type="button" onclick="handleGoogleSignIn()" class="haven-btn-google" style="width:100%;display:flex;align-items:center;justify-content:center;gap:0.75rem;padding:0.75rem 1rem;background:var(--h-surface-2, #ffffff);border:1px solid var(--h-border);color:var(--h-text);font-size:0.85rem;font-weight:600;cursor:pointer;transition:all 0.15s;margin-bottom:1.25rem;">
            <svg width="18" height="18" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            <span>Continue with Google</span>
        </button>

        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;">
            <div style="flex:1;height:1px;background:var(--h-border);"></div>
            <span style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.08em;color:var(--h-muted);">or email</span>
            <div style="flex:1;height:1px;background:var(--h-border);"></div>
        </div>

        {{-- Standard Email & Password Form --}}
        <form method="POST" action="{{ route('login') }}" id="modal-login-form">
            @csrf

            {{-- Dynamic AJAX Error Alert --}}
            <div id="modal-error-alert" style="display:none;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.35);color:#f87171;border-radius:10px;padding:0.65rem 0.85rem;font-size:0.8rem;margin-bottom:1rem;line-height:1.4;align-items:center;gap:0.5rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span id="modal-error-text">These credentials do not match our records.</span>
            </div>

            @if ($errors->any())
                <div id="modal-server-error" style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.35);color:#f87171;border-radius:10px;padding:0.65rem 0.85rem;font-size:0.8rem;margin-bottom:1rem;line-height:1.4;display:flex;align-items:center;gap:0.5rem;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <div style="margin-bottom:1rem;">
                <label class="haven-label" for="modal_email" style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--h-muted);margin-bottom:0.35rem;display:block;">Email address</label>
                <input id="modal_email" type="email" name="email" required autocomplete="username" placeholder="you@example.com" class="haven-input" style="width:100%;padding:0.65rem 0.85rem;background:var(--h-surface-2);border:1px solid var(--h-border);color:var(--h-text);font-size:0.875rem;box-sizing:border-box;">
            </div>

            <div style="margin-bottom:1rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.35rem;">
                    <label class="haven-label" for="modal_password" style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--h-muted);margin:0;">Password</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" style="font-size:0.75rem;color:var(--h-blue-glow);text-decoration:none;">Forgot?</a>
                    @endif
                </div>
                <input id="modal_password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••" class="haven-input" style="width:100%;padding:0.65rem 0.85rem;background:var(--h-surface-2);border:1px solid var(--h-border);color:var(--h-text);font-size:0.875rem;box-sizing:border-box;">
            </div>

            {{-- Remember me on this device --}}
            <label class="haven-remember-wrap" for="modal_remember">
                <input id="modal_remember" type="checkbox" name="remember" class="haven-remember-input" value="1">
                <span class="haven-remember-box" aria-hidden="true">
                    <svg class="haven-remember-check" width="11" height="9" viewBox="0 0 11 9" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="1.5 4.5 4 7 9.5 1.5"/>
                    </svg>
                </span>
                <span class="haven-remember-text">Remember me on this device</span>
            </label>

            <button type="submit" id="modal-login-btn" class="haven-btn-solid" style="width:100%;padding:0.75rem 1rem;font-size:0.8rem;font-weight:700;letter-spacing:0.08em;background:var(--h-blue);color:#ffffff;border:1px solid var(--h-blue);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.5rem;">
                <span>Sign In to HAVEN</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </form>

        <p style="text-align:center;margin:1.25rem 0 0;font-size:0.8rem;color:var(--h-muted);">
            Don't have an account?
            <a href="{{ route('register') }}" style="color:var(--h-blue-glow);font-weight:600;text-decoration:none;">Create one</a>
        </p>
    </div>
</div>

<script>
    let isModalAnimating = false;

    function restoreRememberedDevice() {
        try {
            const savedEmail = localStorage.getItem('haven_remember_email');
            const shouldRemember = localStorage.getItem('haven_remember_device') === '1';
            const emailInput = document.getElementById('modal_email');
            const rememberCheckbox = document.getElementById('modal_remember');

            if (savedEmail && emailInput && !emailInput.value) {
                emailInput.value = savedEmail;
            }
            if (rememberCheckbox) {
                rememberCheckbox.checked = shouldRemember;
            }
        } catch (e) {
            // localStorage might be unavailable in restricted environments
        }
    }

    function openAuthModal(e) {
        if (e) e.preventDefault();
        const modal = document.getElementById('haven-auth-modal');
        if (!modal || isModalAnimating) return;

        restoreRememberedDevice();

        isModalAnimating = true;
        modal.classList.remove('is-closing');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Force browser reflow so transition triggers cleanly
        void modal.offsetWidth;

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        setTimeout(() => {
            isModalAnimating = false;
            const emailInput = document.getElementById('modal_email');
            const passwordInput = document.getElementById('modal_password');
            if (emailInput && emailInput.value) {
                if (passwordInput) passwordInput.focus();
            } else if (emailInput) {
                emailInput.focus();
            }
        }, 320);
    }

    function closeAuthModal() {
        const modal = document.getElementById('haven-auth-modal');
        if (!modal || isModalAnimating || !modal.classList.contains('is-open')) return;

        isModalAnimating = true;
        modal.classList.add('is-closing');
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        setTimeout(() => {
            modal.style.display = 'none';
            modal.classList.remove('is-closing');
            document.body.style.overflow = '';
            isModalAnimating = false;
        }, 240);
    }

    // Close on backdrop click (outside card)
    document.addEventListener('DOMContentLoaded', function() {
        restoreRememberedDevice();

        @if ($errors->any())
            openAuthModal();
        @endif

        const modal = document.getElementById('haven-auth-modal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeAuthModal();
                }
            });
        }

        const modalForm = document.getElementById('modal-login-form');
        const submitBtn = document.getElementById('modal-login-btn');
        const errorAlert = document.getElementById('modal-error-alert');
        const errorText = document.getElementById('modal-error-text');

        if (modalForm && submitBtn) {
            modalForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Clear previous errors
                if (errorAlert) errorAlert.style.display = 'none';
                const serverErr = document.getElementById('modal-server-error');
                if (serverErr) serverErr.style.display = 'none';

                // Save remember me preference
                try {
                    const rememberCheckbox = document.getElementById('modal_remember');
                    const emailInput = document.getElementById('modal_email');
                    if (rememberCheckbox && rememberCheckbox.checked && emailInput && emailInput.value.trim()) {
                        localStorage.setItem('haven_remember_device', '1');
                        localStorage.setItem('haven_remember_email', emailInput.value.trim());
                    } else {
                        localStorage.removeItem('haven_remember_device');
                        localStorage.removeItem('haven_remember_email');
                    }
                } catch (err) {}

                // Button loading feedback
                const originalHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.85';
                submitBtn.style.cursor = 'wait';
                submitBtn.innerHTML = `
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:havenSpin 0.9s linear infinite;">
                        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                    </svg>
                    <span>Signing In...</span>
                `;

                try {
                    const formData = new FormData(modalForm);
                    const response = await fetch(modalForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        window.location.href = data.redirect || '{{ route('dashboard', absolute: false) }}';
                        return;
                    }

                    // On error (422 or 401)
                    const data = await response.json().catch(() => null);
                    let message = 'These credentials do not match our records.';
                    if (data && data.errors) {
                        const firstKey = Object.keys(data.errors)[0];
                        if (firstKey && data.errors[firstKey][0]) {
                            message = data.errors[firstKey][0];
                        }
                    } else if (data && data.message) {
                        message = data.message;
                    }

                    if (errorAlert && errorText) {
                        errorText.textContent = message;
                        errorAlert.style.display = 'flex';
                    }

                    const pwInput = document.getElementById('modal_password');
                    if (pwInput) {
                        pwInput.value = '';
                        pwInput.focus();
                    }
                } catch (err) {
                    modalForm.submit();
                    return;
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                    submitBtn.innerHTML = originalHtml;
                }
            });
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAuthModal();
        }
    });

    function handleGoogleSignIn() {
        alert('Google Sign-In integration is primed. Standard email & password authentication is active below.');
    }
</script>


