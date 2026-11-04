<x-app-layout :title="$module['title']">

    {{-- Reading progress bar (fixed top) --}}
    <div id="lrn-read-progress-wrap" style="position:fixed;top:0;left:0;right:0;height:3px;z-index:9999;pointer-events:none;">
        <div id="lrn-read-progress-bar" style="height:100%;background:var(--h-teal);width:0%;transition:width 0.1s linear;"></div>
    </div>

    {{-- Breadcrumb --}}
    <div class="h-breadcrumb">
        <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="h-breadcrumb-link">{{ auth()->check() ? 'Dashboard' : 'Home' }}</a>
        <span class="h-breadcrumb-sep">/</span>
        <a href="{{ route('learning.index') }}" class="h-breadcrumb-link">Learning Modules</a>
        <span class="h-breadcrumb-sep">/</span>
        @if(isset($parentTopic))
            <a href="{{ route('learning.topic', $parentTopic['slug']) }}" class="h-breadcrumb-link">{{ $parentTopic['title'] }}</a>
            <span class="h-breadcrumb-sep">/</span>
        @endif
        <span class="h-breadcrumb-current">{{ $module['title'] }}</span>
    </div>

    {{-- Top hero header --}}
    <div class="h-card" style="padding:1.75rem;margin-bottom:1.5rem;background:var(--h-surface);">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:0.75rem;">
            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                <span style="font-size:0.72rem;font-weight:600;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.08em;">
                    {{ $module['category'] }}
                </span>
                <span style="color:var(--h-text3);">·</span>
                @if($module['severity_color'] === 'red')
                    <span class="h-badge h-badge-red">{{ $module['severity'] }} Severity</span>
                @elseif($module['severity_color'] === 'amber')
                    <span class="h-badge h-badge-amber">{{ $module['severity'] }} Severity</span>
                @else
                    <span class="h-badge h-badge-teal">{{ $module['severity'] }}</span>
                @endif
                <span style="color:var(--h-text3);">·</span>
                <span style="font-size:0.75rem;color:var(--h-text3);">{{ $module['read_time'] }}</span>
            </div>

            {{-- Mark as complete button --}}
            <div class="lrn-complete-btn-wrap">
                <button type="button" id="lrn-complete-btn" onclick="toggleComplete('{{ $module['slug'] }}')"
                        style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.45rem 0.95rem;border-radius:999px;font-size:0.78rem;font-weight:600;border:1px solid var(--h-border);background:var(--h-surface2);color:var(--h-text2);cursor:pointer;transition:all 0.2s;font-family:inherit;min-height:38px;">
                    <svg id="lrn-complete-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span id="lrn-complete-label">Mark as Complete</span>
                </button>
            </div>
        </div>

        <h1 style="font-size:1.75rem;font-weight:800;color:var(--h-text);letter-spacing:-0.03em;margin:0 0 0.75rem;line-height:1.2;">
            {{ $module['title'] }}
        </h1>
        <p style="font-size:0.95rem;color:var(--h-text2);line-height:1.65;max-width:780px;margin:0;">
            {{ $module['summary'] }}
        </p>
    </div>

    <div style="display:grid;grid-template-columns:1fr;gap:1.5rem;">

        {{-- Telemetry Signature Grid --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(100%, 280px), 1fr));gap:1.25rem;">
            {{-- Acoustic Card --}}
            <div class="h-card" style="padding:1.5rem;background:var(--h-surface);">
                <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1rem;">
                    <div style="width:34px;height:34px;border-radius:var(--h-radius);background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);display:flex;align-items:center;justify-content:center;color:var(--h-teal);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                    </div>
                    <div>
                        <div style="font-size:0.68rem;font-weight:600;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.07em;">Signal Processing</div>
                        <h3 style="font-size:0.95rem;font-weight:700;color:var(--h-text);margin:0;">Acoustic Audio Signature</h3>
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:0.75rem;font-size:0.82rem;">
                    <div style="padding:0.65rem 0.75rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid var(--h-border);">
                        <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;margin-bottom:0.2rem;">Frequency Bandwidth</div>
                        <div style="font-weight:600;color:var(--h-text);">{{ $module['acoustic_signature']['frequency_range'] }}</div>
                    </div>
                    <div style="padding:0.65rem 0.75rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid var(--h-border);">
                        <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;margin-bottom:0.2rem;">Zero Crossing Rate (ZCR)</div>
                        <div style="font-weight:600;color:var(--h-text);">{{ $module['acoustic_signature']['zcr'] }}</div>
                    </div>
                    <div style="padding:0.65rem 0.75rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid var(--h-border);">
                        <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;margin-bottom:0.2rem;">Spectral Centroid</div>
                        <div style="font-weight:600;color:var(--h-teal);">{{ $module['acoustic_signature']['spectral_centroid'] }}</div>
                    </div>
                    <div style="padding:0.65rem 0.75rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid var(--h-border);">
                        <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;margin-bottom:0.2rem;">Acoustic Envelope</div>
                        <div style="color:var(--h-text2);line-height:1.5;">{{ $module['acoustic_signature']['characteristics'] }}</div>
                    </div>
                </div>
            </div>

            {{-- Vibration Card --}}
            <div class="h-card" style="padding:1.5rem;background:var(--h-surface);">
                <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1rem;">
                    <div style="width:34px;height:34px;border-radius:var(--h-radius);background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);display:flex;align-items:center;justify-content:center;color:var(--h-amber);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </div>
                    <div>
                        <div style="font-size:0.68rem;font-weight:600;color:var(--h-amber);text-transform:uppercase;letter-spacing:0.07em;">ESP32 Accelerometer</div>
                        <h3 style="font-size:0.95rem;font-weight:700;color:var(--h-text);margin:0;">Vibration Telemetry Signature</h3>
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:0.75rem;font-size:0.82rem;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                        <div style="padding:0.65rem 0.75rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid var(--h-border);">
                            <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;margin-bottom:0.2rem;">Peak G-Force</div>
                            <div style="font-weight:700;color:var(--h-text);font-size:1rem;">{{ $module['vibration_signature']['peak_g'] }}</div>
                        </div>
                        <div style="padding:0.65rem 0.75rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid var(--h-border);">
                            <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;margin-bottom:0.2rem;">Crest Factor</div>
                            <div style="font-weight:700;color:var(--h-teal);font-size:1rem;">{{ $module['vibration_signature']['crest_factor'] }}</div>
                        </div>
                    </div>
                    <div style="padding:0.65rem 0.75rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid var(--h-border);">
                        <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;margin-bottom:0.2rem;">RMS G Baseline</div>
                        <div style="font-weight:600;color:var(--h-text);">{{ $module['vibration_signature']['rms_g'] }}</div>
                    </div>
                    <div style="padding:0.65rem 0.75rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid var(--h-border);">
                        <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;margin-bottom:0.2rem;">Dynamic Pattern</div>
                        <div style="color:var(--h-text2);line-height:1.5;">{{ $module['vibration_signature']['pattern'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Root Causes & Mechanics --}}
        <div class="h-card" style="padding:1.75rem;background:var(--h-surface);">
            <div style="font-size:0.72rem;font-weight:600;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.35rem;">Failure Mode Analysis</div>
            <h2 style="font-size:1.15rem;font-weight:700;color:var(--h-text);margin:0 0 1rem;">Primary Root Causes</h2>
            <div style="display:flex;flex-direction:column;gap:0.75rem;">
                @foreach($module['root_causes'] as $cause)
                    <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.75rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);">
                        <div style="width:20px;height:20px;border-radius:50%;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);display:flex;align-items:center;justify-content:center;color:var(--h-red);flex-shrink:0;margin-top:2px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </div>
                        <div style="font-size:0.875rem;color:var(--h-text2);line-height:1.5;">
                            {{ $cause }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Diagnostic Workflow --}}
        <div class="h-card" style="padding:1.75rem;background:var(--h-surface);">
            <div style="font-size:0.72rem;font-weight:600;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.35rem;">Procedural Protocol</div>
            <h2 style="font-size:1.15rem;font-weight:700;color:var(--h-text);margin:0 0 1rem;">Step-by-Step Diagnostic Workflow</h2>
            <div style="display:flex;flex-direction:column;gap:0.875rem;">
                @foreach($module['diagnostic_steps'] as $index => $step)
                    <div class="lrn-step-row" style="display:flex;align-items:flex-start;gap:0.875rem;padding:0.875rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);">
                        <div style="width:28px;height:28px;border-radius:50%;background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);display:flex;align-items:center;justify-content:center;color:var(--h-teal);font-weight:800;font-size:0.75rem;flex-shrink:0;">
                            {{ $index + 1 }}
                        </div>
                        <div style="font-size:0.875rem;color:var(--h-text);line-height:1.55;">
                            {{ $step }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Preventive Maintenance & DTC Codes --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:1.25rem;">
            {{-- Preventive Measures --}}
            <div class="h-card" style="padding:1.5rem;background:var(--h-surface);">
                <div style="font-size:0.72rem;font-weight:600;color:var(--h-green);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.35rem;">Maintenance Advice</div>
                <h3 style="font-size:1rem;font-weight:700;color:var(--h-text);margin:0 0 1rem;">Preventive Guidelines</h3>
                <div style="display:flex;flex-direction:column;gap:0.6rem;">
                    @foreach($module['preventive_actions'] as $act)
                        <div style="display:flex;align-items:flex-start;gap:0.65rem;padding:0.6rem 0.75rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid var(--h-border);">
                            <div style="width:18px;height:18px;border-radius:50%;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);display:flex;align-items:center;justify-content:center;color:var(--h-green);flex-shrink:0;margin-top:1px;">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <span style="font-size:0.84rem;color:var(--h-text2);line-height:1.5;">{{ $act }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Associated DTC Codes --}}
            <div class="h-card" style="padding:1.5rem;background:var(--h-surface);">
                <div style="font-size:0.72rem;font-weight:600;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.35rem;">OBD-II Reference</div>
                <h3 style="font-size:1rem;font-weight:700;color:var(--h-text);margin:0 0 1rem;">Associated Trouble Codes</h3>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
                    @foreach($module['dtc_codes'] as $dtc)
                        <span class="h-badge h-badge-gray" style="font-family:monospace;font-size:0.8rem;padding:0.3rem 0.65rem;">
                            {{ $dtc }}
                        </span>
                    @endforeach
                </div>
                <a href="{{ route('learning.dtc') }}" class="h-btn-sm h-btn-sm-teal" style="font-size:0.75rem;">
                    Explore Full OBD-II Directory →
                </a>
            </div>
        </div>

        {{-- Care Tips (optional) --}}
        @if(!empty($module['care_tips']))
        <div class="h-card" style="padding:1.75rem;background:var(--h-surface);">
            <div style="font-size:0.72rem;font-weight:600;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.35rem;">Practical Guidance</div>
            <h2 style="font-size:1.15rem;font-weight:700;color:var(--h-text);margin:0 0 1rem;">Care & Best Practice Tips</h2>
            <div style="display:flex;flex-direction:column;gap:0.75rem;">
                @foreach($module['care_tips'] as $tip)
                    <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.75rem;background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);border-radius:var(--h-radius);">
                        <div style="width:22px;height:22px;border-radius:50%;background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);display:flex;align-items:center;justify-content:center;color:var(--h-teal);flex-shrink:0;margin-top:1px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        </div>
                        <div style="font-size:0.875rem;color:var(--h-text);line-height:1.55;">{{ $tip }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Risks (optional) --}}
        @if(!empty($module['risks']))
        <div class="h-card" style="padding:1.75rem;background:var(--h-surface);">
            <div style="font-size:0.72rem;font-weight:600;color:var(--h-red, #ef4444);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.35rem;">⚠ Risk Awareness</div>
            <h2 style="font-size:1.15rem;font-weight:700;color:var(--h-text);margin:0 0 1rem;">Known Risks & Safety Hazards</h2>
            <div style="display:flex;flex-direction:column;gap:0.75rem;">
                @foreach($module['risks'] as $risk)
                    @php $riskText = preg_replace('/^Risk:\s*/i', '', $risk); @endphp
                    <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.75rem;background:var(--h-red-bg, rgba(239,68,68,0.08));border:1px solid rgba(239,68,68,0.25);border-radius:var(--h-radius);">
                        <div style="width:22px;height:22px;border-radius:50%;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);display:flex;align-items:center;justify-content:center;color:var(--h-red);flex-shrink:0;margin-top:1px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
                        <div>
                            <div style="font-size:0.72rem;font-weight:700;color:var(--h-red);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.15rem;">Risk</div>
                            <div style="font-size:0.875rem;color:var(--h-text2);line-height:1.55;">{{ $riskText }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- HAVEN Scope Note (optional) --}}
        @if(!empty($module['haven_scope_note']))
        <div style="padding:1rem 1.25rem;background:var(--h-surface2);border:1px solid var(--h-border);border-left:3px solid var(--h-teal);border-radius:var(--h-radius);display:flex;align-items:flex-start;gap:0.75rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            <div>
                <div style="font-size:0.68rem;font-weight:700;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.07em;margin-bottom:0.25rem;">HAVEN AI Scope &amp; Limitations</div>
                <div style="font-size:0.84rem;color:var(--h-text2);line-height:1.6;">{{ $module['haven_scope_note'] }}</div>
            </div>
        </div>
        @endif

        {{-- Related Topics --}}
        <div class="h-card" style="padding:1.5rem;background:var(--h-surface);">
            <div style="font-size:0.72rem;font-weight:600;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.75rem;">Further Reading</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:1rem;">
                @foreach($related as $rel)
                    <a href="{{ route('learning.show', $rel['slug']) }}" class="h-stat-cell lrn-related-card" style="text-decoration:none;border-radius:var(--h-radius);border:1px solid var(--h-border);display:block;">
                        <div style="font-size:0.68rem;color:var(--h-teal);text-transform:uppercase;font-weight:600;margin-bottom:0.25rem;">{{ $rel['category'] }}</div>
                        <div style="font-weight:700;color:var(--h-text);font-size:0.9rem;margin-bottom:0.3rem;">{{ $rel['title'] }}</div>
                        <div style="font-size:0.75rem;color:var(--h-text3);line-height:1.4;">{{ Str::limit($rel['summary'], 90) }}</div>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Bottom navigation --}}
        <div class="lrn-bottom-nav">
            <div class="lrn-bottom-nav-group">
                @if(isset($parentTopic))
                    <a href="{{ route('learning.topic', $parentTopic['slug']) }}" class="haven-btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        <span>Back to {{ $parentTopic['title'] }}</span>
                    </a>
                @endif
                <a href="{{ route('learning.index') }}" class="h-btn-sm h-btn-sm-default" style="min-height:40px;display:inline-flex;align-items:center;justify-content:center;padding:0.55rem 1rem;">
                    <span>All Topics</span>
                </a>
            </div>
            <a href="{{ route('learning.quiz') }}" class="haven-btn-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>Test Your Knowledge</span>
            </a>
        </div>

    </div>

    <script>
        // Reading progress bar
        window.addEventListener('scroll', function () {
            const doc = document.documentElement;
            const scrollTop = doc.scrollTop || document.body.scrollTop;
            const scrollHeight = doc.scrollHeight - doc.clientHeight;
            const pct = scrollHeight > 0 ? (scrollTop / scrollHeight) * 100 : 0;
            document.getElementById('lrn-read-progress-bar').style.width = pct + '%';
        });

        // Mark as complete (persisted to DB for authenticated users + localStorage fallback)
        function toggleComplete(slug) {
            let completed = JSON.parse(localStorage.getItem('haven_lrn_completed') || '[]');
            const idx = completed.indexOf(slug);
            const btn = document.getElementById('lrn-complete-btn');
            const label = document.getElementById('lrn-complete-label');

            if (idx === -1) {
                completed.push(slug);
            }
            btn.style.background = 'var(--h-green-bg)';
            btn.style.borderColor = 'rgba(34,197,94,0.4)';
            btn.style.color = 'var(--h-green)';
            label.textContent = 'Completed ✓';
            localStorage.setItem('haven_lrn_completed', JSON.stringify(completed));

            @auth
            fetch('{{ route('learning.complete', $module['slug']) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            }).catch(() => {});
            @endauth
        }

        // Init complete state from DB ($learningStats) + localStorage
        document.addEventListener('DOMContentLoaded', function () {
            const slug = '{{ $module['slug'] }}';
            const serverCompleted = @json($learningStats['completed_modules'] ?? []);
            const localCompleted = JSON.parse(localStorage.getItem('haven_lrn_completed') || '[]');
            const merged = Array.from(new Set([...serverCompleted, ...localCompleted]));
            localStorage.setItem('haven_lrn_completed', JSON.stringify(merged));

            if (merged.includes(slug)) {
                const btn = document.getElementById('lrn-complete-btn');
                const label = document.getElementById('lrn-complete-label');
                btn.style.background = 'var(--h-green-bg)';
                btn.style.borderColor = 'rgba(34,197,94,0.4)';
                btn.style.color = 'var(--h-green)';
                label.textContent = 'Completed ✓';
            }
        });
    </script>

</x-app-layout>
