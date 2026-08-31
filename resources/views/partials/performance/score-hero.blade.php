@php
    $rwScore = $latestCompleted?->score_reading_writing;
    $mathScore = $latestCompleted?->score_math;
    $targetScore = ($student ?? $user)?->target_score;
    $isStudentView = !isset($student);
    $goalProgressPct = ($targetScore && $latestScore) ? min(100, (int) round(($latestScore / $targetScore) * 100)) : null;
@endphp

<article class="p-card p-card--hero" aria-labelledby="latest-score-title">
    <div class="p-card--hero__main">
        <div class="p-card-header">
            <div>
                <x-ui.status-badge status="brand">Latest Assessment</x-ui.status-badge>
                <h2 id="latest-score-title" class="p-hero-title mt-2">
                    {{ $latestScore ?? '—' }}
                    <span class="p-hero-title__max">/ 1600</span>
                </h2>
            </div>
            @if($scoreDelta !== null)
                <x-ui.status-badge :status="$scoreDelta >= 0 ? 'success' : 'danger'">
                    {{ $scoreDelta >= 0 ? '+' : '' }}{{ $scoreDelta }} pts vs previous
                </x-ui.status-badge>
            @endif
        </div>

        @if($latestCompleted)
            <div class="p-hero-meta">
                <x-ui.icon name="file-text" class="w-3.5 h-3.5 text-slate-400" aria-hidden="true" />
                <span class="font-medium text-slate-700">{{ $latestCompleted->test?->title ?? 'Practice Test' }}</span>
                <span>&bull;</span>
                <span>{{ optional($latestCompleted->completed_at)->format('M j, Y') }}</span>
            </div>

            <!-- RW & Math Breakdowns -->
            <div class="p-hero-subscores">
                <div class="p-subscore-item">
                    <div class="flex items-center justify-between">
                        <span class="p-subscore-label">Reading & Writing</span>
                        <span class="text-xs text-slate-400 font-medium">200–800</span>
                    </div>
                    <strong class="p-subscore-val text-teal-600">{{ $rwScore ?? '—' }}</strong>
                    <div class="p-mini-bar" role="progressbar" aria-valuenow="{{ $rwScore ?? 0 }}" aria-valuemin="200" aria-valuemax="800">
                        <div class="p-mini-bar__fill bg-teal-600" style="transform: scaleX({{ $rwScore ? ($rwScore - 200) / 600 : 0 }});"></div>
                    </div>
                </div>
                <div class="p-subscore-item">
                    <div class="flex items-center justify-between">
                        <span class="p-subscore-label">Math</span>
                        <span class="text-xs text-slate-400 font-medium">200–800</span>
                    </div>
                    <strong class="p-subscore-val text-orange-600">{{ $mathScore ?? '—' }}</strong>
                    <div class="p-mini-bar" role="progressbar" aria-valuenow="{{ $mathScore ?? 0 }}" aria-valuemin="200" aria-valuemax="800">
                        <div class="p-mini-bar__fill bg-orange-600" style="transform: scaleX({{ $mathScore ? ($mathScore - 200) / 600 : 0 }});"></div>
                    </div>
                </div>
            </div>
        @else
            <p class="text-sm text-slate-500 mt-2">
                Complete a full-length practice test to unlock your estimated SAT score, section subscores, and historical trajectory.
            </p>
        @endif
    </div>

    <!-- Goal Tracker Component -->
    <div class="p-card--hero__goal" x-data="{
        target: '{{ $targetScore ?? '' }}',
        saving: false,
        saved: false,
        error: '',
        saveGoal() {
            const val = parseInt(this.target, 10);
            if (isNaN(val) || val < 400 || val > 1600) {
                this.error = 'Score must be 400–1600';
                return;
            }
            this.error = '';
            this.saving = true;
            fetch('{{ route('student.progress.goal') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ target_score: val })
            })
            .then(res => res.json())
            .then(data => {
                this.saving = false;
                this.saved = true;
                if (window.ProgressCharts && window.PROGRESS_CHARTS_CONFIG) {
                    window.PROGRESS_CHARTS_CONFIG.trend.targetScore = data.target_score;
                    window.ProgressCharts.init(window.PROGRESS_CHARTS_CONFIG);
                }
                setTimeout(() => { this.saved = false; }, 2500);
            })
            .catch(() => {
                this.saving = false;
                this.error = 'Failed to save';
            });
        }
    }">
        <div class="p-goal-box">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Target SAT Goal</span>
                    @if($targetScore && $latestScore)
                        <span class="text-xs font-semibold {{ $latestScore >= $targetScore ? 'text-emerald-600' : 'text-slate-600' }}">
                            @if($latestScore >= $targetScore)
                                Goal reached! 🎉
                            @else
                                {{ $targetScore - $latestScore }} pts to go
                            @endif
                        </span>
                    @endif
                </div>

                @if($targetScore && $latestScore)
                    <div class="p-mini-bar mb-3" role="progressbar" aria-valuenow="{{ $goalProgressPct }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="p-mini-bar__fill {{ $latestScore >= $targetScore ? 'bg-emerald-600' : 'bg-brand' }}" style="transform: scaleX({{ ($goalProgressPct ?? 0) / 100 }});"></div>
                    </div>
                @endif
            </div>

            @if($isStudentView)
                <div class="mt-auto">
                    <label for="input-target-score" class="sr-only">Target SAT Score</label>
                    <div class="flex items-center gap-2">
                        <input id="input-target-score"
                               type="number"
                               step="10"
                               min="400"
                               max="1600"
                               x-model="target"
                               placeholder="e.g. 1450"
                               class="p-input text-sm font-bold w-32"
                               @keydown.enter="saveGoal" />
                        <x-ui.button variant="primary" size="sm" type="button" @click="saveGoal" ::disabled="saving">
                            <span x-show="!saving && !saved">Set Goal</span>
                            <span x-show="saving" x-cloak>Saving...</span>
                            <span x-show="saved" x-cloak class="text-white font-bold">Saved ✓</span>
                        </x-ui.button>
                    </div>
                    <p x-show="error" x-text="error" class="text-xs text-rose-600 font-medium mt-1" x-cloak></p>
                    <span class="text-[11px] text-slate-400 mt-1.5 block">Target line is charted against your score trajectory.</span>
                </div>
            @else
                <div class="mt-auto">
                    <div class="text-2xl font-extrabold text-slate-800 tabular-nums">
                        {{ $targetScore ? $targetScore . ' pts' : 'Not set by student' }}
                    </div>
                    <span class="text-[11px] text-slate-400 mt-1 block">Student target score reference.</span>
                </div>
            @endif
        </div>
    </div>
</article>
