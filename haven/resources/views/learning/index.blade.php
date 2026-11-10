<x-app-layout title="Learning Modules">

    {{-- Breadcrumb --}}
    <div class="h-breadcrumb">
        <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="h-breadcrumb-link">{{ auth()->check() ? 'Dashboard' : 'Home' }}</a>
        <span class="h-breadcrumb-sep">/</span>
        <span class="h-breadcrumb-current">Learning Modules</span>
    </div>

    {{-- Clean Header Banner --}}
    <div class="h-card" style="padding:1.5rem 1.75rem;margin-bottom:1.5rem;position:relative;overflow:hidden;background:linear-gradient(135deg, var(--h-surface) 0%, var(--h-surface2) 100%);">
        <div style="position:relative;z-index:2;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1.25rem;">
            <div style="max-width:650px;">
                <div style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.2rem 0.6rem;background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);border-radius:999px;font-size:0.68rem;font-weight:600;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.6rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    Diagnostic Knowledge Hub
                </div>
                <h1 style="font-size:1.45rem;font-weight:800;color:var(--h-text);letter-spacing:-0.03em;margin:0 0 0.4rem;line-height:1.25;">
                    Automotive Diagnostic &amp; Telemetry Topics
                </h1>
                <p style="font-size:0.84rem;color:var(--h-text2);line-height:1.5;margin:0;">
                    Select a topic below to explore specialized technical manuals, acoustic-vibration benchmarks, and step-by-step diagnostic workflows.
                </p>
            </div>

            <div class="lrn-header-actions">
                <button type="button" id="btn-toggle-soundboard" onclick="toggleSoundboard()" class="haven-btn-secondary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                    <span>Sound Simulator</span>
                </button>
                <a href="{{ route('learning.dtc') }}" class="haven-btn-secondary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>OBD-II DTC</span>
                </a>
                <a href="{{ route('learning.quiz') }}" class="haven-btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>Diagnostic Quiz</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Progress Stats Bar --}}
    <div class="h-card lrn-stats-bar" style="padding:0.9rem 1.25rem;margin-bottom:1.5rem;background:var(--h-surface2);">
        <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:0.45rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span style="font-size:0.76rem;color:var(--h-text2);">Topics:</span>
                <span style="font-size:0.76rem;font-weight:700;color:var(--h-text);">{{ $topicCount }} Available</span>
            </div>
            <div style="display:flex;align-items:center;gap:0.45rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                <span style="font-size:0.76rem;color:var(--h-text2);">Total Studies:</span>
                <span style="font-size:0.76rem;font-weight:700;color:var(--h-text);">{{ $totalCount }} Technical Guides</span>
            </div>
            <div style="display:flex;align-items:center;gap:0.45rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--h-green)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <span style="font-size:0.76rem;color:var(--h-text2);">Completed:</span>
                <span id="lrn-completed-count" style="font-size:0.76rem;font-weight:700;color:var(--h-green);">{{ $learningStats['completed_count'] ?? 0 }}</span>
            </div>
            <div style="display:flex;align-items:center;gap:0.45rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--h-amber)" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span style="font-size:0.76rem;color:var(--h-text2);">Quiz Best:</span>
                <span id="lrn-quiz-score" style="font-size:0.76rem;font-weight:700;color:var(--h-amber);">{{ isset($learningStats) && $learningStats['quiz_attempts'] > 0 ? $learningStats['quiz_best_score'] . ' / ' . $learningStats['quiz_total'] . ' (' . $learningStats['quiz_percentage'] . '%)' : '—' }}</span>
            </div>
            @php
                $userProgressPct = $totalCount > 0 ? round((($learningStats['completed_count'] ?? 0) / $totalCount) * 100) : 0;
            @endphp
            <div style="flex:1;min-width:140px;">
                <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.68rem;color:var(--h-text3);margin-bottom:0.25rem;">
                    <span>Learning Progress</span>
                    <span id="lrn-progress-label">{{ $userProgressPct }}%</span>
                </div>
                <div style="height:4px;background:var(--h-border);border-radius:999px;overflow:hidden;">
                    <div id="lrn-progress-bar" style="height:100%;background:var(--h-teal);border-radius:999px;width:{{ $userProgressPct }}%;transition:width 0.4s ease;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Collapsible Interactive Soundboard (Clean on demand) --}}
    <div id="soundboard-drawer" class="h-card" style="display:none;padding:1.25rem;margin-bottom:1.5rem;background:var(--h-surface);border-left:3px solid var(--h-teal);">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:0.75rem;">
            <div>
                <div style="font-size:0.68rem;font-weight:700;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.15rem;">Web Audio Acoustic Laboratory</div>
                <h3 style="font-size:0.95rem;font-weight:700;color:var(--h-text);margin:0;">Synthetic Engine Acoustic Simulator</h3>
            </div>
            <button type="button" onclick="toggleSoundboard()" class="h-btn-sm h-btn-sm-default" style="font-size:0.72rem;">Close Laboratory ✕</button>
        </div>

        <canvas id="soundboard-waveform" style="width:100%;height:75px;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);display:block;margin-bottom:0.75rem;"></canvas>

        <div class="lrn-sound-grid">
            <button type="button" onclick="playEngineSound('normal')" class="h-stat-cell lrn-sound-btn" style="cursor:pointer;border-radius:var(--h-radius);text-align:left;border:1px solid var(--h-border);padding:0.65rem 0.85rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.2rem;">
                    <span class="h-badge h-badge-green" style="font-size:0.65rem;">Healthy Idle</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="color:var(--h-green);"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                </div>
                <div style="font-weight:600;font-size:0.78rem;color:var(--h-text);">Healthy 4-Stroke</div>
                <div style="font-size:0.68rem;color:var(--h-text3);">Smooth 800 RPM</div>
            </button>

            <button type="button" onclick="playEngineSound('knocking')" class="h-stat-cell lrn-sound-btn" style="cursor:pointer;border-radius:var(--h-radius);text-align:left;border:1px solid var(--h-border);padding:0.65rem 0.85rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.2rem;">
                    <span class="h-badge h-badge-red" style="font-size:0.65rem;">Detonation</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="color:var(--h-red);"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                </div>
                <div style="font-weight:600;font-size:0.78rem;color:var(--h-text);">Cylinder Knocking</div>
                <div style="font-size:0.68rem;color:var(--h-text3);">Sharp 6 kHz ping spikes</div>
            </button>

            <button type="button" onclick="playEngineSound('misfire')" class="h-stat-cell lrn-sound-btn" style="cursor:pointer;border-radius:var(--h-radius);text-align:left;border:1px solid var(--h-border);padding:0.65rem 0.85rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.2rem;">
                    <span class="h-badge h-badge-amber" style="font-size:0.65rem;">Misfire</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="color:var(--h-amber);"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                </div>
                <div style="font-weight:600;font-size:0.78rem;color:var(--h-text);">Combustion Drop</div>
                <div style="font-size:0.68rem;color:var(--h-text3);">Cyclic 720° dropout</div>
            </button>

            <button type="button" onclick="playEngineSound('rough')" class="h-stat-cell lrn-sound-btn" style="cursor:pointer;border-radius:var(--h-radius);text-align:left;border:1px solid var(--h-border);padding:0.65rem 0.85rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.2rem;">
                    <span class="h-badge h-badge-amber" style="font-size:0.65rem;">Rough Idle</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="color:var(--h-amber);"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                </div>
                <div style="font-weight:600;font-size:0.78rem;color:var(--h-text);">Mount Resonance</div>
                <div style="font-size:0.68rem;color:var(--h-text3);">Continuous structural hum</div>
            </button>
        </div>
    </div>

    {{-- Clean Search Bar --}}
    <div style="margin-bottom:1.25rem;">
        <form method="GET" action="{{ route('learning.index') }}" class="lrn-search-form" style="display:flex;align-items:center;gap:0.5rem;max-width:540px;width:100%;">
            <div style="position:relative;flex:1;min-width:0;">
                <input type="text" name="q" value="{{ $search }}" placeholder="Search topics, studies, symptoms..." class="haven-input" style="padding-left:2.2rem;font-size:0.82rem;padding-top:0.55rem;padding-bottom:0.55rem;min-height:42px;box-sizing:border-box;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--h-text3)" stroke-width="2" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
            </div>
            <button type="submit" class="haven-btn-secondary" style="padding:0.55rem 0.95rem;font-size:0.8rem;white-space:nowrap;min-height:42px;flex-shrink:0;">Search</button>
            @if($search)
                <a href="{{ route('learning.index') }}" class="h-btn-sm h-btn-sm-default" style="padding:0.55rem 0.75rem;min-height:42px;display:inline-flex;align-items:center;flex-shrink:0;">Clear</a>
            @endif
        </form>
    </div>

    {{-- If search yielded matching individual studies, show quick access banner --}}
    @if($search && count($matchingStudies) > 0)
        <div style="margin-bottom:1.5rem;padding:1.25rem;background:var(--h-surface);border:1px solid var(--h-border);border-left:3px solid var(--h-teal);border-radius:var(--h-radius);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                <div style="font-size:0.75rem;font-weight:700;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.06em;">
                    Matching Studies Found ({{ count($matchingStudies) }})
                </div>
                <span style="font-size:0.72rem;color:var(--h-text3);">Direct matches for "{{ $search }}"</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(min(100%, 280px), 1fr));gap:0.75rem;">
                @foreach($matchingStudies as $m)
                    <a href="{{ route('learning.show', $m['slug']) }}" class="h-stat-cell" style="padding:0.75rem;border-radius:var(--h-radius);border:1px solid var(--h-border);text-decoration:none;display:flex;flex-direction:column;justify-content:space-between;transition:border-color 0.15s;">
                        <div>
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.3rem;">
                                <span style="font-size:0.68rem;color:var(--h-teal);font-weight:600;">{{ $m['category'] }}</span>
                                <span style="font-size:0.68rem;color:var(--h-text3);">{{ $m['read_time'] }}</span>
                            </div>
                            <div style="font-size:0.85rem;font-weight:700;color:var(--h-text);margin-bottom:0.25rem;">{{ $m['title'] }}</div>
                            <p style="font-size:0.74rem;color:var(--h-text2);line-height:1.4;margin:0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $m['summary'] }}</p>
                        </div>
                        <div style="font-size:0.72rem;font-weight:600;color:var(--h-teal);margin-top:0.6rem;display:flex;align-items:center;gap:0.25rem;">
                            <span>Read Manual</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Clean Topics Grid --}}
    <div style="margin-bottom:0.75rem;display:flex;align-items:center;justify-content:space-between;">
        <h2 style="font-size:1.1rem;font-weight:700;color:var(--h-text);margin:0;">Diagnostic Topics</h2>
        <span style="font-size:0.75rem;color:var(--h-text3);">Click any topic to explore its related studies</span>
    </div>

    <div class="lrn-topics-grid">
        @forelse($topics as $t)
            @php
                // Accent colors for topic cards
                $accents = [
                    'amber'   => ['bg' => 'rgba(245,158,11,0.1)', 'color' => 'var(--h-amber, #f59e0b)', 'border' => 'rgba(245,158,11,0.3)'],
                    'red'     => ['bg' => 'rgba(239,68,68,0.1)', 'color' => 'var(--h-red, #ef4444)', 'border' => 'rgba(239,68,68,0.3)'],
                    'rose'    => ['bg' => 'rgba(244,63,94,0.1)', 'color' => '#f43f5e', 'border' => 'rgba(244,63,94,0.3)'],
                    'teal'    => ['bg' => 'var(--h-teal-bg, rgba(2,132,199,0.1))', 'color' => 'var(--h-teal, #0284c7)', 'border' => 'var(--h-teal-ring, rgba(2,132,199,0.3))'],
                    'cyan'    => ['bg' => 'rgba(6,182,212,0.1)', 'color' => '#06b6d4', 'border' => 'rgba(6,182,212,0.3)'],
                    'indigo'  => ['bg' => 'rgba(99,102,241,0.1)', 'color' => '#6366f1', 'border' => 'rgba(99,102,241,0.3)'],
                    'emerald' => ['bg' => 'rgba(16,185,129,0.1)', 'color' => 'var(--h-green, #10b981)', 'border' => 'rgba(16,185,129,0.3)'],
                    'orange'  => ['bg' => 'rgba(249,115,22,0.1)', 'color' => '#f97316', 'border' => 'rgba(249,115,22,0.3)'],
                    'blue'    => ['bg' => 'rgba(59,130,246,0.1)', 'color' => '#3b82f6', 'border' => 'rgba(59,130,246,0.3)'],
                ];
                $acc = $accents[$t['color']] ?? $accents['teal'];
            @endphp

            <a href="{{ route('learning.topic', $t['slug']) }}" class="lrn-topic-card" id="topic-card-{{ $t['slug'] }}">
                <div>
                    {{-- Top Row: Icon + Badge --}}
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem;">
                        <div class="lrn-topic-icon-wrap" style="background:{{ $acc['bg'] }};border:1px solid {{ $acc['border'] }};color:{{ $acc['color'] }};">
                            @if($t['icon'] === 'wrench')
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                            @elseif($t['icon'] === 'flame')
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                            @elseif($t['icon'] === 'shield')
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            @elseif($t['icon'] === 'check-circle')
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            @elseif($t['icon'] === 'activity')
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            @elseif($t['icon'] === 'cpu')
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>
                            @elseif($t['icon'] === 'droplet')
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                            @elseif($t['icon'] === 'battery')
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="6" width="18" height="12" rx="2"/><line x1="23" y1="13" x2="23" y2="11"/><line x1="7" y1="10" x2="7" y2="14"/><line x1="5" y1="12" x2="9" y2="12"/></svg>
                            @elseif($t['icon'] === 'thermometer')
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></svg>
                            @else
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            @endif
                        </div>

                        <span style="font-size:0.72rem;font-weight:700;padding:0.25rem 0.6rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:999px;color:var(--h-text2);">
                            {{ $t['study_count'] }} {{ \Illuminate\Support\Str::plural('Study', $t['study_count']) }}
                        </span>
                    </div>

                    {{-- Topic Title --}}
                    <h3 style="font-size:1.15rem;font-weight:700;color:var(--h-text);margin:0 0 0.45rem;line-height:1.3;">
                        {{ $t['title'] }}
                    </h3>

                    {{-- Crisp 1-sentence description (Clean, NO wall of text!) --}}
                    <p style="font-size:0.83rem;color:var(--h-text2);line-height:1.55;margin:0 0 1rem;">
                        {{ $t['description'] }}
                    </p>

                    {{-- Included Studies Preview Pills --}}
                    <div style="display:flex;flex-wrap:wrap;gap:0.35rem;margin-bottom:1.25rem;">
                        @foreach($t['modules'] as $mod)
                            <span class="lrn-study-tag">
                                <span style="display:inline-block;width:5px;height:5px;border-radius:50%;background:{{ $acc['color'] }};"></span>
                                <span>{{ $mod['title'] }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>

                {{-- Action Strip --}}
                <div style="display:flex;align-items:center;justify-content:space-between;padding-top:0.85rem;border-top:1px solid var(--h-border);font-size:0.8rem;font-weight:600;color:var(--h-teal);">
                    <span>Explore Studies</span>
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);color:var(--h-teal);flex-shrink:0;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </span>
                </div>
            </a>
        @empty
            <div class="h-card" style="padding:3rem 1.5rem;text-align:center;grid-column:1/-1;">
                <p style="color:var(--h-text2);font-size:0.9rem;margin:0 0 0.75rem;">No topics found matching "{{ $search }}".</p>
                <a href="{{ route('learning.index') }}" class="h-btn-sm h-btn-sm-teal">View All Topics</a>
            </div>
        @endforelse
    </div>

    {{-- Soundboard Toggle and Audio Engine Scripts --}}
    <script>
        function toggleSoundboard() {
            const drawer = document.getElementById('soundboard-drawer');
            if (drawer.style.display === 'none' || drawer.style.display === '') {
                drawer.style.display = 'block';
                drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                drawer.style.display = 'none';
                stopAudio();
            }
        }

        let audioCtx = null;
        let activeOscillators = [];
        let animFrameId = null;

        function initAudio() {
            if (!audioCtx) {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
        }

        function stopAudio() {
            activeOscillators.forEach(o => {
                try { o.stop(); o.disconnect(); } catch(e){}
            });
            activeOscillators = [];
            if (animFrameId) cancelAnimationFrame(animFrameId);
        }

        function playEngineSound(type) {
            initAudio();
            stopAudio();

            document.querySelectorAll('.lrn-sound-btn').forEach(b => b.classList.remove('lrn-sound-btn-active'));
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('lrn-sound-btn-active');
            }

            const canvas = document.getElementById('soundboard-waveform');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            canvas.width = canvas.offsetWidth * window.devicePixelRatio;
            canvas.height = canvas.offsetHeight * window.devicePixelRatio;
            ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

            const analyser = audioCtx.createAnalyser();
            analyser.fftSize = 256;
            const bufferLength = analyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);

            const masterGain = audioCtx.createGain();
            masterGain.gain.setValueAtTime(0.3, audioCtx.currentTime);
            masterGain.connect(analyser);
            analyser.connect(audioCtx.destination);

            if (type === 'normal') {
                const osc = audioCtx.createOscillator();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(85, audioCtx.currentTime);
                const filter = audioCtx.createBiquadFilter();
                filter.type = 'lowpass';
                filter.frequency.setValueAtTime(320, audioCtx.currentTime);
                osc.connect(filter);
                filter.connect(masterGain);
                osc.start();
                osc.stop(audioCtx.currentTime + 3.0);
                activeOscillators.push(osc);
            }
            else if (type === 'knocking') {
                const baseOsc = audioCtx.createOscillator();
                baseOsc.type = 'sawtooth';
                baseOsc.frequency.setValueAtTime(90, audioCtx.currentTime);
                baseOsc.connect(masterGain);
                baseOsc.start();
                baseOsc.stop(audioCtx.currentTime + 3.0);
                activeOscillators.push(baseOsc);
                for (let i = 0; i < 9; i++) {
                    const knockOsc = audioCtx.createOscillator();
                    const knockGain = audioCtx.createGain();
                    knockOsc.type = 'sine';
                    knockOsc.frequency.setValueAtTime(5800 + (Math.random() * 400), audioCtx.currentTime + (i * 0.32));
                    knockGain.gain.setValueAtTime(0, audioCtx.currentTime + (i * 0.32));
                    knockGain.gain.linearRampToValueAtTime(0.7, audioCtx.currentTime + (i * 0.32) + 0.005);
                    knockGain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + (i * 0.32) + 0.06);
                    knockOsc.connect(knockGain);
                    knockGain.connect(masterGain);
                    knockOsc.start(audioCtx.currentTime + (i * 0.32));
                    knockOsc.stop(audioCtx.currentTime + (i * 0.32) + 0.07);
                    activeOscillators.push(knockOsc);
                }
            }
            else if (type === 'misfire') {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(80, audioCtx.currentTime);
                for (let i = 0; i < 6; i++) {
                    gain.gain.setValueAtTime(0.35, audioCtx.currentTime + (i * 0.5));
                    gain.gain.setValueAtTime(0.02, audioCtx.currentTime + (i * 0.5) + 0.3);
                }
                osc.connect(gain);
                gain.connect(masterGain);
                osc.start();
                osc.stop(audioCtx.currentTime + 3.0);
                activeOscillators.push(osc);
            }
            else if (type === 'rough') {
                const carrier = audioCtx.createOscillator();
                const mod = audioCtx.createOscillator();
                const modGain = audioCtx.createGain();
                carrier.type = 'triangle';
                carrier.frequency.setValueAtTime(65, audioCtx.currentTime);
                mod.type = 'sine';
                mod.frequency.setValueAtTime(7, audioCtx.currentTime);
                modGain.gain.setValueAtTime(25, audioCtx.currentTime);
                mod.connect(carrier.frequency);
                carrier.connect(masterGain);
                carrier.start();
                mod.start();
                carrier.stop(audioCtx.currentTime + 3.0);
                mod.stop(audioCtx.currentTime + 3.0);
                activeOscillators.push(carrier, mod);
            }

            function draw() {
                animFrameId = requestAnimationFrame(draw);
                analyser.getByteTimeDomainData(dataArray);
                const w = canvas.offsetWidth;
                const h = canvas.offsetHeight;
                const surfaceCol = getComputedStyle(document.documentElement).getPropertyValue('--h-surface2').trim() || '#1e2024';
                const tealCol = getComputedStyle(document.documentElement).getPropertyValue('--h-teal').trim() || '#0284c7';
                ctx.fillStyle = surfaceCol;
                ctx.fillRect(0, 0, w, h);
                ctx.lineWidth = 2;
                ctx.strokeStyle = tealCol;
                ctx.beginPath();
                const sliceWidth = w / bufferLength;
                let x = 0;
                for (let i = 0; i < bufferLength; i++) {
                    const v = dataArray[i] / 128.0;
                    const y = (v * h) / 2;
                    if (i === 0) ctx.moveTo(x, y);
                    else ctx.lineTo(x, y);
                    x += sliceWidth;
                }
                ctx.lineTo(w, h / 2);
                ctx.stroke();
            }
            draw();
        }

        // --- Progress tracking ---
        const TOTAL_STUDIES = {{ $totalCount }};

        function loadProgress() {
            const completed = JSON.parse(localStorage.getItem('haven_lrn_completed') || '[]');
            const quizBest = localStorage.getItem('haven_quiz_best');
            const count = completed.length;

            const completedEl = document.getElementById('lrn-completed-count');
            if (completedEl) completedEl.textContent = count;

            const quizEl = document.getElementById('lrn-quiz-score');
            if (quizEl) quizEl.textContent = quizBest ? quizBest : '—';

            const pct = TOTAL_STUDIES > 0 ? Math.round((count / TOTAL_STUDIES) * 100) : 0;
            const barEl = document.getElementById('lrn-progress-bar');
            if (barEl) barEl.style.width = pct + '%';
            const labelEl = document.getElementById('lrn-progress-label');
            if (labelEl) labelEl.textContent = pct + '% Complete';
        }
        // Authenticated users get DB-backed progress rendered server-side; only guests fall back to localStorage.
        @guest
        document.addEventListener('DOMContentLoaded', loadProgress);
        @endguest
    </script>

</x-app-layout>
