@props(['rows' => []])

<article class="p-card flex flex-col justify-between h-full" aria-labelledby="pacing-title">
    <div class="p-card-header">
        <div>
            <h3 id="pacing-title" class="p-card-title">Pacing by Difficulty</h3>
            <p class="p-card-subtitle">Average time spent per question vs. target pacing expected.</p>
        </div>
    </div>

    @if(count($rows))
        <div class="space-y-3.5">
            @foreach($rows as $row)
                @php
                    $avgSpent = $row['avgTimeSpent'];
                    $avgExpected = $row['avgExpectedTime'];
                    $pace = $row['pace'];
                    $pct = min(150, ($avgSpent / max(1, $avgExpected)) * 100);

                    $status = match($pace) {
                        'Fast' => 'warning',
                        'Slow' => 'danger',
                        default => 'success',
                    };
                @endphp
                <div class="rounded-xl border border-slate-200/80 p-3.5 bg-white">
                    <div class="flex items-center justify-between text-xs font-semibold text-slate-700 mb-2">
                        <span class="font-bold text-sm">{{ $row['label'] }} Tier</span>
                        <div class="flex items-center gap-2">
                            <x-ui.status-badge :status="$status">
                                {{ $pace }}
                            </x-ui.status-badge>
                            <span class="font-bold text-slate-800 tabular-nums">{{ $avgSpent }}s <span class="text-slate-400 font-normal">/ {{ $avgExpected }}s target</span></span>
                        </div>
                    </div>

                    <!-- Dual Track Visualization -->
                    <div class="relative h-2.5 rounded-full bg-slate-100 overflow-hidden" role="progressbar" aria-valuenow="{{ $avgSpent }}" aria-valuemin="0" aria-valuemax="{{ $avgExpected * 1.5 }}">
                        <!-- Actual bar -->
                        <div class="absolute inset-0 h-full rounded-full {{ $pace === 'Slow' ? 'bg-rose-500' : ($pace === 'Fast' ? 'bg-amber-400' : 'bg-emerald-500') }}"
                             style="transform: scaleX({{ min(1, $pct / 100) }}); transform-origin: left;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="p-empty-state">
            <x-ui.icon name="clock" class="w-10 h-10 text-slate-300 mb-2" aria-hidden="true" />
            <p class="font-semibold text-slate-700">No pacing data yet</p>
            <p class="text-xs text-slate-500 max-w-sm mt-1">Complete a timed practice test to assess your pacing speed.</p>
        </div>
    @endif
</article>
