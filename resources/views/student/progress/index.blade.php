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
        $progressStudent = $user;
        $teacherView = false;
        $showBadges = false;
        $reviewRoute = fn ($attempt) => route('student.scores.show', $attempt);
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

            @include('partials.performance.progress-sections')
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
