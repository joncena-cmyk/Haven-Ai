<x-app-layout title="Diagnostic #{{ $diagnostic->id }}">

    <div class="haven-breadcrumb">
        <a href="{{ route('diagnostics.index') }}" class="haven-breadcrumb-link">History</a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <span style="color:var(--h-text2);">Session #{{ $diagnostic->id }}</span>
    </div>

    @if(session('success'))
        <div class="h-flash h-flash-success">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="h-flash h-flash-error">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Session hero --}}
    <div class="haven-vehicle-hero" style="margin-bottom:1.25rem;">
        <div class="haven-vehicle-hero-icon"
             style="{{ $diagnostic->status === 'completed' ? 'background:var(--h-green-bg);color:var(--h-green);border-color:rgba(34,197,94,0.2);' : ($diagnostic->status === 'submitted' ? 'background:var(--h-teal-bg);color:var(--h-teal);border-color:var(--h-teal-ring);' : ($diagnostic->status === 'processing' ? 'background:var(--h-amber-bg);color:var(--h-amber);border-color:rgba(245,158,11,0.2);' : '')) }}">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
        </div>
        <div class="haven-vehicle-hero-info">
            <h1 class="haven-vehicle-hero-name">Diagnostic Session #{{ $diagnostic->id }}</h1>
            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-top:0.25rem;">
                <span class="h-badge {{ $diagnostic->status_badge }}">{{ $diagnostic->status_label }}</span>
                <span class="h-badge {{ $diagnostic->recording_mode === 'revving' ? 'h-badge-amber' : 'h-badge-teal' }}" style="font-size:0.68rem;">
                    {{ $diagnostic->recording_mode_label }}
                </span>
                <a href="{{ route('vehicles.show', $diagnostic->vehicle) }}" class="h-badge h-badge-gray" style="text-decoration:none;">
                    {{ $diagnostic->vehicle->display_name }}
                </a>
                <span class="h-badge {{ $diagnostic->vehicle->scope_meta['badge_css'] }}" style="font-size:0.65rem;">
                    {{ $diagnostic->vehicle->scope_meta['label'] }}
                </span>
                <span style="font-size:0.72rem;color:var(--h-text3);">{{ $diagnostic->created_at->format('M j, Y · g:i A') }}</span>
            </div>
        </div>
        <div style="display:flex;gap:0.5rem;flex-shrink:0;flex-wrap:wrap;align-items:center;">
            @if(in_array($diagnostic->status, ['draft','submitted']) && ($diagnostic->hasAudio() || $diagnostic->hasVibration() || $diagnostic->hasMultiSensor()))
                <form method="POST" action="{{ route('diagnostics.analyze', $diagnostic) }}" id="analyze-form">
                    @csrf
                    <button type="submit" id="btn-analyze" class="haven-btn-primary" style="width:auto;"
                            onclick="this.innerHTML='<svg width=&quot;14&quot; height=&quot;14&quot; viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot; class=&quot;spin&quot;><path d=&quot;M21 12a9 9 0 1 1-6.22-8.56&quot;/></svg> Analyzing…';this.disabled=true;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 3l14 9-14 9V3z"/></svg>
                        Run Analysis
                    </button>
                </form>
            @elseif($diagnostic->status === 'processing')
                <span class="h-badge h-badge-amber" id="async-processing-badge" style="padding:0.4rem 0.75rem;display:inline-flex;align-items:center;gap:0.4rem;">
                    <span class="spin" style="width:11px;height:11px;border:2px solid currentColor;border-top-color:transparent;border-radius:50%;display:inline-block;"></span>
                    <span>Analyzing in Background Queue…</span>
                </span>
                <script>
                (function() {
                    const pollInterval = setInterval(() => {
                        fetch('{{ route('diagnostics.status', $diagnostic) }}', {
                            headers: { 'Accept': 'application/json' }
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'completed' || data.status === 'failed') {
                                clearInterval(pollInterval);
                                window.location.reload();
                            }
                        })
                        .catch(err => console.warn('Status poll error:', err));
                    }, 2500);
                })();
                </script>
            @elseif($diagnostic->status === 'completed')
                <form method="POST" action="{{ route('diagnostics.analyze', $diagnostic) }}">
                    @csrf
                    <button type="submit" class="haven-action-btn haven-action-btn-secondary">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6"/><path d="M23 20v-6h-6"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
                        Re-analyze
                    </button>
                </form>
            @endif
            <a href="{{ route('diagnostics.report', $diagnostic) }}" target="_blank" class="haven-action-btn haven-action-btn-secondary" style="text-decoration:none;" title="Open print-ready inspection report">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Inspection Report
            </a>
            <form method="POST" action="{{ route('diagnostics.destroy', $diagnostic) }}"
                  onsubmit="return confirm('Delete this diagnostic session?')">
                @csrf @method('DELETE')
                <button type="submit" class="haven-action-btn haven-action-btn-danger">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                    Delete
                </button>
            </form>
        </div>
    </div>

    {{-- ── ML Result Panel ── --}}
    @if($diagnostic->status === 'completed' && $diagnostic->ml_condition)
        @php
            $canon = \App\Models\Diagnostic::canonicalCondition($diagnostic->ml_condition ?? 'normal');
            $condColor = match($canon) {
                'normal'          => ['bg'=>'var(--h-green-bg)',  'border'=>'rgba(34,197,94,0.25)',  'label'=>'var(--h-green)', 'icon'=>'#22c55e'],
                'valve_clearance' => ['bg'=>'var(--h-red-bg)',    'border'=>'rgba(239,68,68,0.25)',   'label'=>'var(--h-red)',   'icon'=>'#ef4444'],
                'misfire'         => ['bg'=>'var(--h-amber-bg)',  'border'=>'rgba(245,158,11,0.25)',  'label'=>'var(--h-amber)','icon'=>'#f59e0b'],
                'loose_component' => ['bg'=>'var(--h-amber-bg)',  'border'=>'rgba(245,158,11,0.25)',  'label'=>'var(--h-amber)','icon'=>'#f59e0b'],
                default           => ['bg'=>'var(--h-red-bg)',    'border'=>'rgba(239,68,68,0.25)',   'label'=>'var(--h-red)',   'icon'=>'#ef4444'],
            };
            $rawResult   = $diagnostic->ml_result_raw ?? [];
            $topClasses  = $rawResult['top_classes'] ?? [];
            $isMock      = str_contains($rawResult['mode'] ?? '', 'mock');
            $warnings    = $rawResult['warnings'] ?? [];
        @endphp

        <div style="background:{{ $condColor['bg'] }};border:1px solid {{ $condColor['border'] }};border-radius:var(--h-radius-xl);padding:1.5rem;margin-bottom:1.25rem;">

            {{-- Condition row --}}
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem;">
                <div style="display:flex;align-items:center;gap:0.875rem;">
                    <div style="width:48px;height:48px;border-radius:var(--h-radius-lg);background:var(--h-surface);border:1px solid {{ $condColor['border'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="{{ $condColor['icon'] }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            @if($canon === 'normal')
                                <polyline points="20 6 9 17 4 12"/>
                            @elseif($canon === 'valve_clearance')
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                            @else
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                            @endif
                        </svg>
                    </div>
                    <div>
                        <div style="font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.09em;color:{{ $condColor['label'] }};margin-bottom:0.2rem;">Detected Condition</div>
                        <div style="font-size:1.5rem;font-weight:800;color:var(--h-text);letter-spacing:-0.03em;line-height:1;">
                            {{ $diagnostic->condition_label ?? ucwords(str_replace('_', ' ', $diagnostic->ml_condition)) }}
                        </div>
                    </div>
                </div>
                @if($diagnostic->ml_confidence !== null)
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:2rem;font-weight:800;color:{{ $condColor['label'] }};letter-spacing:-0.03em;line-height:1;">{{ $diagnostic->confidence_percent }}</div>
                        <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.07em;margin-top:0.2rem;">Model Confidence</div>
                    </div>
                @endif
            </div>

            {{-- Confidence bar --}}
            @if($diagnostic->ml_confidence !== null)
                <div style="margin-bottom:1.25rem;">
                    <div style="background:var(--h-surface);border-radius:999px;height:6px;overflow:hidden;">
                        <div style="height:6px;border-radius:999px;background:{{ $condColor['icon'] }};width:{{ round($diagnostic->ml_confidence * 100) }}%;transition:width 0.6s;"></div>
                    </div>
                </div>
            @endif

            {{-- Top classes comparison --}}
            @if(!empty($topClasses))
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0.5rem;margin-bottom:1.25rem;">
                    @foreach($topClasses as $tc)
                        <div style="background:var(--h-surface);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.65rem 0.875rem;">
                            <div style="font-size:0.72rem;font-weight:600;color:var(--h-text2);margin-bottom:0.3rem;">{{ ucwords(str_replace('_',' ',$tc['condition'])) }}</div>
                            <div style="font-size:0.9rem;font-weight:700;color:var(--h-text);font-variant-numeric:tabular-nums;">{{ round(($tc['confidence']??0)*100,1) }}%</div>
                            <div style="height:3px;border-radius:999px;background:var(--h-border2);margin-top:0.4rem;overflow:hidden;">
                                <div style="height:3px;background:var(--h-teal);width:{{ round(($tc['confidence']??0)*100) }}%;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Processed at + mode --}}
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
                <div style="font-size:0.72rem;color:var(--h-text3);">
                    Processed {{ $diagnostic->ml_processed_at?->format('M j, Y · g:i A') }}
                    @if($isMock)
                        · <span style="color:var(--h-amber);">Simulated result</span> (ML server was offline)
                    @else
                        · FastAPI inference
                    @endif
                </div>
            </div>

            @if(!empty($warnings))
                <div style="margin-top:0.75rem;font-size:0.75rem;color:var(--h-text3);">{{ implode(' ', $warnings) }}</div>
            @endif
        </div>

        {{-- Disclaimer --}}
        <div class="h-notice" style="margin-bottom:1.25rem;">
            <svg class="h-notice-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>The confidence score indicates classifier certainty — <em>not</em> the probability of mechanical failure. This result is <strong style="color:var(--h-text2);">not a substitute for professional mechanical inspection.</strong></span>
        </div>

        {{-- HAVEN AI Mechanic Copilot & Recommendations --}}
        @if(!empty($aiRecommendations))
            <div class="haven-ai-copilot-container" style="background:var(--h-surface);border:1px solid var(--h-teal-ring);border-radius:var(--h-radius-lg);margin-bottom:1.5rem;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.18);">
                
                {{-- Copilot Header Bar --}}
                <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--h-border);background:linear-gradient(90deg, rgba(2,132,199,0.1) 0%, rgba(30,41,59,0.6) 100%);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
                    <div style="display:flex;align-items:center;gap:0.875rem;">
                        <div id="haven-ai-orb" class="haven-siri-orb" title="HAVEN AI Mechanic Copilot"></div>
                        <div>
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <h3 style="font-size:0.95rem;font-weight:700;color:var(--h-text);margin:0;letter-spacing:-0.01em;">
                                    HAVEN AI · Mechanic Copilot & Recommendations
                                </h3>
                                <span class="h-badge h-badge-teal" style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.04em;">V2.5 Live</span>
                            </div>
                            <div style="font-size:0.75rem;color:var(--h-text3);margin-top:0.15rem;">
                                Instant vehicle diagnostics & voice assistant for {{ $diagnostic->vehicle?->display_name ?? 'your vehicle' }}
                            </div>
                        </div>
                    </div>

                    {{-- Actions: History & Voice Readout Toggle --}}
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                        <span id="diag-history-badge" title="Saved questions in session" style="font-size:0.68rem;font-weight:600;color:var(--h-text3);background:var(--h-surface2);border:1px solid var(--h-border);padding:0.2rem 0.55rem;border-radius:999px;display:none;">
                            0 msgs
                        </span>
                        <button type="button" id="diag-clear-history" title="Clear copilot session history" onclick="clearDiagHistory()" class="h-btn h-btn-subtle" style="font-size:0.72rem;padding:0.35rem 0.6rem;display:none;align-items:center;gap:0.3rem;color:var(--h-text3);">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            <span>Clear</span>
                        </button>
                        <button type="button" id="voice-toggle-btn" class="h-btn h-btn-subtle" style="font-size:0.75rem;padding:0.35rem 0.75rem;display:inline-flex;align-items:center;gap:0.4rem;">
                            <svg id="voice-toggle-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                            </svg>
                            <span id="voice-toggle-label">Voice: Active</span>
                        </button>
                    </div>
                </div>

                {{-- Safety Verdict & Urgency Banner --}}
                <div style="padding:1.1rem 1.25rem;border-bottom:1px solid var(--h-border);background:rgba(255,255,255,0.015);">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:0.6rem;">
                        <div style="display:flex;align-items:center;gap:0.6rem;">
                            @if(($aiRecommendations['safety_color'] ?? '') === 'red')
                                <span style="background:var(--h-red-bg);color:var(--h-red);border:1px solid rgba(239,68,68,0.3);font-size:0.75rem;font-weight:700;padding:0.25rem 0.65rem;border-radius:999px;letter-spacing:0.02em;">
                                    {{ $aiRecommendations['safety_status'] }}
                                </span>
                            @elseif(($aiRecommendations['safety_color'] ?? '') === 'amber')
                                <span style="background:var(--h-amber-bg);color:var(--h-amber);border:1px solid rgba(245,158,11,0.3);font-size:0.75rem;font-weight:700;padding:0.25rem 0.65rem;border-radius:999px;letter-spacing:0.02em;">
                                    {{ $aiRecommendations['safety_status'] }}
                                </span>
                            @else
                                <span style="background:var(--h-teal-bg);color:var(--h-teal);border:1px solid var(--h-teal-ring);font-size:0.75rem;font-weight:700;padding:0.25rem 0.65rem;border-radius:999px;letter-spacing:0.02em;">
                                    {{ $aiRecommendations['safety_status'] }}
                                </span>
                            @endif
                            <span style="font-size:0.75rem;color:var(--h-text3);">Urgency: <strong style="color:var(--h-text2);">{{ $aiRecommendations['urgency'] ?? 'Standard' }}</strong></span>
                        </div>
                        <div style="font-size:0.78rem;color:var(--h-text2);background:var(--h-surface2);padding:0.25rem 0.75rem;border-radius:var(--h-radius);border:1px solid var(--h-border);">
                            Estimated Repair: <strong style="color:var(--h-teal);">{{ $aiRecommendations['cost_estimate'] ?? 'N/A' }}</strong>
                        </div>
                    </div>
                    <div style="font-size:0.84rem;color:var(--h-text2);line-height:1.6;">
                        {{ $aiRecommendations['drive_safety'] }}
                    </div>
                </div>

                {{-- Two-Column Diagnosis: Causes & Priority Actions --}}
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%, 260px),1fr));gap:1px;background:var(--h-border);">
                    
                    {{-- Probable Causes --}}
                    <div style="background:var(--h-surface);padding:1.15rem 1.25rem;">
                        <div style="display:flex;align-items:center;gap:0.45rem;font-size:0.78rem;font-weight:700;color:var(--h-text);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.75rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--h-amber);"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            Probable Root Causes
                        </div>
                        <ul style="margin:0;padding-left:1.1rem;display:flex;flex-direction:column;gap:0.45rem;">
                            @foreach($aiRecommendations['probable_causes'] ?? [] as $cause)
                                <li style="font-size:0.8rem;color:var(--h-text2);line-height:1.5;">{{ $cause }}</li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Priority Action Checklist --}}
                    <div style="background:var(--h-surface);padding:1.15rem 1.25rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                            <div style="display:flex;align-items:center;gap:0.45rem;font-size:0.78rem;font-weight:700;color:var(--h-text);text-transform:uppercase;letter-spacing:0.04em;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--h-teal);"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                                Priority Action Checklist
                            </div>
                            <span style="font-size:0.7rem;color:var(--h-text3);">Interactive</span>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:0.45rem;">
                            @foreach($aiRecommendations['action_checklist'] ?? [] as $idx => $action)
                                <label style="display:flex;align-items:flex-start;gap:0.5rem;font-size:0.8rem;color:var(--h-text2);cursor:pointer;line-height:1.45;">
                                    <input type="checkbox" class="haven-checklist-cb" style="margin-top:0.2rem;accent-color:var(--h-teal);" onchange="this.parentElement.style.opacity = this.checked ? '0.45' : '1'; this.parentElement.style.textDecoration = this.checked ? 'line-through' : 'none';">
                                    <span>{{ $action }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                </div>

                {{-- Interactive Voice / Gigi Siri Mechanic Console --}}
                <div style="padding:1.25rem;background:var(--h-surface2);border-top:1px solid var(--h-border);">
                    
                    {{-- Conversation history area --}}
                    <div id="ai-chat-thread" style="display:flex;flex-direction:column;gap:0.75rem;max-height:260px;overflow-y:auto;padding-right:0.25rem;margin-bottom:1rem;">
                        
                        {{-- Initial AI Greeting & Overview (Clean, Sophisticated Prompt) --}}
                        <div style="display:flex;align-items:flex-start;gap:0.75rem;">
                            <div style="width:28px;height:28px;border-radius:50%;background:#000000;border:1px solid rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;margin-top:2px;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5">
                                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                                </svg>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="background:var(--h-surface);border:1px solid var(--h-border);border-radius:14px;border-top-left-radius:4px;padding:0.85rem 1.05rem;font-size:0.84rem;color:var(--h-text);line-height:1.58;max-width:90%;">
                                    <strong style="color:var(--h-teal);font-weight:600;">Welcome to Haven. Where craftsmanship meets automotive intelligence.</strong><br>
                                    {{ $aiRecommendations['summary'] }}
                                    <div style="margin-top:0.45rem;font-size:0.75rem;color:var(--h-text3);">
                                        How may I assist you with this diagnostic session? You can type questions below or select a suggested prompt.
                                    </div>
                                </div>
                                <div style="font-size:0.68rem;color:var(--h-text3);margin-top:0.3rem;margin-left:0.25rem;">
                                    Haven AI Assistant | {{ now()->format('g:i A') }}
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- Quick Suggestion Prompt Chips --}}
                    <div style="display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;margin-bottom:0.85rem;">
                        <span style="font-size:0.72rem;color:var(--h-text3);font-weight:600;margin-right:0.2rem;">Suggestions:</span>
                        @foreach($aiRecommendations['prompt_suggestions'] ?? ['Can I drive 10 miles to a garage?', 'What tools do I need for this?', 'Estimated repair cost?'] as $promptChip)
                            <button type="button" class="haven-prompt-chip" onclick="askAiQuestion('{{ addslashes($promptChip) }}')" style="background:var(--h-surface);border:1px solid var(--h-border);color:var(--h-text2);padding:0.28rem 0.65rem;border-radius:999px;font-size:0.73rem;cursor:pointer;transition:all 0.15s ease;">
                                {{ $promptChip }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Interactive Voice & Text Input Bar --}}
                    <div style="display:flex;align-items:center;gap:0.5rem;position:relative;">
                        <div style="position:relative;flex:1;">
                            <input type="text" id="ai-question-input" placeholder="Send a message or ask a diagnostic question..." style="width:100%;background:var(--h-surface);border:1px solid var(--h-border);border-radius:var(--h-radius);padding:0.6rem 2.5rem 0.6rem 0.85rem;font-size:0.82rem;color:var(--h-text);box-sizing:border-box;outline:none;transition:border-color 0.2s ease;" onkeydown="if(event.key === 'Enter') submitAiQuestion();">
                            
                            {{-- Voice Mic Button inside input --}}
                            <button type="button" id="ai-mic-btn" title="Click to speak (Voice Input)" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--h-text3);cursor:pointer;padding:5px;display:flex;align-items:center;justify-content:center;border-radius:4px;transition:color 0.2s ease;">
                                <svg id="mic-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                                    <line x1="12" y1="19" x2="12" y2="23"></line>
                                    <line x1="8" y1="23" x2="16" y2="23"></line>
                                </svg>
                            </button>
                        </div>

                        {{-- Ask / Submit button --}}
                        <button type="button" id="ai-ask-btn" onclick="submitAiQuestion()" class="h-btn h-btn-primary" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.58rem 1rem;font-size:0.8rem;white-space:nowrap;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                            <span>Ask</span>
                        </button>
                    </div>

                    {{-- Listening / Status Bar --}}
                    <div id="ai-status-indicator" style="display:none;font-size:0.74rem;color:var(--h-teal);margin-top:0.4rem;display:flex;align-items:center;gap:0.4rem;">
                        <span class="spin" style="width:10px;height:10px;border:2px solid var(--h-teal);border-top-color:transparent;border-radius:50%;display:inline-block;"></span>
                        <span id="ai-status-text">Listening... Speak now</span>
                    </div>

                </div>

            </div>
        @endif

    @elseif($diagnostic->status === 'processing')
        <div class="h-flash h-flash-warn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg>
            ML analysis in progress. Refresh in a moment.
        </div>

    @elseif($diagnostic->status === 'submitted')
        <div class="h-notice" style="margin-bottom:1.25rem;border-color:var(--h-teal-ring);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span>Data captured. Click <strong style="color:var(--h-teal);">Run Analysis</strong> above to submit for ML classification.</span>
        </div>

    @elseif($diagnostic->status === 'failed')
        <div class="h-flash h-flash-error" style="margin-bottom:1.25rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            Analysis failed. Click <strong>Run Analysis</strong> to retry.
        </div>

    @else
        <div class="h-notice" style="margin-bottom:1.25rem;">
            <svg class="h-notice-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>Add audio and/or vibration data, then click <strong style="color:var(--h-text2);">Run Analysis</strong> to classify engine condition.</span>
        </div>
    @endif

    {{-- Data cards --}}
    <div class="haven-detail-grid">

        {{-- ESP32 Hardware Multi-Sensor Telemetry Array --}}
        @if($diagnostic->hasMultiSensor())
        <div class="haven-detail-card" style="grid-column: 1 / -1; border-color: rgba(2, 132, 199, 0.35); background: linear-gradient(180deg, var(--h-surface) 0%, rgba(15, 23, 42, 0.75) 100%);">
            <div class="haven-detail-card-title" style="border-bottom: 1px solid var(--h-border); padding: 0.85rem 1.15rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.55rem;">
                    <div style="width: 24px; height: 24px; border-radius: 6px; background: rgba(2,132,199,0.15); display: flex; align-items: center; justify-content: center; color: var(--h-teal);">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>
                    </div>
                    <div>
                        <span style="font-weight: 700; color: var(--h-text); font-size: 0.88rem;">ESP32 Automotive DAQ Hardware Telemetry</span>
                        <div style="font-size: 0.68rem; color: var(--h-text3);">Direct Wi-Fi Hardware Ingestion · ISO/SAE 21434 Authenticated</div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.4rem;">
                    <span class="h-badge h-badge-teal" style="font-size: 0.68rem; letter-spacing: 0.03em;">5-Sensor DAQ Node</span>
                    <span class="h-badge h-badge-green" style="font-size: 0.68rem;">Live Stream</span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr)); gap: 0.85rem; padding: 1.15rem;">
                {{-- DS18B20 Temp --}}
                <div style="background: var(--h-surface2); border: 1px solid var(--h-border); border-radius: var(--h-radius); padding: 0.9rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
                        <span style="font-size: 0.72rem; font-weight: 700; color: var(--h-text3); text-transform: uppercase;">Engine Temp · DS18B20</span>
                        <code style="font-size: 0.68rem; color: var(--h-teal); background: rgba(2,132,199,0.12); padding: 1px 4px; border-radius: 3px;">PIN D4</code>
                    </div>
                    <div style="display: flex; align-items: baseline; gap: 0.4rem;">
                        <span style="font-size: 1.55rem; font-weight: 800; color: {{ ($diagnostic->temperature_c ?? 0) >= 105 ? 'var(--h-red)' : (($diagnostic->temperature_c ?? 0) >= 95 ? 'var(--h-amber)' : 'var(--h-text)') }};">
                            {{ $diagnostic->temperature_c !== null ? number_format($diagnostic->temperature_c, 1) : '—' }}°C
                        </span>
                        @if($diagnostic->temperature_f)
                            <span style="font-size: 0.75rem; color: var(--h-text3);">({{ $diagnostic->temperature_f }}°F)</span>
                        @endif
                    </div>
                    <div style="margin-top: 0.45rem; font-size: 0.72rem;">
                        @if(($diagnostic->temperature_c ?? 0) >= 105)
                            <span style="color: var(--h-red); font-weight: 600;">🔴 Overheating Threshold Exceeded</span>
                        @elseif(($diagnostic->temperature_c ?? 0) >= 95)
                            <span style="color: var(--h-amber); font-weight: 600;">🟡 Elevated Thermal Level</span>
                        @elseif($diagnostic->temperature_c !== null)
                            <span style="color: var(--h-green); font-weight: 600;">🟢 Optimal Operating Temp</span>
                        @else
                            <span style="color: var(--h-text3);">Sensor unread</span>
                        @endif
                    </div>
                </div>

                {{-- Hall RPM --}}
                <div style="background: var(--h-surface2); border: 1px solid var(--h-border); border-radius: var(--h-radius); padding: 0.9rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
                        <span style="font-size: 0.72rem; font-weight: 700; color: var(--h-text3); text-transform: uppercase;">Tachometer · Hall RPM</span>
                        <code style="font-size: 0.68rem; color: var(--h-teal); background: rgba(2,132,199,0.12); padding: 1px 4px; border-radius: 3px;">PIN D23</code>
                    </div>
                    <div style="display: flex; align-items: baseline; gap: 0.4rem;">
                        <span style="font-size: 1.55rem; font-weight: 800; color: var(--h-text);">
                            {{ $diagnostic->rpm !== null ? number_format($diagnostic->rpm) : '—' }}
                        </span>
                        <span style="font-size: 0.75rem; color: var(--h-text3);">RPM</span>
                    </div>
                    <div style="margin-top: 0.45rem; font-size: 0.72rem;">
                        @if(($diagnostic->rpm ?? 0) > 4000)
                            <span style="color: var(--h-amber); font-weight: 600;">⚡ High Load Velocity</span>
                        @elseif(($diagnostic->rpm ?? 0) >= 600)
                            <span style="color: var(--h-green); font-weight: 600;">🟢 Smooth Harmonic Rotation</span>
                        @elseif($diagnostic->rpm !== null)
                            <span style="color: var(--h-text3);">Cranking / Standstill</span>
                        @else
                            <span style="color: var(--h-text3);">Sensor unread</span>
                        @endif
                    </div>
                </div>

                {{-- INA219 Voltage & Current --}}
                <div style="background: var(--h-surface2); border: 1px solid var(--h-border); border-radius: var(--h-radius); padding: 0.9rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
                        <span style="font-size: 0.72rem; font-weight: 700; color: var(--h-text3); text-transform: uppercase;">DC Power · INA219</span>
                        <code style="font-size: 0.68rem; color: var(--h-teal); background: rgba(2,132,199,0.12); padding: 1px 4px; border-radius: 3px;">I2C D21/D22</code>
                    </div>
                    <div style="display: flex; align-items: baseline; gap: 0.4rem;">
                        <span style="font-size: 1.55rem; font-weight: 800; color: {{ ($diagnostic->voltage_v ?? 0) < 12.2 ? 'var(--h-amber)' : 'var(--h-text)' }};">
                            {{ $diagnostic->voltage_v !== null ? number_format($diagnostic->voltage_v, 2) : '—' }}V
                        </span>
                        @if($diagnostic->current_ma !== null)
                            <span style="font-size: 0.75rem; color: var(--h-text3);">({{ round($diagnostic->current_ma) }} mA)</span>
                        @endif
                    </div>
                    <div style="margin-top: 0.45rem; font-size: 0.72rem;">
                        @if(($diagnostic->voltage_v ?? 0) >= 13.5)
                            <span style="color: var(--h-green); font-weight: 600;">🟢 Alternator Actively Charging</span>
                        @elseif(($diagnostic->voltage_v ?? 0) >= 12.4)
                            <span style="color: var(--h-teal); font-weight: 600;">🔋 Normal Battery Resting Voltage</span>
                        @elseif($diagnostic->voltage_v !== null)
                            <span style="color: var(--h-amber); font-weight: 600;">⚠️ Weak Battery / Low Charge</span>
                        @else
                            <span style="color: var(--h-text3);">Sensor unread</span>
                        @endif
                    </div>
                </div>

                {{-- MPU6050 & INMP441 --}}
                <div style="background: var(--h-surface2); border: 1px solid var(--h-border); border-radius: var(--h-radius); padding: 0.9rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
                        <span style="font-size: 0.72rem; font-weight: 700; color: var(--h-text3); text-transform: uppercase;">Acoustic & Vibration</span>
                        <code style="font-size: 0.68rem; color: var(--h-teal); background: rgba(2,132,199,0.12); padding: 1px 4px; border-radius: 3px;">I2S & I2C</code>
                    </div>
                    <div style="display: flex; align-items: baseline; gap: 0.5rem;">
                        <span style="font-size: 1.55rem; font-weight: 800; color: var(--h-text);">
                            {{ $diagnostic->vibration_peak_g ?? '—' }}g
                        </span>
                        <span style="font-size: 0.75rem; color: var(--h-text3);">Peak G-Force</span>
                    </div>
                    <div style="margin-top: 0.45rem; font-size: 0.72rem;">
                        <span style="color: var(--h-text2);">MPU6050 (D21/D22) · INMP441 (D18/D19/D5)</span>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Audio --}}
        <div class="haven-detail-card">
            <div class="haven-detail-card-title">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
                Engine Audio
                @if($diagnostic->hasAudio())
                    <span class="h-badge h-badge-teal" style="margin-left:auto;">Captured</span>
                @else
                    <span class="h-badge h-badge-gray" style="margin-left:auto;">Missing</span>
                @endif
            </div>
            <div class="haven-detail-rows">
                @if($diagnostic->hasAudio())
                    @if($diagnostic->audio_original_name)
                        <div class="haven-detail-row"><span class="haven-detail-label">File</span><span class="haven-detail-value" style="font-size:0.75rem;">{{ $diagnostic->audio_original_name }}</span></div>
                    @endif
                    @if($diagnostic->audio_duration_sec)
                        <div class="haven-detail-row">
                            <span class="haven-detail-label">Duration</span>
                            <span class="haven-detail-value">{{ floor($diagnostic->audio_duration_sec/60) }}:{{ str_pad($diagnostic->audio_duration_sec%60,2,'0',STR_PAD_LEFT) }}</span>
                        </div>
                    @endif
                    <div style="padding:0.875rem 1rem;">
                        <audio controls style="width:100%;" src="{{ Storage::disk('public')->url($diagnostic->audio_path) }}"></audio>
                    </div>
                @else
                    <p style="padding:0.75rem 1rem;font-size:0.8rem;color:var(--h-text3);margin:0;">No audio recorded for this session.</p>
                @endif
            </div>
        </div>

        {{-- Vibration --}}
        <div class="haven-detail-card">
            <div class="haven-detail-card-title">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Vibration Data
                @if($diagnostic->hasVibration())
                    <span class="h-badge h-badge-teal" style="margin-left:auto;">Captured</span>
                @else
                    <span class="h-badge h-badge-gray" style="margin-left:auto;">Missing</span>
                @endif
            </div>
            <div class="haven-detail-rows">
                @if($diagnostic->hasVibration())
                    @if($diagnostic->vibration_sample_count)
                        <div class="haven-detail-row"><span class="haven-detail-label">Samples</span><span class="haven-detail-value">{{ number_format($diagnostic->vibration_sample_count) }}</span></div>
                    @endif
                    @if($diagnostic->vibration_peak_g !== null)
                        <div class="haven-detail-row"><span class="haven-detail-label">Peak</span><span class="haven-detail-value" style="color:var(--h-amber);">{{ $diagnostic->vibration_peak_g }} g</span></div>
                    @endif
                    @if($diagnostic->vibration_rms_g !== null)
                        <div class="haven-detail-row"><span class="haven-detail-label">RMS</span><span class="haven-detail-value" style="color:var(--h-teal);">{{ $diagnostic->vibration_rms_g }} g</span></div>
                    @endif
                    @if($diagnostic->vibration_peak_g && $diagnostic->vibration_rms_g)
                        <div class="haven-detail-row">
                            <span class="haven-detail-label">Crest Factor</span>
                            <span class="haven-detail-value">{{ $diagnostic->vibration_rms_g > 0 ? round($diagnostic->vibration_peak_g / $diagnostic->vibration_rms_g, 2) : '—' }}</span>
                        </div>
                    @endif
                    <div style="padding:0.5rem 1rem 0.875rem;">
                        <a href="{{ Storage::disk('public')->url($diagnostic->vibration_path) }}" download
                           class="haven-action-btn haven-action-btn-secondary" style="font-size:0.75rem;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Download Data File
                        </a>
                    </div>
                @else
                    <p style="padding:0.75rem 1rem;font-size:0.8rem;color:var(--h-text3);margin:0;">No vibration data for this session.</p>
                @endif
            </div>
        </div>

        {{-- Session info --}}
        <div class="haven-detail-card">
            <div class="haven-detail-card-title">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                Session Info
            </div>
            <div class="haven-detail-rows">
                <div class="haven-detail-row"><span class="haven-detail-label">Vehicle</span><span class="haven-detail-value">{{ $diagnostic->vehicle->display_name }}</span></div>
                <div class="haven-detail-row"><span class="haven-detail-label">Engine</span><span class="haven-detail-value">{{ $diagnostic->vehicle->engine_type ?: '—' }}</span></div>
                <div class="haven-detail-row"><span class="haven-detail-label">Date</span><span class="haven-detail-value">{{ $diagnostic->created_at->format('M j, Y') }}</span></div>
                <div class="haven-detail-row"><span class="haven-detail-label">Time</span><span class="haven-detail-value">{{ $diagnostic->created_at->format('g:i A') }}</span></div>
                @if($diagnostic->ml_processed_at)
                    <div class="haven-detail-row"><span class="haven-detail-label">Analyzed</span><span class="haven-detail-value">{{ $diagnostic->ml_processed_at->format('M j, g:i A') }}</span></div>
                @endif
            </div>
        </div>

        {{-- Notes --}}
        @if($diagnostic->notes)
            <div class="haven-detail-card">
                <div class="haven-detail-card-title">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Session Notes
                </div>
                <p style="padding:0.875rem 1rem;font-size:0.82rem;color:var(--h-text2);line-height:1.65;margin:0;">{{ $diagnostic->notes }}</p>
            </div>
        @endif

    </div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin 1s linear infinite; display: inline-block; }

.haven-prompt-chip:hover {
    border-color: var(--h-teal) !important;
    color: var(--h-teal) !important;
    background: var(--h-teal-bg) !important;
    transform: translateY(-1px);
}

.ai-bubble-user {
    background: linear-gradient(135deg, rgba(2, 132, 199, 0.2) 0%, rgba(3, 105, 161, 0.15) 100%);
    border: 1px solid var(--h-teal-ring);
    color: var(--h-text);
    border-radius: var(--h-radius);
    padding: 0.7rem 0.95rem;
    font-size: 0.82rem;
    max-width: 82%;
    align-self: flex-end;
    line-height: 1.45;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
}

.diag-msg-time {
    font-size: 0.65rem;
    color: var(--h-text3);
    margin-top: 0.25rem;
    text-align: right;
}

.ai-bubble-bot {
    background: var(--h-surface);
    border: 1px solid var(--h-border);
    color: var(--h-text);
    border-radius: 0.95rem;
    border-top-left-radius: 0.2rem;
    padding: 0.85rem 1rem;
    font-size: 0.82rem;
    line-height: 1.6;
    max-width: 88%;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}

/* Rich Diagnostic Response Styles */
.diag-res-header {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--h-teal);
    margin-bottom: 0.4rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.diag-res-alert {
    padding: 0.6rem 0.8rem;
    border-radius: var(--h-radius);
    margin: 0.45rem 0;
    font-size: 0.8rem;
    line-height: 1.5;
}
.diag-res-alert.danger {
    background: var(--h-red-bg);
    border: 1px solid rgba(239, 68, 68, 0.35);
    color: var(--h-red);
}
.diag-res-alert.warn {
    background: var(--h-amber-bg);
    border: 1px solid rgba(245, 158, 11, 0.35);
    color: var(--h-amber);
}
.diag-res-alert.success {
    background: var(--h-green-bg);
    border: 1px solid rgba(34, 197, 94, 0.35);
    color: var(--h-green);
}

.diag-res-list {
    margin: 0.35rem 0;
    padding-left: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.diag-res-list li {
    position: relative;
    padding-left: 1rem;
    font-size: 0.8rem;
    color: var(--h-text2);
    line-height: 1.5;
}
.diag-res-list li::before {
    content: "•";
    position: absolute;
    left: 0.15rem;
    color: var(--h-teal);
    font-weight: bold;
    font-size: 0.95rem;
    top: -1px;
}

.diag-res-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.65rem;
    padding-top: 0.45rem;
    border-top: 1px solid var(--h-border);
    font-size: 0.68rem;
    color: var(--h-text3);
}

.diag-res-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.diag-action-btn {
    background: none;
    border: 1px solid transparent;
    color: var(--h-text3);
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.68rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    transition: all 0.15s ease;
    font-family: inherit;
}
.diag-action-btn:hover {
    color: var(--h-teal);
    background: var(--h-surface2);
    border-color: var(--h-border);
}

/* Wave Thinking Indicator */
.diag-wave-thinking {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--h-text3);
    font-size: 0.78rem;
}
.diag-wave-bars {
    display: flex;
    align-items: center;
    gap: 3px;
    height: 14px;
}
.diag-wave-bar {
    width: 3px;
    height: 100%;
    border-radius: 2px;
    background: linear-gradient(180deg, #0284c7 0%, #38bdf8 100%);
    animation: diagWavePulse 0.9s ease-in-out infinite alternate;
}
.diag-wave-bar:nth-child(1) { animation-delay: 0s; height: 50%; }
.diag-wave-bar:nth-child(2) { animation-delay: 0.2s; height: 100%; }
.diag-wave-bar:nth-child(3) { animation-delay: 0.4s; height: 70%; }
.diag-wave-bar:nth-child(4) { animation-delay: 0.1s; height: 90%; }

@keyframes diagWavePulse {
    0% { transform: scaleY(0.4); opacity: 0.5; }
    100% { transform: scaleY(1.3); opacity: 1; }
}

.mic-active {
    color: #ef4444 !important;
    animation: micPulse 1s infinite alternate;
}

@keyframes micPulse {
    from { transform: translateY(-50%) scale(1); }
    to { transform: translateY(-50%) scale(1.2); }
}
</style>

@if(!empty($aiRecommendations))
<script>
document.addEventListener('DOMContentLoaded', function() {
    const thread = document.getElementById('ai-chat-thread');
    const input = document.getElementById('ai-question-input');
    const askBtn = document.getElementById('ai-ask-btn');
    const micBtn = document.getElementById('ai-mic-btn');
    const micIcon = document.getElementById('mic-icon');
    const orb = document.getElementById('haven-ai-orb');
    const statusBox = document.getElementById('ai-status-indicator');
    const statusText = document.getElementById('ai-status-text');
    const voiceToggleBtn = document.getElementById('voice-toggle-btn');
    const voiceToggleLabel = document.getElementById('voice-toggle-label');
    const voiceToggleIcon = document.getElementById('voice-toggle-icon');

    const STORAGE_KEY = 'haven_diag_ai_{{ $diagnostic->id }}';
    const historyBadge = document.getElementById('diag-history-badge');
    const clearHistoryBtn = document.getElementById('diag-clear-history');
    const diagMessageStore = {};

    let voiceEnabled = true;
    let isListening = false;
    let recognition = null;

    function getFormattedTime() {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function getDiagHistory() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch(e) {
            console.warn('Failed to parse diagnostic history:', e);
            return [];
        }
    }

    function saveDiagMessage(role, text, time) {
        try {
            const hist = getDiagHistory();
            hist.push({
                id: 'diag-msg-' + Date.now() + '-' + Math.random().toString(36).substr(2, 4),
                role: role,
                text: text,
                time: time || getFormattedTime()
            });
            if (hist.length > 50) hist.splice(0, hist.length - 50);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(hist));
            updateDiagHistoryBadge(hist.length);
        } catch(e) {
            console.warn('Failed to save diag history:', e);
        }
    }

    function updateDiagHistoryBadge(count) {
        if (!historyBadge) return;
        if (count > 0) {
            historyBadge.style.display = 'inline-flex';
            historyBadge.style.alignItems = 'center';
            historyBadge.style.whiteSpace = 'nowrap';
            historyBadge.textContent = `${count} msg${count > 1 ? 's' : ''}`;
            if (clearHistoryBtn) clearHistoryBtn.style.display = 'inline-flex';
        } else {
            historyBadge.style.display = 'none';
            if (clearHistoryBtn) clearHistoryBtn.style.display = 'none';
        }
    }

    // Check Speech Recognition capability
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'en-US';

        recognition.onstart = function() {
            isListening = true;
            micBtn.classList.add('mic-active');
            orb.classList.add('listening');
            statusBox.style.display = 'flex';
            statusText.textContent = 'Listening... Speak your question now';
        };

        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            input.value = transcript;
            statusText.textContent = `Heard: "${transcript}"`;
            setTimeout(() => {
                submitAiQuestion();
            }, 400);
        };

        recognition.onerror = function(event) {
            console.warn('Speech recognition error:', event.error);
            stopListening();
            statusText.textContent = 'Could not catch audio. Please try again.';
            setTimeout(() => { statusBox.style.display = 'none'; }, 2500);
        };

        recognition.onend = function() {
            stopListening();
        };
    } else {
        if (micBtn) {
            micBtn.title = 'Speech recognition not supported in this browser. Please use Chrome or Edge.';
            micBtn.style.opacity = '0.5';
        }
    }

    function startListening() {
        if (!recognition) {
            alert('Voice recognition requires Google Chrome, Microsoft Edge, or Safari.');
            return;
        }
        try {
            recognition.start();
        } catch(e) {
            console.error(e);
        }
    }

    function stopListening() {
        isListening = false;
        if (micBtn) micBtn.classList.remove('mic-active');
        if (orb) orb.classList.remove('listening');
        if (statusBox) {
            setTimeout(() => { statusBox.style.display = 'none'; }, 1500);
        }
    }

    if (micBtn) {
        micBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (isListening) {
                recognition.stop();
                stopListening();
            } else {
                startListening();
            }
        });
    }

    // Voice Readout toggle
    if (voiceToggleBtn) {
        voiceToggleBtn.addEventListener('click', function() {
            voiceEnabled = !voiceEnabled;
            if (voiceEnabled) {
                voiceToggleLabel.textContent = 'Voice: Active';
                voiceToggleBtn.style.borderColor = 'var(--h-teal)';
                voiceToggleBtn.style.color = 'var(--h-teal)';
                voiceToggleIcon.innerHTML = '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>';
            } else {
                voiceToggleLabel.textContent = 'Voice: Muted';
                voiceToggleBtn.style.borderColor = 'var(--h-border)';
                voiceToggleBtn.style.color = 'var(--h-text3)';
                voiceToggleIcon.innerHTML = '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line>';
                if ('speechSynthesis' in window) {
                    window.speechSynthesis.cancel();
                }
                orb.classList.remove('speaking');
            }
        });
    }

    // Text to Speech
    function speakText(text) {
        if (!voiceEnabled || !('speechSynthesis' in window)) return;
        window.speechSynthesis.cancel();

        const clean = text
            .replace(/[*#_~`]/g, '')
            .replace(/([^\w\s.,!?'"/-])/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();

        const utterance = new SpeechSynthesisUtterance(clean);
        utterance.rate = 1.05;
        utterance.pitch = 1.0;

        utterance.onstart = function() {
            orb.classList.add('speaking');
        };

        utterance.onend = utterance.onerror = function() {
            orb.classList.remove('speaking');
        };

        window.speechSynthesis.speak(utterance);
    }

    // Rich Diagnostic Response Formatter
    function parseDiagMarkdown(text) {
        const lines = text.split('\n');
        let html = '';
        let inList = false;

        for (let i = 0; i < lines.length; i++) {
            let line = lines[i].trim();
            if (!line) {
                if (inList) { html += '</ul>'; inList = false; }
                continue;
            }

            // Alerts
            if (line.startsWith('🚨') || line.startsWith('🛑') || line.toLowerCase().startsWith('danger:') || line.toLowerCase().startsWith('critical:')) {
                if (inList) { html += '</ul>'; inList = false; }
                const clean = line.replace(/^(🚨|🛑|danger:|critical:)\s*/i, '');
                html += `<div class="diag-res-alert danger"><div style="font-weight:700;display:flex;align-items:center;gap:0.35rem;margin-bottom:0.2rem;"><span>🚨</span> Immediate Attention</div>${formatInlineMd(clean)}</div>`;
                continue;
            }
            if (line.startsWith('⚠️') || line.startsWith('🟡') || line.toLowerCase().startsWith('warning:') || line.toLowerCase().startsWith('caution:')) {
                if (inList) { html += '</ul>'; inList = false; }
                const clean = line.replace(/^(⚠️|🟡|warning:|caution:)\s*/i, '');
                html += `<div class="diag-res-alert warn"><div style="font-weight:700;display:flex;align-items:center;gap:0.35rem;margin-bottom:0.2rem;"><span>⚠️</span> Warning Advisory</div>${formatInlineMd(clean)}</div>`;
                continue;
            }
            if (line.startsWith('✅') || line.toLowerCase().startsWith('success:')) {
                if (inList) { html += '</ul>'; inList = false; }
                const clean = line.replace(/^(✅|success:)\s*/i, '');
                html += `<div class="diag-res-alert success"><div style="font-weight:700;display:flex;align-items:center;gap:0.35rem;margin-bottom:0.2rem;"><span>✅</span> Verified Normal</div>${formatInlineMd(clean)}</div>`;
                continue;
            }

            // Lists
            if (line.startsWith('•') || line.startsWith('- ') || line.startsWith('* ')) {
                if (!inList) {
                    html += '<ul class="diag-res-list">';
                    inList = true;
                }
                const bulletContent = line.replace(/^[•\-\*]\s*/, '');
                html += `<li>${formatInlineMd(bulletContent)}</li>`;
                continue;
            } else {
                if (inList) { html += '</ul>'; inList = false; }
            }

            // Headers
            if (line.match(/^\*\*(.+?):\*\*/)) {
                const headerText = line.replace(/^\*\*(.+?):\*\*/, '$1');
                html += `<div class="diag-res-header">⚡ ${escapeHtml(headerText)}</div>`;
                continue;
            } else if (line.startsWith('### ')) {
                html += `<div class="diag-res-header">⚡ ${escapeHtml(line.replace('### ', ''))}</div>`;
                continue;
            }

            html += `<p style="margin:0.35rem 0;">${formatInlineMd(line)}</p>`;
        }

        if (inList) html += '</ul>';
        return html;
    }

    function formatInlineMd(str) {
        return str
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`([^`]+)`/g, '<code style="background:var(--h-surface2);padding:0.1rem 0.35rem;border-radius:3px;font-size:0.75rem;color:var(--h-teal);">$1</code>');
    }

    function createDiagBotNode(text, timeStr, msgId) {
        msgId = msgId || ('diag-' + Date.now() + '-' + Math.random().toString(36).substr(2, 4));
        timeStr = timeStr || getFormattedTime();
        diagMessageStore[msgId] = text;

        const wrap = document.createElement('div');
        wrap.style.display = 'flex';
        wrap.style.alignItems = 'flex-start';
        wrap.style.gap = '0.65rem';
        wrap.id = msgId;

        wrap.innerHTML = `
            <div style="width:26px;height:26px;border-radius:50%;background:radial-gradient(circle at 30% 30%, #38bdf8 0%, #0284c7 60%, #0369a1 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.65rem;font-weight:800;flex-shrink:0;margin-top:2px;box-shadow:0 0 10px rgba(2,132,199,0.35);">AI</div>
            <div class="ai-bubble-bot">
                <div>${parseDiagMarkdown(text)}</div>
                <div class="diag-res-footer">
                    <span>${escapeHtml(timeStr)}</span>
                    <div class="diag-res-actions">
                        <button type="button" class="diag-action-btn" onclick="speakDiagMessage('${msgId}')" title="Listen to response">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                            Listen
                        </button>
                        <button type="button" class="diag-action-btn" id="diag-copy-${msgId}" onclick="copyDiagMessage('${msgId}')" title="Copy response">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            Copy
                        </button>
                    </div>
                </div>
            </div>
        `;
        return wrap;
    }

    function createDiagUserNode(text, timeStr) {
        timeStr = timeStr || getFormattedTime();
        const wrap = document.createElement('div');
        wrap.style.display = 'flex';
        wrap.style.flexDirection = 'column';
        wrap.style.alignItems = 'flex-end';
        wrap.innerHTML = `
            <div class="ai-bubble-user">
                <strong>You:</strong> ${escapeHtml(text)}
            </div>
            <div class="diag-msg-time">${escapeHtml(timeStr)}</div>
        `;
        return wrap;
    }

    window.speakDiagMessage = function(id) {
        const text = diagMessageStore[id];
        if (text) speakText(text);
    };

    window.copyDiagMessage = function(id) {
        const text = diagMessageStore[id];
        if (!text) return;
        const btn = document.getElementById(`diag-copy-${id}`);
        navigator.clipboard.writeText(text).then(() => {
            if (btn) {
                const oldHTML = btn.innerHTML;
                btn.innerHTML = `
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color:var(--h-teal);"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span style="color:var(--h-teal);font-weight:600;">Copied!</span>
                `;
                setTimeout(() => { btn.innerHTML = oldHTML; }, 1800);
            }
        });
    };

    const initialGreetingHTML = thread.innerHTML;

    function loadDiagHistory() {
        const history = getDiagHistory();
        updateDiagHistoryBadge(history.length);
        if (history.length === 0) return;

        thread.innerHTML = initialGreetingHTML;
        history.forEach(item => {
            if (item.role === 'user') {
                thread.appendChild(createDiagUserNode(item.text, item.time));
            } else {
                thread.appendChild(createDiagBotNode(item.text, item.time, item.id));
            }
        });
        setTimeout(() => { thread.scrollTop = thread.scrollHeight; }, 60);
    }

    window.clearDiagHistory = function() {
        if (!confirm('Clear all Copilot conversation history for this diagnostic session?')) return;
        localStorage.removeItem(STORAGE_KEY);
        updateDiagHistoryBadge(0);
        thread.innerHTML = initialGreetingHTML;
    };

    // Global ask Ai Question
    window.askAiQuestion = function(q) {
        input.value = q;
        submitAiQuestion();
    };

    window.submitAiQuestion = function() {
        const question = input.value.trim();
        if (!question) return;

        input.value = '';
        input.focus();

        const currentTime = getFormattedTime();

        // Append user node & save to history
        thread.appendChild(createDiagUserNode(question, currentTime));
        saveDiagMessage('user', question, currentTime);
        thread.scrollTop = thread.scrollHeight;

        // Append Wave Thinking Bubble
        const tempId = 'ai-typing-' + Date.now();
        const botDiv = document.createElement('div');
        botDiv.style.display = 'flex';
        botDiv.style.alignItems = 'flex-start';
        botDiv.style.gap = '0.65rem';
        botDiv.id = tempId;
        botDiv.innerHTML = `
            <div style="width:26px;height:26px;border-radius:50%;background:radial-gradient(circle at 30% 30%, #38bdf8 0%, #0284c7 60%, #0369a1 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.65rem;font-weight:800;flex-shrink:0;margin-top:2px;">AI</div>
            <div class="ai-bubble-bot">
                <div class="diag-wave-thinking">
                    <div class="diag-wave-bars">
                        <div class="diag-wave-bar"></div>
                        <div class="diag-wave-bar"></div>
                        <div class="diag-wave-bar"></div>
                        <div class="diag-wave-bar"></div>
                    </div>
                    <span>HAVEN AI is analyzing vehicle metrics & diagnostic data...</span>
                </div>
            </div>
        `;
        thread.appendChild(botDiv);
        thread.scrollTop = thread.scrollHeight;

        // Orb pulse
        orb.style.boxShadow = '0 0 25px rgba(2,132,199,0.8), 0 0 40px rgba(56,189,248,0.5)';

        // API Call
        fetch('{{ route('diagnostics.ai.ask', $diagnostic) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ question: question })
        })
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            const botMsgId = 'diag-msg-' + Date.now();
            const botTime = getFormattedTime();
            const botNode = createDiagBotNode(data.answer, botTime, botMsgId);

            const placeholder = document.getElementById(tempId);
            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.replaceChild(botNode, placeholder);
            } else {
                thread.appendChild(botNode);
            }

            saveDiagMessage('bot', data.answer, botTime);
            orb.style.boxShadow = '';
            thread.scrollTop = thread.scrollHeight;
            speakText(data.answer);
        })
        .catch(err => {
            console.error('AI ask error:', err);
            const placeholder = document.getElementById(tempId);
            if (placeholder) {
                placeholder.innerHTML = `
                    <div style="width:26px;height:26px;border-radius:50%;background:#ef4444;display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.75rem;font-weight:bold;flex-shrink:0;">!</div>
                    <div class="ai-bubble-bot" style="border-color:rgba(239,68,68,0.4);color:var(--h-red);">
                        Unable to reach HAVEN AI service right now. Please check your connection and try again.
                    </div>
                `;
            }
            orb.style.boxShadow = '';
        });
    };

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize diagnostic history on load
    loadDiagHistory();
});
</script>
@endif

</x-app-layout>
