{{-- Relocated content of the score report — shell-wrapped only, internals unchanged.
     See design/global_design_direction.md section 7 (step 3). --}}
<div class="scores-embed">

    {{-- ══════════════════════════════════════════════
         HERO BANNER
    ══════════════════════════════════════════════ --}}
    <div class="sd-hero">
        <div class="sd-hero-inner">
            @if ($userTest->attempt_type === 'section')
                <p class="sd-score-context">
                    {{ $userTest->section_type === 'reading_writing' ? 'Reading & Writing Section Score' : 'Math Section Score' }}
                </p>
                <div>
                    <span
                        class="sd-hero-score">{{ $userTest->section_type === 'reading_writing' ? $userTest->score_reading_writing ?? '—' : $userTest->score_math ?? '—' }}</span>
                    <span class="sd-hero-score-range">/ 800</span>
                </div>
                <p class="sd-score-disclosure">
                    @if ($userTest->score_estimate_kind === 'adaptive_irt_provisional')
                        This is an estimate based on the questions you saw. Your score is likely between
                        {{ $userTest->section_type === 'reading_writing' ? $userTest->score_reading_writing_lower . ' and ' . $userTest->score_reading_writing_upper : $userTest->score_math_lower . ' and ' . $userTest->score_math_upper }}.
                    @else
                        This estimate uses all the questions you answered in this section.
                    @endif
                    Not an official College Board score.
                </p>
                <details class="sd-score-explainer">
                    <summary>How is this calculated?</summary>
                    <p>
                        @if ($userTest->score_estimate_kind === 'adaptive_irt_provisional')
                            This section used an adaptive route, so question difficulty adjusted based on your answers.
                            We use a statistical model (EAP 3PL) to estimate your ability from the questions you
                            actually saw, which is why the result is shown as a range rather than one exact number.
                        @else
                            This section was scored using all the questions you answered, without adjusting for adaptive
                            difficulty.
                        @endif
                    </p>
                </details>
            @elseif ($isScaledSatResult)
                <p class="sd-score-context">
                    {{ $userTest->score_estimate_kind === 'adaptive_irt_provisional' ? 'Estimated practice score' : 'Estimated practice score · Normal conversion' }}
                </p>
                <div
                    style="display: flex; gap: 3rem; align-items: center; margin-top: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <div>
                        <span class="sd-hero-score">{{ $userTest->total_score }}</span>
                        <span class="sd-hero-score-range">/ 1600</span>
                    </div>

                    @if (($userTest->score_reading_writing ?? null) !== null || ($userTest->score_math ?? null) !== null)
                        <div
                            style="display: flex; gap: 2rem; align-items: center; border-left: 1.5px solid rgba(255,255,255,0.15); padding-left: 2rem; flex-wrap: wrap;">
                            @if ($userTest->score_reading_writing !== null)
                                <div style="display: flex; align-items: center; gap: 0.8rem;">
                                    <div
                                        style="position: relative; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="48" height="48" viewBox="0 0 36 36"
                                            style="transform: rotate(-90deg); filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));">
                                            <circle cx="18" cy="18" r="16" fill="none"
                                                stroke="rgba(255,255,255,0.12)" stroke-width="3"></circle>
                                            <circle cx="18" cy="18" r="16" fill="none" stroke="#60a5fa"
                                                stroke-width="3"
                                                stroke-dasharray="{{ max(2, min(100, (($userTest->score_reading_writing - 200) / 600) * 100)) }}, 100"
                                                stroke-linecap="round"></circle>
                                        </svg>
                                        <span
                                            style="position: absolute; font-size: 0.65rem; font-weight: 800; color: #fff; letter-spacing: 0.02em;">R&W</span>
                                    </div>
                                    <div>
                                        <div
                                            style="font-size: 1.35rem; font-weight: 800; color: #fff; line-height: 1.1;">
                                            {{ $userTest->score_reading_writing }}</div>
                                        <div
                                            style="font-size: 0.725rem; color: #93c5fd; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;">
                                            Reading & Writing</div>
                                    </div>
                                </div>
                            @endif

                            @if ($userTest->score_math !== null)
                                <div style="display: flex; align-items: center; gap: 0.8rem;">
                                    <div
                                        style="position: relative; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="48" height="48" viewBox="0 0 36 36"
                                            style="transform: rotate(-90deg); filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));">
                                            <circle cx="18" cy="18" r="16" fill="none"
                                                stroke="rgba(255,255,255,0.12)" stroke-width="3"></circle>
                                            <circle cx="18" cy="18" r="16" fill="none" stroke="#34d399"
                                                stroke-width="3"
                                                stroke-dasharray="{{ max(2, min(100, (($userTest->score_math - 200) / 600) * 100)) }}, 100"
                                                stroke-linecap="round"></circle>
                                        </svg>
                                        <span
                                            style="position: absolute; font-size: 0.65rem; font-weight: 800; color: #fff; letter-spacing: 0.02em;">MATH</span>
                                    </div>
                                    <div>
                                        <div
                                            style="font-size: 1.35rem; font-weight: 800; color: #fff; line-height: 1.1;">
                                            {{ $userTest->score_math }}</div>
                                        <div
                                            style="font-size: 0.725rem; color: #a7f3d0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;">
                                            Math</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
                <p class="sd-score-disclosure">
                    @if ($userTest->score_estimate_kind === 'adaptive_irt_provisional')
                        This is an estimate based on the questions you saw. Your score is likely between
                        {{ $userTest->total_score_lower }} and {{ $userTest->total_score_upper }}.
                    @elseif($userTest->score_estimate_kind === 'normal_generic')
                        This estimate uses all the questions you answered, converted with our standard scoring table.
                    @else
                        This estimate uses all the questions you answered, converted with the official scoring table for
                        this test.
                    @endif
                    Not an official College Board score.
                </p>
                <details class="sd-score-explainer">
                    <summary>How is this calculated?</summary>
                    <p>
                        @if ($userTest->score_estimate_kind === 'adaptive_irt_provisional')
                            This test used an adaptive route, so question difficulty adjusted based on your answers. We
                            use a statistical model (EAP 3PL) to estimate your ability from the questions you actually
                            saw. The scaled score mapping for this route is still provisional, which is why the result
                            is shown as a range.
                        @elseif($userTest->score_estimate_kind === 'normal_generic')
                            Your raw score was converted to a 400–1600 scale using our standard conversion table
                            (v{{ $userTest->score_conversion_version }}). A form-specific conversion table, once
                            available, may give a slightly more precise result.
                        @else
                            Your raw score was converted to a 400–1600 scale using the official conversion table for
                            this test form (v{{ $userTest->scoreConversionSet?->version ?? 'legacy' }}).
                        @endif
                    </p>
                </details>
            @else
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="sd-hero-score">{{ $correct }}</span>
                    <span class="sd-hero-score-range">/ {{ $totalQ }} correct</span>
                </div>
                <p class="mt-2 max-w-2xl text-sm text-white">
                    {{ $stats['total']['correct'] }} of {{ $stats['total']['questions'] }} scored questions correct.
                    Practice performance, not a calibrated SAT score.
                    This test type does not produce a full SAT scaled estimate.
                </p>
            @endif
            <p class="sd-hero-meta">
                {{ $userTest->test->title }}
                &nbsp;·&nbsp;
                {{ $userTest->completed_at ? $userTest->completed_at->format('F j, Y') : 'In progress' }}
            </p>
            <div class="sd-hero-pills">
                <button class="sd-hero-pill">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    Review All Questions
                </button>
                <button class="sd-hero-pill">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                    </svg>
                    Practice Weak Areas
                </button>
                <a class="sd-hero-pill"
                    href="{{ route('student.scores.export-pdf', $userTest) }}">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                    </svg>
                    Download Report
                </a>
            </div>
        </div>
    </div>

    {{-- Sticky sentinel — sits right below the hero --}}
    <div id="sd-tabs-sentinel" aria-hidden="true" style="height:1px;"></div>

    {{-- ══════════════════════════════════════════════
         STICKY TABS BAR
    ══════════════════════════════════════════════ --}}
    <div class="sd-tabs-bar" id="sd-tabs-bar">
        <div class="sd-tabs-bar-inner">
            <span class="sd-tabs-bar-title" title="{{ $userTest->test->title }}">{{ $userTest->test->title }}</span>
            <div class="sd-tabs" id="skillTabs">
                <button class="sd-tab active" data-target="all">All</button>
                <button class="sd-tab" data-target="rw">Reading &amp; Writing</button>
                <button class="sd-tab" data-target="math">Math</button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         MAIN CONTENT
    ══════════════════════════════════════════════ --}}
    <div class="sd-container">

        {{-- ── KNOWLEDGE & SKILLS HEADING ── --}}
        <h2 class="text-3xl font-bold">Knowledge &amp; Skills</h2>
        <p class="sd-section-sub">View your performance across the 8 content domains measured on the SAT.</p>

        {{-- ── DOMAIN GROUPS — rendered once, filtered by JS ── --}}
        @php
            $rwDomainSummaries = collect($domainSummaries)->where('sectionKey', 'rw');
            $mathDomainSummaries = collect($domainSummaries)->where('sectionKey', 'math');
        @endphp

        @if (!count($rwDomainSummaries) && !count($mathDomainSummaries))
            <p class="sd-section-sub">No scored domains available for this attempt.</p>
        @endif

        @include('student.scores.partials.domain-group', [
            'sectionKey' => 'rw',
            'sectionLabel' => 'Reading and Writing',
            'summaries' => $rwDomainSummaries,
        ])

        @include('student.scores.partials.domain-group', [
            'sectionKey' => 'math',
            'sectionLabel' => 'Math',
            'summaries' => $mathDomainSummaries,
        ])

        @include('student.scores.partials.difficulty-summary', compact('difficultySummaries'))

        {{-- ── QUESTION REVIEW ── --}}
        <h2 class="text-3xl font-bold">Question Review</h2>
        <p class="sd-section-sub">Detailed results for every question from this practice test.</p>

        {{-- Attempt Diagnostic Timeline Grid --}}
        <div class="sd-timeline-section" style="margin-top: 1.5rem; margin-bottom: 2.25rem;">
            <h3 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.4rem;">Attempt Diagnostic
                Timeline</h3>
            <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 1.25rem;">Click any question node below to
                open its review details instantly. Pacing indicator lines are shown underneath each circle.</p>

            <div class="sd-timeline-container" style="display: flex; flex-direction: column; gap: 1.25rem;">
                @foreach (['rw' => 'Reading & Writing', 'math' => 'Math'] as $secType => $secLabel)
                    @php
                        $modules = collect($allAnswers)->where('sectionType', $secType)->groupBy('moduleNumber');
                    @endphp
                    @if ($modules->isNotEmpty())
                        <div class="sd-timeline-section-row" data-timeline-section="{{ $secType }}"
                            style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; box-shadow: inset 0 1px 2px rgba(0,0,0,0.015);">
                            <div
                                style="font-weight: 800; font-size: 0.85rem; color: #475569; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.08em; display: flex; align-items: center; gap: 0.5rem;">
                                <span
                                    style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background-color: {{ $secType === 'rw' ? '#3b82f6' : '#10b981' }};"></span>
                                {{ $secLabel }}
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                                @foreach ($modules as $modNum => $modAnswers)
                                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                        <div
                                            style="font-weight: 700; font-size: 0.85rem; color: #64748b; min-width: 85px; text-transform: uppercase; letter-spacing: 0.02em;">
                                            Module {{ $modNum }}:
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                                            @foreach ($modAnswers as $row)
                                                @php
                                                    $isCorrect = $row['statusKey'] === 'correct';
                                                    $isOmitted = $row['statusKey'] === 'omitted';

                                                    // Node Color styling
                                                    $nodeBg = $isCorrect
                                                        ? '#d1fae5'
                                                        : ($isOmitted
                                                            ? '#f1f5f9'
                                                            : '#fee2e2');
                                                    $nodeColor = $isCorrect
                                                        ? '#065f46'
                                                        : ($isOmitted
                                                            ? '#64748b'
                                                            : '#991b1b');
                                                    $nodeBorder = $isCorrect
                                                        ? '#a7f3d0'
                                                        : ($isOmitted
                                                            ? '#cbd5e1'
                                                            : '#fca5a5');

                                                    // Pacing line styling
                                                    $time = $row['timeSpent'];
                                                    $expected = $row['expectedTime'] ?? 0;

                                                    if ($expected > 0 && $time > $expected * 1.5) {
                                                        $paceColor = '#ef4444'; // Red (Stuck / Slow)
                                                        $paceLabel = 'Stuck / Slow';
                                                    } elseif ($isCorrect && $expected > 0 && $time < $expected * 0.4) {
                                                        $paceColor = '#f59e0b'; // Amber (Rushed)
                                                        $paceLabel = 'Rushed';
                                                    } else {
                                                        $paceColor = '#3b82f6'; // Blue (Optimal)
                                                        $paceLabel = 'Optimal';
                                                    }
                                                @endphp
                                                <div class="sd-timeline-node-wrapper"
                                                    style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem;">
                                                    <button class="js-review-btn"
                                                        data-question="{{ json_encode($row['questionData']) }}"
                                                        title="Question {{ $row['idx'] }} · {{ ucfirst($row['statusKey']) }} · Spent {{ $time }}s ({{ $paceLabel }})"
                                                        style="width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; background: {{ $nodeBg }}; color: {{ $nodeColor }}; border: 1.5px solid {{ $nodeBorder }}; cursor: pointer; transition: all 0.15s ease-in-out; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                                                        onmouseover="this.style.transform='scale(1.15)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.1)';"
                                                        onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.05)';"
                                                        type="button">
                                                        {{ $row['idx'] }}
                                                    </button>
                                                    <div style="width: 14px; height: 3px; border-radius: 99px; background: {{ $paceColor }};"
                                                        title="{{ $paceLabel }}"></div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- Pacing Legend indicator bar --}}
            <div
                style="display: flex; align-items: center; gap: 1.25rem; margin-top: 0.8rem; font-size: 0.75rem; color: #64748b; font-weight: 600; padding-left: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.35rem;">
                    <div style="width: 10px; height: 3px; background: #3b82f6; border-radius: 99px;"></div>
                    <span>Optimal Pace</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.35rem;">
                    <div style="width: 10px; height: 3px; background: #f59e0b; border-radius: 99px;"></div>
                    <span>Rushed (&lt;40% expected)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.35rem;">
                    <div style="width: 10px; height: 3px; background: #ef4444; border-radius: 99px;"></div>
                    <span>Stuck / Slow (&gt;150% expected)</span>
                </div>
            </div>
        </div>

        {{-- Stats — values updated by JS on tab switch --}}
        <div class="sd-stat-row">
            <div class="sd-stat-card">
                <div class="sd-stat-value" id="stat-total">{{ $totalQ }}</div>
                <div class="sd-stat-label">Total Questions</div>
            </div>
            <div class="sd-stat-card">
                <div class="sd-stat-value correct" id="stat-correct">{{ $correct }}</div>
                <div class="sd-stat-label">Correct</div>
            </div>
            <div class="sd-stat-card">
                <div class="sd-stat-value wrong" id="stat-wrong">{{ $wrong + $omitted }}</div>
                <div class="sd-stat-label">Incorrect / Omitted</div>
            </div>
        </div>

        {{-- Embedded stats for JS (all / rw / math) --}}
        <script id="sd-stats-data" type="application/json">
        {
            "all":  { "total": {{ $totalQ }},  "correct": {{ $correct }},  "wrong": {{ $wrong + $omitted }} },
            "rw":   { "total": {{ $rwTotal }},  "correct": {{ $rwCorrect }}, "wrong": {{ $rwWrong + $rwOmitted }} },
            "math": { "total": {{ $mTotal }},   "correct": {{ $mCorrect }},  "wrong": {{ $mWrong + $mOmitted }} }
        }
        </script>

        {{-- Single table — rows tagged with data-section, filtered by JS --}}
        @include('student.scores.partials.table', ['answers' => $allAnswers, 'tableId' => 'table-main'])

    </div>{{-- /sd-container --}}

</div>{{-- /scores-embed --}}

{{-- ══════════════════════════════════════════════
     REVIEW MODAL
══════════════════════════════════════════════ --}}
<div id="reviewModal" class="sd-modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="sd-modal">
        <div class="sd-modal-header">
            <span class="sd-modal-title">Question Review</span>
            <button class="sd-modal-close" id="reviewModalCloseBtn" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2.5">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
        <div class="sd-modal-body">
            <div class="sd-modal-section-label">Question</div>
            <div class="sd-modal-question-box" id="modalQuestionStem"></div>

            <div class="sd-modal-section-label js-mc-label" style="display:none;margin-top:1.25rem;">Answer
                Choices</div>
            <div class="sd-modal-choices-list js-mc-list" id="modalChoicesList"
                style="display:none;margin-bottom:1.5rem;"></div>

            <div class="sd-modal-answer-row">
                <div class="sd-modal-answer-box your-answer" id="modalYourAnswerBox">
                    <div class="sd-modal-answer-label">Your Answer</div>
                    <div class="sd-modal-answer-val" id="modalYourAnswer"></div>
                </div>
                <div class="sd-modal-answer-box correct-answer">
                    <div class="sd-modal-answer-label">Correct Answer</div>
                    <div class="sd-modal-answer-val" id="modalCorrectAnswer"></div>
                </div>
            </div>

            <div class="sd-modal-section-label">Explanation</div>
            <div class="sd-modal-expl-box" id="modalExplanation"></div>

            <div id="modalErrorReviewPanel" hidden style="margin-top:1.25rem;">
                <label for="modalErrorType" class="sd-modal-section-label" style="display:block;">Why was this missed?</label>
                <p style="font-size:.8rem;color:#64748b;margin:.25rem 0 .55rem;">Choose the primary cause. Teachers can override this classification.</p>
                <select id="modalErrorType" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:.6rem .7rem;color:#0f172a;background:#fff;">
                    <option value="">Not classified</option>
                    <option value="conceptual_gap">Conceptual / knowledge gap</option>
                    <option value="misread_question">Misread question</option>
                    <option value="misread_text_or_data">Misread passage, text, or data</option>
                    <option value="wrong_strategy">Wrong strategy</option>
                    <option value="calculation">Calculation error</option>
                    <option value="grammar_rule">Grammar rule error</option>
                    <option value="elimination">Elimination error</option>
                    <option value="careless">Careless error</option>
                    <option value="time_pressure">Time pressure</option>
                    <option value="guess">Guess</option>
                    <option value="omitted">Omitted</option>
                </select>
                <p id="modalErrorReviewStatus" style="font-size:.75rem;color:#64748b;margin:.45rem 0 0;"></p>
            </div>
        </div>
        <div class="sd-modal-footer">
            <button class="sd-modal-btn-close" id="reviewModalCloseBtn2">Close</button>
        </div>
    </div>
</div>
