<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Reading & Writing Radar -->
    <article class="p-card" aria-labelledby="radar-rw-title">
        <div class="p-card-header">
            <div>
                <x-ui.status-badge status="brand">Section 1 · Reading & Writing</x-ui.status-badge>
                <h3 id="radar-rw-title" class="p-card-title mt-2">Reading & Writing Mastery Profile</h3>
                <p class="p-card-subtitle">Relative strength across 4 foundational RW domains.</p>
            </div>
        </div>

        <div class="p-chart-container" style="height: 320px; position: relative;">
            <canvas id="chart-radar-rw"></canvas>
        </div>

        <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <span>Scale: 0% to 100% accuracy</span>
            <span class="font-semibold text-teal-700">Balanced coverage</span>
        </div>
    </article>

    <!-- Math Radar -->
    <article class="p-card" aria-labelledby="radar-math-title">
        <div class="p-card-header">
            <div>
                <x-ui.status-badge status="warning">Section 2 · Math</x-ui.status-badge>
                <h3 id="radar-math-title" class="p-card-title mt-2">Math Mastery Profile</h3>
                <p class="p-card-subtitle">Relative strength across 4 core Math domains.</p>
            </div>
        </div>

        <div class="p-chart-container" style="height: 320px; position: relative;">
            <canvas id="chart-radar-math"></canvas>
        </div>

        <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <span>Scale: 0% to 100% accuracy</span>
            <span class="font-semibold text-orange-700">Pinpoints focus areas</span>
        </div>
    </article>
</div>
