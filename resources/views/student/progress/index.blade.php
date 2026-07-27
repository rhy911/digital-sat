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
        <x-shell.icon-rail :logo-href="route('home')" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::student('progress')" />

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
            <x-classroom.progress-digest :best-score="$bestScore" :classroom-name="$classroomName"
                :teacher-name="$teacherName" />
        </div>
    </div>
</x-layouts.student>
