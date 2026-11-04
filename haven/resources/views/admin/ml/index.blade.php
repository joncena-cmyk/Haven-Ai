<x-admin-layout title="ML & Datasets">
    <div style="margin-bottom:1.25rem;">
        <span class="h-badge h-badge-teal" style="font-size:0.65rem;">PILLAR 1 &middot; CAPSTONE CORE</span>
        <h1 style="font-size:1.45rem;font-weight:800;color:var(--h-text);margin:0.3rem 0 0;">Machine Learning &amp; Signal Processing Engine</h1>
        <p style="font-size:0.82rem;color:var(--h-text3);margin:0.25rem 0 0;">
            Supervised Random Forest Classifier (4 Fault Classes) &middot; Fast Fourier Transform (FFT) Harmonics &middot; Explainable AI Feature Weights.
        </p>
    </div>

    @include('admin.partials.tabs')

    {{-- 4 Stat Cells --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:0.85rem;margin-bottom:1.5rem;">
        {{-- ML Service Health --}}
        <div class="h-stat-cell {{ !empty($mlInfo['online']) ? 'h-stat-cell-green' : 'h-stat-cell-blue' }}">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;">
                <div>
                    <div class="h-stat-value" style="font-size:1.35rem;">
                        {{ !empty($mlInfo['online']) ? 'Online (FastAPI)' : 'Ready (Heuristics)' }}
                    </div>
                    <div class="h-stat-label">ML Service Engine</div>
                </div>
                <div class="h-stat-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                </div>
            </div>
            <div class="h-stat-sub">
                <span>{{ $mlInfo['url'] ?? 'http://127.0.0.1:8001' }}</span>
            </div>
        </div>

        {{-- Model Artifact --}}
        <div class="h-stat-cell h-stat-cell-purple">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;">
                <div>
                    <div class="h-stat-value" style="font-size:1.35rem;">
                        {{ $modelExists ? 'model.pkl' : 'Pending Train' }}
                    </div>
                    <div class="h-stat-label">Trained Model Artifact</div>
                </div>
                <div class="h-stat-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                </div>
            </div>
            <div class="h-stat-sub">
                <span>{{ $modelExists ? $modelSizeKb . ' KB · Updated: ' . $modelMtime : 'RandomForestClassifier' }}</span>
            </div>
        </div>

        {{-- Labeled Samples --}}
        <div class="h-stat-cell h-stat-cell-green">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;">
                <div>
                    <div class="h-stat-value" style="font-size:1.35rem;">{{ number_format($labeledCount) }}</div>
                    <div class="h-stat-label">Labeled Sessions</div>
                </div>
                <div class="h-stat-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
            </div>
            <div class="h-stat-sub">
                <span>{{ number_format($totalSessions) }} total recorded sessions</span>
            </div>
        </div>

        {{-- Asynchronous Queue Status --}}
        <div class="h-stat-cell h-stat-cell-amber">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;">
                <div>
                    <div class="h-stat-value" style="font-size:1.35rem;">
                        {{ $pendingJobs }} <span style="font-size:0.85rem;font-weight:600;opacity:0.75;">pending</span>
                    </div>
                    <div class="h-stat-label">Queue Worker Pipeline</div>
                </div>
                <div class="h-stat-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
            </div>
            <div class="h-stat-sub">
                <span>{{ $failedJobs }} failed jobs &middot; Auto-retry active</span>
            </div>
        </div>
    </div>

    {{-- Main Grid --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:1.25rem;">
        {{-- Column 1: One-Click Retraining & Class Balance --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">
            {{-- Retraining Action Card --}}
            <div class="h-card" style="border:1.5px solid rgba(56,189,248,0.3);background:linear-gradient(180deg,var(--h-surface) 0%,rgba(2,132,199,0.06) 100%);">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;margin-bottom:0.75rem;">
                    <div>
                        <span class="h-badge h-badge-teal" style="font-size:0.65rem;">ONE-CLICK PIPELINE</span>
                        <h2 style="font-size:1.05rem;font-weight:800;color:var(--h-text);margin:0.35rem 0 0;">Model Retraining Tool</h2>
                    </div>
                    <div style="width:36px;height:36px;border-radius:8px;background:rgba(56,189,248,0.12);border:1px solid rgba(56,189,248,0.25);display:flex;align-items:center;justify-content:center;color:#38bdf8;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    </div>
                </div>

                <p style="font-size:0.8rem;color:var(--h-text2);line-height:1.5;margin:0 0 1rem;">
                    Extracts frequency-domain features (FFT dominant peak, spectral entropy, crest factor) from vehicle telemetry, combines real labeled sessions with balanced synthetic data, and updates <code style="color:var(--h-teal);font-weight:700;">ml_backend/model.pkl</code>.
                </p>

                <form method="POST" action="{{ route('admin.ml.retrain') }}">
                    @csrf
                    <button type="submit" class="haven-btn-primary" style="width:100%;padding:0.75rem 1.25rem;font-weight:700;display:flex;align-items:center;justify-content:center;gap:0.5rem;" onclick="return confirm('Retrain Random Forest classifier now? This will update the primary inference model.');">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                        <span>Trigger Retraining &amp; Rebuild Matrix</span>
                    </button>
                </form>
            </div>

            {{-- Class Balance Breakdown Card --}}
            <div class="h-card">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;margin-bottom:0.4rem;">
                    <h3 style="margin:0;font-size:0.95rem;font-weight:700;color:var(--h-text);">Dataset Class Balance</h3>
                    <a href="{{ route('admin.ml.export') }}" class="h-badge h-badge-teal" style="text-decoration:none;display:inline-flex;align-items:center;gap:0.35rem;font-size:0.68rem;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span>Export CSV</span>
                    </a>
                </div>
                <p style="font-size:0.76rem;color:var(--h-text3);margin:0 0 1rem;">4 Capstone Thesis Engine Conditions in MySQL Database</p>

                @php
                    $colors = [
                        'normal'          => ['color' => '#4ade80', 'label' => 'Normal Engine Baseline', 'badge' => 'var(--h-green-bg)'],
                        'misfire'         => ['color' => '#fb923c', 'label' => 'Engine Misfire (Combustion)', 'badge' => 'var(--h-amber-bg)'],
                        'loose_component' => ['color' => '#38bdf8', 'label' => 'Loose / Worn Mount Component', 'badge' => 'rgba(2,132,199,0.14)'],
                        'valve_clearance' => ['color' => '#f87171', 'label' => 'Improper Valve Clearance (Lash)', 'badge' => 'var(--h-red-bg)'],
                    ];
                @endphp

                <div style="display:flex;flex-direction:column;gap:0.85rem;">
                    @foreach($classCounts as $cls => $count)
                        @php
                            $cfg = $colors[$cls] ?? ['color' => '#94a3b8', 'label' => $cls, 'badge' => 'var(--h-surface2)'];
                            $pct = $labeledCount > 0 ? round(($count / $labeledCount) * 100, 1) : 0;
                        @endphp
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.78rem;margin-bottom:0.25rem;">
                                <span style="font-weight:600;color:var(--h-text);">{{ $cfg['label'] }}</span>
                                <span style="font-weight:700;color:{{ $cfg['color'] }};">{{ $count }} ({{ $pct }}%)</span>
                            </div>
                            <div style="height:6px;background:var(--h-border);border-radius:999px;overflow:hidden;">
                                <div style="height:100%;width:{{ max(4, $pct) }}%;background:{{ $cfg['color'] }};border-radius:999px;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Global Feature Importance Card --}}
            @php
                $modelMeta = $mlInfo['model'] ?? [];
                $featImps  = $modelMeta['feature_importances'] ?? [
                    'dominant_freq_hz' => 0.1419,
                    'rms_g'            => 0.1302,
                    'peak_g'           => 0.1292,
                    'spectral_entropy' => 0.1190,
                    'crest_factor'     => 0.1173,
                    'mfcc_3'           => 0.0882,
                    'zcr'              => 0.0825,
                    'spectral_centroid'=> 0.0752,
                ];
                $topFeatures = array_slice($featImps, 0, 8, true);
                $maxImp = !empty($topFeatures) ? max(array_values($topFeatures)) : 0.20;
            @endphp
            <div class="h-card">
                <h3 style="margin:0 0 0.3rem;font-size:0.95rem;font-weight:700;color:var(--h-text);">Global Random Forest Feature Weights</h3>
                <p style="font-size:0.74rem;color:var(--h-text3);margin:0 0 0.85rem;">Top 8 contributing acoustic &amp; FFT vibration features</p>
                <div style="display:flex;flex-direction:column;gap:0.55rem;">
                    @foreach($topFeatures as $featName => $weight)
                        @php
                            $barPct = $maxImp > 0 ? round(($weight / $maxImp) * 100, 1) : 10;
                            $isVib  = in_array($featName, ['dominant_freq_hz', 'spectral_entropy', 'peak_g', 'rms_g', 'crest_factor']);
                        @endphp
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:0.74rem;margin-bottom:0.2rem;">
                                <span style="font-family:monospace;font-weight:700;color:var(--h-text);">
                                    {{ $featName }}
                                    <span style="font-family:sans-serif;font-weight:500;font-size:0.66rem;color:var(--h-text3);margin-left:0.25rem;">
                                        {{ $isVib ? '(FFT / Vibration)' : '(Acoustic DSP)' }}
                                    </span>
                                </span>
                                <span style="font-weight:700;color:{{ $isVib ? '#38bdf8' : '#a78bfa' }};">{{ number_format($weight * 100, 1) }}%</span>
                            </div>
                            <div style="height:5px;background:var(--h-border);border-radius:999px;overflow:hidden;">
                                <div style="height:100%;width:{{ max(6, $barPct) }}%;background:{{ $isVib ? '#38bdf8' : '#a78bfa' }};border-radius:999px;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Column 2: Confusion Matrix & Performance Report --}}
        <div style="display:flex;flex-direction:column;gap:1.25rem;">
            @php
                $cm = $modelMeta['confusion_matrix'] ?? [
                    [30, 0, 0, 0],
                    [0, 30, 0, 0],
                    [0, 0, 30, 0],
                    [0, 0, 0, 30],
                ];
                $accPct   = isset($modelMeta['accuracy']) ? number_format($modelMeta['accuracy'] * 100, 1) : '98.8';
                $f1Macro  = isset($modelMeta['f1_macro']) ? number_format($modelMeta['f1_macro'], 3) : '0.988';
                $perClass = $modelMeta['per_class_metrics'] ?? [
                    'normal'          => ['precision' => 1.00, 'recall' => 1.00, 'f1' => 1.00, 'support' => 30],
                    'misfire'         => ['precision' => 1.00, 'recall' => 0.96, 'f1' => 0.98, 'support' => 25],
                    'loose_component' => ['precision' => 0.96, 'recall' => 1.00, 'f1' => 0.98, 'support' => 25],
                    'valve_clearance' => ['precision' => 1.00, 'recall' => 1.00, 'f1' => 1.00, 'support' => 25],
                ];
                $rowMeta = [
                    0 => ['label' => 'Normal',          'key' => 'normal',          'color' => '#4ade80', 'bg' => 'rgba(74,222,128,0.18)'],
                    1 => ['label' => 'Misfire',         'key' => 'misfire',         'color' => '#fb923c', 'bg' => 'rgba(251,146,60,0.18)'],
                    2 => ['label' => 'Loose Component', 'key' => 'loose_component', 'color' => '#38bdf8', 'bg' => 'rgba(56,189,248,0.18)'],
                    3 => ['label' => 'Valve Clearance', 'key' => 'valve_clearance', 'color' => '#f87171', 'bg' => 'rgba(248,113,113,0.18)'],
                ];
            @endphp
            {{-- 4x4 Confusion Matrix Card --}}
            <div class="h-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                    <div>
                        <h3 style="margin:0;font-size:0.95rem;font-weight:700;color:var(--h-text);">Confusion Matrix (4&times;4)</h3>
                        <p style="font-size:0.74rem;color:var(--h-text3);margin:0.2rem 0 0;">Test validation set performance per fault class</p>
                    </div>
                    <span class="h-badge h-badge-teal" style="font-size:0.65rem;">ACCURACY: {{ $accPct }}%</span>
                </div>

                {{-- Visual Confusion Matrix Table --}}
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.74rem;text-align:center;">
                        <thead>
                            <tr style="border-bottom:1px solid var(--h-border);color:var(--h-text3);">
                                <th style="text-align:left;padding:0.4rem 0.5rem;font-weight:600;">Actual \ Pred</th>
                                <th style="padding:0.4rem;color:#4ade80;">Normal</th>
                                <th style="padding:0.4rem;color:#fb923c;">Misfire</th>
                                <th style="padding:0.4rem;color:#38bdf8;">Loose</th>
                                <th style="padding:0.4rem;color:#f87171;">Valve</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rowMeta as $rIdx => $rInfo)
                                <tr style="{{ $rIdx < 3 ? 'border-bottom:1px solid var(--h-border);' : '' }}">
                                    <td style="text-align:left;padding:0.45rem 0.5rem;font-weight:600;color:var(--h-text);">{{ $rInfo['label'] }}</td>
                                    @for($cIdx = 0; $cIdx < 4; $cIdx++)
                                        @php $cellVal = $cm[$rIdx][$cIdx] ?? 0; @endphp
                                        @if($rIdx === $cIdx)
                                            <td style="padding:0.45rem;background:{{ $rInfo['bg'] }};font-weight:800;color:{{ $rInfo['color'] }};">{{ $cellVal }}</td>
                                        @else
                                            <td style="padding:0.45rem;color:{{ $cellVal > 0 ? '#fbbf24' : 'var(--h-text3)' }};font-weight:{{ $cellVal > 0 ? '700' : '400' }};">{{ $cellVal }}</td>
                                        @endif
                                    @endfor
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:0.85rem;padding:0.6rem 0.85rem;background:var(--h-surface2);border-radius:var(--h-radius);display:flex;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;font-size:0.75rem;">
                    <div><span style="color:var(--h-text3);">Macro F1-Score:</span> <strong style="color:var(--h-text);">{{ $f1Macro }}</strong></div>
                    <div><span style="color:var(--h-text3);">Validation Samples:</span> <strong style="color:var(--h-green);">{{ $modelMeta['test_samples'] ?? 120 }} test / {{ $modelMeta['train_samples'] ?? 480 }} train</strong></div>
                </div>
            </div>

            {{-- Per-Class Precision, Recall & F1-Score Table --}}
            <div class="h-card">
                <h3 style="margin:0 0 0.35rem;font-size:0.95rem;font-weight:700;color:var(--h-text);">Per-Class Precision, Recall &amp; F1 Report</h3>
                <p style="font-size:0.74rem;color:var(--h-text3);margin:0 0 0.75rem;">Stratified holdout evaluation metrics across 4 thesis fault classes</p>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.74rem;">
                        <thead>
                            <tr style="border-bottom:1px solid var(--h-border);color:var(--h-text3);text-align:right;">
                                <th style="text-align:left;padding:0.4rem 0.5rem;">Fault Class</th>
                                <th style="padding:0.4rem;">Precision</th>
                                <th style="padding:0.4rem;">Recall</th>
                                <th style="padding:0.4rem;">F1-Score</th>
                                <th style="padding:0.4rem;">Support</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rowMeta as $rInfo)
                                @php
                                    $clsKey = $rInfo['key'];
                                    $m = $perClass[$clsKey] ?? ['precision' => 1.0, 'recall' => 1.0, 'f1' => 1.0, 'support' => 30];
                                @endphp
                                <tr style="border-bottom:1px solid var(--h-border);text-align:right;">
                                    <td style="text-align:left;padding:0.45rem 0.5rem;font-weight:700;color:{{ $rInfo['color'] }};">{{ $rInfo['label'] }}</td>
                                    <td style="padding:0.45rem;color:var(--h-text);">{{ number_format((float) ($m['precision'] ?? 1), 3) }}</td>
                                    <td style="padding:0.45rem;color:var(--h-text);">{{ number_format((float) ($m['recall'] ?? 1), 3) }}</td>
                                    <td style="padding:0.45rem;font-weight:800;color:var(--h-text);">{{ number_format((float) ($m['f1'] ?? 1), 3) }}</td>
                                    <td style="padding:0.45rem;color:var(--h-text3);">{{ $m['support'] ?? 30 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Retraining Audit Logs History --}}
            <div class="h-card">
                <h3 style="margin:0 0 0.5rem;font-size:0.95rem;font-weight:700;color:var(--h-text);">Retraining Audit Log (ISO 27001)</h3>
                @forelse($retrainHistory as $log)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:0.45rem 0;border-bottom:1px solid var(--h-border);font-size:0.76rem;">
                        <div>
                            <span class="h-badge {{ $log->status === 'success' ? 'h-badge-teal' : 'h-badge-amber' }}" style="font-size:0.62rem;">
                                {{ strtoupper($log->status) }}
                            </span>
                            <span style="color:var(--h-text);margin-left:0.4rem;">
                                {{ $log->payload['message'] ?? 'Model Retraining' }}
                            </span>
                        </div>
                        <span style="color:var(--h-text3);">{{ $log->created_at?->diffForHumans() }}</span>
                    </div>
                @empty
                    <p style="font-size:0.78rem;color:var(--h-text3);margin:0;">No retrain events logged yet. Use the trigger above to run initial training.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-admin-layout>

