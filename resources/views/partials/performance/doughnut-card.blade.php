@php
    $counts = $chartDoughnut['counts'] ?? [
        'rw' => ['correct' => 0, 'total' => 0, 'pct' => 0],
        'math' => ['correct' => 0, 'total' => 0, 'pct' => 0],
        'overall' => ['correct' => 0, 'total' => 0, 'pct' => 0],
    ];
@endphp

<article class="p-card" aria-labelledby="doughnut-split-title">
    <div class="p-card-header">
        <div>
            <h3 id="doughnut-split-title" class="p-card-title">Subject Contribution & Balance</h3>
            <p class="p-card-subtitle">Accuracy comparison between Reading & Writing and Math.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-12 gap-6 items-center">
        <!-- Doughnut Canvas -->
        <div class="sm:col-span-5 flex items-center justify-center relative">
            <div style="width: 180px; height: 180px; position: relative;">
                <canvas id="chart-doughnut-split"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center">
                    <span class="text-2xl font-black text-slate-800 tabular-nums">{{ $counts['overall']['pct'] }}%</span>
                    <span class="text-[10px] uppercase font-bold text-slate-400">Total Acc</span>
                </div>
            </div>
        </div>

        <!-- Section Metrics Breakdown -->
        <div class="sm:col-span-7 space-y-4">
            <div class="p-split-stat">
                <div class="flex items-center justify-between text-sm mb-1.5">
                    <span class="font-semibold text-teal-700 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-teal-600"></span> Reading & Writing
                    </span>
                    <span class="font-bold text-slate-800 tabular-nums">{{ $counts['rw']['pct'] }}% <span class="text-xs text-slate-400 font-normal">({{ $counts['rw']['correct'] }}/{{ $counts['rw']['total'] }})</span></span>
                </div>
                <div class="p-mini-bar" role="progressbar" aria-valuenow="{{ $counts['rw']['pct'] }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="p-mini-bar__fill bg-teal-600" style="transform: scaleX({{ $counts['rw']['pct'] / 100 }});"></div>
                </div>
            </div>

            <div class="p-split-stat">
                <div class="flex items-center justify-between text-sm mb-1.5">
                    <span class="font-semibold text-orange-700 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span> Math
                    </span>
                    <span class="font-bold text-slate-800 tabular-nums">{{ $counts['math']['pct'] }}% <span class="text-xs text-slate-400 font-normal">({{ $counts['math']['correct'] }}/{{ $counts['math']['total'] }})</span></span>
                </div>
                <div class="p-mini-bar" role="progressbar" aria-valuenow="{{ $counts['math']['pct'] }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="p-mini-bar__fill bg-orange-500" style="transform: scaleX({{ $counts['math']['pct'] / 100 }});"></div>
                </div>
            </div>

            <div class="pt-2 text-xs text-slate-500 border-t border-slate-100 flex items-center justify-between">
                <span>Total evaluated questions:</span>
                <span class="font-bold text-slate-700 tabular-nums">{{ $counts['overall']['total'] }} questions</span>
            </div>
        </div>
    </div>
</article>
