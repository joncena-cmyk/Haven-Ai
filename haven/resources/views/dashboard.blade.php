<x-app-layout title="Dashboard">
@php
    $user            = Auth::user();
    $vehicleCount    = $user->vehicles()->count();
    $diagCount       = $user->diagnostics()->count();
    $lastDiag        = $user->diagnostics()->with('vehicle')->latest()->first();
    $recentVehicles  = $user->vehicles()->withCount('diagnostics')->latest()->take(5)->get();
    $recentDiags     = $user->diagnostics()->with('vehicle')->latest()->take(5)->get();
@endphp

    {{-- Command Center Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
        <div>
            <h1 style="font-size:1.45rem;font-weight:800;color:var(--h-text);letter-spacing:-0.025em;margin:0 0 0.2rem;">
                Welcome back, {{ explode(' ', trim($user->name))[0] }}
            </h1>
            <p style="font-size:0.84rem;color:var(--h-text2);margin:0;">
                Monitor real-time ESP32 powertrain telemetry, manage registered vehicles, and run acoustic-vibration diagnostics.
            </p>
        </div>
        <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
            <a href="{{ route('vehicles.create') }}" class="h-btn-sm h-btn-sm-default" style="text-decoration:none;padding:0.5rem 0.9rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Add Vehicle</span>
            </a>
            <a href="{{ route('diagnostics.create') }}" class="haven-nav-cta" style="text-decoration:none;padding:0.5rem 1rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
                <span>New Diagnostic</span>
            </a>
        </div>
    </div>

    {{-- Stats row --}}
    @php
        $mlConditionLabel = $lastDiag?->ml_condition ? ucwords(str_replace('_', ' ', $lastDiag->ml_condition)) : '—';
        $mlConditionColor = match($lastDiag?->ml_condition) {
            'normal' => 'var(--h-status-ok)',
            'loose_belt', 'worn_bearing' => 'var(--h-status-warn)',
            'severe_knock', 'mechanical_failure' => 'var(--h-status-err)',
            null, '' => 'var(--h-status-warn)',
            default => 'var(--h-status-warn)',
        };
    @endphp
    <div class="h-stat-row">
        {{-- 1. Vehicles (Sky Blue / Cyan) --}}
        <div class="h-stat-cell h-stat-cell-blue">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;">
                <div>
                    <div class="h-stat-value">{{ $vehicleCount }}</div>
                    <div class="h-stat-label">Vehicles</div>
                </div>
                <div class="h-stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                </div>
            </div>
            <div class="h-stat-sub">
                @if($vehicleCount > 0)
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>{{ $vehicleCount }} registered</span>
                @else
                    <span>No vehicles added yet</span>
                @endif
            </div>
        </div>

        {{-- 2. Diagnostics Run (Emerald Green) --}}
        <div class="h-stat-cell h-stat-cell-green">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;">
                <div>
                    <div class="h-stat-value">{{ $diagCount }}</div>
                    <div class="h-stat-label">Diagnostics Run</div>
                </div>
                <div class="h-stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
            </div>
            <div class="h-stat-sub">
                @if($diagCount > 0)
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>{{ $diagCount }} {{ Str::plural('session', $diagCount) }}</span>
                @else
                    <span>No sessions yet</span>
                @endif
            </div>
        </div>

        {{-- 3. Last ML Result (Amber / Orange) --}}
        <div class="h-stat-cell h-stat-cell-amber">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;">
                <div style="min-width:0;">
                    <div class="h-stat-value" style="{{ $lastDiag?->ml_condition ? 'font-size:1.25rem;' : 'font-size:1.65rem;' }}">
                        {{ $mlConditionLabel }}
                    </div>
                    <div class="h-stat-label">Last ML Result</div>
                </div>
                <div class="h-stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
            </div>
            <div class="h-stat-sub">
                @if($lastDiag)
                    <span>{{ $lastDiag->vehicle->display_name }} &middot; {{ $lastDiag->created_at->diffForHumans() }}</span>
                @else
                    <span>No analysis yet</span>
                @endif
            </div>
        </div>

        {{-- 4. Learning Modules (Indigo / Purple) --}}
        @php
            $learningStats = auth()->user()?->getLearningStats();
            $completedMods = $learningStats['completed_count'] ?? 0;
            $quizScore = $learningStats['quiz_percentage'] ?? 0;
            $quizAttempts = $learningStats['quiz_attempts'] ?? 0;
        @endphp
        <a href="{{ route('learning.index') }}" class="h-stat-cell h-stat-cell-purple" style="text-decoration:none;display:block;cursor:pointer;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;">
                <div>
                    <div class="h-stat-value">
                        @if($completedMods > 0)
                            {{ $completedMods }}<span style="font-size:1rem;font-weight:600;opacity:0.85;"> / 12</span>
                        @else
                            12
                        @endif
                    </div>
                    <div class="h-stat-label">Learning Modules</div>
                </div>
                <div class="h-stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                </div>
            </div>
            <div class="h-stat-sub">
                @if($completedMods > 0)
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>{{ $completedMods }} done &middot; {{ $quizAttempts > 0 ? $quizScore . '% Quiz' : 'Take Quiz' }} &rarr;</span>
                @else
                    <span>10 topics &middot; Explore Hub &rarr;</span>
                @endif
            </div>
        </a>
    </div>

    {{-- Live ESP32 Hardware DAQ Cluster --}}
    <x-esp32-live-telemetry />

    {{-- Primary action --}}
    <div class="h-action-panel">
        {{-- Decorative Acoustic Waveform Watermark --}}
        <svg width="260" height="80" viewBox="0 0 260 80" fill="none" style="position:absolute;right:160px;top:50%;transform:translateY(-50%);opacity:0.08;pointer-events:none;" aria-hidden="true">
            <path d="M0 40 L30 40 L42 15 L54 65 L66 8 L78 72 L90 22 L102 58 L114 30 L126 50 L138 12 L150 68 L162 25 L174 55 L186 40 L260 40" stroke="var(--h-teal)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <div style="display:flex;align-items:center;gap:1rem;flex:1;min-width:220px;position:relative;z-index:1;">
            <div style="width:44px;height:44px;border-radius:12px;background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);display:flex;align-items:center;justify-content:center;color:var(--h-teal);flex-shrink:0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
            </div>
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
        </div>
        @if($vehicleCount === 0)
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
                @foreach($recentVehicles as $v)
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
                                · {{ $v->diagnostics_count }} {{ Str::plural('session', $v->diagnostics_count) }}
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
                @foreach($recentDiags as $d)
                    <a href="{{ route('diagnostics.show', $d) }}" class="h-list-item">
                        <div class="h-list-item-icon" style="{{ $d->status === 'submitted' ? 'background:var(--h-teal-bg);color:var(--h-teal);' : ($d->status === 'completed' ? 'background:var(--h-green-bg);color:var(--h-green);' : '') }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <div class="h-list-item-main">
                            <div class="h-list-item-title">{{ $d->vehicle->display_name }}</div>
                            <div class="h-list-item-sub">{{ $d->created_at->format('M j, Y') }} · {{ $d->status_label }}</div>
                        </div>
                        @if($d->ml_condition)
                            <span class="h-badge h-badge-teal" style="flex-shrink:0;">{{ ucwords(str_replace('_', ' ', $d->ml_condition)) }}</span>
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
