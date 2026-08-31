@php
    $totalQuestions = $chartHistogram['totalQuestions'] ?? 0;
@endphp

<article class="p-card" aria-labelledby="time-dist-title">
    <div class="p-card-header">
        <div>
            <h3 id="time-dist-title" class="p-card-title">Time Spent Distribution</h3>
            <p class="p-card-subtitle">Number of questions grouped by seconds spent, separated by answer correctness.</p>
        </div>
        <div class="flex items-center gap-3 text-xs text-slate-600">
            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Correct</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Incorrect</span>
        </div>
    </div>

    @if($totalQuestions > 0)
        <div class="p-chart-container" style="height: 280px; position: relative;">
            <canvas id="chart-time-histogram"></canvas>
        </div>

        <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <span>Official target pace: 40–70s per question</span>
            <span class="font-semibold text-slate-700">Evaluated across {{ $totalQuestions }} responses</span>
        </div>
    @else
        <div class="p-empty-state">
            <x-ui.icon name="clock" class="w-10 h-10 text-slate-300 mb-2" aria-hidden="true" />
            <p class="font-semibold text-slate-700">No timing data recorded</p>
            <p class="text-xs text-slate-500 max-w-sm mt-1">Complete a timed practice test to unlock your question pacing distribution histogram.</p>
        </div>
    @endif
</article>
