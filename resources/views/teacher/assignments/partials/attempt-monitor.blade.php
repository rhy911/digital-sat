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
            @php($isInProgress = $attempt->status === 'in_progress')
            @php($currentModule = $attempt->currentModule)
            @php($savedResponses = $attempt->userAnswers->count())
            @php($answeredResponses = $attempt->userAnswers->filter(fn($answer) => filled($answer->selected_answer))->count())
            @php($moduleResponses = $currentModule ? $attempt->userAnswers->where('module_id', $currentModule->id)->filter(fn($answer) => filled($answer->selected_answer))->count() : $answeredResponses)
            @php($questionTotal = $currentModule?->total_questions ?: max($savedResponses, 1))
            @php($progress = min(100, (int) round(($moduleResponses / max($questionTotal, 1)) * 100)))
            @php($elapsedSeconds = (int) $attempt->current_module_elapsed_seconds)
            @php($isActive = $attempt->id === $initialAttempt->id)
            <section class="attempt-monitor__panel" id="{{ $attemptModalId }}-panel-{{ $attempt->id }}"
                role="tabpanel" aria-labelledby="{{ $attemptModalId }}-tab-{{ $attempt->id }}"
                x-data="{ 
                    selectedAnswerId: null, 
                    previewHtml: '', 
                    loading: false,
                    collapsedModules: {
                        @foreach ($attempt->userAnswers->pluck('module_id')->unique() as $moduleId)
                            '{{ $moduleId }}': true,
                        @endforeach
                    },
                    fetchPreview(answerId) {
                        if (this.selectedAnswerId === answerId) return;
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
                x-show="activeAttempt === {{ $attempt->id }}" {!! !$isActive ? 'x-cloak' : '' !!}>
                <div class="attempt-monitor__summary">
                    <div class="attempt-monitor__status-line">
                        <div>
                            <span
                                class="attempt-monitor__status attempt-monitor__status--{{ $isInProgress ? 'active' : 'complete' }}">{{ $isInProgress ? 'In progress' : ucfirst(str_replace('_', ' ', $attempt->status)) }}</span>
                            <h4>
                                @if ($currentModule)
                                    {{ $currentModule->section?->name ?? 'Current section' }} &middot; Module
                                    {{ $currentModule->module_number }}
                                @else
                                    Attempt {{ $attempt->attempt_number }} overview
                                @endif
                            </h4>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="attempt-monitor__activity">Last activity
                                {{ $attempt->updated_at->diffForHumans() }}</span>
                            @if ($attempt->status === 'completed' || $attempt->total_score !== null)
                                <a href="{{ route('student.scores.show', $attempt) }}" target="_blank" rel="noopener noreferrer" class="btn-sm-primary btn-compact flex items-center gap-1.5 no-underline text-xs shrink-0" style="padding: 4px 10px; font-weight: 600;">
                                    <span>Score Report</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            @else
                                <button type="button" disabled class="btn-sm-ghost is-disabled flex items-center gap-1.5 text-xs shrink-0 cursor-not-allowed opacity-50 select-none" style="padding: 4px 10px; font-weight: 600; background: #e2e8f0; color: #94a3b8; border: 1px solid #cbd5e1;" title="Score report is available once attempt is completed">
                                    <span>Score Report</span>
                                    <svg class="w-3.5 h-3.5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    <dl class="attempt-monitor__metrics">
                        <div>
                            <dt>Answered</dt>
                            <dd>{{ $answeredResponses }}</dd>
                        </div>
                        <div>
                            <dt>{{ $isInProgress ? 'Module progress' : 'Responses saved' }}</dt>
                            <dd>{{ $isInProgress && $currentModule ? $moduleResponses . ' / ' . $questionTotal : $savedResponses }}
                            </dd>
                        </div>
                        <div>
                            <dt>Module time</dt>
                            <dd>{{ intdiv($elapsedSeconds, 60) }}:{{ str_pad((string) ($elapsedSeconds % 60), 2, '0', STR_PAD_LEFT) }}
                            </dd>
                        </div>
                        <div>
                            <dt>{{ $isInProgress ? 'Started' : 'Score' }}</dt>
                            <dd>
                                @if ($isInProgress)
                                    {{ $attempt->created_at->format('M j, g:i A') }}
                                @elseif($attempt->total_score !== null)
                                    {{ $attempt->total_score }}
                                @else
                                    &mdash;
                                @endif
                            </dd>
                        </div>
                        @if ($attempt->rw_m2_path)
                        <div>
                            <dt>R&W Module 2 Path</dt>
                            <dd><span class="font-semibold text-brand">{{ ucfirst($attempt->rw_m2_path) }}</span></dd>
                        </div>
                        @endif
                        @if ($attempt->math_m2_path)
                        <div>
                            <dt>Math Module 2 Path</dt>
                            <dd><span class="font-semibold text-brand">{{ ucfirst($attempt->math_m2_path) }}</span></dd>
                        </div>
                        @endif
                    </dl>

                    @if ($isInProgress && $currentModule)
                        <div class="attempt-monitor__progress">
                            <div><span>Current module completion</span><strong>{{ $progress }}%</strong></div>
                            <span role="progressbar" aria-label="Current module completion" aria-valuemin="0"
                                aria-valuemax="100" aria-valuenow="{{ $progress }}"><i
                                    style="width: {{ $progress }}%"></i></span>
                        </div>
                    @elseif($assignment->due_at && $attempt->completed_at?->gt($assignment->due_at))
                        <p class="attempt-monitor__notice">Submitted after the assignment deadline.</p>
                    @endif
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-6 border-t border-slate-100 pt-6">
                    <!-- Left column: Grouped list of questions (Takes full width) -->
                    <div class="lg:col-span-12 flex flex-col gap-4">
                        <div class="attempt-monitor__responses-heading flex justify-between items-center mb-2">
                            <h5 class="text-base font-semibold text-slate-800 m-0">{{ $isInProgress ? 'Saved responses' : 'Response review' }}</h5>
                            <span class="text-xs text-slate-500">{{ $savedResponses }} {{ \Illuminate\Support\Str::plural('question', $savedResponses) }}</span>
                        </div>

                        @if ($attempt->userAnswers->isNotEmpty())
                            <?php
                                $groupedAnswers = $attempt->userAnswers->groupBy('module_id')->sortBy(function ($answers) {
                                    $module = $answers->first()->module;
                                    return ($module?->section?->order ?? 0) * 100 + ($module?->module_number ?? 0);
                                });
                            ?>
                            <div class="attempt-monitor__questions-list pr-1 space-y-4 max-h-[500px] overflow-y-auto">
                                @foreach ($groupedAnswers as $moduleId => $answers)
                                    <?php 
                                        $module = $answers->first()->module; 
                                        $isSubmitted = $attempt->status === 'completed' || $attempt->moduleSubmissions->contains('module_id', $moduleId);
                                    ?>
                                    <div class="rounded-lg border border-slate-200 bg-white overflow-hidden shadow-sm transition-all"
                                         data-module-card
                                         data-module-id="{{ $moduleId }}"
                                         :data-collapsed="collapsedModules['{{ $moduleId }}'] ? 'true' : 'false'">
                                        <!-- Header (click to collapse) -->
                                        <button type="button" 
                                            class="w-full bg-slate-50 px-4 py-3 border-b border-slate-200 flex justify-between items-center hover:bg-slate-100/70 transition-colors focus:outline-none"
                                            @click="collapsedModules['{{ $moduleId }}'] = !collapsedModules['{{ $moduleId }}']">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-slate-400 transform transition-transform" 
                                                    :class="collapsedModules['{{ $moduleId }}'] ? '-rotate-90' : ''" 
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                                <h6 class="text-sm font-semibold text-slate-800 m-0 text-left">
                                                    {{ $module?->section?->name ?? 'Unknown Section' }} &middot; Module {{ $module?->module_number ?? '?' }}
                                                </h6>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                @if($isSubmitted)
                                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1 shadow-sm">
                                                        <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                        </svg>
                                                        Submitted
                                                    </span>
                                                @else
                                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200 flex items-center gap-1 shadow-sm">
                                                        <svg class="w-3 h-3 text-amber-600 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        Active
                                                    </span>
                                                @endif
                                                @if($module?->difficulty)
                                                    <span class="text-[10px] font-medium px-2 py-0.5 rounded {{ $difficultyBadgeClasses($module->difficulty) }}">
                                                        {{ ucfirst($module->difficulty) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </button>
                                        
                                        <!-- Question items list -->
                                        <div x-show="!collapsedModules['{{ $moduleId }}']">
                                            <ol class="divide-y divide-slate-100 list-none p-0 m-0">
                                                @foreach ($answers as $answer)
                                                    @include('teacher.assignments.partials.attempt-monitor-answer')
                                                @endforeach
                                            </ol>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="attempt-monitor__empty">
                                <strong>No responses saved yet</strong>
                                <span>Activity will appear after student saves an answer.</span>
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
                            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                                <h3 class="text-base font-semibold text-slate-900 m-0">Question Detail</h3>
                                <button type="button" 
                                    class="text-slate-400 hover:text-slate-600 transition-colors focus:outline-none p-1.5 rounded-full hover:bg-slate-200/50"
                                    @click="selectedAnswerId = null; previewHtml = ''"
                                    aria-label="Close question preview">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Modal Body (Scrollable) -->
                            <div class="p-6 overflow-y-auto flex-1 bg-[#FCFBF7]">
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
                            <div class="px-6 py-3 border-t border-slate-100 bg-slate-50 flex justify-end">
                                <button type="button" 
                                    class="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
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
