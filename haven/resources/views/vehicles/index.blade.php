<x-app-layout title="My Vehicles — Showroom">

    {{-- Top Showroom Bar (Matches Reference Image "All Cars" + Sort) --}}
    <div class="h-showroom-header-bar">
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <h1 class="h-showroom-title-clean">All Vehicles</h1>
            <span class="h-showroom-count-pill">{{ $vehicles->count() }}</span>
        </div>

        <div style="display:flex;align-items:center;gap:0.65rem;flex-wrap:wrap;">
            {{-- Quick Filter Pills --}}
            <div class="h-showroom-quick-filters">
                <button type="button" class="h-clean-pill active" data-filter="all">All</button>
                <button type="button" class="h-clean-pill" data-filter="live">Live ESP32</button>
                <button type="button" class="h-clean-pill" data-filter="scope">2015+ In Scope</button>
            </div>

            {{-- Search Box --}}
            <div class="h-showroom-search-wrap">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="vehicle-search" placeholder="Search..." autocomplete="off">
            </div>

            {{-- Sort By Dropdown (Styled like the "Short by" button in reference) --}}
            <div class="h-sort-wrapper">
                <select id="vehicle-sort" class="h-clean-sort-select">
                    <option value="newest">Sort by: Newest</option>
                    <option value="year-desc">Year: High → Low</option>
                    <option value="mileage-desc">Mileage: High → Low</option>
                    <option value="diags-desc">Most Diagnostics</option>
                </select>
            </div>

            {{-- Add Vehicle Primary CTA --}}
            <a href="{{ route('vehicles.create') }}" class="h-add-vehicle-top-btn" title="Register New Vehicle">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Register Vehicle</span>
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="h-flash h-flash-success" style="margin-bottom:1.5rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @if($vehicles->isEmpty())
        <div class="h-empty-showroom">
            <div class="h-empty-showroom-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                    <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                </svg>
            </div>
            <h2 style="font-size:1.15rem;font-weight:700;color:#ffffff;margin:0 0 0.3rem;">No Vehicles in Fleet</h2>
            <p style="font-size:0.8rem;color:#71717a;margin:0 0 1.25rem;">Register your first vehicle to monitor live ESP32 telemetry and run acoustic diagnostics.</p>
            <a href="{{ route('vehicles.create') }}" class="h-add-vehicle-top-btn" style="text-decoration:none;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Register Vehicle</span>
            </a>
        </div>
    @else
        {{-- Luxury Showroom Grid --}}
        <div class="h-clean-showroom-grid" id="showroom-grid">
            @foreach($vehicles as $vehicle)
                @php
                    $livePacket = \Illuminate\Support\Facades\Cache::get('telemetry:live:' . $vehicle->id);
                    $liveTs = is_array($livePacket) ? ($livePacket['timestamp'] ?? $livePacket['received_at'] ?? null) : null;
                    $isOnline = !empty($livePacket['online']) && !empty($liveTs) && abs(\Carbon\Carbon::parse($liveTs)->diffInSeconds(now())) <= 15;
                    $latestDiag = $vehicle->diagnostics->first();
                    $diagCount = $vehicle->diagnostics_count ?? 0;
                    $isNew = $vehicle->created_at && $vehicle->created_at->diffInDays(now()) <= 7;
                @endphp
                <div class="h-clean-car-card" 
                     data-id="{{ $vehicle->id }}"
                     data-make="{{ strtolower($vehicle->make) }}"
                     data-model="{{ strtolower($vehicle->model) }}"
                     data-plate="{{ strtolower($vehicle->plate_number ?? '') }}"
                     data-name="{{ strtolower($vehicle->display_name) }}"
                     data-year="{{ $vehicle->year }}"
                     data-mileage="{{ $vehicle->mileage_km ?? 0 }}"
                     data-diags="{{ $diagCount }}"
                     data-online="{{ $isOnline ? '1' : '0' }}"
                     data-scope="{{ $vehicle->isScopeCompliant() ? '1' : '0' }}"
                     onclick="window.location='{{ route('vehicles.show', $vehicle) }}'">

                    {{-- Top Row: Badge + Right Action Icons --}}
                    <div class="h-card-header-clean">
                        <div style="display:flex;align-items:center;gap:0.4rem;">
                            @if($isOnline)
                                <span class="h-clean-badge h-badge-live">Live</span>
                            @elseif($isNew)
                                <span class="h-clean-badge h-badge-new">New</span>
                            @elseif($vehicle->isScopeCompliant())
                                <span class="h-clean-badge h-badge-scope">In Scope</span>
                            @else
                                <span class="h-clean-badge h-badge-warn">Advisory</span>
                            @endif

                            @if($vehicle->has3dModel())
                                <span class="h-clean-badge h-badge-3d" title="Verified 3D Sketchfab Digital Twin">3D Twin</span>
                            @endif
                        </div>

                        {{-- Subtle Action Icons (Edit / Delete) --}}
                        <div class="h-card-top-actions" onclick="event.stopPropagation();">
                            <a href="{{ route('vehicles.edit', $vehicle) }}" class="h-subtle-icon-link" title="Edit Vehicle">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('vehicles.destroy', $vehicle) }}" onsubmit="return confirm('Remove {{ addslashes($vehicle->display_name) }}?')" style="display:inline;">
                                @csrf @method('DELETE')
                                <button type="submit" class="h-subtle-icon-link h-subtle-icon-danger" title="Delete Vehicle">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Center Car Stage: Natural 3D Car Render Directly on Dark Canvas --}}
                    <div class="h-clean-stage">
                        @if($vehicle->image_url)
                            <img src="{{ $vehicle->image_url }}" alt="{{ $vehicle->display_name }}" class="h-clean-car-img" loading="lazy">
                        @else
                            {{-- Fallback: Body type studio render --}}
                            @php
                                $fallbackBody = $vehicle->resolved_body_type ?? 'sedan';
                            @endphp
                            <img src="{{ asset('images/vehicles/' . $fallbackBody . '.jpg') }}" alt="{{ $vehicle->display_name }}" class="h-clean-car-img" loading="lazy">
                        @endif
                    </div>

                    {{-- Vehicle Title & Subtitle --}}
                    <div class="h-clean-body">
                        <h2 class="h-clean-title">{{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}</h2>
                        <div class="h-clean-subtitle">
                            <span>{{ $vehicle->nickname ?: ($vehicle->plate_number ?: 'Plate Unassigned') }}</span>
                            <span style="opacity:0.4;">·</span>
                            <span>{{ $vehicle->body_type_label }}</span>
                        </div>
                    </div>

                    {{-- Specs Row (Clean Horizontal Flow with Subtle Icons — No Clunky Box!) --}}
                    <div class="h-clean-specs-row">
                        <div class="h-clean-spec-item" title="Engine">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <span>{{ $vehicle->engine_type ? Str::limit($vehicle->engine_type, 13) : 'Gasoline ICE' }}</span>
                        </div>
                        <div class="h-clean-spec-item" title="Transmission">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            <span>{{ $vehicle->transmission ?: 'Automatic' }}</span>
                        </div>
                        <div class="h-clean-spec-item" title="Mileage">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>{{ $vehicle->mileage_km !== null ? number_format($vehicle->mileage_km).' km' : '-- km' }}</span>
                        </div>
                    </div>

                    {{-- Footer: Gold Rating/Status on Left, Sleek Diagnose CTA on Right --}}
                    <div class="h-clean-footer">
                        <div class="h-clean-rating">
                            @if($latestDiag && $latestDiag->ml_condition)
                                @php
                                    $cond = strtolower($latestDiag->ml_condition);
                                    $isOptimal = str_contains($cond, 'normal') || str_contains($cond, 'optimal');
                                @endphp
                                <span class="h-gold-star">★</span>
                                <span class="{{ $isOptimal ? 'h-status-text-optimal' : 'h-status-text-warn' }}">
                                    {{ ucwords(str_replace('_', ' ', $latestDiag->ml_condition)) }}
                                </span>
                            @elseif($diagCount > 0)
                                <span class="h-gold-star">★</span>
                                <span class="h-status-text-neutral">{{ $diagCount }} {{ Str::plural('session', $diagCount) }}</span>
                            @else
                                <span class="h-gold-star" style="opacity:0.4;">★</span>
                                <span class="h-status-text-empty">Ready for Baseline</span>
                            @endif
                        </div>

                        {{-- High-Contrast Pill CTA --}}
                        <a href="{{ route('diagnostics.create', ['vehicle_id' => $vehicle->id]) }}" 
                           class="h-clean-diagnose-btn" 
                           onclick="event.stopPropagation();" 
                           title="Launch AI Diagnostic on {{ $vehicle->display_name }}">
                            <span>Diagnose</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                    </div>

                </div>
            @endforeach
        </div>
    @endif

    {{-- Interactive Client-side Search, Filter & Sort Script --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const grid = document.getElementById('showroom-grid');
        if (!grid) return;

        const cards = Array.from(grid.querySelectorAll('.h-clean-car-card'));
        const searchInput = document.getElementById('vehicle-search');
        const sortSelect = document.getElementById('vehicle-sort');
        const filterPills = document.querySelectorAll('.h-clean-pill');

        let activeFilter = 'all';

        function updateDisplay() {
            const query = (searchInput.value || '').trim().toLowerCase();

            cards.forEach(card => {
                const name = card.dataset.name || '';
                const make = card.dataset.make || '';
                const model = card.dataset.model || '';
                const plate = card.dataset.plate || '';
                const isOnline = card.dataset.online === '1';
                const isScope = card.dataset.scope === '1';

                const matchesQuery = !query || name.includes(query) || make.includes(query) || model.includes(query) || plate.includes(query);
                
                let matchesFilter = true;
                if (activeFilter === 'live') matchesFilter = isOnline;
                else if (activeFilter === 'scope') matchesFilter = isScope;

                card.style.display = (matchesQuery && matchesFilter) ? '' : 'none';
            });
        }

        function sortCards() {
            const mode = sortSelect.value;
            const sorted = cards.slice().sort((a, b) => {
                if (mode === 'year-desc') {
                    return parseInt(b.dataset.year || 0) - parseInt(a.dataset.year || 0);
                }
                if (mode === 'mileage-desc') {
                    return parseInt(b.dataset.mileage || 0) - parseInt(a.dataset.mileage || 0);
                }
                if (mode === 'diags-desc') {
                    return parseInt(b.dataset.diags || 0) - parseInt(a.dataset.diags || 0);
                }
                return parseInt(b.dataset.id || 0) - parseInt(a.dataset.id || 0);
            });

            sorted.forEach(c => grid.appendChild(c));
        }

        filterPills.forEach(pill => {
            pill.addEventListener('click', () => {
                filterPills.forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                activeFilter = pill.dataset.filter;
                updateDisplay();
            });
        });

        if (searchInput) searchInput.addEventListener('input', updateDisplay);
        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                sortCards();
                updateDisplay();
            });
        }
    });
    </script>

</x-app-layout>
