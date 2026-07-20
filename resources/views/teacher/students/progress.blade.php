<x-layouts.student :user="auth()->user()" :title="$student->name . ' - Progress'" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/progress.css'])
    @endpush

    @php
        $userInitials = auth()->user()?->initials ?? 'U';

        $scoredAttempts = $completedTests->whereNotNull('total_score')->values();
        $latestCompleted = $scoredAttempts->first();
        $latestScoreAttempt = $latestCompleted;
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
        $hasLatestScore = $latestScore !== null;
        $bestScore = $comparableScores->max('total_score');
        $averageScore = $comparableScores->isNotEmpty() ? round($comparableScores->avg('total_score')) : null;
        $history = $comparableScores->take(5)->reverse()->values();
        $hasOlderScoreVersion = $scoredAttempts->count() > $comparableScores->count();
        $hasSingleHistory = $history->count() === 1;
        $baselineAttempt = $hasSingleHistory ? $history->first() : null;
        $reviewRoute = fn ($attempt) => $attempt->assignment_id
            ? route('teacher.assignments.students.show', [$attempt->assignment, $student])
            : null;
        $showBadges = (bool) $student->share_independent_practice;
        $classroomName = $classroom->name;
        $teacherName = $classroom->owner->name;
    @endphp

    <div class="app-shell" x-data="{ searchQuery: '', statusFilter: 'active' }">
        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="route('teacher.progress')" :avatar-label="$userInitials" :items="[
            [
                'route' => route('teacher.progress'),
                'label' => 'Progress',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M4 19V5M4 19h16M8 15l3-4 3 3 5-7\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>',
            ],
            [
                'route' => route('teacher.classes.index'),
                'label' => 'Classes',
                'active' => true,
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><rect x=\'3.5\' y=\'5\' width=\'17\' height=\'14\' rx=\'2\' /><path d=\'M3.5 9.5h17M8 5v-1M16 5v-1\' stroke-linecap=\'round\' /></svg>',
            ],
            [
                'route' => route('teacher.assignments.index'),
                'label' => 'Reports',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M5 3v18h16\' stroke-linecap=\'round\' /><rect x=\'8\' y=\'12\' width=\'3\' height=\'6\' /><rect x=\'13\' y=\'8\' width=\'3\' height=\'10\' /><rect x=\'18\' y=\'5\' width=\'3\' height=\'13\' /></svg>',
            ],
            [
                'route' => route('home.practice'),
                'label' => 'Test Library',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M12 6.5c-1.6-1.2-3.7-1.8-6-1.8-.7 0-1.4.05-2 .15v13.5c.6-.1 1.3-.15 2-.15 2.3 0 4.4.6 6 1.8m0-13.5c1.6-1.2 3.7-1.8 6-1.8.7 0 1.4.05 2 .15v13.5c-.6-.1-1.3-.15-2-.15-2.3 0-4.4.6-6 1.8m0-13.5v13.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>',
            ],
            [
                'route' => route('home-dashboard.index'),
                'label' => 'Test Builder',
                'target' => '_blank',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M14.7 6.3a3 3 0 0 0 4 4L14 15l-4 1 1-4Z\' stroke-linejoin=\'round\' /></svg>',
            ],
        ]" />

        <!-- COLUMN 2: CLASSROOM ROSTER -->
        <x-shell.sidebar-list title="{{ $classroom->name }}" count-text="{{ $rosterItems->count() }} students"
            search-placeholder="Search students..." :filters="[['value' => 'active', 'label' => 'Students']]" :items="$rosterItems
                ->map(
                    fn($membership) => [
                        'route' => route('teacher.classes.students.progress', [$classroom, $membership->student]),
                        'name' => $membership->student->name,
                        'status' => 'active',
                        'selected' => $membership->student->id === $student->id,
                        'meta' => [
                            'Joined ' . ($membership->decided_at ?? $membership->created_at)->format('M j, Y'),
                        ],
                    ],
                )
                ->all()">
        </x-shell.sidebar-list>

        <!-- COLUMN 3: STUDENT PROGRESS DETAIL -->
        <div class="ledger-pane">
            <x-ui.flash />

            <div class="binder-panel">
                <div class="ledger-header">
                    <div class="dh-left">
                        <h2>{{ $student->name }}</h2>
                        <div class="dh-desc">
                            Aggregated across every class you share with this student.
                        </div>
                    </div>
                </div>

                <x-shell.stat-row :stats="[
                    ['value' => $completedTests->count(), 'label' => 'Completed'],
                    ['value' => $bestScore ?? '—', 'label' => 'Best'],
                    ['value' => $averageScore ?? '—', 'label' => 'Average'],
                ]" />

                <div class="progress-cards" style="margin-top: 20px;">
                    @include('partials.performance.score-hero', compact('latestScore', 'scoreDelta', 'scorePercent', 'hasLatestScore', 'latestCompleted'))
                </div>

                <div class="progress-cards" style="margin-top: 20px;">
                    @include('partials.performance.score-trend', compact('history', 'hasSingleHistory', 'baselineAttempt', 'latestScore', 'scoreDelta', 'hasOlderScoreVersion', 'latestScoreAttempt', 'latestCompleted', 'reviewRoute', 'showBadges'))
                    @include('partials.performance.recommendations-card', compact('recommendations'))
                </div>

                <div class="progress-cards" style="margin-top: 20px;">
                    @include('partials.performance.accuracy-bar-card', [
                        'title' => 'Weak areas by domain',
                        'subtitle' => 'Accuracy across every visible completed test, weakest first.',
                        'rows' => $weakAreaSummaries,
                        'emptyTitle' => 'No domain data yet',
                        'emptyBody' => 'No completed tests are visible for this student yet.',
                        'ariaLabelledby' => 'weak-areas-title',
                        'rowLabelKey' => 'domain',
                    ])
                </div>

                <div class="progress-cards progress-cards--secondary" style="margin-top: 20px;">
                    @include('partials.performance.accuracy-bar-card', [
                        'title' => 'Difficulty breakdown',
                        'subtitle' => 'Accuracy by question difficulty.',
                        'rows' => $difficultyPerformanceSummaries,
                        'emptyTitle' => 'No difficulty data yet',
                        'emptyBody' => 'No completed tests are visible for this student yet.',
                        'ariaLabelledby' => 'difficulty-title',
                        'rowLabelKey' => 'label',
                    ])

                    @include('partials.performance.pacing-bar-card', ['rows' => $pacingSummaries])
                </div>

                <div class="progress-cards progress-cards--secondary" style="margin-top: 20px;">
                    @include('partials.performance.habits-card', compact('skippedCount', 'stuckCount', 'rushedCount'))
                    @include('partials.performance.efficiency-card', compact('timeMatrix'))
                </div>
            </div>

            <!-- COLUMN 3: CORKBOARD -->
            <x-shell.corkboard header="Digest">
                <!-- Best Score Note -->
                <div class="cork-note">
                    <h3>Personal Best</h3>
                    <p class="digest-line" style="font-family: 'IBM Plex Mono', monospace; font-size: 1.8rem; font-weight: 900; color: var(--ds-ink-strong); margin-top: 4px;">
                        {{ $bestScore ?? '—' }}
                    </p>
                    <p class="cork-empty mt-2">Highest estimated score achieved across all attempts.</p>
                </div>

                <!-- Classroom Note -->
                <div class="cork-note">
                    <h3>Study Scope</h3>
                    <p class="digest-line"><strong>{{ $classroomName }}</strong></p>
                    <p class="cork-empty mt-2">Instructor: {{ $teacherName }}</p>
                </div>

                <!-- Quick Tip Note -->
                <div class="cork-note">
                    <h3>Quick Tip</h3>
                    <p class="cork-empty">"Double-check your Math answers using the graphic calculator. It saves time and prevents calculation slips!"</p>
                </div>
            </x-shell.corkboard>
        </div>
    </div>
</x-layouts.student>
