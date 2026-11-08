<section>
    <header style="margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid var(--h-border);">
        <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.35rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--h-red)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <h2 style="font-size:1.15rem;font-weight:700;color:var(--h-text);margin:0;">
                {{ __('Account Ownership & Control') }}
            </h2>
        </div>

        <p style="font-size:0.82rem;color:var(--h-text3);margin:0;line-height:1.5;">
            {{ __('Manage permanent account deletion and connected vehicle data removal.') }}
        </p>
    </header>

    {{-- Facebook-style Action Card --}}
    <div style="background:var(--h-surface2);border:1px solid rgba(239,68,68,0.28);border-radius:var(--h-radius-lg);padding:1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1.25rem;flex-wrap:wrap;">
        <div style="max-width:520px;">
            <div style="font-size:0.92rem;font-weight:700;color:var(--h-text);margin-bottom:0.3rem;">
                {{ __('Permanently Delete Account') }}
            </div>
            <p style="font-size:0.8rem;color:var(--h-text3);margin:0;line-height:1.5;">
                {{ __('Deleting your account is permanent. All registered vehicles, ESP32 telemetry keys, audio/vibration recordings, and ML diagnostic reports will be purged immediately.') }}
            </p>
        </div>

        <button
            type="button"
            onclick="document.getElementById('haven-delete-user-modal').style.display='flex'"
            class="haven-action-btn haven-action-btn-danger"
            style="padding:0.6rem 1.2rem;font-size:0.82rem;font-weight:600;cursor:pointer;white-space:nowrap;"
        >
            {{ __('Delete Account') }}
        </button>
    </div>

    {{-- Custom Dark/Light Themed Confirmation Modal --}}
    <div
        id="haven-delete-user-modal"
        style="display:{{ $errors->userDeletion->isNotEmpty() ? 'flex' : 'none' }};position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.72);backdrop-filter:blur(3px);align-items:center;justify-content:center;padding:1rem;"
        onclick="if(event.target === this) this.style.display='none'"
    >
        <div style="background:var(--h-surface);border:1px solid var(--h-border2);border-radius:var(--h-radius-xl);padding:1.75rem;max-width:460px;width:100%;box-shadow:0 16px 48px rgba(0,0,0,0.65);">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <div style="display:flex;align-items:center;gap:0.65rem;margin-bottom:0.75rem;">
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--h-red-bg);border:1px solid rgba(239,68,68,0.35);display:flex;align-items:center;justify-content:center;color:var(--h-red);flex-shrink:0;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </div>
                    <h2 style="font-size:1.05rem;font-weight:700;color:var(--h-text);margin:0;">
                        {{ __('Confirm Permanent Account Deletion') }}
                    </h2>
                </div>

                <p style="font-size:0.82rem;color:var(--h-text2);line-height:1.55;margin:0 0 1.25rem 0;">
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                </p>

                <div style="margin-bottom:1.25rem;">
                    <label for="delete_account_password" class="haven-label">{{ __('Confirm Your Password') }}</label>
                    <input
                        id="delete_account_password"
                        name="password"
                        type="password"
                        class="haven-input {{ $errors->userDeletion->has('password') ? 'haven-input-error' : '' }}"
                        placeholder="{{ __('Enter your password') }}"
                        required
                    />
                    @if ($errors->userDeletion->has('password'))
                        <p class="haven-error-msg">{{ $errors->userDeletion->first('password') }}</p>
                    @endif
                </div>

                <div style="display:flex;justify-content:flex-end;gap:0.65rem;">
                    <button
                        type="button"
                        onclick="document.getElementById('haven-delete-user-modal').style.display='none'"
                        class="haven-action-btn haven-action-btn-secondary"
                        style="padding:0.55rem 1.1rem;font-size:0.82rem;cursor:pointer;"
                    >
                        {{ __('Cancel') }}
                    </button>

                    <button
                        type="submit"
                        class="haven-action-btn haven-action-btn-danger"
                        style="padding:0.55rem 1.2rem;font-size:0.82rem;font-weight:600;cursor:pointer;"
                    >
                        {{ __('Delete Account') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
