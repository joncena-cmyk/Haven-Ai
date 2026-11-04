<x-app-layout :title="$topic['title']">

    {{-- Breadcrumb --}}
    <div class="h-breadcrumb">
        <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="h-breadcrumb-link">{{ auth()->check() ? 'Dashboard' : 'Home' }}</a>
        <span class="h-breadcrumb-sep">/</span>
        <a href="{{ route('learning.index') }}" class="h-breadcrumb-link">Learning Modules</a>
        <span class="h-breadcrumb-sep">/</span>
        <span class="h-breadcrumb-current">{{ $topic['title'] }}</span>
    </div>

    @php
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
        $acc = $accents[$topic['color']] ?? $accents['teal'];
    @endphp

    {{-- Topic Hero Header --}}
    <div class="h-card" style="padding:1.75rem;margin-bottom:1.5rem;background:linear-gradient(135deg, var(--h-surface) 0%, var(--h-surface2) 100%);border-left:4px solid {{ $acc['color'] }};position:relative;overflow:hidden;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1.25rem;position:relative;z-index:2;">
            <div style="display:flex;align-items:flex-start;gap:1rem;max-width:720px;">
                <div class="lrn-topic-icon-wrap" style="width:52px;height:52px;border-radius:12px;background:{{ $acc['bg'] }};border:1px solid {{ $acc['border'] }};color:{{ $acc['color'] }};">
                    @if($topic['icon'] === 'wrench')
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    @elseif($topic['icon'] === 'flame')
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                    @elseif($topic['icon'] === 'shield')
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    @elseif($topic['icon'] === 'check-circle')
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    @elseif($topic['icon'] === 'activity')
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    @elseif($topic['icon'] === 'cpu')
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>
                    @elseif($topic['icon'] === 'droplet')
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                    @elseif($topic['icon'] === 'battery')
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="6" width="18" height="12" rx="2"/><line x1="23" y1="13" x2="23" y2="11"/><line x1="7" y1="10" x2="7" y2="14"/><line x1="5" y1="12" x2="9" y2="12"/></svg>
                    @elseif($topic['icon'] === 'thermometer')
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></svg>
                    @else
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    @endif
                </div>

                <div>
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;flex-wrap:wrap;">
                        <span style="font-size:0.7rem;font-weight:700;color:{{ $acc['color'] }};text-transform:uppercase;letter-spacing:0.08em;">
                            Topic Field: {{ $topic['category'] }}
                        </span>
                        <span style="color:var(--h-text3);">·</span>
                        <span style="font-size:0.72rem;font-weight:700;padding:0.15rem 0.55rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:999px;color:var(--h-text);">
                            {{ count($modules) }} {{ \Illuminate\Support\Str::plural('Related Study', count($modules)) }}
                        </span>
                    </div>

                    <h1 style="font-size:1.55rem;font-weight:800;color:var(--h-text);letter-spacing:-0.03em;margin:0 0 0.4rem;line-height:1.2;">
                        {{ $topic['title'] }}
                    </h1>
                    <p style="font-size:0.88rem;color:var(--h-text2);line-height:1.6;margin:0;">
                        {{ $topic['description'] }}
                    </p>
                </div>
            </div>

            <div class="lrn-topic-back-wrap" style="display:flex;gap:0.5rem;align-items:center;">
                <a href="{{ route('learning.index') }}" class="haven-btn-secondary lrn-topic-back-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                    <span>All Topics</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Topic Studies Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
        <div>
            <h2 style="font-size:1.15rem;font-weight:700;color:var(--h-text);margin:0 0 0.15rem;">
                Related Technical Studies
            </h2>
            <p style="font-size:0.76rem;color:var(--h-text3);margin:0;">
                Click "Quick Preview" to inspect telemetry &amp; root causes, or read the full procedural manual.
            </p>
        </div>
    </div>

    {{-- Studies Grid --}}
    <div class="lrn-modules-grid" style="margin-bottom:2.5rem;">
        @foreach($modules as $mod)
            <div class="lrn-study-card" id="mod-card-{{ $mod['slug'] }}">
                <div>
                    {{-- Status Badge (synced via localStorage) --}}
                    <div id="mod-done-{{ $mod['slug'] }}" class="lrn-done-badge" style="display:none;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Completed
                    </div>

                    {{-- Top Meta Strip --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                        <span style="font-size:0.7rem;font-weight:600;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.06em;">
                            {{ $mod['category'] }}
                        </span>
                        <div style="display:flex;align-items:center;gap:0.4rem;">
                            @if($mod['severity_color'] === 'red')
                                <span class="h-badge h-badge-red">{{ $mod['severity'] }}</span>
                            @elseif($mod['severity_color'] === 'amber')
                                <span class="h-badge h-badge-amber">{{ $mod['severity'] }}</span>
                            @else
                                <span class="h-badge h-badge-teal">{{ $mod['severity'] }}</span>
                            @endif
                            <span style="font-size:0.7rem;color:var(--h-text3);">{{ $mod['read_time'] }}</span>
                        </div>
                    </div>

                    {{-- Title --}}
                    <h3 style="font-size:1.1rem;font-weight:700;color:var(--h-text);letter-spacing:-0.02em;margin:0 0 0.5rem;line-height:1.3;">
                        {{ $mod['title'] }}
                    </h3>

                    {{-- Summary --}}
                    <p style="font-size:0.82rem;color:var(--h-text2);line-height:1.55;margin:0 0 1rem;">
                        {{ $mod['summary'] }}
                    </p>

                    {{-- Key Telemetry Snapshot --}}
                    <div style="padding:0.6rem 0.75rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);font-size:0.72rem;margin-bottom:1.25rem;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem;">
                            <span style="color:var(--h-text3);">Acoustic Range:</span>
                            <span style="color:var(--h-text);font-weight:600;">{{ $mod['acoustic_signature']['frequency_range'] }}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--h-text3);">Vibration Peak G:</span>
                            <span style="color:var(--h-text);font-weight:600;">{{ $mod['vibration_signature']['peak_g'] }}</span>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="lrn-card-actions">
                    <button type="button" onclick="openStudyModal('{{ $mod['slug'] }}')" class="haven-btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <span>Quick Preview</span>
                    </button>
                    <a href="{{ route('learning.show', $mod['slug']) }}" class="haven-btn-primary">
                        <span>Read Manual</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Interactive Quick Preview Modal ("Studies Pop Up") --}}
    <div id="study-modal-backdrop" class="lrn-modal-backdrop" onclick="handleModalBackdropClick(event)">
        <div class="lrn-modal-content" id="study-modal-content">
            {{-- Modal Top Bar --}}
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.25rem;border-bottom:1px solid var(--h-border);padding-bottom:1rem;">
                <div>
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                        <span id="modal-category" style="font-size:0.68rem;font-weight:700;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.06em;"></span>
                        <span id="modal-severity-badge"></span>
                        <span id="modal-read-time" style="font-size:0.7rem;color:var(--h-text3);"></span>
                    </div>
                    <h2 id="modal-title" style="font-size:1.35rem;font-weight:800;color:var(--h-text);margin:0;line-height:1.25;"></h2>
                </div>
                <button type="button" onclick="closeStudyModal()" class="h-btn-sm h-btn-sm-default lrn-modal-close-btn" title="Close">✕</button>
            </div>

            {{-- Modal Body Content --}}
            <div style="display:flex;flex-direction:column;gap:1.25rem;">
                {{-- Summary --}}
                <div style="padding:0.85rem 1rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid var(--h-border);">
                    <div style="font-size:0.68rem;font-weight:700;color:var(--h-text3);text-transform:uppercase;margin-bottom:0.25rem;">Clinical Diagnostic Overview</div>
                    <p id="modal-summary" style="font-size:0.85rem;color:var(--h-text);line-height:1.6;margin:0;"></p>
                </div>

                {{-- Signatures side-by-side --}}
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:0.85rem;">
                    {{-- Acoustic --}}
                    <div style="padding:0.85rem 1rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid var(--h-border);">
                        <div style="display:flex;align-items:center;gap:0.4rem;margin-bottom:0.5rem;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
                            <span style="font-size:0.72rem;font-weight:700;color:var(--h-teal);text-transform:uppercase;">Acoustic Audio</span>
                        </div>
                        <div style="font-size:0.76rem;color:var(--h-text3);margin-bottom:0.15rem;">Frequency Band:</div>
                        <div id="modal-acoustic-freq" style="font-size:0.82rem;font-weight:600;color:var(--h-text);margin-bottom:0.4rem;"></div>
                        <div style="font-size:0.76rem;color:var(--h-text3);margin-bottom:0.15rem;">Spectral Centroid:</div>
                        <div id="modal-acoustic-centroid" style="font-size:0.82rem;font-weight:600;color:var(--h-teal);"></div>
                    </div>

                    {{-- Vibration --}}
                    <div style="padding:0.85rem 1rem;background:var(--h-surface2);border-radius:var(--h-radius);border:1px solid var(--h-border);">
                        <div style="display:flex;align-items:center;gap:0.4rem;margin-bottom:0.5rem;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--h-amber)" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            <span style="font-size:0.72rem;font-weight:700;color:var(--h-amber);text-transform:uppercase;">Vibration Telemetry</span>
                        </div>
                        <div style="font-size:0.76rem;color:var(--h-text3);margin-bottom:0.15rem;">Peak G-Force:</div>
                        <div id="modal-vibration-peak" style="font-size:0.82rem;font-weight:600;color:var(--h-text);margin-bottom:0.4rem;"></div>
                        <div style="font-size:0.76rem;color:var(--h-text3);margin-bottom:0.15rem;">Crest Factor:</div>
                        <div id="modal-vibration-crest" style="font-size:0.82rem;font-weight:600;color:var(--h-amber);"></div>
                    </div>
                </div>

                {{-- Root Causes Preview --}}
                <div>
                    <div style="font-size:0.72rem;font-weight:700;color:var(--h-text);text-transform:uppercase;margin-bottom:0.5rem;display:flex;align-items:center;gap:0.4rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--h-red)" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>Key Root Causes</span>
                    </div>
                    <ul id="modal-root-causes" style="margin:0;padding-left:1.25rem;font-size:0.82rem;color:var(--h-text2);line-height:1.5;display:flex;flex-direction:column;gap:0.35rem;"></ul>
                </div>

                {{-- Diagnostic Steps Preview --}}
                <div>
                    <div style="font-size:0.72rem;font-weight:700;color:var(--h-text);text-transform:uppercase;margin-bottom:0.5rem;display:flex;align-items:center;gap:0.4rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        <span>Diagnostic Steps</span>
                    </div>
                    <div id="modal-diagnostic-steps" style="display:flex;flex-direction:column;gap:0.4rem;font-size:0.8rem;color:var(--h-text);"></div>
                </div>
            </div>

            {{-- Modal Footer CTA --}}
            <div class="lrn-modal-actions">
                <button type="button" onclick="closeStudyModal()" class="haven-btn-secondary">
                    Close Preview
                </button>
                <a id="modal-full-study-btn" href="#" class="haven-btn-primary">
                    <span>Open Full Technical Manual</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            </div>
        </div>
    </div>

    {{-- Browse Other Diagnostic Topics Bar --}}
    <div class="h-card" style="padding:1.25rem 1.5rem;background:var(--h-surface2);margin-top:1rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem;">
            <div style="font-size:0.74rem;font-weight:700;color:var(--h-text);text-transform:uppercase;letter-spacing:0.06em;">
                Browse Other Diagnostic Topics
            </div>
            <a href="{{ route('learning.index') }}" style="font-size:0.75rem;color:var(--h-teal);text-decoration:none;font-weight:600;">
                View All Topics Hub →
            </a>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
            @foreach($otherTopics as $ot)
                <a href="{{ route('learning.topic', $ot['slug']) }}" class="lrn-chip" style="font-size:0.75rem;padding:0.35rem 0.75rem;display:inline-flex;align-items:center;gap:0.4rem;">
                    <span>{{ $ot['title'] }}</span>
                    <span style="font-size:0.68rem;opacity:0.7;background:var(--h-surface);padding:0.1rem 0.35rem;border-radius:999px;">{{ $ot['study_count'] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Interactive Modal Script & Data Binding --}}
    <script>
        const MODULES_DATA = @json($modules);

        function openStudyModal(slug) {
            const data = MODULES_DATA[slug];
            if (!data) return;

            document.getElementById('modal-title').textContent = data.title;
            document.getElementById('modal-category').textContent = data.category;
            document.getElementById('modal-read-time').textContent = data.read_time;
            document.getElementById('modal-summary').textContent = data.summary;

            // Severity Badge
            const badgeContainer = document.getElementById('modal-severity-badge');
            if (data.severity_color === 'red') {
                badgeContainer.innerHTML = '<span class="h-badge h-badge-red">' + data.severity + '</span>';
            } else if (data.severity_color === 'amber') {
                badgeContainer.innerHTML = '<span class="h-badge h-badge-amber">' + data.severity + '</span>';
            } else {
                badgeContainer.innerHTML = '<span class="h-badge h-badge-teal">' + data.severity + '</span>';
            }

            // Acoustic & Vibration
            document.getElementById('modal-acoustic-freq').textContent = data.acoustic_signature ? data.acoustic_signature.frequency_range : '—';
            document.getElementById('modal-acoustic-centroid').textContent = data.acoustic_signature ? data.acoustic_signature.spectral_centroid : '—';
            document.getElementById('modal-vibration-peak').textContent = data.vibration_signature ? data.vibration_signature.peak_g : '—';
            document.getElementById('modal-vibration-crest').textContent = data.vibration_signature ? data.vibration_signature.crest_factor : '—';

            // Root causes
            const rootUl = document.getElementById('modal-root-causes');
            rootUl.innerHTML = '';
            if (data.root_causes && data.root_causes.length > 0) {
                data.root_causes.slice(0, 3).forEach(c => {
                    const li = document.createElement('li');
                    li.textContent = c;
                    rootUl.appendChild(li);
                });
            }

            // Diagnostic Steps
            const stepsDiv = document.getElementById('modal-diagnostic-steps');
            stepsDiv.innerHTML = '';
            if (data.diagnostic_steps && data.diagnostic_steps.length > 0) {
                data.diagnostic_steps.slice(0, 3).forEach((s, idx) => {
                    const row = document.createElement('div');
                    row.style.cssText = 'padding:0.4rem 0.6rem;background:var(--h-surface);border:1px solid var(--h-border);border-radius:6px;';
                    row.textContent = s;
                    stepsDiv.appendChild(row);
                });
            }

            // Link to full study
            const fullBtn = document.getElementById('modal-full-study-btn');
            fullBtn.href = '/learning/' + data.slug;

            // Show modal
            const backdrop = document.getElementById('study-modal-backdrop');
            backdrop.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }

        function closeStudyModal() {
            const backdrop = document.getElementById('study-modal-backdrop');
            backdrop.classList.remove('is-open');
            document.body.style.overflow = '';
        }

        function handleModalBackdropClick(e) {
            if (e.target.id === 'study-modal-backdrop') {
                closeStudyModal();
            }
        }

        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeStudyModal();
            }
        });

        // Load completion status from localStorage
        function loadTopicProgress() {
            const completed = JSON.parse(localStorage.getItem('haven_lrn_completed') || '[]');
            completed.forEach(slug => {
                const badge = document.getElementById('mod-done-' + slug);
                if (badge) badge.style.display = 'inline-flex';
                const card = document.getElementById('mod-card-' + slug);
                if (card) card.classList.add('lrn-card-done');
            });
        }
        document.addEventListener('DOMContentLoaded', loadTopicProgress);
    </script>

</x-app-layout>

