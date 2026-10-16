<div class="progress-container space-y-10">
    <section id="section-scores" class="progress-section space-y-6">
        <div class="progress-section-header">
            <h2 class="progress-section-title">
                <span class="w-2.5 h-2.5 rounded-full bg-brand"></span>
                Scores &amp; Trajectory
            </h2>
        </div>

        @if($teacherView)
            @include('partials.performance.score-hero', compact('latestScore', 'scoreDelta', 'scorePercent', 'hasLatestScore', 'latestCompleted', 'student'))
        @else
            @include('partials.performance.score-hero', compact('latestScore', 'scoreDelta', 'scorePercent', 'hasLatestScore', 'latestCompleted', 'user'))
        @endif

        <div class="p-stat-grid">
            <div class="p-stat-card">
                <div class="p-stat-card__val">{{ $completedTests->count() }}</div>
                <div class="p-stat-card__label">Completed Tests</div>
            </div>
            <div class="p-stat-card">
                <div class="p-stat-card__val">{{ $bestScore ?? '—' }}</div>
                <div class="p-stat-card__label">{{ $teacherView ? 'Best Score' : 'Best Total Score' }}</div>
            </div>
            <div class="p-stat-card">
                <div class="p-stat-card__val">{{ $averageScore ?? '—' }}</div>
                <div class="p-stat-card__label">Average Score</div>
            </div>
            <div class="p-stat-card">
                <div class="p-stat-card__val">{{ $progressStudent->target_score ?? '—' }}</div>
                <div class="p-stat-card__label">Target Score Goal</div>
            </div>
        </div>

        @include('partials.performance.score-trend', [
            'chartTrend' => $chartTrend,
            'comparableScores' => $comparableScores,
            'showBadges' => $showBadges,
            'reviewRoute' => $reviewRoute,
        ])
    </section>

    <section id="section-domains" class="progress-section space-y-6">
        <div class="progress-section-header">
            <h2 class="progress-section-title">
                <span class="w-2.5 h-2.5 rounded-full bg-teal-600"></span>
                Domain Mastery &amp; Skills
            </h2>
        </div>

        @include('partials.performance.radar-cards')

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <div class="lg:col-span-5">
                @include('partials.performance.doughnut-card', ['chartDoughnut' => $chartDoughnut])
            </div>
            <div class="lg:col-span-7">
                @include('partials.performance.accuracy-bar-card', [
                    'title' => $teacherView ? 'Domain Accuracy Breakdown' : 'Weak areas by domain',
                    'subtitle' => $teacherView ? 'Granular accuracy ranking across evaluated domains.' : 'Detailed accuracy ranking across content domains and sub-skills.',
                    'rows' => $weakAreaSummaries,
                    'emptyTitle' => 'No domain data yet',
                    'emptyBody' => $teacherView ? 'No completed tests visible for this student yet.' : 'Complete a test to view granular domain accuracy.',
                    'ariaLabelledby' => 'weak-areas-title',
                    'rowLabelKey' => 'domain',
                ])
            </div>
        </div>
    </section>

    <section id="section-difficulty" class="progress-section space-y-6">
        <div class="progress-section-header">
            <h2 class="progress-section-title">
                <span class="w-2.5 h-2.5 rounded-full bg-orange-600"></span>
                Difficulty Cross-Matrix
            </h2>
        </div>

        @include('partials.performance.heatmap-card', ['chartHeatmap' => $chartHeatmap])

        @unless($teacherView)
            @include('partials.performance.accuracy-bar-card', [
                'title' => 'Difficulty breakdown',
                'subtitle' => 'Overall accuracy percentage broken down by question difficulty tier.',
                'rows' => $difficultyPerformanceSummaries,
                'emptyTitle' => 'No difficulty data yet',
                'emptyBody' => 'Complete a test to view difficulty tier accuracy.',
                'ariaLabelledby' => 'difficulty-title',
                'rowLabelKey' => 'label',
            ])
        @endunless
    </section>

    <section id="section-timing" class="progress-section space-y-6">
        <div class="progress-section-header">
            <h2 class="progress-section-title">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                {{ $teacherView ? 'Pacing, Timing & Test Habits' : 'Pacing, Timing & Habits' }}
            </h2>
        </div>

        @include('partials.performance.time-histogram', ['chartHistogram' => $chartHistogram])

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @include('partials.performance.pacing-bar-card', ['rows' => $pacingSummaries])
            @include('partials.performance.efficiency-card', ['timeMatrix' => $timeMatrix])
        </div>

        @include('partials.performance.habits-card', compact('skippedCount', 'stuckCount', 'rushedCount'))
    </section>
</div>
