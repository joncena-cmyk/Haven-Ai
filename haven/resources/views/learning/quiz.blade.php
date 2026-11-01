<x-app-layout title="Diagnostic Knowledge Quiz">

    {{-- Breadcrumb --}}
    <div class="h-breadcrumb">
        <a href="{{ route('dashboard') }}" class="h-breadcrumb-link">Dashboard</a>
        <span class="h-breadcrumb-sep">/</span>
        <a href="{{ route('learning.index') }}" class="h-breadcrumb-link">Learning Modules</a>
        <span class="h-breadcrumb-sep">/</span>
        <span class="h-breadcrumb-current">Diagnostic Mastery Quiz</span>
    </div>

    {{-- Header --}}
    <div class="h-card" style="padding:1.75rem;margin-bottom:1.5rem;background:var(--h-surface);">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div>
                <div style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.2rem 0.6rem;background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);border-radius:999px;font-size:0.68rem;font-weight:600;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.5rem;">
                    Self-Assessment Examination
                </div>
                <h1 style="font-size:1.5rem;font-weight:800;color:var(--h-text);letter-spacing:-0.03em;margin:0 0 0.35rem;">
                    Diagnostic Signal &amp; Telemetry Mastery Quiz
                </h1>
                <p style="font-size:0.875rem;color:var(--h-text2);margin:0;">
                    Test your understanding of ICE acoustic signal processing, crest factors, and vibration fault patterns.
                </p>
            </div>

            <div id="quiz-score-card" style="display:none;padding:0.75rem 1.25rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);text-align:right;min-width:100px;">
                <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;font-weight:600;">Score</div>
                <div id="quiz-score-val" style="font-size:1.5rem;font-weight:800;color:var(--h-teal);line-height:1;">0 / {{ count($questions) }}</div>
            </div>
        </div>

        {{-- Question progress bar --}}
        <div style="margin-top:1.25rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem;">
                <span style="font-size:0.72rem;color:var(--h-text3);" id="quiz-progress-label">0 of {{ count($questions) }} answered</span>
                <span style="font-size:0.72rem;font-weight:600;color:var(--h-teal);" id="quiz-progress-pct">0%</span>
            </div>
            <div style="height:5px;background:var(--h-border);border-radius:999px;overflow:hidden;">
                <div id="quiz-progress-bar" style="height:100%;background:var(--h-teal);border-radius:999px;width:0%;transition:width 0.35s ease;"></div>
            </div>
        </div>
    </div>

    {{-- Questions --}}
    <div style="display:flex;flex-direction:column;gap:1.5rem;" id="quiz-container">
        @foreach($questions as $q)
            <div class="h-card quiz-card" id="q-card-{{ $q['id'] }}" style="padding:1.75rem;background:var(--h-surface);">
                <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.75rem;">
                    <span id="q-num-{{ $q['id'] }}" style="width:28px;height:28px;border-radius:50%;background:var(--h-surface2);border:1px solid var(--h-border);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:var(--h-teal);flex-shrink:0;">
                        {{ $q['id'] }}
                    </span>
                    <span style="font-size:0.72rem;font-weight:600;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.07em;">
                        Question {{ $q['id'] }} of {{ count($questions) }}
                    </span>
                </div>

                <h3 style="font-size:1.05rem;font-weight:700;color:var(--h-text);letter-spacing:-0.01em;margin:0 0 1.25rem;line-height:1.4;">
                    {{ $q['question'] }}
                </h3>

                <div class="lrn-quiz-options" style="margin-bottom:1rem;">
                    @foreach($q['options'] as $key => $opt)
                        <button type="button"
                                onclick="selectAnswer({{ $q['id'] }}, '{{ $key }}', '{{ $q['correct'] }}')"
                                id="btn-{{ $q['id'] }}-{{ $key }}"
                                class="quiz-option-btn"
                                style="text-align:left;padding:0.75rem 1rem;background:var(--h-surface2);border:1px solid var(--h-border);border-radius:var(--h-radius);color:var(--h-text);font-size:0.875rem;cursor:pointer;transition:all 0.15s;font-family:inherit;display:flex;align-items:center;gap:0.75rem;width:100%;">
                            <span style="width:26px;height:26px;border-radius:6px;background:var(--h-surface3, var(--h-surface));border:1px solid var(--h-border);display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0;color:var(--h-teal);">
                                {{ $key }}
                            </span>
                            <span style="line-height:1.4;">{{ $opt }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- Explanation Panel (revealed on selection) --}}
                <div id="expl-{{ $q['id'] }}" style="display:none;padding:1rem;border-radius:var(--h-radius);font-size:0.82rem;line-height:1.55;margin-top:0.5rem;">
                    <div style="font-weight:700;margin-bottom:0.4rem;display:flex;align-items:center;gap:0.4rem;" id="expl-verdict-{{ $q['id'] }}"></div>
                    <div style="color:var(--h-text2);">{{ $q['explanation'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Final Result Panel --}}
    <div id="quiz-result-panel" style="display:none;margin-top:2rem;">
        <div class="h-card" style="padding:2rem;background:var(--h-surface);text-align:center;">
            <div id="quiz-result-icon" style="font-size:2.5rem;margin-bottom:0.75rem;line-height:1;">🎯</div>
            <div style="font-size:0.72rem;font-weight:600;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.35rem;">Quiz Complete</div>
            <h2 style="font-size:1.5rem;font-weight:800;color:var(--h-text);margin:0 0 0.35rem;" id="quiz-result-score-txt">You scored 0 / {{ count($questions) }}</h2>
            <div id="quiz-result-tier" style="font-size:0.9rem;color:var(--h-text2);margin-bottom:1.5rem;"></div>
            <div class="lrn-quiz-action-row">
                <button type="button" onclick="window.location.reload()" class="haven-btn-secondary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.57"/></svg>
                    <span>Retake Quiz</span>
                </button>
                <a href="{{ route('learning.index') }}" class="haven-btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    <span>Browse Topics</span>
                </a>
            </div>
        </div>
    </div>

    <div style="margin-top:1.5rem;text-align:center;" id="quiz-retake-row">
        <button type="button" onclick="window.location.reload()" class="haven-btn-secondary" style="min-height:42px;padding:0.6rem 1.25rem;">
            Reset &amp; Retake Quiz
        </button>
    </div>

    <script>
        let answered = {};
        let score = 0;
        const total = {{ count($questions) }};

        function updateProgress() {
            const answeredCount = Object.keys(answered).length;
            const pct = total > 0 ? Math.round((answeredCount / total) * 100) : 0;
            document.getElementById('quiz-progress-bar').style.width = pct + '%';
            document.getElementById('quiz-progress-label').textContent = answeredCount + ' of ' + total + ' answered';
            document.getElementById('quiz-progress-pct').textContent = pct + '%';

            // Show score card
            const scoreCard = document.getElementById('quiz-score-card');
            const scoreVal = document.getElementById('quiz-score-val');
            scoreCard.style.display = 'block';
            scoreVal.textContent = score + ' / ' + answeredCount;

            // All answered → show result
            if (answeredCount === total) {
                showFinalResult();
            }
        }

        function selectAnswer(qId, selected, correct) {
            if (answered[qId]) return;
            answered[qId] = true;

            const isCorrect = (selected === correct);
            if (isCorrect) score++;

            const selectedBtn = document.getElementById(`btn-${qId}-${selected}`);
            const correctBtn = document.getElementById(`btn-${qId}-${correct}`);

            // Disable all buttons for this question
            ['A','B','C','D'].forEach(k => {
                const b = document.getElementById(`btn-${qId}-${k}`);
                if (b) b.style.pointerEvents = 'none';
            });

            if (isCorrect) {
                selectedBtn.style.background = 'var(--h-green-bg)';
                selectedBtn.style.borderColor = 'rgba(34,197,94,0.4)';
                selectedBtn.style.color = 'var(--h-green)';
            } else {
                selectedBtn.style.background = 'var(--h-red-bg)';
                selectedBtn.style.borderColor = 'rgba(239,68,68,0.4)';
                selectedBtn.style.color = 'var(--h-red)';
                correctBtn.style.background = 'var(--h-green-bg)';
                correctBtn.style.borderColor = 'rgba(34,197,94,0.4)';
                correctBtn.style.color = 'var(--h-green)';
            }

            // Mark question number badge
            const numBadge = document.getElementById(`q-num-${qId}`);
            if (numBadge) {
                numBadge.style.background = isCorrect ? 'var(--h-green-bg)' : 'var(--h-red-bg)';
                numBadge.style.borderColor = isCorrect ? 'rgba(34,197,94,0.4)' : 'rgba(239,68,68,0.4)';
                numBadge.style.color = isCorrect ? 'var(--h-green)' : 'var(--h-red)';
                numBadge.innerHTML = isCorrect
                    ? '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>'
                    : '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
            }

            // Reveal explanation
            const expl = document.getElementById(`expl-${qId}`);
            const verdict = document.getElementById(`expl-verdict-${qId}`);
            expl.style.display = 'block';
            if (isCorrect) {
                expl.style.background = 'var(--h-green-bg)';
                expl.style.border = '1px solid rgba(34,197,94,0.25)';
                verdict.style.color = 'var(--h-green)';
                verdict.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Correct Answer';
            } else {
                expl.style.background = 'var(--h-red-bg)';
                expl.style.border = '1px solid rgba(239,68,68,0.25)';
                verdict.style.color = 'var(--h-red)';
                verdict.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Incorrect — Correct: Option ${correct}`;
            }

            updateProgress();
        }

        function showFinalResult() {
            const pct = Math.round((score / total) * 100);

            // Save best score
            const prev = parseInt(localStorage.getItem('haven_quiz_best_raw') || '0');
            if (score > prev) {
                localStorage.setItem('haven_quiz_best_raw', score);
                localStorage.setItem('haven_quiz_best', score + ' / ' + total);
            }

            let icon, tier, tierColor;
            if (pct === 100) {
                icon = '🏆'; tier = 'Master Diagnostician — Perfect Score!'; tierColor = 'var(--h-teal)';
            } else if (pct >= 80) {
                icon = '🎯'; tier = 'Senior Technician — Excellent Result'; tierColor = 'var(--h-green)';
            } else if (pct >= 60) {
                icon = '🔧'; tier = 'Field Technician — Good Performance'; tierColor = 'var(--h-amber)';
            } else {
                icon = '📖'; tier = 'Apprentice — Review the modules and try again'; tierColor = 'var(--h-text3)';
            }

            document.getElementById('quiz-result-icon').textContent = icon;
            document.getElementById('quiz-result-score-txt').textContent = `You scored ${score} / ${total}`;
            const tierEl = document.getElementById('quiz-result-tier');
            tierEl.textContent = tier;
            tierEl.style.color = tierColor;

            // Update final score display
            const scoreVal = document.getElementById('quiz-score-val');
            scoreVal.textContent = score + ' / ' + total;
            scoreVal.style.color = pct >= 60 ? 'var(--h-green)' : 'var(--h-red)';

            document.getElementById('quiz-result-panel').style.display = 'block';
            document.getElementById('quiz-retake-row').style.display = 'none';

            // Scroll to result
            setTimeout(() => {
                document.getElementById('quiz-result-panel').scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 300);
        }
    </script>

</x-app-layout>
