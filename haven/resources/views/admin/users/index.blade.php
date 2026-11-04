<x-admin-layout title="Admin Users">
    <div style="margin-bottom:1rem;">
        <span class="h-badge h-badge-teal" style="font-size:0.65rem;">ADMIN CONSOLE</span>
        <h1 style="font-size:1.35rem;font-weight:800;color:var(--h-text);margin:0.3rem 0 0;">User Management</h1>
    </div>

    @include('admin.partials.tabs')

    <form method="GET" action="{{ route('admin.users.index') }}" style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-bottom:1rem;">
        <input type="text" name="q" value="{{ $search }}" placeholder="Search name or email" class="h-input" style="flex:1;min-width:200px;">
        <select name="filter" class="h-input" style="min-width:140px;">
            <option value="">All accounts</option>
            @foreach(['active' => 'Active', 'suspended' => 'Suspended'] as $k => $v)
                <option value="{{ $k }}" @selected($filter === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <button type="submit" class="haven-action-btn haven-action-btn-secondary" style="padding:0.45rem 1rem;">Filter</button>
    </form>

    <div class="h-card" style="padding:0;overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
            <thead>
                <tr style="text-align:left;color:var(--h-text3);font-size:0.7rem;text-transform:uppercase;letter-spacing:0.05em;">
                    <th style="padding:0.75rem 1rem;">User</th><th>Status</th><th>Vehicles</th><th>Diagnostics</th><th>Joined</th><th style="text-align:right;padding-right:1rem;">Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($users as $u)
                <tr style="border-top:1px solid var(--h-border);">
                    <td style="padding:0.7rem 1rem;">
                        <a href="{{ route('admin.users.show', $u) }}" style="color:var(--h-text);font-weight:600;text-decoration:none;">{{ $u->name }}</a>
                        <div style="color:var(--h-text3);font-size:0.72rem;">{{ $u->email }}</div>
                    </td>
                    <td>
                        @if($u->isSuspended())
                            <span style="color:var(--h-red);font-weight:600;">Suspended</span>
                        @else
                            <span style="color:var(--h-green);font-weight:600;">Active</span>
                        @endif
                    </td>
                    <td>{{ $u->vehicles_count }}</td>
                    <td>{{ $u->diagnostics_count }}</td>
                    <td style="color:var(--h-text3);">{{ $u->created_at?->format('M d, Y') }}</td>
                    <td style="text-align:right;padding-right:1rem;white-space:nowrap;">
                        @if($u->isSuspended())
                            <form method="POST" action="{{ route('admin.users.unsuspend', $u) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="haven-action-btn haven-action-btn-secondary" style="font-size:0.7rem;padding:0.25rem 0.55rem;">Reinstate</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.users.suspend', $u) }}" style="display:inline;" onsubmit="return confirm('Suspend {{ $u->email }}? They will be signed out immediately.');">
                                @csrf
                                <button type="submit" class="haven-action-btn haven-action-btn-secondary" style="font-size:0.7rem;padding:0.25rem 0.55rem;color:var(--h-red);">Suspend</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem;">{{ $users->links() }}</div>
</x-admin-layout>

