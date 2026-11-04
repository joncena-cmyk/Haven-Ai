<x-admin-layout title="Admin Security & Password">
    <div style="margin-bottom:1.25rem;">
        <span class="h-badge h-badge-teal" style="font-size:0.65rem;">ISO/IEC 27001 &middot; ACCESS GOVERNANCE</span>
        <h1 style="font-size:1.45rem;font-weight:800;color:var(--h-text);margin:0.3rem 0 0;">Administrator Credentials &amp; Access Control</h1>
        <p style="font-size:0.82rem;color:var(--h-text3);margin:0.25rem 0 0;">
            Dedicated multi-admin management (Guard: <code style="color:var(--h-teal);">admin</code>). Drivers and administrators are strictly separated.
        </p>
    </div>

    @include('admin.partials.tabs')

    {{-- Admin Roster Card --}}
    <div class="h-card" style="margin-bottom:1.5rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;margin-bottom:1rem;flex-wrap:wrap;">
            <div>
                <h2 style="margin:0;font-size:1rem;font-weight:800;color:var(--h-text);">Active Administrators Roster</h2>
                <p style="font-size:0.76rem;color:var(--h-text3);margin:0.2rem 0 0;">All accounts authorized with full administrative privileges</p>
            </div>
            <span class="h-badge h-badge-teal">{{ count($admins) }} {{ Str::plural('Administrator', count($admins)) }}</span>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.8rem;text-align:left;">
                <thead>
                    <tr style="border-bottom:1px solid var(--h-border);color:var(--h-text3);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;">
                        <th style="padding:0.5rem 0.75rem;">Administrator</th>
                        <th style="padding:0.5rem 0.75rem;">Email Address</th>
                        <th style="padding:0.5rem 0.75rem;">Last Login</th>
                        <th style="padding:0.5rem 0.75rem;">Created</th>
                        <th style="padding:0.5rem 0.75rem;text-align:right;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($admins as $adm)
                        <tr style="border-bottom:1px solid var(--h-border);">
                            <td style="padding:0.65rem 0.75rem;font-weight:700;color:var(--h-text);display:flex;align-items:center;gap:0.5rem;">
                                <div style="width:28px;height:28px;border-radius:6px;background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);display:flex;align-items:center;justify-content:center;color:var(--h-teal);font-size:0.75rem;font-weight:800;">
                                    {{ strtoupper(substr($adm->name, 0, 1)) }}
                                </div>
                                <span>{{ $adm->name }}</span>
                                @if($adm->id === $currentAdmin->id)
                                    <span class="h-badge h-badge-teal" style="font-size:0.58rem;padding:0.15rem 0.4rem;">YOU</span>
                                @endif
                            </td>
                            <td style="padding:0.65rem 0.75rem;color:var(--h-text2);">{{ $adm->email }}</td>
                            <td style="padding:0.65rem 0.75rem;color:var(--h-text3);">
                                {{ $adm->last_login_at ? $adm->last_login_at->diffForHumans() : 'Never' }}
                            </td>
                            <td style="padding:0.65rem 0.75rem;color:var(--h-text3);">
                                {{ $adm->created_at->format('M d, Y') }}
                            </td>
                            <td style="padding:0.65rem 0.75rem;text-align:right;">
                                <span class="h-badge h-badge-teal" style="font-size:0.65rem;">ACTIVE</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Forms Grid --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.25rem;margin-bottom:1.5rem;">
        {{-- Card 1: Change My Password --}}
        <div class="h-card">
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(56,189,248,0.12);border:1px solid rgba(56,189,248,0.25);display:flex;align-items:center;justify-content:center;color:#38bdf8;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div>
                    <h3 style="margin:0;font-size:0.95rem;font-weight:800;color:var(--h-text);">Change My Password</h3>
                    <p style="margin:0.15rem 0 0;font-size:0.74rem;color:var(--h-text3);">Update credentials for {{ $currentAdmin->email }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.security.password') }}" style="display:flex;flex-direction:column;gap:0.85rem;">
                @csrf

                <div>
                    <label style="display:block;font-size:0.74rem;font-weight:600;color:var(--h-text2);margin-bottom:0.3rem;">Current Password</label>
                    <input type="password" name="current_password" class="haven-input @error('current_password') haven-input-error @enderror" required autocomplete="current-password" placeholder="••••••••">
                    @error('current_password')
                        <div style="color:var(--h-red);font-size:0.72rem;margin-top:0.25rem;">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label style="display:block;font-size:0.74rem;font-weight:600;color:var(--h-text2);margin-bottom:0.3rem;">New Password</label>
                    <input type="password" name="password" class="haven-input @error('password') haven-input-error @enderror" required autocomplete="new-password" placeholder="Minimum 8 characters">
                    @error('password')
                        <div style="color:var(--h-red);font-size:0.72rem;margin-top:0.25rem;">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label style="display:block;font-size:0.74rem;font-weight:600;color:var(--h-text2);margin-bottom:0.3rem;">Confirm New Password</label>
                    <input type="password" name="password_confirmation" class="haven-input" required autocomplete="new-password" placeholder="Re-type new password">
                </div>

                <button type="submit" class="haven-btn-primary" style="margin-top:0.4rem;padding:0.65rem 1rem;">
                    Update Administrator Password
                </button>
            </form>
        </div>

        {{-- Card 2: Provision New Administrator --}}
        <div class="h-card">
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(74,222,128,0.12);border:1px solid rgba(74,222,128,0.25);display:flex;align-items:center;justify-content:center;color:#4ade80;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                </div>
                <div>
                    <h3 style="margin:0;font-size:0.95rem;font-weight:800;color:var(--h-text);">Provision New Admin</h3>
                    <p style="margin:0.15rem 0 0;font-size:0.74rem;color:var(--h-text3);">Grant full administrative access to a new user</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.security.store') }}" style="display:flex;flex-direction:column;gap:0.85rem;">
                @csrf

                <div>
                    <label style="display:block;font-size:0.74rem;font-weight:600;color:var(--h-text2);margin-bottom:0.3rem;">Full Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="haven-input @error('name') haven-input-error @enderror" required placeholder="Eng. Maria Santos">
                    @error('name')
                        <div style="color:var(--h-red);font-size:0.72rem;margin-top:0.25rem;">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label style="display:block;font-size:0.74rem;font-weight:600;color:var(--h-text2);margin-bottom:0.3rem;">Corporate Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="haven-input @error('email') haven-input-error @enderror" required placeholder="admin@umak.edu.ph">
                    @error('email')
                        <div style="color:var(--h-red);font-size:0.72rem;margin-top:0.25rem;">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label style="display:block;font-size:0.74rem;font-weight:600;color:var(--h-text2);margin-bottom:0.3rem;">Initial Password</label>
                    <input type="password" name="password" class="haven-input @error('password') haven-input-error @enderror" required placeholder="Minimum 8 characters">
                    @error('password')
                        <div style="color:var(--h-red);font-size:0.72rem;margin-top:0.25rem;">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label style="display:block;font-size:0.74rem;font-weight:600;color:var(--h-text2);margin-bottom:0.3rem;">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="haven-input" required placeholder="Repeat initial password">
                </div>

                <button type="submit" class="haven-btn-secondary" style="margin-top:0.4rem;padding:0.65rem 1rem;font-weight:700;">
                    Provision Administrator
                </button>
            </form>
        </div>
    </div>

    {{-- Audit Log Card --}}
    <div class="h-card">
        <h3 style="margin:0 0 0.5rem;font-size:0.95rem;font-weight:700;color:var(--h-text);">Administrative Audit Trail (ISO 27001)</h3>
        @forelse($recentAdminAudits as $log)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.45rem 0;border-bottom:1px solid var(--h-border);font-size:0.78rem;">
                <div>
                    <span class="h-badge {{ $log->status === 'success' ? 'h-badge-teal' : 'h-badge-amber' }}" style="font-size:0.62rem;">
                        {{ strtoupper($log->status) }}
                    </span>
                    <span style="color:var(--h-text);margin-left:0.5rem;font-weight:600;">{{ $log->event }}</span>
                    <span style="color:var(--h-text3);margin-left:0.35rem;">by {{ $log->admin?->name ?? 'System' }}</span>
                </div>
                <span style="color:var(--h-text3);">{{ $log->created_at?->diffForHumans() }}</span>
            </div>
        @empty
            <p style="font-size:0.78rem;color:var(--h-text3);margin:0;">No administrative audit entries logged yet.</p>
        @endforelse
    </div>
</x-admin-layout>

