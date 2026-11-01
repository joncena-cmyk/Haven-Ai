<x-app-layout title="Dashboard">
@php
    $vehicleCount    = Auth::user()->vehicles()->count();
    $diagCount       = Auth::user()->diagnostics()->count();
    $lastDiag        = Auth::user()->diagnostics()->with('vehicle')->latest()->first();
@endphp

    {{-- Stats row --}}
    <div class="h-stat-row">
        <div class="h-stat-cell">
            <div class="h-stat-value">{{ $vehicleCount }}</div>
            <div class="h-stat-label">Vehicles</div>
            <div class="h-stat-sub {{ $vehicleCount > 0 ? 'h-stat-sub-ok' : '' }}">
                @if($vehicleCount > 0)
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    {{ $vehicleCount }} registered
                @else
                    No vehicles added yet
                @endif
            </div>
        </div>
        <div class="h-stat-cell">
            <div class="h-stat-value">{{ $diagCount }}</div>
            <div class="h-stat-label">Diagnostics Run</div>
            <div class="h-stat-sub {{ $diagCount > 0 ? 'h-stat-sub-ok' : '' }}">
                @if($diagCount > 0)
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    {{ $diagCount }} {{ Str::plural('session', $diagCount) }}
                @else
                    No sessions yet
                @endif
            </div>
        </div>
        <div class="h-stat-cell">
            <div class="h-stat-value" style="{{ $lastDiag?->ml_condition ? '' : 'font-size:1rem;color:var(--h-text3);' }}">
                {{ $lastDiag?->ml_condition ? ucfirst($lastDiag->ml_condition) : '—' }}
            </div>
            <div class="h-stat-label">Last ML Result</div>
            <div class="h-stat-sub">
                @if($lastDiag)
                    {{ $lastDiag->vehicle->display_name }} · {{ $lastDiag->created_at->diffForHumans() }}
                @else
                    No analysis yet
                @endif
            </div>
        </div>
        <a href="{{ route('learning.index') }}" class="h-stat-cell" style="text-decoration:none;display:block;cursor:pointer;">
            <div class="h-stat-value" style="color:var(--h-teal);">6</div>
            <div class="h-stat-label">Learning Modules</div>
            <div class="h-stat-sub" style="color:var(--h-teal);">Explore Knowledge Hub →</div>
        </a>
    </div>

    {{-- Live ESP32 Hardware DAQ Cluster --}}
    <x-esp32-live-telemetry />

    {{-- Primary action --}}
    <div class="h-action-panel">
        <div class="h-action-panel-info">
            <p class="h-action-panel-title">
                @if($vehicleCount === 0) Register a Vehicle First
                @else Start a Diagnostic Session @endif
            </p>
            <p class="h-action-panel-desc">
                @if($vehicleCount === 0)
                    Add at least one vehicle before running an engine diagnostic session.
                @else
                    Record engine audio and upload ESP32 vibration data to get an ML-assisted condition report.
                @endif
            </p>
        </div>
        @if($vehicleCount > 0)
            <a href="{{ route('diagnostics.create') }}" style="text-decoration:none;flex-shrink:0;">
                <button class="haven-btn-primary" style="width:auto;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                    </svg>
                    New Diagnostic
                </button>
            </a>
        @else
            <a href="{{ route('vehicles.create') }}" style="text-decoration:none;flex-shrink:0;">
                <button class="haven-btn-primary" style="width:auto;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add First Vehicle
                </button>
            </a>
        @endif
    </div>

    {{-- Content grid --}}
    <div class="h-content-grid">

        {{-- Vehicles --}}
        <div class="h-list-card">
            <div class="h-list-header">
                <span class="h-list-title">My Vehicles</span>
                <a href="{{ route('vehicles.create') }}" class="h-btn-sm h-btn-sm-teal" style="text-decoration:none;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add
                </a>
            </div>
            @if($vehicleCount > 0)
                @foreach(Auth::user()->vehicles()->latest()->take(5)->get() as $v)
                    <a href="{{ route('vehicles.show', $v) }}" class="h-list-item">
                        <div class="h-list-item-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                                <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                            </svg>
                        </div>
                        <div class="h-list-item-main">
                            <div class="h-list-item-title">{{ $v->display_name }}</div>
                            <div class="h-list-item-sub">
                                {{ $v->plate_number ?: 'No plate' }}
                                @if($v->engine_type) · {{ $v->engine_type }} @endif
                                · {{ $v->diagnostics()->count() }} {{ Str::plural('session', $v->diagnostics()->count()) }}
                            </div>
                        </div>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--h-text3);flex-shrink:0;"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                @endforeach
                @if($vehicleCount > 5)
                    <a href="{{ route('vehicles.index') }}" class="h-list-item" style="color:var(--h-teal);justify-content:center;">
                        View all {{ $vehicleCount }} vehicles
                    </a>
                @endif
            @else
                <div class="h-list-empty">
                    No vehicles registered yet.<br>
                    <a href="{{ route('vehicles.create') }}" class="haven-link" style="font-size:0.8rem;">Add one now →</a>
                </div>
            @endif
        </div>

        {{-- Recent Diagnostics --}}
        <div class="h-list-card">
            <div class="h-list-header">
                <span class="h-list-title">Recent Diagnostics</span>
                @if($diagCount > 0)
                    <a href="{{ route('diagnostics.index') }}" class="h-btn-sm h-btn-sm-default" style="text-decoration:none;">View All</a>
                @endif
            </div>
            @if($diagCount > 0)
                @foreach(Auth::user()->diagnostics()->with('vehicle')->latest()->take(5)->get() as $d)
                    <a href="{{ route('diagnostics.show', $d) }}" class="h-list-item">
                        <div class="h-list-item-icon" style="{{ $d->status === 'submitted' ? 'background:var(--h-teal-bg);color:var(--h-teal);' : ($d->status === 'completed' ? 'background:var(--h-green-bg);color:var(--h-green);' : '') }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <div class="h-list-item-main">
                            <div class="h-list-item-title">{{ $d->vehicle->display_name }}</div>
                            <div class="h-list-item-sub">{{ $d->created_at->format('M j, Y') }} · {{ $d->status_label }}</div>
                        </div>
                        @if($d->ml_condition)
                            <span class="h-badge h-badge-teal" style="flex-shrink:0;">{{ $d->ml_condition }}</span>
                        @else
                            <span class="h-badge {{ $d->status_badge }}" style="flex-shrink:0;">{{ $d->status_label }}</span>
                        @endif
                    </a>
                @endforeach
            @else
                <div class="h-list-empty">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 0.75rem;display:block;color:var(--h-text3);"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    @if($vehicleCount > 0)
                        <a href="{{ route('diagnostics.create') }}" class="haven-link" style="font-size:0.8rem;">Run your first diagnostic →</a>
                    @else
                        Diagnostic history will appear here.
                    @endif
                </div>
            @endif
        </div>

    </div>



</x-app-layout>
