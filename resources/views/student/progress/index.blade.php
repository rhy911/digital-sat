<x-layouts.student :user="$user" title="Progress & Analytics" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/progress.css'])
    @endpush

    @php
        $scoredAttempts = $completedTests->whereNotNull('total_score')->values();
        $latestCompleted = $scoredAttempts->first();
        $latestScore = $latestCompleted?->total_score;
        $previousCompleted = $comparableScores->skip(1)->first();
        $previousScore = $previousCompleted?->total_score;
        $scoreDelta = ($latestScore !== null && $previousScore !== null) ? $latestScore - $previousScore : null;
        $scorePercent = $latestScore !== null ? max(0, min(100, round((($latestScore - 400) / 1200) * 100))) : 0;
        $hasLatestScore = $latestScore !== null;
        $bestScore = $comparableScores->max('total_score');
        $averageScore = $comparableScores->isNotEmpty() ? round($comparableScores->avg('total_score')) : null;
    @endphp

    <div class="app-shell app-shell--no-list">
        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="route('home')" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::student('progress')" />

        <!-- MAIN WORKSPACE -->
        <div class="shell-content progress-page-shell">
            <x-ui.flash />

            <div class="progress-container">
                <!-- Page Header Lead -->
                <div class="progress-head">
                    <div>
                        <h1 class="progress-head__title">Performance & Analytics</h1>
                        <p class="progress-head__desc">
                            Visual trajectory models, skill radar mastery, difficulty cross-matrix, and pacing habits.
                        </p>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <x-ui.button :href="route('student.progress.export-pdf', ['lang' => 'vi'])" variant="secondary" size="sm">
                            <x-slot:icon>
                                <x-ui.icon name="download" class="w-4 h-4" aria-hidden="true" />
                            </x-slot:icon>
                            Xuất PDF (VI)
                        </x-ui.button>
                        <x-ui.button :href="route('student.progress.export-pdf', ['lang' => 'en'])" variant="secondary" size="sm">
                            <x-slot:icon>
                                <x-ui.icon name="download" class="w-4 h-4" aria-hidden="true" />
                            </x-slot:icon>
                            Export PDF (EN)
                        </x-ui.button>
                        <x-ui.button :href="route('home.practice')" variant="primary" size="sm">
                            <x-slot:icon>
                                <x-ui.icon name="play" class="w-4 h-4" aria-hidden="true" />
                            </x-slot:icon>
                            Take Practice Test
                        </x-ui.button>
                    </div>
                </div>
            </div>

            <!-- Sticky Quick-Scroll Navigation -->
            <nav class="progress-nav-sticky" aria-label="Progress navigation">
                <div class="progress-nav-inner">
                    <a href="#section-scores" class="progress-nav-link is-active">
                        <x-ui.icon name="trending-up" class="w-4 h-4" aria-hidden="true" />
                        Scores & Trajectory
                    </a>
                    <a href="#section-domains" class="progress-nav-link">
                        <x-ui.icon name="pie-chart" class="w-4 h-4" aria-hidden="true" />
                        Domain Mastery
                    </a>
                    <a href="#section-difficulty" class="progress-nav-link">
                        <x-ui.icon name="layers" class="w-4 h-4" aria-hidden="true" />
                        Difficulty Matrix
                    </a>
                    <a href="#section-timing" class="progress-nav-link">
                        <x-ui.icon name="clock" class="w-4 h-4" aria-hidden="true" />
                        Pacing & Habits
                    </a>
                </div>
            </nav>

            <div class="progress-container space-y-10">
                <!-- ================= 1. SCORES & TRAJECTORY ================= -->
                <section id="section-scores" class="progress-section">
                    <div class="progress-section-header">
                        <h2 class="progress-section-title">
                            <span class="w-2.5 h-2.5 rounded-full bg-brand"></span>
                            Scores & Trajectory
                        </h2>
                    </div>

                    <div class="space-y-6">
                        @include('partials.performance.score-hero', compact('latestScore', 'scoreDelta', 'scorePercent', 'hasLatestScore', 'latestCompleted', 'user'))

                        <div class="p-stat-grid">
                            <div class="p-stat-card">
                                <div class="p-stat-card__val">{{ $completedTests->count() }}</div>
                                <div class="p-stat-card__label">Completed Tests</div>
                            </div>
                            <div class="p-stat-card">
                                <div class="p-stat-card__val">{{ $bestScore ?? '—' }}</div>
                                <div class="p-stat-card__label">Best Total Score</div>
                            </div>
                            <div class="p-stat-card">
                                <div class="p-stat-card__val">{{ $averageScore ?? '—' }}</div>
                                <div class="p-stat-card__label">Average Score</div>
                            </div>
                            <div class="p-stat-card">
                                <div class="p-stat-card__val">{{ $user->target_score ?? '—' }}</div>
                                <div class="p-stat-card__label">Target Score Goal</div>
                            </div>
                        </div>

                        @include('partials.performance.score-trend', [
                            'chartTrend' => $chartTrend,
                            'comparableScores' => $comparableScores,
                        ])
                    </div>
                </section>

                <!-- ================= 2. DOMAIN MASTERY & RADAR ================= -->
                <section id="section-domains" class="progress-section">
                    <div class="progress-section-header">
                        <h2 class="progress-section-title">
                            <span class="w-2.5 h-2.5 rounded-full bg-teal-600"></span>
                            Domain Mastery & Skills
                        </h2>
                    </div>

                    <div class="space-y-6">
                        @include('partials.performance.radar-cards')

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                            <div class="lg:col-span-5">
                                @include('partials.performance.doughnut-card', ['chartDoughnut' => $chartDoughnut])
                            </div>
                            <div class="lg:col-span-7">
                                @include('partials.performance.accuracy-bar-card', [
                                    'title' => 'Weak areas by domain',
                                    'subtitle' => 'Detailed accuracy ranking across content domains and sub-skills.',
                                    'rows' => $weakAreaSummaries,
                                    'emptyTitle' => 'No domain data yet',
                                    'emptyBody' => 'Complete a test to view granular domain accuracy.',
                                    'ariaLabelledby' => 'weak-areas-title',
                                    'rowLabelKey' => 'domain',
                                ])
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ================= 3. DIFFICULTY MATRIX ================= -->
                <section id="section-difficulty" class="progress-section">
                    <div class="progress-section-header">
                        <h2 class="progress-section-title">
                            <span class="w-2.5 h-2.5 rounded-full bg-orange-600"></span>
                            Difficulty Cross-Matrix
                        </h2>
                    </div>

                    <div class="space-y-6">
                        @include('partials.performance.heatmap-card', ['chartHeatmap' => $chartHeatmap])

                        @include('partials.performance.accuracy-bar-card', [
                            'title' => 'Difficulty breakdown',
                            'subtitle' => 'Overall accuracy percentage broken down by question difficulty tier.',
                            'rows' => $difficultyPerformanceSummaries,
                            'emptyTitle' => 'No difficulty data yet',
                            'emptyBody' => 'Complete a test to view difficulty tier accuracy.',
                            'ariaLabelledby' => 'difficulty-title',
                            'rowLabelKey' => 'label',
                        ])
                    </div>
                </section>

                <!-- ================= 4. PACING & HABITS ================= -->
                <section id="section-timing" class="progress-section">
                    <div class="progress-section-header">
                        <h2 class="progress-section-title">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                            Pacing, Timing & Habits
                        </h2>
                    </div>

                    <div class="space-y-6">
                        @include('partials.performance.time-histogram', ['chartHistogram' => $chartHistogram])

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @include('partials.performance.pacing-bar-card', ['rows' => $pacingSummaries])
                            @include('partials.performance.efficiency-card', ['timeMatrix' => $timeMatrix])
                        </div>

                        @include('partials.performance.habits-card', compact('skippedCount', 'stuckCount', 'rushedCount'))
                    </div>
                </section>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.PROGRESS_CHARTS_CONFIG = {
                trend: @json($chartTrend),
                radar: @json($chartRadar),
                doughnut: @json($chartDoughnut),
                histogram: @json($chartHistogram)
            };
        </script>
        @vite(['resources/js/student/progress-charts.js'])
    @endpush
</x-layouts.student>
