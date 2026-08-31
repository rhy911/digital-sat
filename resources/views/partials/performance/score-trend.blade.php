@php
    $hasHistory = !empty($chartTrend['labels']) && count($chartTrend['labels']) > 0;
    $showBadges = $showBadges ?? false;
    $reviewRoute = $reviewRoute ?? fn ($attempt) => route('student.scores.show', $attempt);
@endphp

<article class="p-card" aria-labelledby="trend-title" x-data="{
    filter: 'last_10',
    onFilterChange() {
        if (window.ProgressCharts && window.PROGRESS_CHARTS_CONFIG) {
            window.ProgressCharts.updateTrendRange(
                window.PROGRESS_CHARTS_CONFIG.trend,
                this.filter,
                window.PROGRESS_CHARTS_CONFIG.trend.targetScore
            );
        }
    }
}">
    <div class="p-card-header">
        <div>
            <h3 id="trend-title" class="p-card-title">Score Trajectory & Subject Trends</h3>
            <p class="p-card-subtitle">Track your Total (400–1600), Reading & Writing (200–800), and Math (200–800) progression.</p>
        </div>

        @if($hasHistory)
            <div class="flex items-center gap-3">
                <label for="trend-range-select" class="sr-only">Time range</label>
                <select id="trend-range-filter" class="p-select text-xs" onchange="ProgressCharts.updateTrendRange(window.PROGRESS_CHARTS_CONFIG.trend, this.value, {{ $chartTrend['targetScore'] ?? 'null' }})">
                    <option value="all" selected>All attempts (up to 30)</option>
                    <option value="last_10">Last 10 attempts</option>
                    <option value="last_5">Last 5 attempts</option>
                    <option value="this_month">This month</option>
                </select>
            </div>
        @endif
    </div>

    @if($hasHistory)
        @if(count($chartTrend['labels']) === 1)
            <div class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-lg bg-blue-50/80 border border-blue-100 text-xs text-blue-900 mb-3">
                <x-ui.icon name="info" class="w-4 h-4 text-brand shrink-0" aria-hidden="true" />
                <span><strong>Baseline Assessment:</strong> Plotted at {{ $chartTrend['total'][0] ?? '—' }} pts. Complete future tests to view your progression slope and trajectory line.</span>
            </div>
        @endif

        <div class="p-chart-container" style="height: 320px; position: relative;">
            <canvas id="chart-score-trend"></canvas>
        </div>

        <div class="p-chart-legend mt-3">
            <div class="flex flex-wrap items-center justify-between gap-4 text-xs text-slate-500 pt-3 border-t border-slate-100">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                        <span class="w-3 h-3 rounded-full bg-[var(--color-brand,#3A52EE)]"></span> Total Score
                    </span>
                    <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                        <span class="w-3 h-3 rounded-full bg-teal-600"></span> Reading & Writing
                    </span>
                    <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                        <span class="w-3 h-3 rounded-full bg-orange-600"></span> Math
                    </span>
                    @if(!empty($chartTrend['targetScore']))
                        <span class="inline-flex items-center gap-1.5 font-medium text-rose-600">
                            <span class="w-3 h-0.5 bg-rose-500 border-t border-dashed border-rose-500"></span> Target Goal
                        </span>
                    @endif
                </div>
                <div class="text-slate-400 text-[11px]">
                    Interactive: hover any point to view test details
                </div>
            </div>
        </div>

        @if(!empty($comparableScores) && $comparableScores->isNotEmpty())
            <!-- Recent Attempts History List -->
            <div class="mt-4 pt-4 border-t border-slate-100">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2.5">Recent Scored Attempts</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                    @foreach($comparableScores->take(6) as $attempt)
                        @php($route = ($reviewRoute)($attempt))
                        <div class="flex items-center justify-between p-3 rounded-lg border border-slate-200/80 bg-slate-50/70 hover:bg-white hover:border-slate-300 transition-colors text-xs">
                            <div>
                                <div class="flex items-baseline gap-1.5">
                                    <span class="font-extrabold text-slate-900 text-sm tabular-nums">{{ $attempt->total_score ?? '—' }}</span>
                                    <span class="text-slate-400 text-[10px] uppercase font-bold">pts</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-[11px] text-slate-500 mt-0.5">
                                    <span class="font-semibold text-teal-700">RW {{ $attempt->score_reading_writing ?? '—' }}</span>
                                    <span>·</span>
                                    <span class="font-semibold text-orange-700">M {{ $attempt->score_math ?? '—' }}</span>
                                </div>
                                <span class="text-slate-400 block text-[10px] mt-0.5">{{ optional($attempt->completed_at)->format('M j, Y') }}</span>
                            </div>
                            <div class="flex flex-col items-end gap-1.5">
                                @if($showBadges)
                                    <x-ui.status-badge :status="$attempt->assignment_id ? 'brand' : 'warning'" class="!text-[10px] !py-0">
                                        {{ $attempt->assignment_id ? 'Assignment' : 'Shared practice' }}
                                    </x-ui.status-badge>
                                @endif
                                @if($route)
                                    <a href="{{ $route }}" class="inline-flex items-center gap-0.5 text-brand font-bold hover:text-brand-hover hover:underline text-xs mt-1">
                                        Review
                                        <x-ui.icon name="chevron-right" class="w-3 h-3" aria-hidden="true" />
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        <div class="p-empty-state">
            <x-ui.icon name="trending-up" class="w-10 h-10 text-slate-300 mb-2" aria-hidden="true" />
            <p class="font-semibold text-slate-700">No score history available yet</p>
            <p class="text-xs text-slate-500 max-w-sm mt-1">Complete your first test to plot trajectory trends across your practice sessions.</p>
        </div>
    @endif
</article>
