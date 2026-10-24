<?php
$domainLabels = [
    'craft_and_structure' => 'Craft and Structure',
    'information_and_ideas' => 'Information and Ideas',
    'standard_english_conventions' => 'Standard English Conventions',
    'expression_of_ideas' => 'Expression of Ideas',
    'algebra' => 'Algebra',
    'advanced_math' => 'Advanced Math',
    'problem_solving' => 'Problem-Solving and Data Analysis',
    'problem_solving_and_data_analysis' => 'Problem-Solving and Data Analysis',
    'geometry' => 'Geometry and Trigonometry',
    'geometry_and_trigonometry' => 'Geometry and Trigonometry',
];

// Shared hard/easy/standard badge coloring — used for both module- and question-level
// difficulty badges below so they can't drift out of sync with each other again.
$difficultyBadgeClasses = fn (?string $difficulty) => match ($difficulty) {
    'hard' => 'bg-rose-50 text-rose-700 border border-rose-100',
    'easy' => 'bg-emerald-50 text-emerald-700 border border-emerald-100',
    default => 'bg-amber-50 text-amber-700 border border-amber-100',
};
?>
<div class="attempt-monitor"
     @if(empty($hideHeader)) style="max-height: 70vh; overflow-y: auto;" @endif
     x-data="{ activeAttempt: {{ $initialAttempt->id }} }"
     data-attempt-monitor
    data-poll-url="{{ route('teacher.assignments.attempt-monitor', [$assignment, $row['recipient']->student]) }}"
    data-active-attempt="{{ $initialAttempt->id }}">
    @if(empty($hideHeader))
    <header class="attempt-monitor__student">
        <div>
            <p>{{ $row['recipient']->student->email }}</p>
            <span>{{ $row['attempts']->count() }}
                {{ \Illuminate\Support\Str::plural('attempt', $row['attempts']->count()) }} recorded</span>
        </div>
        <div class="attempt-monitor__presence">
            @if ($row['in_progress'])
                <span class="attempt-monitor__live"><i aria-hidden="true"></i> In progress</span>
            @else
                <span class="attempt-monitor__complete">No active attempt</span>
            @endif
            <small data-monitor-update-status aria-live="polite">Live updates</small>
        </div>
    </header>
    @endif

    <nav class="attempt-monitor__tabs" role="tablist" aria-label="Student attempts">
        @foreach ($row['attempts'] as $attempt)
            <button type="button" role="tab" id="{{ $attemptModalId }}-tab-{{ $attempt->id }}"
                aria-controls="{{ $attemptModalId }}-panel-{{ $attempt->id }}" data-attempt-id="{{ $attempt->id }}"
                x-on:click="activeAttempt = {{ $attempt->id }}"
                x-bind:aria-selected="activeAttempt === {{ $attempt->id }}"
                x-bind:class="{ 'is-active': activeAttempt === {{ $attempt->id }} }">
                <span>Attempt {{ $attempt->attempt_number }}</span>
                <small>{{ $attempt->status === 'in_progress' ? 'Active now' : ($attempt->total_score ? 'Score: ' . $attempt->total_score : ucfirst(str_replace('_', ' ', $attempt->status))) }}</small>
            </button>
        @endforeach
    </nav>

    <div class="attempt-monitor__panels">
        @foreach ($row['attempts'] as $attempt)
            <?php
                $isInProgress = $attempt->status === 'in_progress';
                $currentModule = $attempt->currentModule;
                $savedResponses = $attempt->userAnswers->count();
                $answeredResponses = $attempt->userAnswers->filter(fn($answer) => filled($answer->selected_answer))->count();
                $moduleResponses = $currentModule ? $attempt->userAnswers->where('module_id', $currentModule->id)->filter(fn($answer) => filled($answer->selected_answer))->count() : $answeredResponses;
                $questionTotal = $currentModule?->total_questions ?: max($savedResponses, 1);
                $progress = min(100, (int) round(($moduleResponses / max($questionTotal, 1)) * 100));
                $elapsedSeconds = (int) $attempt->current_module_elapsed_seconds;
                $isActive = $attempt->id === $initialAttempt->id;

                $orderedAnswers = $attempt->userAnswers->sortBy(function ($ans) {
                    $m = $ans->module;
                    return ($m?->section?->order ?? 0) * 1000 + ($m?->module_number ?? 0) * 100 + ($ans->question_snapshot['question_number'] ?? $ans->id);
                })->values();
                $groupedAnswers = $orderedAnswers->groupBy('module_id');
            ?>
            <section class="attempt-monitor__panel" id="{{ $attemptModalId }}-panel-{{ $attempt->id }}"
                role="tabpanel" aria-labelledby="{{ $attemptModalId }}-tab-{{ $attempt->id }}"
                x-data="{
                    selectedAnswerId: null,
                    previewHtml: '',
                    loading: false,
                    filterStatus: 'all',
                    allAnswerIds: {{ Js::from($orderedAnswers->pluck('id')->values()) }},
                    collapsedModules: {
                        @foreach ($orderedAnswers->pluck('module_id')->unique() as $moduleId)
                            '{{ $moduleId }}': false,
                        @endforeach
                    },
                    get currentIndex() { return this.allAnswerIds.indexOf(this.selectedAnswerId); },
                    get hasPrev() { return this.currentIndex > 0; },
                    get hasNext() { return this.currentIndex >= 0 && this.currentIndex < this.allAnswerIds.length - 1; },
                    prevQuestion() { if (this.hasPrev) this.fetchPreview(this.allAnswerIds[this.currentIndex - 1]); },
                    nextQuestion() { if (this.hasNext) this.fetchPreview(this.allAnswerIds[this.currentIndex + 1]); },
                    fetchPreview(answerId) {
                        if (this.selectedAnswerId === answerId && this.previewHtml) return;
                        this.selectedAnswerId = answerId;
                        this.loading = true;
                        this.previewHtml = '';

                        let url = '{{ route('teacher.assignments.attempt-question-preview', [$assignment, ':answerId']) }}'.replace(':answerId', answerId);

                        fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'text/html'
                            }
                        })
                        .then(res => {
                            if (!res.ok) throw new Error('Failed to load preview');
                            return res.text();
                        })
                        .then(html => {
                            this.previewHtml = html;
                            this.loading = false;
                            this.$nextTick(() => {
                                if (window.smartRenderMath) {
                                    window.smartRenderMath();
                                }
                            });
                        })
                        .catch(err => {
                            console.error(err);
                            this.previewHtml = '<div class=\'p-4 text-rose-600 bg-rose-50 rounded border border-rose-200 text-sm\'>Error loading preview.</div>';
                            this.loading = false;
                        });
                    }
                }"
                x-on:restore-state="
                    selectedAnswerId = $event.detail.selectedAnswerId;
                    collapsedModules = $event.detail.collapsedModules;
                    previewHtml = $event.detail.previewHtml;
                "
                @keydown.left.window="if (selectedAnswerId) prevQuestion()"
                @keydown.right.window="if (selectedAnswerId) nextQuestion()"
                @keydown.escape.window="selectedAnswerId = null; previewHtml = ''"
                x-show="activeAttempt === {{ $attempt->id }}" {!! !$isActive ? 'x-cloak' : '' !!}>

                <!-- ATTEMPT OVERVIEW CARD -->
                <div class="bg-slate-50/70 border border-slate-200 rounded-xl p-4 sm:p-5 mb-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="status-pill {{ $isInProgress ? 'in-progress' : 'ok' }}">
                                <span class="d"></span>
                                {{ $isInProgress ? 'In progress' : ucfirst(str_replace('_', ' ', $attempt->status)) }}
                            </span>
                            <h4 class="text-sm sm:text-base font-bold text-slate-900 m-0">
                                @if ($currentModule)
                                    {{ $currentModule->section?->name ?? 'Current section' }} &middot; Module {{ $currentModule->module_number }}
                                @else
                                    Attempt {{ $attempt->attempt_number }} Overview
                                @endif
                            </h4>
                        </div>
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="text-sm text-slate-500 font-medium">
                                Last activity {{ $attempt->updated_at->diffForHumans() }}
                            </span>
                            @if ($attempt->status === 'completed' || $attempt->total_score !== null)
                                <a href="{{ route('student.scores.show', $attempt) }}" target="_blank" rel="noopener noreferrer" class="btn-sm-primary flex items-center gap-1.5 no-underline text-sm shrink-0 font-semibold px-3 py-1.5 rounded-lg shadow-2xs">
                                    <span>Score Report</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            @endif
                        </div>
                    </div>

                    @php
                        $totalTimeSeconds = (int) $attempt->userAnswers->sum('time_spent');
                        if ($totalTimeSeconds >= 3600) {
                            $formattedTotalTime = floor($totalTimeSeconds / 3600) . 'h ' . floor(($totalTimeSeconds % 3600) / 60) . 'm';
                        } elseif ($totalTimeSeconds >= 60) {
                            $formattedTotalTime = floor($totalTimeSeconds / 60) . 'm ' . ($totalTimeSeconds % 60) . 's';
                        } else {
                            $formattedTotalTime = $totalTimeSeconds . 's';
                        }
                        $totalCorrect = $attempt->userAnswers->where('is_correct', true)->count();
                        $accuracyPct = $savedResponses > 0 ? (int) round(($totalCorrect / $savedResponses) * 100) : 0;
                    @endphp

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-sm">
                        <!-- Tile 1: Total Score -->
                        <div class="bg-white border border-slate-200 rounded-xl p-3 shadow-2xs">
                            <div class="text-sm uppercase font-bold text-slate-500 tracking-wider mb-1">Score</div>
                            <div class="text-lg tabular-nums font-bold text-slate-900 leading-tight">
                                {{ $attempt->total_score ?? '—' }}
                                <span class="text-sm font-sans font-medium text-slate-500">{{ $assignment->isSectionAssignment() ? '/ 800' : '/ 1600' }}</span>
                            </div>
                            <div class="text-sm text-slate-500 mt-1 truncate">{{ $assignment->isSectionAssignment() ? $assignment->sectionLabel() : 'Full Exam' }}</div>
                        </div>

                        <!-- Tile 2: Accuracy -->
                        <div class="bg-white border border-slate-200 rounded-xl p-3 shadow-2xs">
                            <div class="text-sm uppercase font-bold text-slate-500 tracking-wider mb-1">Accuracy</div>
                            <div class="text-lg tabular-nums font-bold text-slate-900 leading-tight">
                                {{ $accuracyPct }}%
                            </div>
                            <div class="text-sm text-slate-500 mt-1">{{ $totalCorrect }} / {{ $savedResponses }} correct</div>
                        </div>

                        <!-- Tile 3: Reading & Writing Score -->
                        <div class="bg-white border border-slate-200 rounded-xl p-3 shadow-2xs">
                            <div class="text-sm uppercase font-bold text-slate-500 tracking-wider mb-1">R&amp;W Score</div>
                            <div class="text-lg tabular-nums font-bold text-blue-700 leading-tight">
                                {{ $attempt->score_reading_writing ?? '—' }}
                                <span class="text-sm font-sans font-medium text-slate-500">/ 800</span>
                            </div>
                            <div class="text-sm text-slate-500 mt-1">Reading &amp; Writing</div>
                        </div>

                        <!-- Tile 4: Math Score -->
                        <div class="bg-white border border-slate-200 rounded-xl p-3 shadow-2xs">
                            <div class="text-sm uppercase font-bold text-slate-500 tracking-wider mb-1">Math Score</div>
                            <div class="text-lg tabular-nums font-bold text-indigo-700 leading-tight">
                                {{ $attempt->score_math ?? '—' }}
                                <span class="text-sm font-sans font-medium text-slate-500">/ 800</span>
                            </div>
                            <div class="text-sm text-slate-500 mt-1">Math Section</div>
                        </div>

                        <!-- Tile 5: Time Spent -->
                        <div class="bg-white border border-slate-200 rounded-xl p-3 shadow-2xs">
                            <div class="text-sm uppercase font-bold text-slate-500 tracking-wider mb-1">Total Time</div>
                            <div class="text-lg tabular-nums font-bold text-slate-900 leading-tight">
                                {{ $formattedTotalTime }}
                            </div>
                            <div class="text-sm text-slate-500 mt-1">Across all modules</div>
                        </div>

                        <!-- Tile 6: Adaptive Path -->
                        <div class="bg-white border border-slate-200 rounded-xl p-3 shadow-2xs">
                            <div class="text-sm uppercase font-bold text-slate-500 tracking-wider mb-1">Stage 2 Paths</div>
                            <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                @if($attempt->rw_m2_path)
                                    <span class="text-sm font-bold px-1.5 py-0.5 rounded bg-blue-50 text-blue-800 border border-blue-200" title="Reading & Writing Module 2 Path">
                                        RW: {{ ucfirst($attempt->rw_m2_path) }}
                                    </span>
                                @endif
                                @if($attempt->math_m2_path)
                                    <span class="text-sm font-bold px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-800 border border-indigo-200" title="Math Module 2 Path">
                                        M: {{ ucfirst($attempt->math_m2_path) }}
                                    </span>
                                @endif
                                @if(!$attempt->rw_m2_path && !$attempt->math_m2_path)
                                    <span class="text-sm text-slate-500 font-semibold">—</span>
                                @endif
                            </div>
                            <div class="text-sm text-slate-500 mt-1">Adaptive routing</div>
                        </div>
                    </div>

                    @if ($isInProgress && $currentModule)
                        <div class="attempt-monitor__progress mt-4">
                            <div><span>Current module completion ({{ $moduleResponses }} / {{ $questionTotal }} &middot; {{ intdiv($elapsedSeconds, 60) }}:{{ str_pad((string) ($elapsedSeconds % 60), 2, '0', STR_PAD_LEFT) }})</span><strong>{{ $progress }}%</strong></div>
                            <span role="progressbar" aria-label="Current module completion" aria-valuemin="0"
                                aria-valuemax="100" aria-valuenow="{{ $progress }}"><i
                                    style="width: {{ $progress }}%"></i></span>
                        </div>
                    @elseif($assignment->due_at && $attempt->completed_at?->gt($assignment->due_at))
                        <p class="attempt-monitor__notice mt-3 mb-0">Submitted after the assignment deadline.</p>
                    @endif
                </div>

                <!-- RESPONSE REVIEW SECTION -->
                <div class="mt-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm sm:text-base font-bold text-slate-900 m-0">{{ $isInProgress ? 'Saved Responses' : 'Response Review' }}</h4>
                            <span class="text-sm font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                                {{ $savedResponses }} {{ \Illuminate\Support\Str::plural('question', $savedResponses) }}
                            </span>
                        </div>

                        <!-- Quick Filter Pills -->
                        @if(!$isInProgress && $attempt->userAnswers->isNotEmpty())
                            @php
                                $totalWrong = $attempt->userAnswers->filter(fn($a) => $a->is_correct === false && filled($a->selected_answer))->count();
                                $totalOmitted = $attempt->userAnswers->filter(fn($a) => !filled($a->selected_answer))->count();
                            @endphp
                            <div class="flex items-center gap-1.5 text-sm font-semibold">
                                <span class="text-slate-500 mr-1 text-sm uppercase tracking-wider font-bold">Filter:</span>
                                <button type="button" @click="filterStatus = 'all'"
                                    :class="filterStatus === 'all' ? 'bg-slate-900 text-white shadow-2xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                                    class="px-2.5 py-1 rounded-lg transition-colors cursor-pointer">
                                    All ({{ $savedResponses }})
                                </button>
                                <button type="button" @click="filterStatus = 'wrong'"
                                    :class="filterStatus === 'wrong' ? 'bg-rose-600 text-white shadow-2xs' : 'bg-white text-rose-700 border border-rose-200 hover:bg-rose-50'"
                                    class="px-2.5 py-1 rounded-lg transition-colors cursor-pointer flex items-center gap-1">
                                    <span>Incorrect ({{ $totalWrong }})</span>
                                </button>
                                <button type="button" @click="filterStatus = 'correct'"
                                    :class="filterStatus === 'correct' ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-white text-emerald-700 border border-emerald-200 hover:bg-emerald-50'"
                                    class="px-2.5 py-1 rounded-lg transition-colors cursor-pointer flex items-center gap-1">
                                    <span>Correct ({{ $totalCorrect }})</span>
                                </button>
                                @if($totalOmitted > 0)
                                    <button type="button" @click="filterStatus = 'omitted'"
                                        :class="filterStatus === 'omitted' ? 'bg-slate-700 text-white shadow-2xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                                        class="px-2.5 py-1 rounded-lg transition-colors cursor-pointer">
                                        Omitted ({{ $totalOmitted }})
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($attempt->userAnswers->isNotEmpty())
                        <div class="space-y-4">
                            @foreach ($groupedAnswers as $moduleId => $answers)
                                @php
                                    $module = $answers->first()->module;
                                    $isSubmitted = $attempt->status === 'completed' || $attempt->moduleSubmissions->contains('module_id', $moduleId);
                                    $isMath = str_contains(strtolower($module?->section?->name ?? ''), 'math');
                                    $mTotal = $answers->count();
                                    $mCorrect = $answers->where('is_correct', true)->count();
                                    $mIncorrect = $answers->filter(fn($a) => $a->is_correct === false && filled($a->selected_answer))->count();
                                    $mOmitted = $answers->filter(fn($a) => !filled($a->selected_answer))->count();
                                    $mAccuracy = $mTotal > 0 ? (int) round(($mCorrect / $mTotal) * 100) : 0;
                                    $mTimeSeconds = (int) $answers->sum('time_spent');
                                    $mTimeString = $mTimeSeconds >= 60 ? floor($mTimeSeconds / 60) . 'm ' . ($mTimeSeconds % 60) . 's' : $mTimeSeconds . 's';
                                @endphp
                                <div class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-2xs transition-all"
                                     data-module-card
                                     data-module-id="{{ $moduleId }}">
                                    <!-- Module Header (Click to collapse/expand) -->
                                    <button type="button"
                                        class="w-full bg-slate-50/90 px-4 sm:px-5 py-3.5 border-b border-slate-200 flex flex-wrap justify-between items-center gap-2 hover:bg-slate-100/70 transition-colors focus:outline-none cursor-pointer"
                                        @click="collapsedModules['{{ $moduleId }}'] = !collapsedModules['{{ $moduleId }}']">
                                        <div class="flex items-center gap-2.5">
                                            <svg class="w-4 h-4 text-slate-400 transform transition-transform"
                                                :class="collapsedModules['{{ $moduleId }}'] ? '-rotate-90' : ''"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $isMath ? 'bg-indigo-600' : 'bg-blue-600' }}"></span>
                                            <h5 class="text-sm sm:text-sm font-bold text-slate-800 m-0 text-left tracking-tight">
                                                {{ $module?->section?->name ?? 'Section' }} &middot; Module {{ $module?->module_number ?? '?' }}
                                            </h5>
                                            @if($module?->difficulty)
                                                <span class="text-sm uppercase tracking-wider font-bold px-2 py-0.5 rounded {{ $difficultyBadgeClasses($module->difficulty) }}">
                                                    {{ ucfirst($module->difficulty) }} Stage
                                                </span>
                                            @endif
                                            @if($isSubmitted)
                                                <span class="text-sm font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1 shadow-2xs">
                                                    <svg class="w-2.5 h-2.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Submitted
                                                </span>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-3 text-sm font-semibold">
                                            @if(!$isInProgress)
                                                <span class="{{ $mAccuracy >= 80 ? 'text-emerald-700' : ($mAccuracy >= 60 ? 'text-amber-700' : 'text-rose-700') }}">
                                                    {{ $mCorrect }}/{{ $mTotal }} correct ({{ $mAccuracy }}%)
                                                </span>
                                            @else
                                                <span class="text-slate-600">
                                                    {{ $answers->filter(fn($a) => filled($a->selected_answer))->count() }}/{{ $mTotal }} answered
                                                </span>
                                            @endif
                                            <span class="text-slate-300 font-normal">&middot;</span>
                                            <span class="text-slate-500 font-medium">⏱️ {{ $mTimeString }}</span>
                                        </div>
                                    </button>

                                    <!-- Question Chips Palette Grid -->
                                    <div x-show="!collapsedModules['{{ $moduleId }}']" class="p-4 sm:p-5 bg-white">
                                        <div class="flex flex-wrap gap-2 sm:gap-2.5">
                                            @foreach ($answers as $answer)
                                                @php
                                                    $correct = $answer->question?->sprCorrectAnswers->pluck('answer')->implode(', ') ?: $answer->question?->answerChoices->firstWhere('is_correct', true)?->label;
                                                    $timeSpent = (int) ($answer->time_spent ?? 0);
                                                    $timeString = $timeSpent >= 60 ? floor($timeSpent / 60) . 'm ' . ($timeSpent % 60) . 's' : $timeSpent . 's';
                                                    $domainLabel = $domainLabels[$answer->question?->skill_domain] ?? ucwords(str_replace('_', ' ', $answer->question?->skill_domain ?? 'General'));

                                                    $statusKey = $isInProgress
                                                        ? (filled($answer->selected_answer) ? 'answered' : 'omitted')
                                                        : (!filled($answer->selected_answer) ? 'omitted' : ($answer->is_correct ? 'correct' : 'wrong'));

                                                    $statusClasses = match($statusKey) {
                                                        'correct' => 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100 hover:border-emerald-400',
                                                        'wrong' => 'bg-rose-50 text-rose-800 border-rose-300 hover:bg-rose-100 hover:border-rose-400',
                                                        'answered' => 'bg-indigo-50 text-indigo-800 border-indigo-300 hover:bg-indigo-100 hover:border-indigo-400',
                                                        default => 'bg-slate-100 text-slate-700 border-slate-300 hover:bg-slate-200 hover:border-slate-400',
                                                    };
                                                @endphp
                                                <button type="button"
                                                    @click="fetchPreview({{ $answer->id }})"
                                                    :class="{
                                                        'ring-2 ring-blue-600 ring-offset-2 scale-105 z-10': selectedAnswerId === {{ $answer->id }},
                                                        'opacity-20 grayscale pointer-events-none': filterStatus !== 'all' && filterStatus !== '{{ $statusKey }}'
                                                    }"
                                                    class="w-10 h-10 sm:w-11 sm:h-11 rounded-lg flex flex-col items-center justify-center font-mono font-bold border transition-all cursor-pointer relative shadow-2xs {{ $statusClasses }}"
                                                    title="Q{{ $loop->iteration }} &middot; {{ ucfirst($statusKey) }}&#10;Student answer: {{ $answer->selected_answer ?: 'Omitted' }} | Correct: {{ $correct ?: '—' }}&#10;Time: {{ $timeString }}&#10;Domain: {{ $domainLabel }}&#10;Click to inspect question">
                                                    <span class="leading-none text-sm">{{ $loop->iteration }}</span>
                                                    <span class="sr-only">{{ filled($answer->selected_answer) ? 'Answered: ' . $answer->selected_answer : 'Omitted' }}</span>
                                                    @if($isInProgress)
                                                        <span class="text-[9px] leading-none opacity-80 mt-0.5">{{ filled($answer->selected_answer) ? '●' : '○' }}</span>
                                                    @elseif($answer->is_correct)
                                                        <span class="text-[9px] leading-none opacity-80 mt-0.5">✓</span>
                                                    @elseif(filled($answer->selected_answer))
                                                        <span class="text-[9px] leading-none opacity-80 mt-0.5">✗</span>
                                                    @else
                                                        <span class="text-[9px] leading-none opacity-60 mt-0.5">—</span>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="attempt-monitor__empty p-8 text-center bg-slate-50 border border-slate-200 rounded-xl">
                            <strong class="block text-sm font-bold text-slate-700 mb-1">No responses saved yet</strong>
                            <span class="text-sm text-slate-500">Activity will appear after student saves an answer.</span>
                        </div>
                    @endif
                </div>

                <!-- Question Detail Modal Overlay -->
                <div x-show="selectedAnswerId"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-50 items-center justify-center p-4"
                     :class="selectedAnswerId ? 'flex' : 'hidden'"
                     role="dialog"
                     aria-modal="true">
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-slate-900/50 transition-opacity"
                         x-show="selectedAnswerId"
                         x-transition:enter="ease-out duration-150"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-100"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         @click="selectedAnswerId = null; previewHtml = ''"></div>

                    <!-- Modal Content Container -->
                    <div class="relative bg-white rounded-xl shadow-2xl border border-slate-200 w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden transform-gpu"
                         x-show="selectedAnswerId"
                         x-transition:enter="ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-3"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 translate-y-3">

                        <!-- Modal Header -->
                        <div class="px-6 py-3.5 border-b border-slate-200 flex items-center justify-between bg-slate-50/90">
                            <div class="flex items-center gap-3">
                                <h3 class="text-base font-bold text-slate-900 m-0">Question Detail</h3>
                                <span class="text-sm font-semibold px-2 py-0.5 rounded bg-white text-slate-600 border border-slate-200 shadow-2xs"
                                      x-show="currentIndex >= 0"
                                      x-text="'Item ' + (currentIndex + 1) + ' of ' + allAnswerIds.length"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button"
                                    class="px-2.5 py-1 text-sm font-semibold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-35 disabled:cursor-not-allowed transition-colors flex items-center gap-1 shadow-2xs cursor-pointer"
                                    :disabled="!hasPrev"
                                    @click="prevQuestion()"
                                    title="Previous question (Left Arrow)">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                    <span class="hidden sm:inline">Prev</span>
                                </button>
                                <button type="button"
                                    class="px-2.5 py-1 text-sm font-semibold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-35 disabled:cursor-not-allowed transition-colors flex items-center gap-1 shadow-2xs cursor-pointer"
                                    :disabled="!hasNext"
                                    @click="nextQuestion()"
                                    title="Next question (Right Arrow)">
                                    <span class="hidden sm:inline">Next</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                                <button type="button"
                                    class="text-slate-400 hover:text-slate-600 transition-colors focus:outline-none p-1.5 rounded-full hover:bg-slate-200/50 ml-1 cursor-pointer"
                                    @click="selectedAnswerId = null; previewHtml = ''"
                                    aria-label="Close question preview">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Modal Body (Scrollable) -->
                        <div class="p-6 overflow-y-auto flex-1 bg-slate-50/60">
                            <!-- Loading State -->
                            <div x-show="loading"
                                 :class="loading ? 'flex flex-col gap-4' : 'hidden'"
                                 class="animate-pulse h-full">
                                <div class="flex justify-between items-center pb-3 border-b border-slate-100">
                                    <div class="h-4 bg-slate-200 rounded w-1/4"></div>
                                    <div class="h-4 bg-slate-200 rounded w-12"></div>
                                </div>
                                <div class="space-y-2 mt-2">
                                    <div class="h-3 bg-slate-200 rounded w-full"></div>
                                    <div class="h-3 bg-slate-200 rounded w-5/6"></div>
                                </div>
                                <div class="space-y-2 mt-4">
                                    <div class="h-10 bg-slate-100 rounded w-full"></div>
                                    <div class="h-10 bg-slate-100 rounded w-full"></div>
                                </div>
                            </div>

                            <!-- Rendered Preview Content -->
                            <div x-show="!loading"
                                 :class="!loading ? 'block' : 'hidden'"
                                 x-html="previewHtml"
                                 class="min-h-0"
                                 data-morph-skip></div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="px-6 py-3 border-t border-slate-200 bg-slate-50/80 flex items-center justify-between">
                            <div class="text-sm text-slate-500 font-medium hidden sm:block">
                                Use <kbd class="px-1.5 py-0.5 rounded border border-slate-300 bg-white font-mono text-sm font-semibold text-slate-700">←</kbd> and <kbd class="px-1.5 py-0.5 rounded border border-slate-300 bg-white font-mono text-sm font-semibold text-slate-700">→</kbd> to navigate, <kbd class="px-1.5 py-0.5 rounded border border-slate-300 bg-white font-mono text-sm font-semibold text-slate-700">ESC</kbd> to close
                            </div>
                            <div class="flex items-center gap-2 ml-auto">
                                <button type="button"
                                    class="px-4 py-1.5 text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-2xs cursor-pointer"
                                    @click="selectedAnswerId = null; previewHtml = ''">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endforeach
    </div>
</div>
