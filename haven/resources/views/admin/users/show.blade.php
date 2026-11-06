<x-admin-layout title="Admin User Detail">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;">
        <div>
            <a href="{{ route('admin.users.index') }}" style="font-size:0.78rem;color:var(--h-teal);text-decoration:none;">&larr; All users</a>
            <h1 style="font-size:1.35rem;font-weight:800;color:var(--h-text);margin:0.3rem 0 0;">{{ $user->name }}</h1>
            <p style="font-size:0.8rem;color:var(--h-text3);margin:0.2rem 0 0;">
                {{ $user->email }} &middot; Driver account &middot;
                @if($user->isSuspended())
                    <span style="color:var(--h-red);">Suspended {{ $user->suspended_at->diffForHumans() }}</span>
                @else
                    <span style="color:var(--h-green);">Active</span>
                @endif
                &middot; Joined {{ $user->created_at?->format('M d, Y') }}
            </p>
        </div>
        <div>
            @if($user->isSuspended())
                <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="haven-action-btn haven-action-btn-secondary" style="font-size:0.76rem;padding:0.4rem 0.85rem;">Reinstate Account</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.users.suspend', $user) }}" style="display:inline;" onsubmit="return confirm('Suspend {{ $user->email }}? They will be signed out immediately.');">
                    @csrf
                    <button type="submit" class="haven-action-btn haven-action-btn-secondary" style="font-size:0.76rem;padding:0.4rem 0.85rem;color:var(--h-red);">Suspend Account</button>
                </form>
            @endif
        </div>
    </div>

    @include('admin.partials.tabs')

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.25rem;">
        <div class="h-card">
            <h3 style="margin:0 0 0.75rem;font-size:0.9rem;color:var(--h-text);">Vehicles ({{ $user->vehicles_count }}) &middot; Diagnostics ({{ $user->diagnostics_count }})</h3>
            @forelse($vehicles as $v)
                <div style="display:flex;justify-content:space-between;padding:0.45rem 0;border-bottom:1px solid var(--h-border);font-size:0.78rem;">
                    <span style="color:var(--h-text);">{{ $v->display_name }}</span>
                    <span style="color:var(--h-text3);">{{ $v->diagnostics_count }} diagnostics</span>
                </div>
            @empty
                <p style="font-size:0.8rem;color:var(--h-text3);margin:0;">No vehicles registered.</p>
            @endforelse
            <p style="font-size:0.7rem;color:var(--h-text3);margin:0.75rem 0 0;">Read-only view. Raw audio/vibration files remain private to the owner.</p>
        </div>

        <div class="h-card">
            <h3 style="margin:0 0 0.75rem;font-size:0.9rem;color:var(--h-text);">Recent Activity</h3>
            @forelse($events as $log)
                <div style="display:flex;justify-content:space-between;gap:0.5rem;padding:0.45rem 0;border-bottom:1px solid var(--h-border);font-size:0.78rem;">
                    <span style="color:{{ $log->status === 'failure' ? 'var(--h-red)' : 'var(--h-text)' }};">{{ $log->event }}</span>
                    <span style="color:var(--h-text3);">{{ $log->ip_address }} &middot; {{ $log->created_at?->diffForHumans() }}</span>
                </div>
            @empty
                <p style="font-size:0.8rem;color:var(--h-text3);margin:0;">No recorded activity.</p>
            @endforelse
        </div>
    </div>
</x-admin-layout>
