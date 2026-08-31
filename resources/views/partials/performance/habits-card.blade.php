@props([
    'skippedCount' => 0,
    'stuckCount' => 0,
    'rushedCount' => 0,
])

<article class="p-card" aria-labelledby="habits-title">
    <div class="p-card-header">
        <div>
            <h3 id="habits-title" class="p-card-title">Test-Taking Habits</h3>
            <p class="p-card-subtitle">Behavioral response analysis across all evaluated questions.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
        <!-- Skipped -->
        <div class="rounded-xl border border-slate-200/80 p-4 bg-slate-50/60 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Skipped Items</span>
                    <x-ui.icon name="flag" class="w-4 h-4 text-slate-400" aria-hidden="true" />
                </div>
                <div class="text-2xl font-extrabold text-slate-900 mt-2 tabular-nums">{{ $skippedCount }}</div>
            </div>
            <p class="text-[11px] text-slate-500 mt-3 leading-relaxed">Blank answers receive zero points. Always guess before time expires.</p>
        </div>

        <!-- Stuck -->
        <div class="rounded-xl border border-amber-200/70 p-4 bg-amber-50/40 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Stuck Questions</span>
                    <x-ui.icon name="alert-triangle" class="w-4 h-4 text-amber-600" aria-hidden="true" />
                </div>
                <div class="text-2xl font-extrabold text-amber-700 mt-2 tabular-nums">{{ $stuckCount }}</div>
            </div>
            <p class="text-[11px] text-slate-500 mt-3 leading-relaxed">&gt; 1.5x expected time and incorrect. Flag early to conserve stamina.</p>
        </div>

        <!-- Rushed -->
        <div class="rounded-xl border border-emerald-200/70 p-4 bg-emerald-50/40 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Fast Correct</span>
                    <x-ui.icon name="zap" class="w-4 h-4 text-emerald-600" aria-hidden="true" />
                </div>
                <div class="text-2xl font-extrabold text-emerald-700 mt-2 tabular-nums">{{ $rushedCount }}</div>
            </div>
            <p class="text-[11px] text-slate-500 mt-3 leading-relaxed">&lt; 0.4x expected time. Demonstrates high mastery and confidence.</p>
        </div>
    </div>
</article>
