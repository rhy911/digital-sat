<x-layouts.student :user="$user" title="Progress" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/progress.css'])
    @endpush

    @php
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
        $reviewRoute = fn ($attempt) => route('student.scores.show', $attempt);
        $membership = $user->classroomMemberships()->with(['classroom.owner'])->first();
        $classroomName = $membership?->classroom?->name ?? 'Independent Study';
        $teacherName = $membership?->classroom?->owner?->name ?? 'System Tutor';
    @endphp

    <div class="app-shell app-shell--no-list" x-data="{ activeTab: 'overview' }">
        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="route('home')" :avatar-label="$user->initials" :items="[
            [
                'route' => route('home'),
                'label' => __('classroom.nav_dashboard'),
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\'/><polyline points=\'9 22 9 12 15 12 15 22\'/></svg>',
            ],
            [
                'route' => route('student.classes.index'),
                'label' => __('classroom.nav_my_classes'),
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><rect x=\'3.5\' y=\'5\' width=\'17\' height=\'14\' rx=\'2\' /><path d=\'M3.5 9.5h17M8 5v-1M16 5v-1\' stroke-linecap=\'round\' /></svg>',
            ],
            [
                'route' => route('student.assignments.index'),
                'label' => 'Assignments',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><rect x=\'3\' y=\'4\' width=\'18\' height=\'16\' rx=\'2\' /><path d=\'M7 8h10M7 12h10M7 16h6\' stroke-linecap=\'round\' /></svg>',
            ],
            [
                'route' => route('student.progress'),
                'label' => __('classroom.nav_progress'),
                'active' => true,
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M4 19V5M4 19h16M8 15l3-4 3 3 5-7\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>',
            ],
            [
                'route' => route('home.practice'),
                'label' => __('classroom.nav_practice'),
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M12 6.5c-1.6-1.2-3.7-1.8-6-1.8-.7 0-1.4.05-2 .15v13.5c.6-.1 1.3-.15 2-.15 2.3 0 4.4.6 6 1.8m0-13.5c1.6-1.2 3.7-1.8 6-1.8.7 0 1.4.05 2 .15v13.5c-.6-.1-1.3-.15-2-.15-2.3 0-4.4.6-6 1.8m0-13.5v13.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>',
            ],
            [
                'route' => route('student.scores.index'),
                'label' => __('classroom.nav_scores'),
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><line x1=\'18\' y1=\'20\' x2=\'18\' y2=\'10\'/><line x1=\'12\' y1=\'20\' x2=\'12\' y2=\'4\'/><line x1=\'6\' y1=\'20\' x2=\'6\' y2=\'14\'/></svg>',
            ],
        ]" />

        <!-- COLUMN 2: LEDGER PANE (main workspace) -->
        <div class="ledger-pane">
            <x-ui.flash />

            <div class="binder-panel">
                <div class="ledger-header">
                    <div class="dh-left">
                        <h2>{{ __('classroom.nav_progress') }} <span class="handwriting status-quote">"My Analytics"</span></h2>
                        <div class="dh-desc">
                            Track your learning milestones, estimated scores, and domain performance.
                        </div>
                    </div>
                </div>

                <!-- PINNED TAB BAR -->
                <x-shell.tab-bar :tabs="[
                    ['key' => 'overview', 'label' => 'Overview'],
                    ['key' => 'skills', 'label' => 'Domain Accuracy'],
                    ['key' => 'pacing', 'label' => 'Pacing & Difficulty'],
                ]" />

                <!-- OVERVIEW TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'overview' }">
                    <div class="progress-cards">
                        @include('partials.performance.score-hero', compact('latestScore', 'scoreDelta', 'scorePercent', 'hasLatestScore', 'latestCompleted'))
                    </div>

                    <div style="margin-top: 24px; margin-bottom: 24px;">
                        <x-shell.stat-row :stats="[
                            ['value' => $completedTests->count(), 'label' => 'Completed'],
                            ['value' => $bestScore ?? '—', 'label' => 'Best'],
                            ['value' => $averageScore ?? '—', 'label' => 'Average'],
                        ]" />
                    </div>

                    <div class="progress-cards">
                        @include('partials.performance.score-trend', compact('history', 'hasSingleHistory', 'baselineAttempt', 'latestScore', 'scoreDelta', 'hasOlderScoreVersion', 'latestScoreAttempt', 'latestCompleted', 'reviewRoute'))
                        @include('partials.performance.recommendations-card', compact('recommendations'))
                    </div>
                </div>

                <!-- DOMAIN PERFORMANCE TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'skills' }">
                    <div class="progress-cards">
                        @include('partials.performance.accuracy-bar-card', [
                            'title' => 'Weak areas by domain',
                            'subtitle' => 'Accuracy across every completed test, weakest first.',
                            'rows' => $weakAreaSummaries,
                            'emptyTitle' => 'No domain data yet',
                            'emptyBody' => 'Complete a practice test to see accuracy broken down by content domain.',
                            'ariaLabelledby' => 'weak-areas-title',
                            'rowLabelKey' => 'domain',
                        ])
                    </div>
                </div>

                <!-- PACING & DIFFICULTY TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'pacing' }">
                    <div class="progress-cards progress-cards--secondary">
                        @include('partials.performance.accuracy-bar-card', [
                            'title' => 'Difficulty breakdown',
                            'subtitle' => 'Accuracy by question difficulty, all completed tests.',
                            'rows' => $difficultyPerformanceSummaries,
                            'emptyTitle' => 'No difficulty data yet',
                            'emptyBody' => 'Complete a practice test to see accuracy by difficulty tier.',
                            'ariaLabelledby' => 'difficulty-title',
                            'rowLabelKey' => 'label',
                        ])

                        @include('partials.performance.pacing-bar-card', ['rows' => $pacingSummaries])
                    </div>

                    <div class="progress-cards progress-cards--secondary" style="margin-top: 24px;">
                        @include('partials.performance.habits-card', compact('skippedCount', 'stuckCount', 'rushedCount'))
                        @include('partials.performance.efficiency-card', compact('timeMatrix'))
                    </div>
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
