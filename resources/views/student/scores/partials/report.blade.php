{{-- Score report partial: continuous paper workspace --}}
<div class="scores-embed" id="scores-report-root" data-is-teacher="{{ !empty($isTeacherView) ? 'true' : 'false' }}">

    {{-- ══════════════════════════════════════════════
        1. SCORE OVERVIEW SECTION (#overview)
    ══════════════════════════════════════════════ --}}
    <section id="overview" class="sd-section sd-overview-section" style="scroll-margin-top: 80px; margin-bottom: 2rem;">
        <div class="sd-paper-card sd-score-hero">
            {{-- Top Header / Metadata Bar --}}
            <div class="sd-score-header-bar">
                <div class="sd-score-header-badges">
                    <span
                        class="sd-score-context-badge">{{ $scorePresentation['contextLabel'] ?: 'Practice score' }}</span>
                    <span class="sd-basis-badge">{{ $scorePresentation['basisLabel'] }}</span>
                    <details class="sd-score-explainer-popover">
                        <summary class="sd-explainer-trigger" aria-haspopup="dialog"
                            aria-label="How is this calculated?">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="16" x2="12" y2="12" />
                                <line x1="12" y1="8" x2="12.01" y2="8" />
                            </svg>
                            <span>How is this calculated?</span>
                        </summary>
                        <div class="sd-score-explainer-content" role="region"
                            aria-label="Scoring calculation explanation">
                            {{ $scorePresentation['calculationDisclosure'] }}
                        </div>
                    </details>
                </div>
            </div>

            {{-- Main Metrics Display --}}
            <div
                class="sd-metrics-grid {{ !$scorePresentation['isScaledSatResult'] || ($scorePresentation['rwScore'] === null && $scorePresentation['mathScore'] === null) ? 'sd-metrics-grid-single' : '' }}">
                {{-- Zone 1: Total Score --}}
                <div class="sd-metric-zone sd-metric-zone-total">
                    <div class="sd-score-value-line" role="text"
                        aria-label="Total score: {{ $scorePresentation['score'] ?? '—' }} out of {{ $scorePresentation['maxScore'] }}">
                        <span class="sd-hero-score">{{ $scorePresentation['score'] ?? '—' }}</span>
                        <span class="sd-hero-score-range">/ {{ $scorePresentation['maxScore'] }}</span>
                    </div>

                    @if ($scorePresentation['hasRange'] && !empty($scorePresentation['rangeFormatted']))
                        <div class="sd-score-band">
                            Score band: <strong>{{ $scorePresentation['rangeFormatted'] }}</strong>
                        </div>
                    @endif
                </div>

                {{-- Vertical divider between total score and sections --}}
                @if (
                    $scorePresentation['isScaledSatResult'] &&
                        ($scorePresentation['rwScore'] !== null || $scorePresentation['mathScore'] !== null))
                    <div class="sd-metric-divider" aria-hidden="true"></div>

                    {{-- Section Ring Badges & Scores (RW and Math) --}}
                    <div class="sd-section-metrics-group">
                        @if ($scorePresentation['rwScore'] !== null)
                            <div class="sd-metric-zone sd-micro-card sd-micro-card-rw">
                                <div class="sd-section-badge-ring rw" aria-hidden="true">
                                    <span>R&amp;W</span>
                                </div>
                                <div class="sd-section-badge-info">
                                    <div class="sd-micro-card-value-line" role="text"
                                        aria-label="Reading and Writing score: {{ $scorePresentation['rwScore'] }} out of 800">
                                        <strong
                                            class="sd-micro-card-score">{{ $scorePresentation['rwScore'] }}</strong>
                                        <span class="sd-micro-card-max">/ 800</span>
                                    </div>
                                    <span class="sd-micro-card-title">Reading &amp; Writing</span>
                                    @if (!empty($scorePresentation['rwRange']))
                                        <div class="sd-micro-card-band">
                                            Score band: <strong>{{ $scorePresentation['rwRange'] }}</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($scorePresentation['mathScore'] !== null)
                            <div class="sd-metric-zone sd-micro-card sd-micro-card-math">
                                <div class="sd-section-badge-ring math" aria-hidden="true">
                                    <span>MATH</span>
                                </div>
                                <div class="sd-section-badge-info">
                                    <div class="sd-micro-card-value-line" role="text"
                                        aria-label="Math score: {{ $scorePresentation['mathScore'] }} out of 800">
                                        <strong
                                            class="sd-micro-card-score">{{ $scorePresentation['mathScore'] }}</strong>
                                        <span class="sd-micro-card-max">/ 800</span>
                                    </div>
                                    <span class="sd-micro-card-title">Math</span>
                                    @if (!empty($scorePresentation['mathRange']))
                                        <div class="sd-micro-card-band">
                                            Score band: <strong>{{ $scorePresentation['mathRange'] }}</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Disclosures --}}
            <div class="sd-score-disclosure">
                @if (!empty($scorePresentation['scoreDisclosure']))
                    <p class="sd-score-disclosure-text">{{ $scorePresentation['scoreDisclosure'] }}</p>
                @endif
                <p class="sd-score-unofficial-notice">{{ $scorePresentation['unofficialNotice'] }}</p>
            </div>

            {{-- Attempt Context & Date --}}
            <div class="sd-score-attempt-meta">
                <span>{{ $userTest->test->title ?? 'Practice Test' }}</span>
                @if (!empty($userTest->completed_at))
                    <span class="sd-meta-sep" aria-hidden="true">&bull;</span>
                    <span>{{ $userTest->completed_at->format('F j, Y') }}</span>
                @endif
            </div>

            {{-- Bottom Translucent Glass Action Buttons --}}
            <div class="sd-overview-actions">
                <a href="#review" id="btn-goto-review" class="sd-action-btn primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    Review questions
                </a>

                <a href="{{ route('student.scores.export-pdf', $userTest) }}" class="sd-action-btn secondary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                    </svg>
                    Download summary PDF
                </a>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════
        STICKY ANCHOR & SUBJECT NAVIGATION BAR
    ══════════════════════════════════════════════ --}}
    <nav class="sd-tabs-bar" id="sd-tabs-bar" aria-label="Score report navigation">
        <div
            style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; padding: 1rem 0;">
            {{-- Anchor Links --}}
            <div class="sd-anchor-nav" style="display: flex; gap: 0.5rem;" aria-label="Report sections">
                <a href="#overview" class="sd-nav-link active" data-anchor="overview">Score overview</a>
                <a href="#performance" class="sd-nav-link" data-anchor="performance">Performance</a>
                <a href="#review" class="sd-nav-link" data-anchor="review">Question review</a>
            </div>

            {{-- Subject Filter Chips --}}
            @if (count($scorePresentation['availableSubjects']) > 1)
                <div class="sd-subject-filter-chips" style="display: flex; align-items: center; gap: 0.35rem;"
                    role="group" aria-label="Subject filter">
                    <span
                        style="font-size: 0.75rem; font-weight: 600; color: var(--ds-text-tertiary, #64748b); margin-right: 0.25rem;">Subject:</span>
                    <button type="button" class="sd-tab active" data-subject="all" aria-pressed="true">All
                        subjects</button>
                    @if (in_array('rw', $scorePresentation['availableSubjects'], true))
                        <button type="button" class="sd-tab" data-subject="rw" aria-pressed="false">Reading &amp;
                            Writing</button>
                    @endif
                    @if (in_array('math', $scorePresentation['availableSubjects'], true))
                        <button type="button" class="sd-tab" data-subject="math" aria-pressed="false">Math</button>
                    @endif
                </div>
            @endif
        </div>
    </nav>

    {{-- ══════════════════════════════════════════════
         2. PERFORMANCE SECTION (#performance)
    ══════════════════════════════════════════════ --}}
    <section id="performance" class="sd-section" style="scroll-margin-top: 80px; margin-bottom: 3rem;">
        <div style="margin-bottom: 1.25rem; margin-top: 1rem;">
            <div
                style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 0.5rem;">
                <h2 class="sd-section-title">Performance Diagnostics</h2>
                <span style="font-size: 0.775rem; color: var(--ds-text-tertiary, #64748b); font-weight: 600;">All practice questions</span>
            </div>
            <p class="sd-section-sub">
                Learning diagnostics include all practice questions (including {{ $unscored }} unscored question{{ $unscored === 1 ? '' : 's' }}). Scaled scoring excludes unscored questions.
            </p>
        </div>

        {{-- Diagnostic Summary Stat Cards (scope updated via JS) --}}
        <div class="sd-stat-row">
            <div class="sd-stat-card total">
                <div class="sd-stat-value" id="stat-total">{{ $totalQ }}</div>
                <div class="sd-stat-label">Total Questions</div>
            </div>
            <div class="sd-stat-card correct">
                <div class="sd-stat-value correct" id="stat-correct">{{ $correct }}</div>
                <div class="sd-stat-label">Correct</div>
            </div>
            <div class="sd-stat-card wrong">
                <div class="sd-stat-value wrong" id="stat-wrong">{{ $wrong }}</div>
                <div class="sd-stat-label">Incorrect</div>
            </div>
            <div class="sd-stat-card omitted">
                <div class="sd-stat-value omitted" id="stat-omitted">{{ $omitted }}</div>
                <div class="sd-stat-label">Omitted</div>
            </div>
        </div>

        {{-- Domain Breakdown Groups --}}
        @php
            $rwDomainSummaries = collect($domainSummaries)->where('sectionKey', 'rw');
            $mathDomainSummaries = collect($domainSummaries)->where('sectionKey', 'math');
        @endphp

        @if (!count($rwDomainSummaries) && !count($mathDomainSummaries))
            <p class="sd-section-sub" style="color: var(--ds-text-secondary, #475569); font-style: italic;">No domain
                diagnostics recorded for this attempt.</p>
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

        {{-- Difficulty Breakdown --}}
        @include('student.scores.partials.difficulty-summary', [
            'difficultySummaries' => $difficultySummaries,
            'difficultyBySection' => $difficultyBySection,
        ])

        {{-- Pacing explanation notice --}}
        <div class="sd-callout-note">
            <strong>Note on diagnostics:</strong> Pacing labels are heuristics based on question expected-time ranges (
            <40% potentially rushed, 40–150% expected range,>150% possibly stuck/slow). Mistake causes are self-reported
                by the student or teacher.
        </div>
    </section>

    {{-- ══════════════════════════════════════════════
        3. QUESTION REVIEW SECTION (#review)
    ══════════════════════════════════════════════ --}}
    <section id="review" class="sd-section" style="scroll-margin-top: 80px; margin-bottom: 2rem;">
        <div style="margin-bottom: 1.25rem;">
            <h2 class="sd-section-title">Question Review</h2>
            <p class="sd-section-sub">
                Detailed question results, passage texts, student submissions, and answer classifications.
            </p>
        </div>

        {{-- Diagnostic Question Map (Timeline Navigator) --}}
        <div class="sd-timeline-section">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                <h3 class="sd-subsection-title">Question Map</h3>
                <span style="font-size: 0.75rem; color: var(--ds-text-tertiary, #64748b);">Select any question to open
                    review details</span>
            </div>

            <div class="sd-timeline-container" style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach (['rw' => 'Reading & Writing', 'math' => 'Math'] as $secType => $secLabel)
                    @php
                        $modules = collect($allAnswers)->where('sectionType', $secType)->groupBy('moduleNumber');
                    @endphp
                    @if ($modules->isNotEmpty())
                        <div class="sd-timeline-section-row sd-module-group-box"
                            data-timeline-section="{{ $secType }}">
                            <div class="sd-module-group-header">
                                <span class="sd-sec-dot {{ $secType }}"></span>
                                {{ $secLabel }}
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                @foreach ($modules as $modNum => $modAnswers)
                                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                        <div
                                            style="font-weight: 700; font-size: 0.75rem; color: var(--ds-text-tertiary, #64748b); min-width: 75px; text-transform: uppercase; letter-spacing: 0.02em;">
                                            Module {{ $modNum }}:
                                        </div>
                                        <div
                                            style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
                                            @foreach ($modAnswers as $row)
                                                @php
                                                    $isCorrect = $row['statusKey'] === 'correct';
                                                    $isOmitted = $row['statusKey'] === 'omitted';
                                                    $isPretest = !empty($row['isPretest']);
                                                    $pacing = $row['pacing'];
                                                    $timingDesc =
                                                        $pacing['status'] === 'none'
                                                            ? 'no timing data'
                                                            : "spent {$pacing['actualSeconds']}s, {$pacing['shortLabel']}";
                                                    $nodeAriaLabel = sprintf(
                                                        '%s, module %s, question %s, %s%s, %s',
                                                        $row['sectionName'],
                                                        $row['moduleNumber'],
                                                        $row['idx'],
                                                        $row['statusKey'],
                                                        $isPretest ? ', unscored' : '',
                                                        $timingDesc,
                                                    );
                                                @endphp
                                                <div class="sd-timeline-node-wrapper"
                                                    data-status="{{ $row['statusKey'] }}"
                                                    data-section="{{ $row['sectionType'] }}"
                                                    data-answer-id="{{ $row['answer']->id }}"
                                                    style="display: flex; flex-direction: column; align-items: center; gap: 3px;">
                                                    <button class="sd-map-node js-review-btn {{ $row['statusKey'] }}"
                                                        type="button" data-answer-id="{{ $row['answer']->id }}"
                                                        aria-label="{{ $nodeAriaLabel }}"
                                                        title="Question {{ $row['idx'] }} &middot; {{ ucfirst($row['statusKey']) }} &middot; {{ $pacing['shortLabel'] }}">
                                                        <span>{{ $row['idx'] }}</span>
                                                        @if ($isPretest)
                                                            <span class="sd-node-unscored" aria-hidden="true">*</span>
                                                        @endif
                                                        <span class="sd-map-node-badge {{ $row['statusKey'] }}"
                                                            aria-hidden="true">
                                                            @if ($row['statusKey'] === 'correct')
                                                                &#10003;
                                                            @elseif ($row['statusKey'] === 'wrong')
                                                                &#10005;
                                                            @else
                                                                &ndash;
                                                            @endif
                                                        </span>
                                                    </button>
                                                    <div class="sd-pace-indicator" title="{{ $pacing['label'] }}"
                                                        aria-hidden="true">
                                                        <div class="sd-pace-bar {{ $pacing['status'] }}"></div>
                                                        @if ($pacing['status'] === 'slow')
                                                            <span class="sd-pace-mark slow">!</span>
                                                        @elseif ($pacing['status'] === 'rushed')
                                                            <span class="sd-pace-mark rushed">&raquo;</span>
                                                        @elseif ($pacing['status'] === 'expected')
                                                            <span class="sd-pace-mark expected">&bull;</span>
                                                        @elseif ($pacing['status'] === 'none')
                                                            <span class="sd-pace-mark none">&ndash;</span>
                                                        @endif
                                                    </div>
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

            {{-- Question Map Legend (Multi-modal result and pacing keys) --}}
            <div class="sd-map-legend">
                <div class="sd-map-legend-group">
                    <span class="sd-map-legend-title">Result:</span>
                    <span class="sd-map-legend-item">
                        <span class="sd-map-node-badge static correct" aria-hidden="true">&#10003;</span>
                        <span>Correct</span>
                    </span>
                    <span class="sd-map-legend-item">
                        <span class="sd-map-node-badge static wrong" aria-hidden="true">&#10005;</span>
                        <span>Incorrect</span>
                    </span>
                    <span class="sd-map-legend-item">
                        <span class="sd-map-node-badge static omitted" aria-hidden="true">&ndash;</span>
                        <span>Omitted</span>
                    </span>
                    <span class="sd-map-legend-item">
                        <span class="sd-unscored-indicator" aria-hidden="true">*</span>
                        <span>Unscored pretest</span>
                    </span>
                </div>
                <div class="sd-map-legend-group">
                    <span class="sd-map-legend-title">Pacing:</span>
                    <span class="sd-map-legend-item">
                        <span class="sd-pace-bar expected" aria-hidden="true"></span>
                        <span class="sd-pace-mark expected" aria-hidden="true">&bull;</span>
                        <span>Expected (40&ndash;150%)</span>
                    </span>
                    <span class="sd-map-legend-item">
                        <span class="sd-pace-bar rushed" aria-hidden="true"></span>
                        <span class="sd-pace-mark rushed" aria-hidden="true">&raquo;</span>
                        <span>Rushed (&lt;40%)</span>
                    </span>
                    <span class="sd-map-legend-item">
                        <span class="sd-pace-bar slow" aria-hidden="true"></span>
                        <span class="sd-pace-mark slow" aria-hidden="true">!</span>
                        <span>Slow (&gt;150%)</span>
                    </span>
                    <span class="sd-map-legend-item">
                        <span class="sd-pace-bar none" aria-hidden="true"></span>
                        <span class="sd-pace-mark none" aria-hidden="true">&ndash;</span>
                        <span>No timing data</span>
                    </span>
                </div>
            </div>
        </div>

        {{-- Single Table — rows carry data-status, data-section, and data-answer-id --}}
        @include('student.scores.partials.table', ['answers' => $allAnswers, 'tableId' => 'table-main'])
    </section>

</div>{{-- /scores-embed --}}

{{-- Single Question Data Payload (Indexed by answer ID, emitted once) --}}
<script id="score-question-data" type="application/json">
@json($questionDataMap)
</script>
{{-- Embedded Scope Stats --}}
<script id="sd-stats-data" type="application/json">
{
    "all":  { "total": {{ $totalQ }},  "correct": {{ $correct }},  "wrong": {{ $wrong }}, "omitted": {{ $omitted }} },
    "rw":   { "total": {{ $rwTotal }}, "correct": {{ $rwCorrect }}, "wrong": {{ $rwWrong }}, "omitted": {{ $rwOmitted }} },
    "math": { "total": {{ $mTotal }},  "correct": {{ $mCorrect }},  "wrong": {{ $mWrong }}, "omitted": {{ $mOmitted }} }
}
</script>
