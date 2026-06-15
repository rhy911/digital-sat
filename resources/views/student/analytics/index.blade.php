<x-layouts.student :user="$user" header-type="progress" :cancel-route="route('home')">
    @push('styles')
        @vite(['resources/css/student/analytics.css'])
    @endpush

    @php
        $latestCompleted = $completedTests->first();
        $previousCompleted = $completedTests->skip(1)->first();
        $latestScore = $latestCompleted?->total_score;
        $previousScore = $previousCompleted?->total_score;
        $scoreDelta = ($latestScore !== null && $previousScore !== null) ? $latestScore - $previousScore : null;
        $scorePercent = $latestScore !== null ? max(0, min(100, round((($latestScore - 400) / 1200) * 100))) : 0;
        $rwScore = $latestCompleted?->score_reading_writing;
        $mathScore = $latestCompleted?->score_math;
        $hasLatestScore = $latestScore !== null;
        $hasSectionScores = $rwScore !== null && $mathScore !== null;
        $bestScore = $completedTests->max('total_score');
        $averageScore = $completedTests->whereNotNull('total_score')->isNotEmpty()
            ? round($completedTests->whereNotNull('total_score')->avg('total_score'))
            : null;
        $history = $completedTests->whereNotNull('total_score')->take(5)->reverse()->values();
        $hasSingleHistory = $history->count() === 1;
        $baselineAttempt = $hasSingleHistory ? $history->first() : null;
        $primaryInProgress = $inProgressTests->first();
        $resumeModuleUlid = $primaryInProgress?->currentModule?->ulid
            ?? $primaryInProgress?->test?->sections?->first()?->modules?->first()?->ulid;
        $lowerSection = $hasSectionScores && $rwScore <= $mathScore ? 'Reading and Writing' : 'Math';
        $sectionGap = $hasSectionScores ? abs($rwScore - $mathScore) : null;
        $nextFocus = $hasSectionScores ? $lowerSection : ($latestCompleted ? 'Score report review' : 'Test preview');
        $displayName = $user->username ?? 'student';
        $todayLabel = now()->format('l, M j');
    @endphp

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
                    <a href="{{ route('engine.session', ['ulid' => $resumeModuleUlid]) }}?attempt={{ $primaryInProgress->ulid }}" class="ds-button ds-button--secondary">
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
            <article class="ds-card ds-next-card {{ $hasSectionScores ? 'is-ready' : 'is-pending' }}" aria-labelledby="focus-title">
                <span class="ds-card-label ds-card-label--accent">Today</span>
                <h2 id="focus-title">{{ $nextFocus }}</h2>
                @if($hasSectionScores)
                    <p>{{ $lowerSection }} is {{ $sectionGap }} points lower on your latest test. Review missed questions there before your next full practice.</p>
                    <a href="{{ route('home.practice') }}" class="ds-button ds-button--secondary">Choose practice</a>
                @elseif($latestCompleted)
                    <p>Your latest score is ready. Review missed questions before choosing the next practice block.</p>
                    <a href="{{ route('my-practice.score', $latestCompleted) }}" class="ds-button ds-button--secondary">Open score report</a>
                @else
                    <p>Take a quick look at the digital test interface first, then start your first full-length baseline.</p>
                    <div class="ds-card-actions">
                        <a href="{{ route('test.preview') }}" class="ds-button ds-button--secondary">Open test preview</a>
                        <a href="{{ route('home.practice') }}" class="ds-link">Start baseline test</a>
                    </div>
                @endif
            </article>

            <article class="ds-card ds-hero-score {{ $hasLatestScore ? '' : 'ds-hero-score--empty' }}" aria-labelledby="latest-score-title">
                <div>
                    <span class="ds-card-label">Latest score report</span>
                    <h2 id="latest-score-title">{{ $latestScore ?? 'No baseline yet' }}</h2>
                    @if($latestCompleted)
                        <p>
                            Completed {{ optional($latestCompleted->completed_at)->format('M j, Y') ?? 'recently' }}.
                            @if($scoreDelta !== null)
                                {{ $scoreDelta >= 0 ? 'Up' : 'Down' }} {{ abs($scoreDelta) }} points from your previous completed test.
                            @else
                                Complete another test to see score movement.
                            @endif
                        </p>
                    @else
                        <p>Finish one full-length practice test to unlock score movement and focus recommendations.</p>
                    @endif

                    @if($scoreDelta !== null)
                        <span class="ds-score-change {{ $scoreDelta >= 0 ? 'is-positive' : 'is-negative' }}" aria-label="Score changed {{ $scoreDelta >= 0 ? 'up' : 'down' }} {{ abs($scoreDelta) }} points">
                            {{ $scoreDelta >= 0 ? '+' : '-' }}{{ abs($scoreDelta) }} since previous test
                        </span>
                    @elseif(! $hasLatestScore)
                        <span class="ds-score-change is-pending">Baseline needed</span>
                    @endif
                </div>

                @if($hasLatestScore)
                    <div class="ds-score-visual">
                        <div class="ds-score-ring" role="img" aria-label="Latest SAT score {{ $latestScore }} out of 1600" style="--score-progress: {{ $scorePercent }}%">
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
                <h3 id="section-split-title" class="ds-card-title">Section split</h3>
                <div class="ds-section-bars">
                    <div class="ds-section-row">
                        <div class="ds-section-score">
                            <span>Reading and Writing</span>
                            <strong>{{ $rwScore ?? '--' }}</strong>
                        </div>
                        <div class="ds-meter-container">
                            <div class="ds-meter" @if($rwScore !== null) role="progressbar" aria-valuenow="{{ $rwScore }}" aria-valuemin="200" aria-valuemax="800" @endif aria-label="{{ $rwScore !== null ? 'Reading and Writing score meter' : 'Reading and Writing score unavailable' }}">
                                <span style="width: {{ $rwScore ? max(0, min(100, round((($rwScore - 200) / 600) * 100))) : 0 }}%"></span>
                            </div>
                        </div>
                    </div>

                    <div class="ds-section-row">
                        <div class="ds-section-score">
                            <span>Math</span>
                            <strong>{{ $mathScore ?? '--' }}</strong>
                        </div>
                        <div class="ds-meter-container">
                            <div class="ds-meter" @if($mathScore !== null) role="progressbar" aria-valuenow="{{ $mathScore }}" aria-valuemin="200" aria-valuemax="800" @endif aria-label="{{ $mathScore !== null ? 'Math score meter' : 'Math score unavailable' }}">
                                <span style="width: {{ $mathScore ? max(0, min(100, round((($mathScore - 200) / 600) * 100))) : 0 }}%"></span>
                            </div>
                        </div>
                    </div>
                </div>

                @if($hasSectionScores)
                    <p class="ds-section-insight">{{ $lowerSection }} trails by {{ $sectionGap }} points. Use the next practice block to tighten that section first.</p>
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
                        <h3 id="trend-title" class="ds-card-title">Recent score movement</h3>
                    </div>
                    @if($latestCompleted)
                        <a href="{{ route('my-practice.score', $latestCompleted) }}" class="ds-link">Open score report</a>
                    @endif
                </div>

                @if($history->isNotEmpty())
                    <div class="ds-trend-body {{ $hasSingleHistory ? 'is-single' : '' }}">
                        @if($hasSingleHistory && $baselineAttempt)
                            <div class="ds-baseline-card" aria-label="Baseline SAT score {{ $baselineAttempt->total_score }}">
                                <span>Baseline</span>
                                <strong>{{ $baselineAttempt->total_score }}</strong>
                                <small>{{ optional($baselineAttempt->completed_at)->format('M j, Y') ?? 'Completed' }}</small>
                            </div>
                        @endif

                        @unless($hasSingleHistory)
                            <div class="ds-trend-panel">
                                <div class="ds-trend-bars" aria-label="Last {{ $history->count() }} completed test scores" style="--history-count: {{ $history->count() }}">
                                    @foreach($history as $attempt)
                                        @php
                                            $height = max(12, min(100, round((($attempt->total_score - 400) / 1200) * 100)));
                                        @endphp
                                        <div class="ds-trend-bars__item" aria-label="Test score {{ $attempt->total_score }} on {{ optional($attempt->completed_at)->format('M j') ?? 'Done' }}">
                                            <span style="height: {{ $height }}%"></span>
                                            <strong>{{ $attempt->total_score }}</strong>
                                            <small>{{ optional($attempt->completed_at)->format('M j') ?? 'Done' }}</small>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endunless

                        <div class="ds-trend-insight">
                            <span>{{ $hasSingleHistory ? 'Baseline captured' : 'Latest movement' }}</span>
                            <strong>{{ $latestScore }}</strong>
                            @if($scoreDelta !== null)
                                <p>{{ $scoreDelta >= 0 ? 'Up' : 'Down' }} {{ abs($scoreDelta) }} points from the previous completed test.</p>
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
                            <a href="{{ route('engine.session', ['ulid' => $moduleUlid]) }}?attempt={{ $attempt->ulid }}" class="ds-link">Resume</a>
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
</x-layouts.student>
