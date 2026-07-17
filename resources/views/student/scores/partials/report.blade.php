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
                    <span class="sd-hero-score">{{ $userTest->section_type === 'reading_writing' ? ($userTest->score_reading_writing ?? '—') : ($userTest->score_math ?? '—') }}</span>
                    <span class="sd-hero-score-range">/ 800</span>
                </div>
                <p class="sd-score-disclosure">
                    @if($userTest->score_estimate_kind === 'adaptive_irt_provisional')
                        This is an estimate based on the questions you saw. Your score is likely between
                        {{ $userTest->section_type === 'reading_writing' ? ($userTest->score_reading_writing_lower . ' and ' . $userTest->score_reading_writing_upper) : ($userTest->score_math_lower . ' and ' . $userTest->score_math_upper) }}.
                    @else
                        This estimate uses all the questions you answered in this section.
                    @endif
                    Not an official College Board score.
                </p>
                <details class="sd-score-explainer">
                    <summary>How is this calculated?</summary>
                    <p>
                        @if($userTest->score_estimate_kind === 'adaptive_irt_provisional')
                            This section used an adaptive route, so question difficulty adjusted based on your answers. We use a statistical model (EAP 3PL) to estimate your ability from the questions you actually saw, which is why the result is shown as a range rather than one exact number.
                        @else
                            This section was scored using all the questions you answered, without adjusting for adaptive difficulty.
                        @endif
                    </p>
                </details>
            @elseif ($isScaledSatResult)
                <p class="sd-score-context">
                    {{ $userTest->score_estimate_kind === 'adaptive_irt_provisional' ? 'Estimated practice score' : 'Estimated practice score · Normal conversion' }}
                </p>
                <div>
                    <span class="sd-hero-score">{{ $userTest->total_score }}</span>
                    <span class="sd-hero-score-range">/ 1600</span>
                </div>
                <p class="sd-score-disclosure">
                    @if($userTest->score_estimate_kind === 'adaptive_irt_provisional')
                        This is an estimate based on the questions you saw. Your score is likely between
                        {{ $userTest->total_score_lower }} and {{ $userTest->total_score_upper }}.
                    @elseif($userTest->score_estimate_kind === 'normal_generic')
                        This estimate uses all the questions you answered, converted with our standard scoring table.
                    @else
                        This estimate uses all the questions you answered, converted with the official scoring table for this test.
                    @endif
                    Not an official College Board score.
                </p>
                <details class="sd-score-explainer">
                    <summary>How is this calculated?</summary>
                    <p>
                        @if($userTest->score_estimate_kind === 'adaptive_irt_provisional')
                            This test used an adaptive route, so question difficulty adjusted based on your answers. We use a statistical model (EAP 3PL) to estimate your ability from the questions you actually saw. The scaled score mapping for this route is still provisional, which is why the result is shown as a range.
                        @elseif($userTest->score_estimate_kind === 'normal_generic')
                            Your raw score was converted to a 400–1600 scale using our standard conversion table (v{{ $userTest->score_conversion_version }}). A form-specific conversion table, once available, may give a slightly more precise result.
                        @else
                            Your raw score was converted to a 400–1600 scale using the official conversion table for this test form (v{{ $userTest->scoreConversionSet?->version ?? 'legacy' }}).
                        @endif
                    </p>
                </details>
            @else
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="sd-hero-score">{{ $correct }}</span>
                    <span class="sd-hero-score-range">/ {{ $totalQ }} correct</span>
                </div>
                <p class="mt-2 max-w-2xl text-sm font-semibold text-slate-700">
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
                <a class="sd-hero-pill" href="{{ ($isMerged ?? false) ? route('student.scores.merged.export-pdf', [$rwAttempt->ulid, $mathAttempt->ulid]) : route('student.scores.export-pdf', $userTest) }}">
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
        @if (! count($stats['sections']['reading_and_writing']['domains']) && ! count($stats['sections']['math']['domains']))
            <p class="sd-section-sub">No scored domains available for this attempt.</p>
        @endif

        @if (count($stats['sections']['reading_and_writing']['domains']))
            <div class="sd-domain-group" data-section="rw">
                <h4 class="sd-domain-section-label !font-bold">Reading and Writing</h4>
                <div class="sd-domains-grid">
                    @foreach ($stats['sections']['reading_and_writing']['domains'] as $domain => $data)
                        @php
                            $pct = $data['total'] > 0 ? $data['correct'] / $data['total'] : 0;
                            $filled = max(1, round($pct * 7));
                            $perfLabel = $pct >= 0.8 ? 'High' : ($pct >= 0.5 ? 'Medium' : 'Low');
                            $barClass = $pct >= 0.8 ? '' : ($pct >= 0.5 ? 'medium' : 'low');
                            $badgeClass = strtolower($perfLabel);
                            $secPct = $rwTotal > 0 ? round(($data['total'] / $rwTotal) * 100) : 0;
                        @endphp
                        @include(
                            'student.scores.partials.domain',
                            compact('domain', 'data', 'filled', 'barClass', 'badgeClass', 'perfLabel', 'secPct'))
                    @endforeach
                </div>
            </div>
        @endif

        @if (count($stats['sections']['math']['domains']))
            <div class="sd-domain-group" data-section="math">
                <h4 class="sd-domain-section-label !font-bold">Math</h4>
                <div class="sd-domains-grid">
                    @foreach ($stats['sections']['math']['domains'] as $domain => $data)
                        @php
                            $pct = $data['total'] > 0 ? $data['correct'] / $data['total'] : 0;
                            $filled = max(1, round($pct * 7));
                            $perfLabel = $pct >= 0.8 ? 'High' : ($pct >= 0.5 ? 'Medium' : 'Low');
                            $barClass = $pct >= 0.8 ? '' : ($pct >= 0.5 ? 'medium' : 'low');
                            $badgeClass = strtolower($perfLabel);
                            $secPct = $mTotal > 0 ? round(($data['total'] / $mTotal) * 100) : 0;
                        @endphp
                        @include(
                            'student.scores.partials.domain',
                            compact('domain', 'data', 'filled', 'barClass', 'badgeClass', 'perfLabel', 'secPct'))
                    @endforeach
                </div>
            </div>
        @endif

        @include('student.scores.partials.difficulty-summary', compact('difficultySummaries'))

        {{-- ── QUESTION REVIEW ── --}}
        <h2 class="text-3xl font-bold">Question Review</h2>
        <p class="sd-section-sub">Detailed results for every question from this practice test.</p>

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
        </div>
        <div class="sd-modal-footer">
            <button class="sd-modal-btn-close" id="reviewModalCloseBtn2">Close</button>
        </div>
    </div>
</div>
