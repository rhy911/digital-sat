<x-layouts.student :user="$user" header-type="progress" :cancel-route="route('home')">
    @push('styles')
        @if($user->role === 'teacher' && $user->isApprovedTeacher())
            @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
        @else
            @vite(['resources/css/student/analytics.css'])
        @endif
    @endpush

    @php
        $latestCompleted = $completedTests->first();
        $scoredAttempts = $completedTests->whereNotNull('total_score')->values();
        $latestScoreAttempt = $scoredAttempts->first();
        $conversionKey = fn ($attempt) => implode(':', [
            $attempt->score_estimate_kind ?? 'legacy',
            $attempt->score_conversion_version ?? 'legacy',
            $attempt->score_conversion_set_id ?? 'none',
            $attempt->test?->test_type ?? 'unknown',
        ]);
        $latestConversionKey = $latestScoreAttempt ? $conversionKey($latestScoreAttempt) : null;
        $comparableScores = $scoredAttempts->filter(fn ($attempt) => $conversionKey($attempt) === $latestConversionKey)->values();
        $previousCompleted = $comparableScores->skip(1)->first();
        $latestScore = $latestScoreAttempt?->total_score;
        $previousScore = $previousCompleted?->total_score;
        $scoreDelta = ($latestScore !== null && $previousScore !== null) ? $latestScore - $previousScore : null;
        $scorePercent = $latestScore !== null ? max(0, min(100, round((($latestScore - 400) / 1200) * 100))) : 0;
        $rwScore = $latestScoreAttempt?->score_reading_writing;
        $mathScore = $latestScoreAttempt?->score_math;
        $hasLatestScore = $latestScore !== null;
        $hasSectionScores = $rwScore !== null && $mathScore !== null;
        $bestScore = $comparableScores->max('total_score');
        $averageScore = $comparableScores->isNotEmpty()
            ? round($comparableScores->avg('total_score'))
            : null;
        $history = $comparableScores->take(5)->reverse()->values();
        $hasOlderScoreVersion = $scoredAttempts->count() > $comparableScores->count();
        $hasSingleHistory = $history->count() === 1;
        $baselineAttempt = $hasSingleHistory ? $history->first() : null;
        $primaryInProgress = $inProgressTests->first();
        $resumeModuleUlid = $primaryInProgress?->currentModule?->ulid
            ?? $primaryInProgress?->test?->sections?->first()?->modules?->first()?->ulid;
        $lowerSection = $hasSectionScores && $rwScore <= $mathScore ? 'Reading and Writing' : 'Math';
        $sectionGap = $hasSectionScores ? abs($rwScore - $mathScore) : null;
        $nextFocus = $hasSectionScores ? $lowerSection : ($latestCompleted ? 'Score report review' : 'Test preview');
        $displayName = $user->name ?? $user->username ?? 'student';
        $todayLabel = now()->format('l, M j');
        $formatSkillLabel = fn ($value) => $value ? \Illuminate\Support\Str::headline(str_replace(['_', '-'], ' ', $value)) : 'Unclassified';
        $canUseTeacherWorkspace = $user->role === 'teacher' && $user->isApprovedTeacher();
        $storedHomeTab = session('teacher_home.tab', 'progress');
        $initialHomeTab = $canUseTeacherWorkspace && in_array($storedHomeTab, ['classes', 'reports'], true) ? $storedHomeTab : 'progress';
        $sectionSummaries = collect([
            'reading_and_writing' => [
                'label' => 'Reading and Writing',
                'short' => 'R&W',
                'latest' => $rwScore,
                'average' => $comparableScores->whereNotNull('score_reading_writing')->isNotEmpty()
                    ? round($comparableScores->whereNotNull('score_reading_writing')->avg('score_reading_writing'))
                    : null,
            ],
            'math' => [
                'label' => 'Math',
                'short' => 'Math',
                'latest' => $mathScore,
                'average' => $comparableScores->whereNotNull('score_math')->isNotEmpty()
                    ? round($comparableScores->whereNotNull('score_math')->avg('score_math'))
                    : null,
            ],
        ])->map(function ($section, $key) use ($latestCompleted, $formatSkillLabel) {
            $answers = $latestCompleted?->userAnswers
                ? $latestCompleted->userAnswers->filter(function ($answer) use ($key) {
                    $question = $answer->question;
                    if (!$question || $question->is_pretest) {
                        return false;
                    }

                    return ($question->section_type === 'math' ? 'math' : 'reading_and_writing') === $key;
                })
                : collect();

            $total = $answers->count();
            $correct = $answers->where('is_correct', true)->count();
            $accuracy = $total > 0 ? round(($correct / $total) * 100) : null;

            $domains = $answers->groupBy(fn ($answer) => $answer->question->skill_domain ?: 'unclassified')
                ->map(function ($domainAnswers, $domainKey) use ($formatSkillLabel) {
                    $domainTotal = $domainAnswers->count();
                    $domainCorrect = $domainAnswers->where('is_correct', true)->count();

                    return [
                        'label' => $formatSkillLabel($domainKey),
                        'total' => $domainTotal,
                        'correct' => $domainCorrect,
                        'accuracy' => $domainTotal > 0 ? round(($domainCorrect / $domainTotal) * 100) : 0,
                        'subdomains' => $domainAnswers->groupBy(fn ($answer) => $answer->question->skill_subdomain ?: 'unclassified')
                            ->map(function ($subdomainAnswers, $subdomainKey) use ($formatSkillLabel) {
                                $subdomainTotal = $subdomainAnswers->count();
                                $subdomainCorrect = $subdomainAnswers->where('is_correct', true)->count();

                                return [
                                    'label' => $formatSkillLabel($subdomainKey),
                                    'total' => $subdomainTotal,
                                    'correct' => $subdomainCorrect,
                                    'accuracy' => $subdomainTotal > 0 ? round(($subdomainCorrect / $subdomainTotal) * 100) : 0,
                                ];
                            })->sortBy('label')->values(),
                    ];
                })->sortBy('label')->values();

            return array_merge($section, [
                'total' => $total,
                'correct' => $correct,
                'accuracy' => $accuracy,
                'domains' => $domains,
            ]);
        });
    @endphp

    <div x-data="{ tab: '{{ $initialHomeTab }}' }" @teacher-home-tab-requested.window="tab = $event.detail.tab" @teacher-home-tab-changed.window="tab = $event.detail.tab">
    <div x-show="tab === 'progress'">
    <div class="ds-home">
        <section class="ds-workspace-head" aria-labelledby="progress-title">
            <div class="ds-profile-panel">
                <div>
                    <span class="ds-page-kicker">{{ $todayLabel }}</span>
                    <h1 id="progress-title">{{ $displayName }}'s SAT workspace</h1>
                    <p class="ds-workspace-copy">
                        Your score reports, active practice, and next step are gathered here.
                    </p>
                </div>
            </div>

            <div class="ds-workspace-actions" aria-label="Primary progress actions">
                @if($latestCompleted)
                    <a href="{{ route('my-practice.score', $latestCompleted) }}" class="ds-button ds-button--primary">
                        Review latest score
                    </a>
                @else
                    <a href="{{ route('test.preview') }}" class="ds-button ds-button--primary">
                        Preview test format
                    </a>
                @endif

                @if($primaryInProgress && $resumeModuleUlid)
                    <a href="{{ route('engine.session', ['ulid' => $resumeModuleUlid]) }}?attempt={{ $primaryInProgress->ulid }}"
                        class="ds-button ds-button--secondary">
                        Resume practice
                    </a>
                @else
                    <a href="{{ route('home.practice') }}" class="ds-button ds-button--secondary">
                        Choose practice
                    </a>
                @endif
            </div>
        </section>

        <section class="ds-command-grid" aria-label="Personal practice command center">
            <article class="ds-card ds-next-card {{ $hasSectionScores ? 'is-ready' : 'is-pending' }}"
                aria-labelledby="focus-title">
                <span class="ds-card-label ds-card-label--accent">Today</span>
                <h2 id="focus-title">{{ $nextFocus }}</h2>
                @if($hasSectionScores)
                    <p>{{ $lowerSection }} is {{ $sectionGap }} points lower on your latest test. Review missed questions
                        there before your next full practice.</p>
                    <a href="{{ route('home.practice') }}" class="ds-button ds-button--secondary">Choose practice</a>
                @elseif($latestCompleted)
                    <p>Your latest score is ready. Review missed questions before choosing the next practice block.</p>
                    <a href="{{ route('my-practice', $latestCompleted) }}" class="ds-button ds-button--secondary">Open score
                        report</a>
                @else
                    <p>Take a quick look at the digital test interface first, then start your first full-length baseline.
                    </p>
                    <div class="ds-card-actions">
                        <a href="{{ route('test.preview') }}" class="ds-button ds-button--secondary">Open test preview</a>
                        <a href="{{ route('home.practice') }}" class="ds-link">Start baseline test</a>
                    </div>
                @endif
            </article>

            <article class="ds-card ds-hero-score {{ $hasLatestScore ? '' : 'ds-hero-score--empty' }}"
                aria-labelledby="latest-score-title">
                <div>
                    <span class="ds-card-label">Latest estimated practice score</span>
                    <h2 id="latest-score-title">{{ $latestScore ?? 'No baseline yet' }}</h2>
                    @if($latestCompleted)
                        <p>
                            Completed {{ optional($latestCompleted->completed_at)->format('M j, Y') ?? 'recently' }}.
                            @if($scoreDelta !== null)
                                {{ $scoreDelta >= 0 ? 'Up' : 'Down' }} {{ abs($scoreDelta) }} points from your previous
                                completed test.
                            @else
                                Complete another test to see score movement.
                            @endif
                        </p>
                    @else
                        <p>Finish one full-length practice test to unlock score movement and focus recommendations.</p>
                    @endif

                    @if($scoreDelta !== null)
                        <span class="ds-score-change {{ $scoreDelta >= 0 ? 'is-positive' : 'is-negative' }}"
                            aria-label="Score changed {{ $scoreDelta >= 0 ? 'up' : 'down' }} {{ abs($scoreDelta) }} points">
                            {{ $scoreDelta >= 0 ? '+' : '-' }}{{ abs($scoreDelta) }} since previous test
                        </span>
                    @elseif(!$hasLatestScore)
                        <span class="ds-score-change is-pending">Baseline needed</span>
                    @endif
                </div>

                @if($hasLatestScore)
                    <div class="ds-score-visual">
                        <div class="ds-score-ring" role="img" aria-label="Latest estimated practice score {{ $latestScore }} out of 1600"
                            style="--score-progress: {{ $scorePercent }}%">
                            <span>{{ $latestScore }}</span>
                        </div>
                        <span class="ds-score-range">400-1600</span>
                    </div>
                @else
                    <div class="ds-score-placeholder" aria-hidden="true">
                        <span>--</span>
                    </div>
                @endif
            </article>

            <article class="ds-card ds-section-card" aria-labelledby="section-split-title">
                <h3 id="section-split-title" class="ds-card-title">Section performance</h3>

                @if($hasSectionScores)
                    <div class="ds-section-overview" aria-label="Section averages and latest accuracy">
                        @foreach($sectionSummaries as $section)
                            @php
                                $scaledPercent = $section['latest'] !== null
                                    ? max(0, min(100, round((($section['latest'] - 200) / 600) * 100)))
                                    : 0;
                            @endphp
                            <div class="ds-section-metric">
                                <span>{{ $section['label'] }}</span>
                                <strong>{{ $section['average'] ?? '--' }}</strong>
                                <small>Avg score</small>
                                <div class="ds-meter" role="progressbar" aria-valuenow="{{ $scaledPercent }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $section['label'] }} latest scaled score percentage">
                                    <span style="width: {{ $scaledPercent }}%"></span>
                                </div>
                                <em>{{ $section['accuracy'] ?? '--' }}% correct latest</em>
                            </div>
                        @endforeach
                    </div>

                    <div class="ds-domain-breakdown">
                        @foreach($sectionSummaries as $section)
                            <details class="ds-domain-section" @if($section['short'] === $lowerSection || $section['label'] === $lowerSection) open @endif>
                                <summary>
                                    <span>{{ $section['label'] }}</span>
                                    <strong>{{ $section['accuracy'] ?? '--' }}%</strong>
                                </summary>

                                @forelse($section['domains'] as $domain)
                                    <details class="ds-domain-row">
                                        <summary>
                                            <span>{{ $domain['label'] }}</span>
                                            <strong>{{ $domain['accuracy'] }}%</strong>
                                            <small>{{ $domain['correct'] }}/{{ $domain['total'] }}</small>
                                        </summary>

                                        <div class="ds-subdomain-list">
                                            @foreach($domain['subdomains'] as $subdomain)
                                                <div class="ds-subdomain-row">
                                                    <span>{{ $subdomain['label'] }}</span>
                                                    <strong>{{ $subdomain['accuracy'] }}%</strong>
                                                    <small>{{ $subdomain['correct'] }}/{{ $subdomain['total'] }}</small>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @empty
                                    <p class="ds-card-note">No domain answers recorded for this section.</p>
                                @endforelse
                            </details>
                        @endforeach
                    </div>

                    <p class="ds-section-insight">{{ $lowerSection }} trails by {{ $sectionGap }} points. Expand domains to choose the next drill focus.</p>
                @else
                    <p class="ds-card-note">Section scores appear after your first completed score report.</p>
                @endif
            </article>

            <article class="ds-card ds-summary-card" aria-labelledby="summary-title">
                <h3 id="summary-title" class="ds-card-title">Practice summary</h3>
                <div class="ds-stat-grid">
                    <div class="ds-stat-tile">
                        <span>Completed</span>
                        <strong>{{ $completedTests->count() }}</strong>
                    </div>
                    <div class="ds-stat-tile">
                        <span>In progress</span>
                        <strong>{{ $inProgressTests->count() }}</strong>
                    </div>
                    <div class="ds-stat-tile">
                        <span>Best</span>
                        <strong>{{ $bestScore ?? '--' }}</strong>
                    </div>
                    <div class="ds-stat-tile">
                        <span>Average</span>
                        <strong>{{ $averageScore ?? '--' }}</strong>
                    </div>
                </div>
            </article>
        </section>

        <section class="ds-dashboard-grid">
            <article class="ds-card ds-trend-card" aria-labelledby="trend-title">
                <div class="ds-card__header">
                    <div>
                        <h3 id="trend-title" class="ds-card-title">Recent estimated score movement</h3>
                        @if($hasOlderScoreVersion)
                            <p class="text-sm text-slate-600">Trend uses {{ $latestScoreAttempt?->score_conversion_version ?? 'legacy' }} conversion only.</p>
                        @endif
                    </div>
                    @if($latestCompleted)
                        <a href="{{ route('my-practice.score', $latestCompleted) }}" class="ds-link">Open score report</a>
                    @endif
                </div>

                @if($history->isNotEmpty())
                    <div class="ds-trend-body {{ $hasSingleHistory ? 'is-single' : '' }}">
                        @if($hasSingleHistory && $baselineAttempt)
                            <div class="ds-baseline-card" aria-label="Baseline estimated practice score {{ $baselineAttempt->total_score }}">
                                <span>Baseline</span>
                                <strong>{{ $baselineAttempt->total_score }}</strong>
                                <small>{{ optional($baselineAttempt->completed_at)->format('M j, Y') ?? 'Completed' }}</small>
                            </div>
                        @endif

                        @unless($hasSingleHistory)
                            <div class="ds-trend-panel">
                                @php
                                    $trendCount = max(1, $history->count());
                                    $trendPoints = $history->values()->map(function ($attempt, $index) use ($trendCount) {
                                        $score = max(400, min(1600, (int) $attempt->total_score));
                                        $x = $trendCount === 1 ? 50 : round(($index / ($trendCount - 1)) * 100, 2);
                                        $y = round(100 - ((($score - 400) / 1200) * 100), 2);

                                        return "{$x},{$y}";
                                    })->implode(' ');
                                @endphp
                                <div class="ds-trend-chart"
                                    aria-label="Last {{ $history->count() }} completed test scores from 400 to 1600"
                                    style="--history-count: {{ $history->count() }}">
                                    <div class="ds-trend-chart__plot" aria-hidden="true">
                                        <span class="ds-trend-chart__axis is-top">1600</span>
                                        <span class="ds-trend-chart__axis is-bottom">400</span>
                                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" focusable="false">
                                            <polyline points="{{ $trendPoints }}" />
                                        </svg>

                                        @foreach($history as $attempt)
                                            @php
                                                $score = max(400, min(1600, (int) $attempt->total_score));
                                                $x = $trendCount === 1 ? 50 : round(($loop->index / ($trendCount - 1)) * 100, 2);
                                                $y = round(100 - ((($score - 400) / 1200) * 100), 2);
                                            @endphp
                                            <span class="ds-trend-chart__point" style="--x: {{ $x }}; --y: {{ $y }};"></span>
                                        @endforeach
                                    </div>

                                    <div class="ds-trend-chart__labels">
                                        @foreach($history as $attempt)
                                            <div class="ds-trend-chart__label"
                                                aria-label="Estimated practice score {{ $attempt->total_score }} on {{ optional($attempt->completed_at)->format('M j') ?? 'Done' }}">
                                                <strong>{{ $attempt->total_score }}</strong>
                                                <small>{{ optional($attempt->completed_at)->format('M j') ?? 'Done' }}</small>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endunless

                        <div class="ds-trend-insight">
                            <span>{{ $hasSingleHistory ? 'Baseline captured' : 'Latest movement' }}</span>
                            <strong>{{ $latestScore }}</strong>
                            @if($scoreDelta !== null)
                                <p>{{ $scoreDelta >= 0 ? 'Up' : 'Down' }} {{ abs($scoreDelta) }} points from the previous
                                    completed test.</p>
                            @else
                                <p>Complete one more full-length practice to turn this into a real movement trend.</p>
                            @endif
                        </div>
                    </div>

                    <ol class="ds-history-list" aria-label="Recent completed score reports">
                        @foreach($history->reverse()->values() as $attempt)
                            <li>
                                <span>{{ optional($attempt->completed_at)->format('M j') ?? 'Completed' }}</span>
                                <strong>{{ $attempt->total_score }}</strong>
                                <a href="{{ route('my-practice.score', $attempt) }}" class="ds-link">Review</a>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <div class="ds-empty">
                        <h4>No completed practice yet</h4>
                        <p>Finish a full-length practice test and your score trend will appear here.</p>
                        <a href="{{ route('home.practice') }}" class="ds-button ds-button--primary">Start first practice</a>
                    </div>
                @endif
            </article>
        </section>

        <section class="ds-dashboard-grid ds-dashboard-grid--secondary">
            <article class="ds-card" aria-labelledby="active-work-title">
                <div class="ds-card__header">
                    <div>
                        <h3 id="active-work-title" class="ds-card-title">Continue where you left off</h3>
                    </div>
                </div>

                @forelse($inProgressTests as $attempt)
                    @php
                        $moduleUlid = $attempt->currentModule?->ulid ?? $attempt->test?->sections?->first()?->modules?->first()?->ulid;
                    @endphp
                    <div class="ds-attempt-row">
                        <div>
                            <strong>{{ $attempt->test->title }}</strong>
                            <span>Updated {{ $attempt->updated_at->diffForHumans() }}</span>
                        </div>
                        @if($moduleUlid)
                            <a href="{{ route('engine.session', ['ulid' => $moduleUlid]) }}?attempt={{ $attempt->ulid }}"
                                class="ds-link">Resume</a>
                        @else
                            <span class="ds-muted">Unavailable</span>
                        @endif
                    </div>
                @empty
                    <div class="ds-empty ds-empty--compact">
                        <h4>No active practice</h4>
                        <p>Start a session when you are ready. Your in-progress work will stay here.</p>
                    </div>
                @endforelse
            </article>

            <article class="ds-card" aria-labelledby="completed-work-title">
                <div class="ds-card__header">
                    <div>
                        <h3 id="completed-work-title" class="ds-card-title">Review score reports</h3>
                    </div>
                    @if($completedTests->isNotEmpty())
                        <a href="{{ route('my-practice', $completedTests->first()) }}" class="ds-link">See all</a>
                    @endif
                </div>

                @forelse($completedTests->take(4) as $attempt)
                    <div class="ds-attempt-row">
                        <div>
                            <strong>{{ $attempt->test->title }}</strong>
                            <span>{{ optional($attempt->completed_at)->format('M j, Y') ?? 'Completed' }}</span>
                        </div>
                        <div class="ds-attempt-row__score">
                            <strong>{{ $attempt->total_score ?? '--' }}</strong>
                            <a href="{{ route('my-practice.score', $attempt) }}" class="ds-link">Review</a>
                        </div>
                    </div>
                @empty
                    <div class="ds-empty ds-empty--compact">
                        <h4>No results yet</h4>
                        <p>Completed tests will appear here with scores, section detail, and review links.</p>
                    </div>
                @endforelse
            </article>
        </section>
    </div>
    </div>

    @if($canUseTeacherWorkspace)
        <div x-show="tab === 'classes' || tab === 'reports'" x-cloak class="ds-teacher-workspace">
            <livewire:teacher.workspace />
        </div>
    @endif
    </div>
</x-layouts.student>
