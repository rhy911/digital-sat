<x-layouts.app :user="$user" title="Score Details — {{ $userTest->test->title }}" header-class="!bg-[#0a2d6e]"
    logo-class="!text-white" user-class="!text-white">
    <x-slot name="head">
        @vite(['resources/css/score-details.css'])
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
        <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>
    </x-slot>

    {{-- ══════════════════════════════════════════════
         HERO BANNER
    ══════════════════════════════════════════════ --}}
    <div class="sd-hero">
        <div class="sd-hero-inner">
            <div>
                <span class="sd-hero-score">{{ $userTest->total_score ?? '—' }}</span>
                <span class="sd-hero-score-range">/ 1600</span>
            </div>
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
                <button class="sd-hero-pill">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                    </svg>
                    Download Report
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         BREADCRUMB
    ══════════════════════════════════════════════ --}}
    <div class="sd-breadcrumb">
        <div class="sd-hero-inner" style="display:flex;align-items:center;">
            <a href="{{ route('my-practice', $userTest->id) }}">My Tests</a>
            <span class="sd-breadcrumb-sep">›</span>
            <span class="sd-breadcrumb-current">
                {{ $userTest->test->title }}
                @if ($userTest->completed_at)
                    — {{ $userTest->completed_at->format('M j, Y') }}
                @endif
            </span>
        </div>
    </div>

    {{-- Sticky sentinel — sits right below the breadcrumb --}}
    <div id="sd-tabs-sentinel" aria-hidden="true" style="height:1px;"></div>

    {{-- ══════════════════════════════════════════════
         STICKY TABS BAR
    ══════════════════════════════════════════════ --}}
    <div class="sd-tabs-bar" id="sd-tabs-bar">
        <div class="sd-tabs-bar-inner">
            <span class="sd-tabs-bar-title">{{ $userTest->test->title }}</span>
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

        {{-- Pre-compute all answer sets grouped by section --}}
        @php
            $totalQ = $stats['total']['questions'];
            $correct = $stats['total']['correct'];
            $wrong = $stats['total']['incorrect'];
            $omitted = $stats['total']['omitted'];

            $rwTotal = $stats['sections']['reading_and_writing']['total'];
            $rwCorrect = $stats['sections']['reading_and_writing']['correct'];
            $rwWrong = 0;
            $rwOmitted = 0;

            $mTotal = $stats['sections']['math']['total'];
            $mCorrect = $stats['sections']['math']['correct'];
            $mWrong = 0;
            $mOmitted = 0;

            // Collect answers split by section, preserving display index
            $allAnswers = [];
            $rwAnswers = [];
            $mathAnswers = [];
            $displayIdx = 0;

            foreach ($userTest->userAnswers as $answer) {
                if (!$answer->question || $answer->question->is_pretest) {
                    continue;
                }
                $displayIdx++;

                $isOmitted = $answer->selected_answer === null || $answer->selected_answer === '';
                $statusKey = $isOmitted ? 'omitted' : ($answer->is_correct ? 'correct' : 'wrong');
                $sectionType = $answer->question->section_type === 'math' ? 'math' : 'rw';
                $sectionName = $sectionType === 'math' ? 'Math' : 'Reading & Writing';
                $correctAnswer =
                    $answer->question->sprCorrectAnswers->pluck('answer')->implode(', ') ?:
                    $answer->question->answerChoices->where('is_correct', true)->first()?->label ?? 'N/A';

                $row = [
                    'idx' => $displayIdx,
                    'answer' => $answer,
                    'statusKey' => $statusKey,
                    'sectionType' => $sectionType,
                    'sectionName' => $sectionName,
                    'correctAnswer' => $correctAnswer,
                    'questionData' => [
                        'stem' => \Illuminate\Support\Str::markdown($answer->question->stem ?? '', ['html_input' => 'strip', 'allow_unsafe_links' => false]),
                        'explanation' => \Illuminate\Support\Str::markdown($answer->question->explanation?->explanation ?? 'No explanation available.', ['html_input' => 'strip', 'allow_unsafe_links' => false]),
                        'correct_answer' => $correctAnswer,
                        'your_answer' => $answer->selected_answer ?? 'Omitted',
                        'status' => $statusKey,
                        'question_type' => $answer->question->question_type,
                        'choices' => $answer->question->answerChoices->map(function($c) {
                            return [
                                'label' => $c->label,
                                'content' => \Illuminate\Support\Str::markdown($c->content ?? '', ['html_input' => 'strip', 'allow_unsafe_links' => false]),
                                'is_correct' => (bool)$c->is_correct
                            ];
                        })->toArray(),
                    ],
                ];

                $allAnswers[] = $row;
                if ($sectionType === 'rw') {
                    $rwAnswers[] = $row;
                    if ($statusKey === 'wrong') {
                        $rwWrong++;
                    }
                    if ($statusKey === 'omitted') {
                        $rwOmitted++;
                    }
                } else {
                    $mathAnswers[] = $row;
                    if ($statusKey === 'wrong') {
                        $mWrong++;
                    }
                    if ($statusKey === 'omitted') {
                        $mOmitted++;
                    }
                }
            }
        @endphp

        {{-- ── KNOWLEDGE & SKILLS HEADING ── --}}
        <h2 class="!font-bold">Knowledge &amp; Skills</h2>
        <p class="sd-section-sub">View your performance across the 8 content domains measured on the SAT.</p>

        {{-- ── DOMAIN GROUPS — rendered once, filtered by JS ── --}}
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
                            'tests.score-details-domain',
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
                            'tests.score-details-domain',
                            compact('domain', 'data', 'filled', 'barClass', 'badgeClass', 'perfLabel', 'secPct'))
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── QUESTION REVIEW ── --}}
        <h2 class="!font-bold">Question Review</h2>
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
        @include('tests.score-details-table', ['answers' => $allAnswers, 'tableId' => 'table-main'])

    </div>{{-- /sd-container --}}




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

                <div class="sd-modal-section-label js-mc-label" style="display:none;margin-top:1.25rem;">Answer Choices</div>
                <div class="sd-modal-choices-list js-mc-list" id="modalChoicesList" style="display:none;margin-bottom:1.5rem;"></div>

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

    <x-slot name="scripts">
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof window.initScoreDetailsPage === 'function') {
                    window.initScoreDetailsPage();
                }
            });
        </script>
    </x-slot>
</x-layouts.app>
