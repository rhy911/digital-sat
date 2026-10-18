<x-layouts.student :user="auth()->user()" :title="$assignment ? $assignment->title : 'Assignments'" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/scores.css', 'resources/js/question-renderer.js'])
    @endpush

    @php
        $userInitials = auth()->user()?->initials ?? 'U';
    @endphp

    <div class="app-shell" x-data="{
        searchQuery: '',
        statusFilter: 'all',
        activeTab: (['results', 'analysis', 'settings'].includes(new URLSearchParams(location.search).get('tab')) ? new URLSearchParams(location.search).get('tab') : 'results'),
        analysisMounted: new URLSearchParams(location.search).get('tab') === 'analysis',
        init() {
            this.$watch('activeTab', (tab) => {
                if (tab === 'analysis') {
                    this.analysisMounted = true;
                }
                if (tab === 'settings') {
                    this.$nextTick(() => window.initDatePickers?.());
                }
            });
        }
    }">
        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="route('teacher.progress')" :avatar-label="$userInitials"
            :items="\App\Support\NavRail::teacher('reports')" />

        <!-- COLUMN 2: SIDEBAR LIST -->
        <x-shell.sidebar-list title="Assignments"
            count-text="{{ $assignments->where('status', 'published')->count() }} published · {{ $assignments->where('status', 'closed')->count() }} closed"
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
        <main class="ledger-pane no-corkboard" id="main-content">
            <x-ui.flash />

            <!-- MAIN LEDGER COLUMN -->
            <div class="binder-panel">
                @if ($assignment)
                    @php
                        $pillClass = match ($assignment->status) {
                            'published', 'active' => 'ok',
                            'closed', 'danger', 'overdue' => 'danger',
                            default => 'draft',
                        };
                    @endphp

                    <!-- MOBILE CONTEXT BAR -->
                    <div class="sm:hidden mb-4 p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between gap-3 text-xs">
                        <div class="flex items-center gap-2 truncate">
                            <x-ui.icon name="file-earmark-text" class="w-4 h-4 text-blue-700 shrink-0" />
                            <span class="font-bold text-slate-900 truncate">{{ $assignment->title }}</span>
                            <span class="status-pill {{ $pillClass }} text-3xs shrink-0"><span class="d"></span>{{ ucfirst($assignment->status) }}</span>
                        </div>
                        @if ($assignment->classroom)
                            <a href="{{ route('teacher.classes.show', $assignment->classroom) }}" class="text-blue-700 hover:text-blue-800 font-semibold shrink-0 flex items-center gap-1">
                                <span>{{ $assignment->classroom->name }}</span> &rarr;
                            </a>
                        @endif
                    </div>

                    <div class="ledger-header mb-5">
                        <div class="dh-left">
                            @if ($origin === 'workspace')
                                <a class="back-link text-xs font-semibold text-blue-700 hover:text-blue-800 transition-colors mb-1.5 inline-block no-underline" href="{{ route('teacher.assignments.index') }}">Back to assignments &amp; reports</a>
                            @endif
                            <div class="flex items-center gap-3 flex-wrap">
                                <h2>{{ $assignment->title }}</h2>
                                <span class="status-pill {{ $pillClass }}"><span class="d"></span>{{ ucfirst($assignment->status) }}</span>
                            </div>
                            <div class="dh-desc">
                                {{ $assignment->test->title }}{{ $assignment->sectionSuffix() }} &middot; Classroom: <strong>{{ $assignment->classroom->name }}</strong>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            @if ($assignment->classroom->status === 'active')
                                @if($assignment->status === 'published')
                                    <form method="POST" action="{{ route('teacher.assignments.close', $assignment) }}" onsubmit="return confirm('Close this assignment? Students will no longer be able to start new attempts.');">
                                        @csrf
                                        <button type="submit" class="btn-sm-ghost">Close</button>
                                    </form>
                                @elseif($assignment->status === 'closed')
                                    <form method="POST" action="{{ route('teacher.assignments.reopen', $assignment) }}">
                                        @csrf
                                        <button type="submit" class="btn-sm-ghost">Reopen</button>
                                    </form>
                                @elseif($assignment->status === 'draft')
                                    <form method="POST" action="{{ route('teacher.assignments.publish', $assignment) }}">
                                        @csrf
                                        <button type="submit" class="btn-sm-ghost">Publish</button>
                                    </form>
                                @endif
                            @endif
                            <a href="{{ route('teacher.assignments.export.csv', $assignment) }}" class="btn-sm-ghost inline-flex items-center gap-1.5" style="text-decoration: none;">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                <span>Export CSV</span>
                            </a>
                            <a href="{{ route('teacher.assignments.export.print', $assignment) }}" target="_blank" class="btn-sm-ghost inline-flex items-center gap-1.5" style="text-decoration: none;">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                <span>Print PDF</span>
                            </a>
                        </div>
                    </div>

                    <!-- OVERVIEW DETAILS CARD -->
                    <div class="bg-slate-50/70 border border-slate-200 rounded-xl p-4 sm:p-5 mb-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                            <div>
                                <div class="text-3xs uppercase font-bold text-slate-500 tracking-wider mb-1">Available</div>
                                <div class="font-semibold text-slate-800">
                                    {{ $assignment->available_at ? $assignment->available_at->format('M j, Y g:i A') : 'Immediately' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-3xs uppercase font-bold text-slate-500 tracking-wider mb-1">Due Date</div>
                                <div class="font-semibold text-slate-800">
                                    {{ $assignment->due_at ? $assignment->due_at->format('M j, Y g:i A') : 'No deadline' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-3xs uppercase font-bold text-slate-500 tracking-wider mb-1">Scope</div>
                                <div class="font-semibold text-slate-800">
                                    {{ $assignment->isSectionAssignment() ? $assignment->sectionLabel() . ' Only' : 'Full Test' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-3xs uppercase font-bold text-slate-500 tracking-wider mb-1">Clock Mode</div>
                                <div class="font-semibold text-slate-800">
                                    {{ $assignment->isTimedExam() ? 'Standard Timed' : 'Flexible Clock' }}
                                </div>
                            </div>
                        </div>
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
                        ['key' => 'results', 'label' => 'Student results', 'count' => $report['metrics']['assigned']],
                        ['key' => 'analysis', 'label' => 'Question Analysis', 'count' => $report['question_count'] ?? count($report['questionAnalysis'] ?? [])],
                        ['key' => 'settings', 'label' => 'Settings'],
                    ]" />

                    <!-- RESULTS PANEL -->
                    <div id="panel-results" role="tabpanel" aria-labelledby="tab-results" class="section-panel" :class="{ 'active': activeTab === 'results' }">
                        <livewire:teacher.assignment-student-results :assignment="$assignment" />
                    </div>

                    <!-- ANALYSIS PANEL -->
                    <div id="panel-analysis" role="tabpanel" aria-labelledby="tab-analysis" class="section-panel" :class="{ 'active': activeTab === 'analysis' }">
                        <template x-if="analysisMounted">
                            <div class="analysis-root"
                                 x-on:close-modal.window="if ($event.detail === 'modal-inspect-question') { isInspectModalOpen = false; }"
                                 @keydown.left.window="if (isInspectModalOpen && !['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) prevQuestion()"
                                 @keydown.right.window="if (isInspectModalOpen && !['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) nextQuestion()"
                                 @keydown.escape.window="if (isInspectModalOpen) closeInspection()"
                                 x-data="{
                                loadingAnalysis: false,
                                analysisLoaded: false,
                                analysisError: null,
                                questions: [],
                                stats: {
                                    total: 0,
                                    unique_modules: [],
                                    unique_domains: [],
                                    avg_error_rate: 0,
                                    most_challenging: null
                                },
                                questionSearchQuery: '',
                                selectedModule: 'all',
                                selectedDifficulty: 'all',
                                selectedDomain: 'all',
                                activeQuestion: null,
                                isInspectModalOpen: false,
                                inspectStudentFilter: 'all',
                                inspectStudentSearch: '',
                                passageCollapsed: false,

                                sortCol: 'position',
                                sortAsc: true,
                                sortBy(col) {
                                    if (this.sortCol === col) {
                                        this.sortAsc = !this.sortAsc;
                                    } else {
                                        this.sortCol = col;
                                        this.sortAsc = col === 'position';
                                    }
                                },

                                init() {
                                    this.loadAnalysis();
                                    this.$watch('filteredQuestions', () => {
                                        this.$nextTick(() => {
                                            if (window.smartRenderMath) window.smartRenderMath(this.$el);
                                        });
                                    });
                                },

                                async loadAnalysis() {
                                    if (this.analysisLoaded || this.loadingAnalysis) return;
                                    this.loadingAnalysis = true;
                                    this.analysisError = null;
                                    try {
                                        const res = await fetch('{{ route('teacher.assignments.analysis', $assignment) }}', {
                                            headers: { 'Accept': 'application/json' }
                                        });
                                        if (!res.ok) {
                                            throw new Error('HTTP ' + res.status);
                                        }
                                        const data = await res.json();
                                        if (!data || !Array.isArray(data.questions)) {
                                            throw new Error('Invalid analysis payload');
                                        }
                                        this.questions = data.questions;
                                        this.stats = data.stats || this.stats;
                                        this.analysisLoaded = true;
                                        this.analysisError = null;
                                        this.$nextTick(() => {
                                            if (window.smartRenderMath) window.smartRenderMath(this.$el);
                                        });
                                    } catch (err) {
                                        console.error('Failed to load analysis:', err);
                                        this.analysisError = 'Failed to load question analysis. Please try again.';
                                        this.analysisLoaded = false;
                                    } finally {
                                        this.loadingAnalysis = false;
                                    }
                                },

                        get currentQuestionIndex() {
                            if (!this.activeQuestion) return -1;
                            return this.filteredQuestions.findIndex(q => q.id === this.activeQuestion.id);
                        },

                        get hasPrevQuestion() {
                            return this.currentQuestionIndex > 0;
                        },

                        get hasNextQuestion() {
                            return this.currentQuestionIndex >= 0 && this.currentQuestionIndex < this.filteredQuestions.length - 1;
                        },

                        prevQuestion() {
                            if (this.hasPrevQuestion) {
                                this.openInspection(this.filteredQuestions[this.currentQuestionIndex - 1]);
                            }
                        },

                        nextQuestion() {
                            if (this.hasNextQuestion) {
                                this.openInspection(this.filteredQuestions[this.currentQuestionIndex + 1]);
                            }
                        },

                        jumpToQuestion(id) {
                            const target = this.filteredQuestions.find(q => q.id === Number(id));
                            if (target) {
                                this.openInspection(target);
                            }
                        },

                        async openInspection(q) {
                            if (!q.students) q.students = [];
                            if (!q.choices) q.choices = [];
                            this.activeQuestion = q;
                            this.inspectStudentFilter = 'all';
                            this.inspectStudentSearch = '';
                            this.isInspectModalOpen = true;
                            $dispatch('open-modal', 'modal-inspect-question');
                            if (!q.details_loaded && !q.loading_details) {
                                q.loading_details = true;
                                q.load_error = null;
                                try {
                                    const url = '{{ route('teacher.assignments.question-inspection', ['assignment' => $assignment, 'question' => '__QID__']) }}'.replace('__QID__', q.id);
                                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                                    if (!res.ok) {
                                        throw new Error('HTTP ' + res.status);
                                    }
                                    const details = await res.json();
                                    if (!details || typeof details !== 'object' || !Array.isArray(details.choices)) {
                                        throw new Error('Invalid inspection payload');
                                    }
                                    Object.assign(q, details);
                                    q.details_loaded = true;
                                    q.load_error = null;
                                } catch (err) {
                                    console.error('Failed to load inspection details:', err);
                                    q.load_error = 'Unable to load question details. Please try again.';
                                    q.details_loaded = false;
                                } finally {
                                    q.loading_details = false;
                                }
                            }
                            this.renderModalMath();
                        },

                        closeInspection() {
                            this.isInspectModalOpen = false;
                            $dispatch('close-modal', 'modal-inspect-question');
                        },

                        renderModalMath() {
                            this.$nextTick(() => {
                                const modalEl = document.getElementById('modal-inspect-question');
                                if (window.smartRenderMath) {
                                    window.smartRenderMath(modalEl || document.body);
                                }
                            });
                        },

                        get activeChoiceBreakdown() {
                            if (!this.activeQuestion) return { choices: [], omitted: { count: 0, pct: 0 } };
                            const total = this.activeQuestion.total_presented || 0;
                            const students = this.activeQuestion.students || [];

                            if (this.activeQuestion.question_type === 'multiple_choice') {
                                const choices = this.activeQuestion.choices || [];
                                const result = choices.map(c => {
                                    const count = students.filter(s => s.selected === c.label).length;
                                    const pct = total > 0 ? Math.round((count / total) * 100) : 0;
                                    return {
                                        label: c.label,
                                        content: c.content,
                                        is_correct: c.is_correct,
                                        count: count,
                                        pct: pct
                                    };
                                });

                                const omittedCount = students.filter(s => s.status === 'omitted' || s.selected === 'Omitted').length;
                                const omittedPct = total > 0 ? Math.round((omittedCount / total) * 100) : 0;

                                return {
                                    choices: result,
                                    omitted: { count: omittedCount, pct: omittedPct }
                                };
                            }

                            const omittedCount = students.filter(s => s.status === 'omitted' || s.selected === 'Omitted').length;
                            const omittedPct = total > 0 ? Math.round((omittedCount / total) * 100) : 0;

                            return {
                                choices: [],
                                omitted: { count: omittedCount, pct: omittedPct }
                            };
                        },

                        get filteredInspectStudents() {
                            if (!this.activeQuestion || !this.activeQuestion.students) return [];
                            let list = this.activeQuestion.students;
                            const filter = this.inspectStudentFilter;
                            const query = (this.inspectStudentSearch || '').toLowerCase().trim();

                            if (filter === 'correct') {
                                list = list.filter(s => s.status === 'correct');
                            } else if (filter === 'missed') {
                                list = list.filter(s => s.status === 'incorrect' || s.status === 'omitted');
                            } else if (filter === 'omitted') {
                                list = list.filter(s => s.status === 'omitted' || s.selected === 'Omitted');
                            } else if (['A', 'B', 'C', 'D'].includes(filter)) {
                                list = list.filter(s => s.selected === filter);
                            }

                            if (query) {
                                list = list.filter(s =>
                                    (s.name && s.name.toLowerCase().includes(query)) ||
                                    (s.email && s.email.toLowerCase().includes(query)) ||
                                    (s.selected && s.selected.toLowerCase().includes(query))
                                );
                            }

                            return list;
                        },

                        get filteredQuestions() {
                            return this.questions.filter(q => {
                                const query = this.questionSearchQuery.toLowerCase().trim();
                                const stemStr = (q.stem || '').toLowerCase();
                                const domainStr = (q.domain || '').toLowerCase();
                                const answerStr = (q.correct_answer || '').toLowerCase();

                                const matchesSearch = !query ||
                                    stemStr.includes(query) ||
                                    ('q' + (q.position || '')).includes(query) ||
                                    domainStr.includes(query) ||
                                    answerStr.includes(query);

                                const groupKey = `${q.section_label} — ${q.module_label}`;
                                const matchesModule = this.selectedModule === 'all' || q.module_label === this.selectedModule || groupKey === this.selectedModule;
                                const matchesDifficulty = this.selectedDifficulty === 'all' || q.difficulty === this.selectedDifficulty;
                                const matchesDomain = this.selectedDomain === 'all' || q.domain === this.selectedDomain;

                                return matchesSearch && matchesModule && matchesDifficulty && matchesDomain;
                            });
                        },

                        get groupedQuestions() {
                            const groups = {};
                            for (const q of this.filteredQuestions) {
                                const groupKey = `${q.section_label} — ${q.module_label}`;
                                if (!groups[groupKey]) {
                                    groups[groupKey] = {
                                        key: groupKey,
                                        title: groupKey,
                                        section: q.section_label,
                                        module: q.module_label,
                                        questions: []
                                    };
                                }
                                groups[groupKey].questions.push(q);
                            }
                            return Object.values(groups).map(g => {
                                const total = g.questions.length;
                                const avgRate = total > 0
                                    ? Math.round(g.questions.reduce((sum, q) => sum + (q.incorrect_rate || 0), 0) / total)
                                    : 0;
                                const sorted = [...g.questions].sort((a, b) => {
                                    const dir = this.sortAsc ? 1 : -1;
                                    if (this.sortCol === 'position') {
                                        return ((a.position || 0) - (b.position || 0)) * dir;
                                    }
                                    if (this.sortCol === 'incorrect_rate') {
                                        return ((a.incorrect_rate || 0) - (b.incorrect_rate || 0)) * dir;
                                    }
                                    return 0;
                                });
                                return {
                                    ...g,
                                    avgIncorrectRate: avgRate,
                                    questions: sorted
                                };
                            });
                        }
                    }">

                        <!-- Loading Indicator -->
                        <div x-show="loadingAnalysis" class="py-8 text-center text-xs text-slate-500 font-medium">
                            <svg class="animate-spin h-5 w-5 mx-auto mb-2 text-blue-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>Loading question analysis...</span>
                        </div>

                        <!-- Analysis Error State -->
                        <div x-show="analysisError && !loadingAnalysis" x-cloak class="p-6 mb-6 text-center rounded-xl border border-rose-200 bg-rose-50/80">
                            <p class="text-xs font-semibold text-rose-800 mb-3" x-text="analysisError"></p>
                            <button type="button" @click="loadAnalysis()" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-700 hover:bg-rose-800 text-white transition shadow-2xs cursor-pointer">
                                Retry
                            </button>
                        </div>

                        <div x-show="analysisLoaded && !analysisError">
                        <!-- Summary Stat Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="analysis-stat-card">
                                <div class="analysis-stat-card__badge accent" x-text="stats.total"></div>
                                <div>
                                    <div class="text-2xs font-bold uppercase tracking-wider text-muted">Questions Analyzed</div>
                                    <div class="text-xs font-semibold text-main" x-text="'Across ' + stats.unique_modules.length + ' ' + (stats.unique_modules.length === 1 ? 'module' : 'modules')"></div>
                                </div>
                            </div>

                            <div class="analysis-stat-card">
                                <div class="analysis-stat-card__badge amber" x-text="stats.avg_error_rate + '%'"></div>
                                <div>
                                    <div class="text-2xs font-bold uppercase tracking-wider text-muted">Average Error Rate</div>
                                    <div class="text-xs font-semibold text-main">Overall class difficulty</div>
                                </div>
                            </div>

                            <div class="analysis-stat-card">
                                <div class="analysis-stat-card__badge red" x-text="stats.most_challenging ? stats.most_challenging.incorrect_rate + '%' : '—'"></div>
                                <div>
                                    <div class="text-2xs font-bold uppercase tracking-wider text-muted">Most Challenging Item</div>
                                    <div class="text-xs font-semibold text-main truncate max-w-[180px]" x-text="stats.most_challenging ? stats.most_challenging.module_label + ' · Q' + stats.most_challenging.position : 'None'"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Filter & Search Toolbar -->
                        <div class="analysis-toolbar">
                            <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[280px]">
                                <div class="relative flex-1 min-w-[160px]">
                                    <input type="text" x-model="questionSearchQuery" placeholder="Search stem, question #, domain..." aria-label="Search stem, question #, or domain" class="analysis-toolbar__input w-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/20 transition-all">
                                    <svg class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>

                                <select x-model="selectedModule" aria-label="Filter by module" class="analysis-toolbar__select focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/20 transition-all">
                                    <option value="all">All Modules</option>
                                    <template x-for="mod in stats.unique_modules" :key="mod">
                                        <option :value="mod" x-text="mod"></option>
                                    </template>
                                </select>

                                <select x-model="selectedDifficulty" aria-label="Filter by difficulty" class="analysis-toolbar__select focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/20 transition-all">
                                    <option value="all">All Difficulties</option>
                                    <option value="easy">Easy</option>
                                    <option value="medium">Medium</option>
                                    <option value="hard">Hard</option>
                                </select>

                                <select x-model="selectedDomain" aria-label="Filter by domain" class="analysis-toolbar__select focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/20 transition-all">
                                    <option value="all">All Domains</option>
                                    <template x-for="dom in stats.unique_domains" :key="dom">
                                        <option :value="dom" x-text="dom"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="text-xs text-muted font-medium" aria-live="polite" aria-atomic="true">
                                Showing <strong class="text-main" x-text="filteredQuestions.length"></strong> of <span x-text="questions.length"></span> questions
                            </div>
                        </div>

                        <!-- Grouped Questions by Section-Module -->
                        <div class="space-y-6">
                            <template x-for="group in groupedQuestions" :key="group.key">
                                <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs bg-white">
                                    <!-- Section-Module Header Bar -->
                                    <div class="bg-slate-50/90 px-4 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2.5">
                                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="group.section === 'Math' ? 'bg-indigo-600' : 'bg-blue-600'"></span>
                                            <h3 class="font-bold text-xs sm:text-sm text-slate-800 tracking-tight" x-text="group.title"></h3>
                                            <span class="text-3xs font-semibold px-2 py-0.5 rounded-full bg-white text-slate-600 border border-slate-200" x-text="group.questions.length + (group.questions.length === 1 ? ' question' : ' questions')"></span>
                                        </div>
                                        <div class="text-xs text-slate-500 font-medium flex items-center gap-3">
                                            <span>Avg error rate: <strong :class="group.avgIncorrectRate >= 50 ? 'text-rose-700' : (group.avgIncorrectRate >= 25 ? 'text-amber-800' : 'text-emerald-800')" x-text="group.avgIncorrectRate + '%'"></strong></span>
                                        </div>
                                    </div>

                                    <!-- Table for this Section-Module -->
                                    <div class="overflow-x-auto">
                                        <table class="w-full !border-0 !rounded-none !shadow-none min-w-[620px]">
                                            <thead>
                                                <tr>
                                                    <th style="width: 22%;">
                                                        <button type="button" @click="sortBy('position')" class="inline-flex items-center gap-1 font-semibold text-xs text-slate-700 hover:text-slate-900 cursor-pointer">
                                                            <span>Item &amp; Attributes</span>
                                                            <svg x-show="sortCol === 'position'" class="w-3.5 h-3.5 transition-transform" :class="sortAsc ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                                        </button>
                                                    </th>
                                                    <th style="width: 38%;">Stem Preview</th>
                                                    <th style="width: 10%;">Answer</th>
                                                    <th style="width: 18%;">
                                                        <button type="button" @click="sortBy('incorrect_rate')" class="inline-flex items-center gap-1 font-semibold text-xs text-slate-700 hover:text-slate-900 cursor-pointer">
                                                            <span>Incorrect Rate</span>
                                                            <svg x-show="sortCol === 'incorrect_rate'" class="w-3.5 h-3.5 transition-transform" :class="sortAsc ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                                        </button>
                                                    </th>
                                                    <th class="text-right" style="width: 12%;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                <template x-for="q in group.questions" :key="q.id">
                                                    <tr @click="openInspection(q)" class="cursor-pointer hover:bg-slate-50/80 transition-colors">
                                                        <td class="name-cell" style="vertical-align: top; padding: 12px 10px;">
                                                            <div class="flex items-center gap-1.5 mb-1.5 flex-wrap">
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded font-mono font-bold text-xs bg-slate-800 text-white" x-text="'Q' + q.position"></span>
                                                                <span class="type-tag" x-text="q.module_label"></span>
                                                            </div>
                                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                                <span class="status-pill text-3xs font-bold uppercase tracking-wider"
                                                                    :class="{
                                                                        'ok': q.difficulty === 'easy',
                                                                        'pending': q.difficulty === 'medium' || q.difficulty === 'standard',
                                                                        'danger': q.difficulty === 'hard'
                                                                    }"
                                                                    x-text="q.difficulty"></span>
                                                                <span class="text-3xs font-medium text-muted truncate max-w-[130px]" :title="q.domain" x-text="q.domain"></span>
                                                            </div>
                                                        </td>
                                                        <td style="vertical-align: top; padding: 12px 10px;">
                                                            <div class="text-xs text-main font-medium leading-relaxed" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" x-html="q.stem"></div>
                                                        </td>
                                                        <td style="vertical-align: top; padding: 12px 10px;">
                                                            <span class="font-mono font-bold text-xs px-2.5 py-1 rounded bg-emerald-50 text-emerald-800 border border-emerald-300" x-text="q.correct_answer"></span>
                                                        </td>
                                                        <td style="vertical-align: top; padding: 12px 10px;">
                                                            <div class="space-y-1.5">
                                                                <div class="flex items-center justify-between text-xs gap-1">
                                                                    <strong class="font-bold whitespace-nowrap text-xs" :class="{
                                                                        'text-rose-700': q.incorrect_rate >= 50,
                                                                        'text-amber-800': q.incorrect_rate >= 25 && q.incorrect_rate < 50,
                                                                        'text-emerald-800': q.incorrect_rate < 25
                                                                    }" x-text="q.incorrect_rate + '% incorrect'"></strong>
                                                                    <span class="text-2xs text-muted font-medium whitespace-nowrap" x-text="q.incorrect_count + '/' + q.total_presented + ' students'"></span>
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
                                                            <button type="button" @click.stop="openInspection(q)" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:text-blue-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/40 rounded px-1.5 py-0.5 cursor-pointer" :aria-label="'Inspect question ' + q.position">
                                                                <span>Inspect</span> <span aria-hidden="true">&rarr;</span>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>

                            <template x-if="filteredQuestions.length === 0">
                                <div class="border border-slate-200 rounded-xl p-8 text-center bg-white text-slate-500 text-sm">
                                    No questions match the selected filters.
                                </div>
                            </template>
                        </div>
                        </div>

                        <!-- Modal for Question Inspection -->
                        <x-ui.modal id="modal-inspect-question" maxWidth="6xl">
                            <template x-if="activeQuestion">
                                <div class="space-y-5" id="modal-title-modal-inspect-question">
                                    <!-- Modal Header Bar -->
                                    <div class="bg-slate-50/95 -mx-5 -mt-5 sm:-mx-6 sm:-mt-6 p-4 sm:px-6 sm:py-3.5 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-2.5 flex-wrap">
                                            <!-- Question Badge -->
                                            <span class="px-2.5 py-1 rounded-md font-mono font-bold text-xs bg-slate-800 text-white shadow-2xs"
                                                  x-text="'Q' + activeQuestion.position"></span>

                                            <!-- Direct Question Jump Select -->
                                            <div class="relative inline-flex items-center">
                                                <select @change="jumpToQuestion($event.target.value)"
                                                        aria-label="Jump to question"
                                                        class="text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg px-2.5 py-1 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500/20 shadow-2xs cursor-pointer">
                                                    <template x-for="q in filteredQuestions" :key="q.id">
                                                        <option :value="q.id" :selected="q.id === activeQuestion.id"
                                                                x-text="'Q' + q.position + ' (' + q.module_label + ' · ' + q.incorrect_rate + '% err)'"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <span class="type-tag" x-text="activeQuestion.module_label"></span>
                                            <span class="status-pill text-3xs font-bold uppercase tracking-wider"
                                                  :class="{
                                                      'ok': activeQuestion.difficulty === 'easy',
                                                      'pending': activeQuestion.difficulty === 'medium' || activeQuestion.difficulty === 'standard',
                                                      'danger': activeQuestion.difficulty === 'hard'
                                                  }"
                                                  x-text="activeQuestion.difficulty"></span>
                                            <span class="text-3xs font-medium text-slate-500 truncate max-w-[170px]" :title="activeQuestion.domain" x-text="activeQuestion.domain"></span>
                                            <template x-if="activeQuestion.is_pretest">
                                                <span class="status-pill draft text-3xs font-bold uppercase tracking-wider" title="Pretest items do not count toward the score">Unscored pretest</span>
                                            </template>
                                        </div>

                                        <!-- Navigation Controls and Close Button -->
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-white text-slate-600 border border-slate-200 shadow-2xs hidden sm:inline-block"
                                                  x-text="'Item ' + (currentQuestionIndex + 1) + ' of ' + filteredQuestions.length"></span>

                                            <!-- Previous Button -->
                                            <button type="button"
                                                    @click="prevQuestion()"
                                                    :disabled="!hasPrevQuestion"
                                                    class="px-2.5 py-1 text-xs font-semibold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-35 disabled:cursor-not-allowed transition-colors flex items-center gap-1 shadow-2xs cursor-pointer"
                                                    title="Previous question (Left Arrow)">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                                </svg>
                                                <span class="hidden md:inline">Prev</span>
                                            </button>

                                            <!-- Next Button -->
                                            <button type="button"
                                                    @click="nextQuestion()"
                                                    :disabled="!hasNextQuestion"
                                                    class="px-2.5 py-1 text-xs font-semibold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-35 disabled:cursor-not-allowed transition-colors flex items-center gap-1 shadow-2xs cursor-pointer"
                                                    title="Next question (Right Arrow)">
                                                <span class="hidden md:inline">Next</span>
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </button>

                                            <!-- Close Button -->
                                            <button type="button"
                                                    @click="closeInspection()"
                                                    class="text-slate-400 hover:text-slate-700 hover:bg-slate-200/50 p-1.5 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/30 cursor-pointer ml-1"
                                                    aria-label="Close question inspection">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Modal Loading Indicator -->
                                    <template x-if="activeQuestion && activeQuestion.loading_details">
                                        <div class="py-20 text-center text-xs text-slate-500 font-medium">
                                            <svg class="animate-spin h-6 w-6 mx-auto mb-2 text-blue-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                            <span>Loading question content and student responses...</span>
                                        </div>
                                    </template>

                                    <!-- Modal Error Indicator -->
                                    <template x-if="activeQuestion && activeQuestion.load_error && !activeQuestion.loading_details">
                                        <div class="py-16 text-center p-6">
                                            <div class="p-4 max-w-md mx-auto rounded-xl border border-rose-200 bg-rose-50/80 text-rose-800">
                                                <p class="text-xs font-semibold mb-3" x-text="activeQuestion.load_error"></p>
                                                <button type="button" @click="activeQuestion.details_loaded = false; openInspection(activeQuestion)"
                                                        class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-700 hover:bg-rose-800 text-white transition shadow-2xs cursor-pointer">
                                                    Retry loading details
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Modal Body: 2-Column Split -->
                                    <div x-show="activeQuestion && !activeQuestion.loading_details && !activeQuestion.load_error" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                                        <!-- Left Column: Passage, Question & Choices & Explanation -->
                                        <div class="lg:col-span-7 space-y-4">
                                            <!-- Passage if available -->
                                            <template x-if="activeQuestion.passage_body">
                                                <div class="bg-amber-50/50 border border-amber-200/70 rounded-xl overflow-hidden shadow-2xs">
                                                    <div class="px-4 py-2 bg-amber-100/60 border-b border-amber-200/70 flex items-center justify-between gap-2">
                                                        <div class="flex items-center gap-2">
                                                            <svg class="w-3.5 h-3.5 text-amber-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                                            </svg>
                                                            <span class="text-xs font-bold uppercase tracking-wider text-amber-900" x-text="activeQuestion.passage_title || 'Reading Passage'"></span>
                                                        </div>
                                                        <button type="button" @click="passageCollapsed = !passageCollapsed" class="text-xs font-semibold text-amber-800 hover:text-amber-950 flex items-center gap-1 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-amber-500 rounded px-1.5 py-0.5 cursor-pointer">
                                                            <span x-text="passageCollapsed ? 'Expand' : 'Collapse'"></span>
                                                            <svg class="w-3.5 h-3.5 transition-transform" :class="passageCollapsed ? '-rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <div x-show="!passageCollapsed" class="p-4 max-h-60 overflow-y-auto leading-relaxed prose prose-sm max-w-none text-slate-800 math-content" x-html="activeQuestion.passage_body"></div>
                                                </div>
                                            </template>

                                            <!-- Question Stem -->
                                            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs">
                                                <div class="text-2xs font-bold text-slate-500 uppercase tracking-wider mb-2">Question Prompt</div>
                                                <div class="text-sm font-medium text-slate-900 leading-relaxed math-content" x-html="activeQuestion.stem"></div>
                                            </div>

                                            <!-- MCQ Options or SPR Answer -->
                                            <template x-if="activeQuestion.question_type === 'multiple_choice'">
                                                <div class="space-y-2">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-2xs font-bold text-slate-500 uppercase tracking-wider">Answer Options</span>
                                                        <span class="text-3xs text-slate-600">Click option to filter responses</span>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <template x-for="c in activeChoiceBreakdown.choices" :key="c.label">
                                                            <div role="button" tabindex="0"
                                                                 @click="inspectStudentFilter = (inspectStudentFilter === c.label ? 'all' : c.label)"
                                                                 @keydown.enter.prevent="inspectStudentFilter = (inspectStudentFilter === c.label ? 'all' : c.label)"
                                                                 @keydown.space.prevent="inspectStudentFilter = (inspectStudentFilter === c.label ? 'all' : c.label)"
                                                                 :aria-pressed="inspectStudentFilter === c.label ? 'true' : 'false'"
                                                                 class="p-3 rounded-xl border transition-all cursor-pointer select-none relative overflow-hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                                                 :class="{
                                                                     'bg-emerald-50/70 border-emerald-300 ring-2 ring-emerald-500/20': c.is_correct && inspectStudentFilter !== c.label,
                                                                     'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50/60': !c.is_correct && inspectStudentFilter !== c.label,
                                                                     'ring-2 ring-blue-600 bg-blue-50/50 border-blue-400 shadow-xs': inspectStudentFilter === c.label
                                                                 }">
                                                                <!-- Distribution fill bar -->
                                                                <div class="absolute inset-y-0 left-0 opacity-15 pointer-events-none transition-all duration-300"
                                                                     :class="c.is_correct ? 'bg-emerald-500' : 'bg-slate-400'"
                                                                     :style="'width: ' + c.pct + '%'"></div>

                                                                <div class="relative flex items-start gap-3">
                                                                    <span class="w-6 h-6 rounded-full flex items-center justify-center font-mono font-bold text-xs shrink-0 shadow-2xs"
                                                                          :class="c.is_correct ? 'bg-emerald-800 text-white' : 'bg-slate-100 text-slate-700 border border-slate-300'"
                                                                          x-text="c.label"></span>
                                                                    <div class="text-xs pt-0.5 leading-relaxed flex-1 math-content"
                                                                         :class="c.is_correct ? 'text-emerald-950 font-medium' : 'text-slate-800'"
                                                                         x-html="c.content"></div>
                                                                    <div class="flex items-center gap-2 shrink-0 ml-2">
                                                                        <span class="font-mono text-2xs font-semibold px-2 py-0.5 rounded border"
                                                                              :class="c.is_correct ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
                                                                              x-text="c.count + ' (' + c.pct + '%)'"></span>
                                                                        <template x-if="c.is_correct">
                                                                            <span class="text-2xs font-bold px-2 py-0.5 rounded bg-emerald-800 text-white shadow-2xs">Correct</span>
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>

                                            <template x-if="activeQuestion.question_type === 'student_produced_response'">
                                                <div class="bg-emerald-50/60 border border-emerald-300 rounded-xl p-4 space-y-2">
                                                    <div class="text-2xs font-bold uppercase tracking-wider text-emerald-800">Accepted Correct Answers (SPR)</div>
                                                    <div class="flex flex-wrap gap-2">
                                                        <template x-if="activeQuestion.spr_answers && activeQuestion.spr_answers.length > 0">
                                                            <template x-for="ans in activeQuestion.spr_answers" :key="ans">
                                                                <span class="px-2.5 py-1 bg-white border border-emerald-300 text-emerald-800 font-mono font-bold text-xs rounded-lg shadow-2xs" x-text="ans"></span>
                                                            </template>
                                                        </template>
                                                        <template x-if="!activeQuestion.spr_answers || activeQuestion.spr_answers.length === 0">
                                                            <span class="font-mono font-bold text-sm text-emerald-900" x-text="activeQuestion.correct_answer"></span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Explanation & Strategy Tips -->
                                            <template x-if="activeQuestion.explanation && (activeQuestion.explanation.text || activeQuestion.explanation.strategy_tip)">
                                                <div class="bg-indigo-50/50 border border-indigo-200/80 rounded-xl p-4 sm:p-5 text-slate-800 space-y-3">
                                                    <div class="flex items-center gap-2">
                                                        <svg class="w-4 h-4 text-indigo-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                                        </svg>
                                                        <span class="text-xs font-bold text-indigo-900 uppercase tracking-wider">Answer Explanation &amp; Rationale</span>
                                                    </div>
                                                    <template x-if="activeQuestion.explanation.text">
                                                        <div class="text-xs leading-relaxed text-slate-800 prose prose-sm max-w-none math-content" x-html="activeQuestion.explanation.text"></div>
                                                    </template>
                                                    <template x-if="activeQuestion.explanation.strategy_tip">
                                                        <div class="p-3.5 rounded-lg border border-amber-200 bg-amber-50/80 text-xs text-amber-950 space-y-1">
                                                            <div class="font-bold text-2xs uppercase tracking-wider text-amber-800 flex items-center gap-1.5">
                                                                <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                                <span>Test-Taking Strategy Tip</span>
                                                            </div>
                                                            <div class="leading-relaxed" x-html="activeQuestion.explanation.strategy_tip"></div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>

                                        <!-- Right Column: Student Response Breakdown & Analytics -->
                                        <div class="lg:col-span-5 space-y-4">
                                            <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 flex flex-col h-full shadow-2xs space-y-4">
                                                <!-- Header of breakdown -->
                                                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                                    <div>
                                                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 m-0">Student Responses</h4>
                                                        <div class="text-3xs text-slate-500 mt-0.5" x-text="((activeQuestion.students || []).length) + ' total student responses'"></div>
                                                    </div>
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="text-3xs font-bold px-2 py-0.5 rounded-full"
                                                              :class="activeQuestion.incorrect_rate >= 50 ? 'bg-rose-50 text-rose-800 border border-rose-300' : 'bg-emerald-50 text-emerald-800 border border-emerald-300'"
                                                              x-text="activeQuestion.incorrect_rate + '% Missed'"></span>
                                                    </div>
                                                </div>

                                                <!-- Segmented Distribution Bar (for MCQ) -->
                                                <template x-if="activeQuestion.question_type === 'multiple_choice' && activeChoiceBreakdown.choices">
                                                    <div class="space-y-1.5">
                                                        <div class="text-2xs font-semibold text-slate-600 flex justify-between">
                                                            <span>Choice Distribution</span>
                                                            <span class="text-3xs text-slate-600" x-text="activeQuestion.correct_count + ' correct · ' + activeQuestion.incorrect_count + ' missed'"></span>
                                                        </div>
                                                        <div class="h-3 w-full bg-slate-100 rounded-full overflow-hidden flex shadow-inner">
                                                            <template x-for="c in activeChoiceBreakdown.choices" :key="c.label">
                                                                <div x-show="c.pct > 0"
                                                                     role="button" tabindex="0"
                                                                     :style="'width: ' + c.pct + '%'"
                                                                     :title="'Choice ' + c.label + ': ' + c.count + ' students (' + c.pct + '%)'"
                                                                     :aria-label="'Filter by Choice ' + c.label + ': ' + c.count + ' students'"
                                                                     :aria-pressed="inspectStudentFilter === c.label ? 'true' : 'false'"
                                                                     class="h-full transition-all duration-300 relative group cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                                                     :class="c.is_correct ? 'bg-emerald-500' : 'bg-rose-400/80'"
                                                                     @keydown.enter.prevent="inspectStudentFilter = (inspectStudentFilter === c.label ? 'all' : c.label)"
                                                                     @keydown.space.prevent="inspectStudentFilter = (inspectStudentFilter === c.label ? 'all' : c.label)"
                                                                     @click="inspectStudentFilter = (inspectStudentFilter === c.label ? 'all' : c.label)">
                                                                </div>
                                                            </template>
                                                            <div x-show="activeChoiceBreakdown.omitted && activeChoiceBreakdown.omitted.pct > 0"
                                                                 role="button" tabindex="0"
                                                                 :style="'width: ' + activeChoiceBreakdown.omitted.pct + '%'"
                                                                 :title="'Omitted: ' + activeChoiceBreakdown.omitted.count + ' (' + activeChoiceBreakdown.omitted.pct + '%)'"
                                                                 :aria-label="'Filter by Omitted: ' + activeChoiceBreakdown.omitted.count + ' students'"
                                                                 :aria-pressed="inspectStudentFilter === 'omitted' ? 'true' : 'false'"
                                                                 class="h-full bg-slate-300 transition-all duration-300 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                                                 @keydown.enter.prevent="inspectStudentFilter = (inspectStudentFilter === 'omitted' ? 'all' : 'omitted')"
                                                                 @keydown.space.prevent="inspectStudentFilter = (inspectStudentFilter === 'omitted' ? 'all' : 'omitted')"
                                                                 @click="inspectStudentFilter = (inspectStudentFilter === 'omitted' ? 'all' : 'omitted')">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>

                                                <!-- Filter Pills -->
                                                <div class="flex flex-wrap items-center gap-1.5 text-xs font-semibold">
                                                    <button type="button" @click="inspectStudentFilter = 'all'"
                                                            :aria-pressed="inspectStudentFilter === 'all' ? 'true' : 'false'"
                                                            :class="inspectStudentFilter === 'all' ? 'bg-slate-800 text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                                            class="px-2.5 py-1 rounded-lg text-2xs transition-colors cursor-pointer">
                                                        All (<span x-text="(activeQuestion.students || []).length"></span>)
                                                    </button>
                                                    <button type="button" @click="inspectStudentFilter = 'correct'"
                                                            :aria-pressed="inspectStudentFilter === 'correct' ? 'true' : 'false'"
                                                            :class="inspectStudentFilter === 'correct' ? 'bg-emerald-800 text-white shadow-2xs' : 'bg-emerald-50 text-emerald-800 border border-emerald-300 hover:bg-emerald-100'"
                                                            class="px-2.5 py-1 rounded-lg text-2xs transition-colors cursor-pointer">
                                                        Correct (<span x-text="activeQuestion.correct_count"></span>)
                                                    </button>
                                                    <button type="button" @click="inspectStudentFilter = 'missed'"
                                                            :aria-pressed="inspectStudentFilter === 'missed' ? 'true' : 'false'"
                                                            :class="inspectStudentFilter === 'missed' ? 'bg-rose-700 text-white shadow-2xs' : 'bg-rose-50 text-rose-800 border border-rose-300 hover:bg-rose-100'"
                                                            class="px-2.5 py-1 rounded-lg text-2xs transition-colors cursor-pointer">
                                                        Missed (<span x-text="activeQuestion.incorrect_count"></span>)
                                                    </button>
                                                    <template x-if="activeQuestion.question_type === 'multiple_choice'">
                                                        <div class="flex items-center gap-1">
                                                            <template x-for="c in activeChoiceBreakdown.choices" :key="c.label">
                                                                <button type="button" @click="inspectStudentFilter = (inspectStudentFilter === c.label ? 'all' : c.label)"
                                                                        :aria-pressed="inspectStudentFilter === c.label ? 'true' : 'false'"
                                                                        :class="inspectStudentFilter === c.label ? 'bg-blue-600 text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'"
                                                                        class="px-2 py-0.5 rounded text-2xs font-mono transition-colors cursor-pointer"
                                                                        :title="'Filter by Choice ' + c.label">
                                                                    <span x-text="c.label + ' (' + c.count + ')'"></span>
                                                                </button>
                                                            </template>
                                                            <template x-if="activeChoiceBreakdown.omitted && activeChoiceBreakdown.omitted.count > 0">
                                                                <button type="button" @click="inspectStudentFilter = (inspectStudentFilter === 'omitted' ? 'all' : 'omitted')"
                                                                        :aria-pressed="inspectStudentFilter === 'omitted' ? 'true' : 'false'"
                                                                        :class="inspectStudentFilter === 'omitted' ? 'bg-slate-700 text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'"
                                                                        class="px-2 py-0.5 rounded text-2xs font-mono transition-colors cursor-pointer"
                                                                        :title="'Filter by Omitted (' + activeChoiceBreakdown.omitted.count + ')'">
                                                                    <span x-text="'Omitted (' + activeChoiceBreakdown.omitted.count + ')'"></span>
                                                                </button>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>

                                                <!-- Student search bar -->
                                                <div class="relative">
                                                    <input type="text" x-model="inspectStudentSearch" placeholder="Filter by student name, email, or answer..."
                                                           class="w-full text-xs pl-8 pr-3 py-1.5 border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all">
                                                    <svg class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                    </svg>
                                                </div>

                                                <!-- Student List (Scrollable) -->
                                                <div class="flex-1 overflow-y-auto max-h-[400px] space-y-2 pr-1">
                                                    <template x-for="st in filteredInspectStudents" :key="st.id">
                                                        <div class="p-2.5 rounded-lg border border-slate-200 bg-slate-50/70 hover:bg-white hover:border-slate-300 transition-colors flex items-center justify-between text-xs">
                                                            <div class="flex items-center gap-2.5 min-w-0">
                                                                <div class="w-7 h-7 rounded-full text-white bg-blue-700 flex items-center justify-center font-bold text-2xs shrink-0 shadow-2xs" x-text="st.initials"></div>
                                                                <div class="min-w-0">
                                                                    <div class="font-semibold text-slate-800 truncate" x-text="st.name"></div>
                                                                    <div class="text-3xs text-slate-500 truncate" x-text="st.email"></div>
                                                                </div>
                                                            </div>
                                                            <div class="shrink-0 ml-2">
                                                                <template x-if="st.status === 'correct'">
                                                                    <span class="inline-flex items-center gap-1 text-2xs font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                                        <span>✓</span>
                                                                        <span x-text="st.selected === 'Omitted' ? 'Correct' : st.selected"></span>
                                                                    </span>
                                                                </template>
                                                                <template x-if="st.status === 'incorrect'">
                                                                    <span class="inline-flex items-center gap-1 text-2xs font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 border border-rose-200">
                                                                        <span>✗</span>
                                                                        <span x-text="st.selected"></span>
                                                                    </span>
                                                                </template>
                                                                <template x-if="st.status === 'omitted'">
                                                                    <span class="text-2xs font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                                                                        Omitted
                                                                    </span>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </template>

                                                    <template x-if="filteredInspectStudents.length === 0">
                                                        <div class="text-center py-8 text-xs text-slate-500 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                                                            <p class="font-semibold m-0">No matching students found</p>
                                                            <p class="text-3xs text-slate-600 mt-1 m-0">Try changing your filter or search query</p>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal Footer Bar -->
                                    <div class="bg-slate-50/95 -mx-5 -mb-5 sm:-mx-6 sm:-mb-6 px-5 sm:px-6 py-3 border-t border-slate-200 flex items-center justify-between">
                                        <div class="text-2xs text-slate-500 font-medium hidden sm:block">
                                            Navigate with <kbd class="px-1.5 py-0.5 rounded border border-slate-300 bg-white font-mono text-3xs font-semibold text-slate-700">←</kbd> and <kbd class="px-1.5 py-0.5 rounded border border-slate-300 bg-white font-mono text-3xs font-semibold text-slate-700">→</kbd>, close with <kbd class="px-1.5 py-0.5 rounded border border-slate-300 bg-white font-mono text-3xs font-semibold text-slate-700">ESC</kbd>
                                        </div>
                                        <div class="flex items-center gap-2 ml-auto">
                                            <button type="button"
                                                    @click="closeInspection()"
                                                    class="px-4 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-2xs cursor-pointer">
                                                Close
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </x-ui.modal>
                            </div>
                        </template>
                    </div>

                    <!-- SETTINGS PANEL -->
                    <div id="panel-settings" role="tabpanel" aria-labelledby="tab-settings" class="section-panel" :class="{ 'active': activeTab === 'settings' }">
                        @if ($assignment->classroom->status === 'active')
                            <div class="settings-card" style="margin-bottom: 16px;">
                                <h3 class="settings-title lg">Edit details</h3>
                                <form method="POST" action="{{ route('teacher.assignments.update', $assignment) }}" class="flex flex-col gap-4 max-w-xl">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="test_id" value="{{ $assignment->test_id }}">
                                    <label class="form-field-label flex flex-col gap-1.5">Title
                                        <input name="title" value="{{ $assignment->title }}" required maxlength="180" class="settings-input w-full">
                                    </label>
                                    <label class="form-field-label flex flex-col gap-1.5">Attempt limit
                                        <input type="number" name="attempt_limit" value="{{ $assignment->attempt_limit }}" min="1" max="10" required class="settings-input w-full">
                                    </label>
                                    <label class="form-field-label flex flex-col gap-1.5">Instructions
                                        <textarea name="instructions" rows="3" class="settings-input w-full" style="resize:vertical;">{{ $assignment->instructions }}</textarea>
                                    </label>
                                    <div class="space-y-3 pt-3 border-t border-slate-200/70">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-1.5 font-bold text-2xs uppercase tracking-wider text-slate-700">
                                                <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-slate-500" />
                                                <span>Schedule &amp; Availability</span>
                                            </div>
                                            <span class="inline-flex items-center gap-1 text-3xs font-semibold text-slate-700 bg-slate-100 border border-slate-300 rounded-md px-1.5 py-0.5" title="All schedule dates and deadlines use this timezone">
                                                <x-ui.icon name="clock" class="w-3 h-3 text-slate-500" />
                                                <span>Asia/Ho_Chi_Minh</span>
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <div class="flex items-center justify-between mb-1">
                                                    <label id="label_settings_available_at" for="settings_available_at" class="font-semibold text-3xs uppercase tracking-wider text-slate-700">{{ __('classroom.available_from') }}</label>
                                                    <button type="button"
                                                        onclick="window.clearDatePicker?.('settings_available_at')"
                                                        aria-label="Clear available from date"
                                                        class="min-h-[28px] min-w-[36px] inline-flex items-center justify-end px-1.5 py-0.5 rounded text-3xs text-slate-600 hover:text-slate-900 font-medium transition cursor-pointer hover:bg-slate-100">
                                                        Clear
                                                    </button>
                                                </div>
                                                <div class="relative">
                                                    <input id="settings_available_at" type="text"
                                                        name="available_at"
                                                        value="{{ $assignment->available_at?->format('Y-m-d\\TH:i') }}"
                                                        class="datetime-picker w-full text-xs bg-white border border-slate-300 rounded-lg px-2.5 py-1.5"
                                                        placeholder="{{ __('classroom.open_immediately') }}"
                                                        autocomplete="off"
                                                        aria-labelledby="label_settings_available_at"
                                                        aria-describedby="desc_settings_available_at"
                                                        data-allow-past="true"
                                                        data-window-role="start"
                                                        data-window-pair="settings_due_at">
                                                </div>
                                                <p id="desc_settings_available_at" class="text-3xs text-slate-600 mt-1">Students can start after this time. Leave empty to open immediately.</p>
                                            </div>

                                            <div>
                                                <div class="flex items-center justify-between mb-1">
                                                    <label id="label_settings_due_at" for="settings_due_at" class="font-semibold text-3xs uppercase tracking-wider text-slate-700">{{ __('classroom.due_at') }}</label>
                                                    <button type="button"
                                                        onclick="window.clearDatePicker?.('settings_due_at')"
                                                        aria-label="Clear due at date"
                                                        class="min-h-[28px] min-w-[36px] inline-flex items-center justify-end px-1.5 py-0.5 rounded text-3xs text-slate-600 hover:text-slate-900 font-medium transition cursor-pointer hover:bg-slate-100">
                                                        Clear
                                                    </button>
                                                </div>
                                                <div class="relative">
                                                    <input id="settings_due_at" type="text"
                                                        name="due_at"
                                                        value="{{ $assignment->due_at?->format('Y-m-d\\TH:i') }}"
                                                        class="datetime-picker w-full text-xs bg-white border border-slate-300 rounded-lg px-2.5 py-1.5"
                                                        placeholder="{{ __('classroom.no_deadline') }}"
                                                        autocomplete="off"
                                                        aria-labelledby="label_settings_due_at"
                                                        aria-describedby="desc_settings_due_at"
                                                        data-allow-past="true"
                                                        data-window-role="end"
                                                        data-window-pair="settings_available_at">
                                                </div>
                                                <p id="desc_settings_due_at" class="text-3xs text-slate-600 mt-1">Submissions lock or mark late after this time. Leave empty for no deadline.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn-sm-primary btn-px-16 self-start flex-none w-auto mt-2">Save settings</button>
                                </form>
                            </div>

                            <div class="settings-card border border-rose-200 bg-rose-50/20 rounded-xl p-5">
                                <h3 class="settings-title lg text-rose-700 mb-1">Danger Zone</h3>
                                <p class="text-xs text-slate-600 mb-4">Once deleted, student results remain in the database but are detached from this classroom assignment.</p>
                                <form method="POST" action="{{ route('teacher.assignments.destroy', $assignment) }}"
                                    onsubmit="return confirm('Delete this assignment? Student attempt records will be detached.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-4 py-2 text-xs font-bold rounded-lg bg-rose-700 hover:bg-rose-800 text-white cursor-pointer transition shadow-xs">Delete assignment</button>
                                </form>
                            </div>
                        @else
                            <div class="settings-card">
                                <p>Classroom is archived. Settings are locked.</p>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="py-16 px-6 text-center max-w-lg mx-auto">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-blue-50 text-blue-700 flex items-center justify-center">
                            <x-ui.icon name="journal-open" class="w-8 h-8" />
                        </div>
                        <h2 class="text-xl font-bold text-slate-900 mb-2">No Assignment Selected</h2>
                        <p class="text-sm text-slate-600 mb-6 leading-relaxed">
                            @if ($assignments->isNotEmpty())
                                Select an assignment from the sidebar list to inspect student submissions, review difficulty indices, and edit assignment settings.
                            @else
                                You have not created any assignments yet. Go to your classes to create and publish assignments.
                            @endif
                        </p>
                        <div class="flex items-center justify-center gap-3 flex-wrap">
                            <a href="{{ route('teacher.classes.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-700 hover:bg-blue-800 text-white text-xs sm:text-sm font-bold transition shadow-xs">
                                <x-ui.icon name="people" class="w-4 h-4" />
                                <span>My Classes</span>
                            </a>
                            <a href="{{ route('teacher.workspace') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-bold transition border border-slate-300 shadow-2xs">
                                <x-ui.icon name="layout-sidebar-inset" class="w-4 h-4" />
                                <span>Class Workspace</span>
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </main>
    </div>
</x-layouts.student>
