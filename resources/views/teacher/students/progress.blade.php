<x-layouts.student ui="product" body-class="antialiased teacher-workspace" :user="auth()->user()" :title="$student->name . ' - Student Analytics'" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/progress.css'])
    @endpush

    @php
        $userInitials = auth()->user()?->initials ?? 'U';

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
        $reviewRoute = fn ($attempt) => $attempt->assignment_id
            ? route('teacher.assignments.students.show', [$attempt->assignment, $student])
            : null;
        $showBadges = (bool) ($student->share_independent_practice ?? false);
        $progressStudent = $student;
        $teacherView = true;
    @endphp

    <div class="app-shell" x-data="{ searchQuery: '', statusFilter: 'all' }">
        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="route('teacher.progress')" :avatar-label="$userInitials"
            :items="\App\Support\NavRail::teacher('classes')" />

        <!-- COLUMN 2: CLASSROOM ROSTER -->
        <x-shell.sidebar-list title="{{ $classroom->name }}" count-text="{{ $rosterItems->count() }} students"
            search-placeholder="Search students..." :filters="[['value' => 'all', 'label' => 'Students']]" :items="$rosterItems
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
        <div class="shell-content progress-page-shell">
            <x-ui.flash />

            <div class="progress-container">
                <!-- Header -->
                <div class="progress-head">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="progress-head__title">{{ $student->name }}</h1>
                            <x-ui.status-badge status="brand">Student Diagnostic</x-ui.status-badge>
                        </div>
                        <p class="progress-head__desc">
                            Performance records for class: <span class="font-bold text-slate-700">{{ $classroom->name }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <x-ui.button :href="route('teacher.classes.students.progress.export-pdf', [$classroom, $student, 'lang' => 'vi'])" variant="secondary" size="sm">
                            <x-slot:icon>
                                <x-ui.icon name="download" class="w-4 h-4" aria-hidden="true" />
                            </x-slot:icon>
                            Xuất PDF (VI)
                        </x-ui.button>
                        <x-ui.button :href="route('teacher.classes.students.progress.export-pdf', [$classroom, $student, 'lang' => 'en'])" variant="secondary" size="sm">
                            <x-slot:icon>
                                <x-ui.icon name="download" class="w-4 h-4" aria-hidden="true" />
                            </x-slot:icon>
                            Export PDF (EN)
                        </x-ui.button>
                        <x-ui.button :href="route('teacher.classes.show', $classroom)" variant="secondary" size="sm">
                            <x-slot:icon>
                                <x-ui.icon name="arrow-left" class="w-4 h-4" aria-hidden="true" />
                            </x-slot:icon>
                            Back to Class
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
