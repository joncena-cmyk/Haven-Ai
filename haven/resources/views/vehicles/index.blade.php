<x-app-layout title="My Vehicles">

    <div class="h-page-header">
        <div>
            <h1 class="h-page-title">My Vehicles</h1>
            <p class="h-page-desc">Vehicles registered for diagnostic tracking.</p>
        </div>
        <a href="{{ route('vehicles.create') }}" class="haven-btn-primary" style="width:auto;text-decoration:none;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add Vehicle
        </a>
    </div>

    @if(session('success'))
        <div class="h-flash h-flash-success">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @if($vehicles->isEmpty())
        <div class="h-empty">
            <div class="h-empty-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                    <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                </svg>
            </div>
            <h2 class="h-empty-title">No Vehicles Yet</h2>
            <p class="h-empty-desc">Register a vehicle to begin tracking its diagnostic history and run engine analysis.</p>
            <a href="{{ route('vehicles.create') }}" class="haven-btn-primary" style="width:auto;text-decoration:none;">Register a Vehicle</a>
        </div>
    @else
        <div class="haven-vehicle-grid">
            @foreach($vehicles as $vehicle)
                <div class="haven-vehicle-card">
                    <div class="haven-vehicle-card-header">
                        <div class="haven-vehicle-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                                <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                            </svg>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="haven-vehicle-name">{{ $vehicle->display_name }}</div>
                            <div style="font-size:0.72rem;color:var(--h-text3);margin-top:0.1rem;">
                                {{ $vehicle->plate_number ?: 'No plate number' }}
                            </div>
                            <div style="margin-top:0.35rem;">
                                <span class="h-badge {{ $vehicle->scope_meta['badge_css'] }}" style="font-size:0.62rem;padding:0.15rem 0.4rem;">
                                    {{ $vehicle->scope_meta['label'] }}
                                </span>
                            </div>
                        </div>
                        @if($vehicle->transmission)
                            <span class="h-badge h-badge-gray">{{ $vehicle->transmission }}</span>
                        @endif
                    </div>

                    @if($vehicle->engine_type || $vehicle->color || $vehicle->mileage_km !== null)
                        <div class="haven-vehicle-details">
                            @if($vehicle->engine_type)
                                <div class="haven-vehicle-detail-row">
                                    <span class="haven-vehicle-detail-label">Engine</span>
                                    <span class="haven-vehicle-detail-value">{{ $vehicle->engine_type }}</span>
                                </div>
                            @endif
                            @if($vehicle->color)
                                <div class="haven-vehicle-detail-row">
                                    <span class="haven-vehicle-detail-label">Color</span>
                                    <span class="haven-vehicle-detail-value">{{ $vehicle->color }}</span>
                                </div>
                            @endif
                            @if($vehicle->mileage_km !== null)
                                <div class="haven-vehicle-detail-row">
                                    <span class="haven-vehicle-detail-label">Mileage</span>
                                    <span class="haven-vehicle-detail-value">{{ number_format($vehicle->mileage_km) }} km</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="haven-vehicle-card-actions">
                        <a href="{{ route('vehicles.show', $vehicle) }}" class="haven-action-btn haven-action-btn-primary" style="text-decoration:none;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            View
                        </a>
                        <a href="{{ route('vehicles.edit', $vehicle) }}" class="haven-action-btn haven-action-btn-secondary" style="text-decoration:none;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Edit
                        </a>
                        <form method="POST" action="{{ route('vehicles.destroy', $vehicle) }}" onsubmit="return confirm('Remove {{ addslashes($vehicle->display_name) }}?')" class="haven-vehicle-destroy-form">
                            @csrf @method('DELETE')
                            <button type="submit" class="haven-action-btn haven-action-btn-danger">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</x-app-layout>
