@props(['timeMatrix' => []])

<article class="p-card flex flex-col justify-between h-full" aria-labelledby="efficiency-title">
    <div class="p-card-header">
        <div>
            <h3 id="efficiency-title" class="p-card-title">Time Efficiency Breakdown</h3>
            <p class="p-card-subtitle">Average seconds on correct vs. incorrect answers.</p>
        </div>
    </div>

    <div class="space-y-4">
        <!-- Reading & Writing -->
        @php
            $rwCorrect = $timeMatrix['reading_and_writing']['correctAvg'] ?? 0;
            $rwIncorrect = $timeMatrix['reading_and_writing']['incorrectAvg'] ?? 0;
            $rwMax = max(90, max($rwCorrect, $rwIncorrect));
        @endphp
        <div class="rounded-xl border border-slate-200/80 p-3.5 bg-white">
            <h4 class="font-bold text-xs uppercase tracking-wider text-teal-700 mb-2.5">Reading & Writing</h4>
            <div class="space-y-2">
                <div class="flex items-center gap-3 text-xs">
                    <span class="w-16 font-semibold text-slate-600">Correct</span>
                    <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden relative">
                        <div class="absolute inset-0 h-full rounded-full bg-emerald-500" style="transform: scaleX({{ min(1, $rwCorrect / $rwMax) }}); transform-origin: left;"></div>
                    </div>
                    <span class="w-12 text-right font-bold text-slate-800 tabular-nums">{{ $rwCorrect }}s</span>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <span class="w-16 font-semibold text-slate-600">Incorrect</span>
                    <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden relative">
                        <div class="absolute inset-0 h-full rounded-full bg-rose-500" style="transform: scaleX({{ min(1, $rwIncorrect / $rwMax) }}); transform-origin: left;"></div>
                    </div>
                    <span class="w-12 text-right font-bold text-slate-800 tabular-nums">{{ $rwIncorrect }}s</span>
                </div>
            </div>
        </div>

        <!-- Math -->
        @php
            $mCorrect = $timeMatrix['math']['correctAvg'] ?? 0;
            $mIncorrect = $timeMatrix['math']['incorrectAvg'] ?? 0;
            $mMax = max(90, max($mCorrect, $mIncorrect));
        @endphp
        <div class="rounded-xl border border-slate-200/80 p-3.5 bg-white">
            <h4 class="font-bold text-xs uppercase tracking-wider text-orange-700 mb-2.5">Math</h4>
            <div class="space-y-2">
                <div class="flex items-center gap-3 text-xs">
                    <span class="w-16 font-semibold text-slate-600">Correct</span>
                    <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden relative">
                        <div class="absolute inset-0 h-full rounded-full bg-emerald-500" style="transform: scaleX({{ min(1, $mCorrect / $mMax) }}); transform-origin: left;"></div>
                    </div>
                    <span class="w-12 text-right font-bold text-slate-800 tabular-nums">{{ $mCorrect }}s</span>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <span class="w-16 font-semibold text-slate-600">Incorrect</span>
                    <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden relative">
                        <div class="absolute inset-0 h-full rounded-full bg-rose-500" style="transform: scaleX({{ min(1, $mIncorrect / $mMax) }}); transform-origin: left;"></div>
                    </div>
                    <span class="w-12 text-right font-bold text-slate-800 tabular-nums">{{ $mIncorrect }}s</span>
                </div>
            </div>
        </div>
    </div>
</article>
