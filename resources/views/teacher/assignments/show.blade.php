<x-layouts.student :user="auth()->user()" :title="$assignment ? $assignment->title : 'Assignments'" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css'])
    @endpush

    @php
        $userInitials = auth()->user()?->initials ?? 'U';
    @endphp

    <div class="app-shell" x-data="{
        searchQuery: '',
        statusFilter: 'all',
        activeTab: 'results'
    }">
        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="route('teacher.progress')" :avatar-label="$userInitials"
            :items="\App\Support\NavRail::teacher('reports')" />

        <!-- COLUMN 2: SIDEBAR LIST -->
        <x-shell.sidebar-list title="Assignments"
            count-text="{{ $assignments->where('status', 'published')->count() }} active · {{ $assignments->where('status', 'closed')->count() }} closed"
            search-placeholder="Search assignments..."
            :filters="[
                ['value' => 'all', 'label' => 'All'],
                ['value' => 'published', 'label' => 'Published'],
                ['value' => 'closed', 'label' => 'Closed'],
            ]"
            :items="$assignments
                ->map(
                    fn($a) => [
                        'route' => route('teacher.assignments.show', $a),
                        'name' => $a->title,
                        'status' => $a->status,
                        'selected' => $assignment && $assignment->id === $a->id,
                        'meta' => [
                            $a->classroom->name,
                            $a->attempts_count . ' ' . Str::plural('attempt', $a->attempts_count),
                        ],
                    ],
                )
                ->all()" />

        <!-- COLUMN 3: LEDGER PANE -->
        <div class="ledger-pane" :class="{ 'no-corkboard': activeTab === 'analysis' }">
            <x-ui.flash />

            <!-- MAIN LEDGER COLUMN -->
            <div class="binder-panel">
                @if ($assignment)
                    <div class="ledger-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="dh-left">
                            @if ($origin === 'workspace')
                                <a class="back-link" href="{{ route('teacher.assignments.index') }}" style="font-size: 11.5px; font-weight: 600; text-decoration: none; color: var(--accent); margin-bottom: 6px; display: inline-block;">Back to assignments &amp; reports</a>
                            @endif
                            <h2>{{ $assignment->title }} <span class="handwriting status-quote">"{{ ucfirst($assignment->status) }}"</span></h2>
                            <div class="dh-desc">
                                {{ $assignment->test->title }}{{ $assignment->sectionSuffix() }} &middot; Classroom: <strong>{{ $assignment->classroom->name }}</strong>
                            </div>
                        </div>
                        @if ($assignment->classroom->status === 'active')
                            <div style="display: flex; gap: 8px;">
                                @if($assignment->status === 'published')
                                    <form method="POST" action="{{ route('teacher.assignments.close', $assignment) }}">
                                        @csrf
                                        <button type="submit" class="btn-sm-ghost">Close</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('teacher.assignments.reopen', $assignment) }}">
                                        @csrf
                                        <button type="submit" class="btn-sm-ghost">Reopen</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>

                    <x-shell.stat-row :stats="[
                        ['value' => $report['metrics']['assigned'], 'label' => 'Assigned'],
                        ['value' => $report['metrics']['completed'], 'label' => 'Completed'],
                        ['value' => $report['metrics']['in_progress'], 'label' => 'In progress'],
                        ['value' => $assignment->assign_type === 'section'
                            ? ($assignment->section_type === 'reading_writing' ? ($report['metrics']['average_rw'] ? $report['metrics']['average_rw'] . ' / 800' : '—') : ($report['metrics']['average_math'] ? $report['metrics']['average_math'] . ' / 800' : '—'))
                            : ($report['metrics']['average_score'] ? $report['metrics']['average_score'] . ' / 1600' : '—'), 'label' => 'Average Score'],
                    ]" />

                    <!-- PINNED TAB BAR -->
                    <x-shell.tab-bar :tabs="[
                        ['key' => 'results', 'label' => 'Student results', 'count' => count($report['rows'])],
                        ['key' => 'analysis', 'label' => 'Question Analysis', 'count' => count($report['questionAnalysis'])],
                        ['key' => 'settings', 'label' => 'Settings'],
                    ]" />

                    <!-- RESULTS PANEL -->
                    <div class="section-panel" :class="{ 'active': activeTab === 'results' }">
                        <div class="sp-head">
                            <div>
                                <p>Best completed score represents each student; every attempt remains available.</p>
                            </div>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Status</th>
                                    <th>Attempts</th>
                                    <th>Best score</th>
                                    @if($assignment->assign_type !== 'section')
                                        <th>R&amp;W Score</th>
                                        <th>Math Score</th>
                                    @endif
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($report['rows'] as $row)
                                    <tr>
                                        <td class="name-cell">
                                            <x-ui.person-cell :name="$row['recipient']->student->name"
                                                :email="$row['recipient']->student->email"
                                                :initials="$row['recipient']->student->initials" />
                                        </td>
                                        <td>
                                            <span class="status-pill {{ $row['recipient']->status === 'withdrawn' ? 'pending' : ($row['in_progress'] ? 'pending' : ($row['best'] ? 'ok' : 'pending')) }}">
                                                <span class="d"></span>
                                                {{ $row['recipient']->status === 'withdrawn' ? 'Withdrawn' : ($row['in_progress'] ? 'In progress' : ($row['best'] ? ($row['late'] ? 'Completed late' : 'Completed') : 'Not started')) }}
                                            </span>
                                        </td>
                                        <td>{{ $row['completed_count'] }} / {{ $assignment->attempt_limit }}</td>
                                        <td>
                                            @if ($row['best'])
                                                @if ($assignment->assign_type === 'section')
                                                    <strong>{{ $assignment->section_type === 'reading_writing' ? ($row['best']->score_reading_writing ?? '—') : ($row['best']->score_math ?? '—') }} / 800</strong>
                                                @else
                                                    <strong>{{ $row['best']->total_score ?? '—' }} / 1600</strong>
                                                @endif
                                                <small style="display: block; font-size: 11px; color: var(--ink-soft); margin-top: 2px;">
                                                    Correct: {{ $row['best']->correct_answers_count }}/{{ $row['best']->total_questions_count }}
                                                </small>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        @if($assignment->assign_type !== 'section')
                                            <td>{{ $row['best']?->score_reading_writing ?? '—' }}</td>
                                            <td>{{ $row['best']?->score_math ?? '—' }}</td>
                                        @endif
                                        <td class="text-right">
                                            @if ($row['attempts']->isNotEmpty())
                                                <a href="{{ route('teacher.assignments.students.show', [$assignment, $row['recipient']->student]) }}" class="link-action">
                                                    View attempts
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $assignment->assign_type === 'section' ? 5 : 7 }}" class="empty-row">No recipients yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- ANALYSIS PANEL -->
                    @php
                        $analysisList = $report['questionAnalysis'] ?? [];
                        $analysisJsonData = collect($analysisList)->map(function($item) {
                            $q = $item['question'];
                            return [
                                'id' => $q->id,
                                'position' => $item['position'],
                                'module_label' => $item['module_label'],
                                'module_number' => $item['module_number'],
                                'difficulty_level' => $item['difficulty_level'],
                                'difficulty' => strtolower($q->difficulty ?? 'standard'),
                                'domain' => $q->skill_domain ?? 'General Domain',
                                'section_label' => $item['section_label'],
                                'question_type' => $q->question_type,
                                'stem' => $q->stem,
                                'passage_title' => $q->passage?->title,
                                'passage_body' => $q->passage?->body,
                                'correct_answer' => $item['correct_answer'],
                                'incorrect_rate' => $item['incorrect_rate'],
                                'total_presented' => $item['total_presented'],
                                'correct_count' => $item['correct_count'],
                                'incorrect_count' => count($item['incorrect_students']),
                                'choices' => $q->answerChoices->map(fn($c) => [
                                    'label' => $c->label,
                                    'content' => $c->content,
                                    'is_correct' => (bool)$c->is_correct,
                                ])->values()->all(),
                                'spr_answers' => $q->sprCorrectAnswers->pluck('answer')->all(),
                                'explanation' => [
                                    'text' => $q->explanation?->explanation,
                                    'strategy_tip' => $q->explanation?->strategy_tip,
                                    'common_mistakes' => $q->explanation?->common_mistakes,
                                ],
                                'students' => collect($item['incorrect_students'])->map(fn($st) => [
                                    'id' => $st['student']->id,
                                    'name' => $st['student']->name,
                                    'email' => $st['student']->email,
                                    'initials' => $st['student']->initials ?? strtoupper(substr($st['student']->name, 0, 1)),
                                    'status' => $st['status'],
                                    'selected' => $st['selected'],
                                ])->values()->all(),
                            ];
                        })->values();

                        $uniqueModules = collect($analysisJsonData)->pluck('module_label')->unique()->values();
                        $uniqueDomains = collect($analysisJsonData)->pluck('domain')->filter()->unique()->values();
                        $avgErrorRate = count($analysisJsonData) > 0 ? (int) round(collect($analysisJsonData)->avg('incorrect_rate')) : 0;
                        $mostChallenging = collect($analysisJsonData)->sortByDesc('incorrect_rate')->first();
                    @endphp

                    <div class="section-panel" :class="{ 'active': activeTab === 'analysis' }" x-data="{
                        searchQuery: '',
                        selectedModule: 'all',
                        selectedDifficulty: 'all',
                        selectedDomain: 'all',
                        questions: {{ Js::from($analysisJsonData) }},
                        activeQuestion: null,

                        openInspection(q) {
                            this.activeQuestion = q;
                            $dispatch('open-modal', 'modal-inspect-question');
                            $nextTick(() => {
                                if (window.smartRenderMath) {
                                    window.smartRenderMath();
                                }
                            });
                        },

                        get filteredQuestions() {
                            return this.questions.filter(q => {
                                const query = this.searchQuery.toLowerCase().trim();
                                const matchesSearch = !query ||
                                    q.stem.toLowerCase().includes(query) ||
                                    ('q' + q.position).includes(query) ||
                                    q.domain.toLowerCase().includes(query) ||
                                    q.correct_answer.toLowerCase().includes(query);

                                const matchesModule = this.selectedModule === 'all' || q.module_label === this.selectedModule;
                                const matchesDifficulty = this.selectedDifficulty === 'all' || q.difficulty === this.selectedDifficulty;
                                const matchesDomain = this.selectedDomain === 'all' || q.domain === this.selectedDomain;

                                return matchesSearch && matchesModule && matchesDifficulty && matchesDomain;
                            });
                        }
                    }">

                        <!-- Summary Stat Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="p-4 rounded-xl bg-slate-900/4 border border-slate-900/10 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-indigo-500/10 text-indigo-700 flex items-center justify-center font-bold text-lg">
                                    {{ count($analysisJsonData) }}
                                </div>
                                <div>
                                    <div class="text-2xs font-bold text-slate-500 uppercase tracking-wider">Questions Analyzed</div>
                                    <div class="text-xs font-semibold text-slate-800">Across {{ count($uniqueModules) }} {{ Str::plural('module', count($uniqueModules)) }}</div>
                                </div>
                            </div>

                            <div class="p-4 rounded-xl bg-slate-900/4 border border-slate-900/10 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-amber-500/10 text-amber-700 flex items-center justify-center font-bold text-lg">
                                    {{ $avgErrorRate }}%
                                </div>
                                <div>
                                    <div class="text-2xs font-bold text-slate-500 uppercase tracking-wider">Average Error Rate</div>
                                    <div class="text-xs font-semibold text-slate-800">Overall class difficulty</div>
                                </div>
                            </div>

                            <div class="p-4 rounded-xl bg-slate-900/4 border border-slate-900/10 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-rose-500/10 text-rose-700 flex items-center justify-center font-bold text-sm">
                                    {{ $mostChallenging ? $mostChallenging['incorrect_rate'] . '%' : '—' }}
                                </div>
                                <div>
                                    <div class="text-2xs font-bold text-slate-500 uppercase tracking-wider">Most Challenging Item</div>
                                    <div class="text-xs font-semibold text-slate-800 truncate max-w-[180px]">
                                        {{ $mostChallenging ? $mostChallenging['module_label'] . ' · Q' . $mostChallenging['position'] : 'None' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filter & Search Toolbar -->
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                            <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[280px]">
                                <div class="relative flex-1 min-w-[160px]">
                                    <input type="text" x-model="searchQuery" placeholder="Search stem, question #, domain..." class="w-full text-xs py-1.5 pl-7 pr-3 rounded-lg border border-slate-300 focus:outline-none focus:border-indigo-500">
                                    <svg class="w-3.5 h-3.5 absolute left-2 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>

                                <select x-model="selectedModule" class="text-xs py-1.5 px-2 rounded-lg border border-slate-300 bg-white text-slate-700 focus:outline-none">
                                    <option value="all">All Modules</option>
                                    @foreach($uniqueModules as $mod)
                                        <option value="{{ $mod }}">{{ $mod }}</option>
                                    @endforeach
                                </select>

                                <select x-model="selectedDifficulty" class="text-xs py-1.5 px-2 rounded-lg border border-slate-300 bg-white text-slate-700 focus:outline-none">
                                    <option value="all">All Difficulties</option>
                                    <option value="easy">Easy</option>
                                    <option value="medium">Medium</option>
                                    <option value="hard">Hard</option>
                                </select>

                                <select x-model="selectedDomain" class="text-xs py-1.5 px-2 rounded-lg border border-slate-300 bg-white text-slate-700 focus:outline-none">
                                    <option value="all">All Domains</option>
                                    @foreach($uniqueDomains as $dom)
                                        <option value="{{ $dom }}">{{ $dom }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="text-xs text-slate-500 font-medium">
                                Showing <strong class="text-slate-800" x-text="filteredQuestions.length"></strong> of {{ count($analysisJsonData) }} questions
                            </div>
                        </div>

                        <!-- Questions Table -->
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 22%;">Item &amp; Attributes</th>
                                    <th style="width: 38%;">Stem Preview</th>
                                    <th style="width: 10%;">Answer</th>
                                    <th style="width: 18%;">Incorrect Rate</th>
                                    <th class="text-right" style="width: 12%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="q in filteredQuestions" :key="q.id">
                                    <tr>
                                        <td class="name-cell" style="vertical-align: top; padding: 12px 10px;">
                                            <div class="flex items-center gap-1.5 mb-1.5 flex-wrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-900 text-white font-mono font-bold text-xs" x-text="'Q' + q.position"></span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 text-slate-800 border border-slate-300 font-semibold text-2xs whitespace-nowrap" x-text="q.module_label"></span>
                                            </div>
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-3xs font-bold uppercase tracking-wider whitespace-nowrap"
                                                    :class="{
                                                        'bg-emerald-100 text-emerald-800 border border-emerald-300': q.difficulty === 'easy',
                                                        'bg-amber-100 text-amber-800 border border-amber-300': q.difficulty === 'medium' || q.difficulty === 'standard',
                                                        'bg-rose-100 text-rose-800 border border-rose-300': q.difficulty === 'hard'
                                                    }"
                                                    x-text="q.difficulty"></span>
                                                <span class="inline-flex items-center text-3xs font-medium text-slate-600 bg-slate-100/90 px-1.5 py-0.5 rounded border border-slate-200 truncate max-w-[130px]" :title="q.domain" x-text="q.domain"></span>
                                            </div>
                                        </td>
                                        <td style="vertical-align: top; padding: 12px 10px;">
                                            <div class="text-xs text-slate-800 font-medium leading-relaxed" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" x-text="q.stem.replace(/<[^>]*>?/gm, '').substring(0, 140)"></div>
                                        </td>
                                        <td style="vertical-align: top; padding: 12px 10px;">
                                            <span class="inline-flex items-center font-mono font-bold text-xs px-2.5 py-1 rounded bg-emerald-50 text-emerald-900 border border-emerald-200" x-text="q.correct_answer"></span>
                                        </td>
                                        <td style="vertical-align: top; padding: 12px 10px;">
                                            <div class="space-y-1.5">
                                                <div class="flex items-center justify-between text-xs gap-1">
                                                    <strong class="font-bold whitespace-nowrap text-xs" :class="{
                                                        'text-rose-600': q.incorrect_rate >= 50,
                                                        'text-amber-600': q.incorrect_rate >= 25 && q.incorrect_rate < 50,
                                                        'text-emerald-600': q.incorrect_rate < 25
                                                    }" x-text="q.incorrect_rate + '% incorrect'"></strong>
                                                    <span class="text-2xs text-slate-500 font-medium whitespace-nowrap" x-text="q.incorrect_count + '/' + q.total_presented + ' students'"></span>
                                                </div>
                                                <div class="w-full bg-slate-200/80 h-2 rounded-full overflow-hidden flex">
                                                    <div class="h-full transition-all duration-300"
                                                        :class="{
                                                            'bg-rose-500': q.incorrect_rate >= 50,
                                                            'bg-amber-500': q.incorrect_rate >= 25 && q.incorrect_rate < 50,
                                                            'bg-emerald-500': q.incorrect_rate < 25
                                                        }"
                                                        :style="'width: ' + q.incorrect_rate + '%'"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-right whitespace-nowrap" style="vertical-align: top; padding: 12px 10px;">
                                            <button type="button" @click="openInspection(q)" class="btn-sm-primary btn-compact text-2xs font-semibold whitespace-nowrap" style="padding: 5px 10px;">
                                                Inspect Question
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="filteredQuestions.length === 0">
                                    <tr>
                                        <td colspan="5" class="empty-row text-center py-8">
                                            No questions match the selected filters.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>

                        <!-- Modal for Question Inspection -->
                        <x-ui.modal id="modal-inspect-question" maxWidth="5xl">
                            <template x-if="activeQuestion">
                                <div class="space-y-6">
                                    <!-- Modal Header Bar -->
                                    <div class="pb-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="px-2 py-0.5 rounded bg-slate-900 text-white font-mono font-bold text-xs" x-text="'Question ' + activeQuestion.position"></span>
                                                <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-300" x-text="activeQuestion.module_label"></span>
                                            </div>
                                            <div class="text-xs text-slate-500 flex items-center gap-2">
                                                <span>Domain: <strong class="text-slate-800" x-text="activeQuestion.domain"></strong></span>
                                                <span>&middot;</span>
                                                <span>Difficulty: <strong class="capitalize text-slate-800" x-text="activeQuestion.difficulty"></strong></span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <div class="text-right">
                                                <div class="text-sm font-bold" :class="activeQuestion.incorrect_rate >= 50 ? 'text-rose-600' : 'text-amber-600'" x-text="activeQuestion.incorrect_rate + '% Incorrect'"></div>
                                                <div class="text-2xs text-slate-500" x-text="activeQuestion.incorrect_count + ' / ' + activeQuestion.total_presented + ' students missed'"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal Body: 2-Column Split -->
                                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                                        <!-- Left Column: Passage, Question & Choices & Explanation -->
                                        <div class="lg:col-span-7 space-y-4">
                                            <!-- Passage if available -->
                                            <template x-if="activeQuestion.passage_body">
                                                <div class="p-4 rounded-xl bg-amber-50/60 border border-amber-200/80 text-xs text-slate-800 space-y-2">
                                                    <div class="font-bold text-amber-900 text-2xs uppercase tracking-wider" x-text="activeQuestion.passage_title || 'Reading Passage'"></div>
                                                    <div class="leading-relaxed prose prose-sm max-w-none text-slate-800" x-html="activeQuestion.passage_body"></div>
                                                </div>
                                            </template>

                                            <!-- Question Stem -->
                                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                                                <div class="text-2xs font-bold text-slate-400 uppercase tracking-wider mb-2">Question Prompt</div>
                                                <div class="text-sm font-medium text-slate-900 leading-relaxed math-content" x-html="activeQuestion.stem"></div>
                                            </div>

                                            <!-- MCQ Options or SPR Answer -->
                                            <template x-if="activeQuestion.question_type === 'multiple_choice'">
                                                <div class="space-y-2">
                                                    <div class="text-2xs font-bold text-slate-400 uppercase tracking-wider">Answer Options</div>
                                                    <div class="space-y-2">
                                                        <template x-for="c in activeQuestion.choices" :key="c.label">
                                                            <div class="p-3 rounded-lg border flex items-start gap-3 transition-colors"
                                                                :class="c.is_correct ? 'bg-emerald-50 border-emerald-300 text-emerald-950 font-medium' : 'bg-white border-slate-200 text-slate-700'">
                                                                <span class="w-6 h-6 rounded-full flex items-center justify-center font-mono font-bold text-xs shrink-0"
                                                                    :class="c.is_correct ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'"
                                                                    x-text="c.label"></span>
                                                                <div class="text-xs pt-0.5 leading-relaxed flex-1 math-content" x-html="c.content"></div>
                                                                <template x-if="c.is_correct">
                                                                    <span class="text-2xs font-bold px-2 py-0.5 rounded bg-emerald-600 text-white shrink-0">Correct Choice</span>
                                                                </template>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>

                                            <template x-if="activeQuestion.question_type === 'student_produced_response'">
                                                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 space-y-1">
                                                    <div class="text-2xs font-bold text-emerald-800 uppercase tracking-wider">Accepted SPR Correct Answers</div>
                                                    <div class="font-mono font-bold text-sm text-emerald-950" x-text="activeQuestion.correct_answer"></div>
                                                </div>
                                            </template>

                                            <!-- Explanation & Strategy Tips -->
                                            <template x-if="activeQuestion.explanation && (activeQuestion.explanation.text || activeQuestion.explanation.strategy_tip)">
                                                <div class="p-4 rounded-xl bg-slate-900 text-white space-y-3">
                                                    <div class="text-2xs font-bold text-indigo-300 uppercase tracking-wider">Answer Explanation & Strategy</div>
                                                    <template x-if="activeQuestion.explanation.text">
                                                        <div class="text-xs text-slate-200 leading-relaxed math-content" x-html="activeQuestion.explanation.text"></div>
                                                    </template>
                                                    <template x-if="activeQuestion.explanation.strategy_tip">
                                                        <div class="p-3 rounded-lg bg-indigo-950/80 border border-indigo-700/50 text-xs text-indigo-200 space-y-1">
                                                            <div class="font-bold text-indigo-400 text-2xs uppercase">Strategy Tip</div>
                                                            <div x-html="activeQuestion.explanation.strategy_tip"></div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>

                                        <!-- Right Column: Student Response Breakdown -->
                                        <div class="lg:col-span-5 space-y-4">
                                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                                                <div class="flex items-center justify-between mb-3">
                                                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Student Responses</h4>
                                                    <span class="text-2xs font-bold px-2 py-0.5 rounded bg-rose-100 text-rose-800" x-text="activeQuestion.students.length + ' Missed'"></span>
                                                </div>

                                                <div class="space-y-2 max-h-[480px] overflow-y-auto pr-1">
                                                    <template x-for="st in activeQuestion.students" :key="st.id">
                                                        <div class="p-2.5 rounded-lg bg-white border border-slate-200 flex items-center justify-between text-xs">
                                                            <div class="flex items-center gap-2">
                                                                <div class="w-7 h-7 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-2xs" x-text="st.initials"></div>
                                                                <div>
                                                                    <div class="font-semibold text-slate-900" x-text="st.name"></div>
                                                                    <div class="text-3xs text-slate-400" x-text="st.email"></div>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <span class="text-2xs font-bold px-2 py-0.5 rounded"
                                                                    :class="st.status === 'omitted' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800'"
                                                                    x-text="st.status === 'omitted' ? 'Omitted' : 'Choice ' + st.selected"></span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <template x-if="activeQuestion.students.length === 0">
                                                        <div class="text-center py-6 text-xs text-emerald-600 font-semibold">
                                                            All students answered this question correctly!
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </x-ui.modal>
                    </div>

                    <!-- SETTINGS PANEL -->
                    <div class="section-panel" :class="{ 'active': activeTab === 'settings' }">
                        @if ($assignment->classroom->status === 'active')
                            <div class="settings-card" style="margin-bottom: 16px;">
                                <h3 class="settings-title lg">Edit details</h3>
                                <form method="POST" action="{{ route('teacher.assignments.update', $assignment) }}" class="flex flex-col gap-4 max-w-420">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="test_id" value="{{ $assignment->test_id }}">
                                    <label class="form-field-label">Title
                                        <input name="title" value="{{ $assignment->title }}" required maxlength="180" class="settings-input w-full mt-4">
                                    </label>
                                    <label class="form-field-label">Attempt limit
                                        <input type="number" name="attempt_limit" value="{{ $assignment->attempt_limit }}" min="1" max="10" required class="settings-input w-full mt-4">
                                    </label>
                                    <label class="form-field-label">Instructions
                                        <textarea name="instructions" rows="3" class="settings-input w-full mt-4" style="resize:vertical;">{{ $assignment->instructions }}</textarea>
                                    </label>
                                    <label class="form-field-label">Available from (Asia/Ho_Chi_Minh)
                                        <input type="datetime-local" name="available_at" value="{{ $assignment->available_at?->format('Y-m-d\\TH:i') }}" class="settings-input w-full mt-4">
                                    </label>
                                    <label class="form-field-label">Due at (Asia/Ho_Chi_Minh)
                                        <input type="datetime-local" name="due_at" value="{{ $assignment->due_at?->format('Y-m-d\\TH:i') }}" class="settings-input w-full mt-4">
                                    </label>
                                    <button type="submit" class="btn-sm-primary btn-px-16 self-start flex-none w-auto mt-4">Save settings</button>
                                </form>
                            </div>

                            <div class="settings-card danger-zone">
                                <h3 class="settings-title lg text-red-600">Danger Zone</h3>
                                <p class="text-xs text-slate-500 mb-12" style="font-size: 12px; margin-bottom: 12px; color: var(--ink-soft);">Once deleted, student results remain in the database but are detached from this classroom assignment.</p>
                                <form method="POST" action="{{ route('teacher.assignments.destroy', $assignment) }}"
                                    onsubmit="return confirm('Delete this assignment? Student attempt records will be detached.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-sm-primary btn-px-16" style="background-color: var(--red); border-color: var(--red);">Delete assignment</button>
                                </form>
                            </div>
                        @else
                            <div class="settings-card">
                                <p>Classroom is archived. Settings are locked.</p>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="ledger-header">
                        <h2>No Assignment Selected</h2>
                    </div>
                    <div class="empty-row" style="padding: 40px; text-align: center;">
                        Select an assignment from the sidebar list to view reports.
                    </div>
                @endif
            </div>

            <!-- CORKBOARD -->
            <x-shell.corkboard header="Pinned Actions">
                @if ($assignment)
                    <div x-show="activeTab === 'results'" class="cork-note">
                        <h3>Actions</h3>
                        <p class="digest-line">Download or print results for offline review.</p>
                        <div class="flex flex-col mt-8">
                            <a href="{{ route('teacher.assignments.export.csv', $assignment) }}" class="btn-sm-primary btn-pin text-center" style="text-decoration:none;">Export CSV</a>
                            <a href="{{ route('teacher.assignments.export.print', $assignment) }}" target="_blank" class="btn-sm-primary btn-pin text-center" style="text-decoration:none;">Print PDF</a>
                        </div>
                    </div>
                    
                    <div x-show="activeTab === 'analysis'" x-cloak class="cork-note">
                        <h3>Analysis Info</h3>
                        <p class="digest-line">Hover over rates to target learning gaps.</p>
                    </div>

                    <div x-show="activeTab === 'settings'" x-cloak class="cork-note">
                        <h3>Settings Note</h3>
                        <p class="digest-line">Changing values affects only future student attempts.</p>
                    </div>
                @else
                    <div class="cork-note">
                        <h3>Assignments</h3>
                        <p class="digest-line">Access class report data, item difficulty indices, and options.</p>
                    </div>
                @endif
            </x-shell.corkboard>
        </div>
    </div>
</x-layouts.student>
