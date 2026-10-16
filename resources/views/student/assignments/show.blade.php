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
        classFilter: '{{ request('classroom', 'all') }}',
        init() {
            this.$watch('classFilter', (val) => {
                const url = new URL(window.location.href);
                if (val && val !== 'all') {
                    url.searchParams.set('classroom', val);
                } else {
                    url.searchParams.delete('classroom');
                }
                window.history.replaceState({}, '', url.toString());
            });
        },
        assignmentUrl(baseRoute) {
            if (!this.classFilter || this.classFilter === 'all') {
                return baseRoute;
            }
            const separator = baseRoute.includes('?') ? '&' : '?';
            return `${baseRoute}${separator}classroom=${encodeURIComponent(this.classFilter)}`;
        }
    }">
        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="route('home')" :avatar-label="$userInitials"
            :items="\App\Support\NavRail::student('assignments')" />

        @php
            $itemsData = $assignments->map(function ($a) use ($assignment) {
                $attempts = $a->attempts;
                $itemRecipient = $a->recipients->first();
                $resolved = \App\Support\AssignmentStatusResolver::resolve($a, $attempts, $itemRecipient);
                $state = $resolved['state'];
                $status = match ($state) {
                    'Completed' => 'completed',
                    'Overdue' => 'overdue',
                    'Closed', 'Withdrawn' => 'closed',
                    default => 'active',
                };

                // Contextual meta text (e.g. Score or Due date)
                if ($state === 'Completed') {
                    $bestScore = $resolved['completed']->max('total_score');
                    $maxScore = $a->isSectionAssignment() ? ' / 800' : ' / 1600';
                    $contextMeta = $bestScore ? 'Score: ' . $bestScore . $maxScore : 'Completed';
                } elseif ($state === 'Overdue') {
                    $contextMeta = 'Due ' . ($a->due_at ? $a->due_at->format('M j') : 'past');
                } elseif ($a->due_at) {
                    $contextMeta = 'Due ' . $a->due_at->format('M j');
                } else {
                    $contextMeta = 'No deadline';
                }

                return [
                    'route' => route('student.assignments.show', ['assignment' => $a->ulid] + (request()->filled('classroom') ? ['classroom' => request('classroom')] : [])),
                    'base_route' => route('student.assignments.show', ['assignment' => $a->ulid]),
                    'name' => $a->title,
                    'subtitle' => $a->test->title . $a->sectionSuffix(),
                    'status' => $status,
                    'status_label' => $state,
                    'status_variant' => $resolved['variant'],
                    'class_id' => (string) $a->classroom_id,
                    'class_name' => $a->classroom->name,
                    'selected' => $assignment && $assignment->id === $a->id,
                    'meta' => [$contextMeta],
                ];
            });

            $filterCounts = [
                'all' => $itemsData->count(),
                'active' => $itemsData->where('status', 'active')->count(),
                'overdue' => $itemsData->where('status', 'overdue')->count(),
                'completed' => $itemsData->where('status', 'completed')->count(),
                'closed' => $itemsData->where('status', 'closed')->count(),
            ];
        @endphp

        <!-- COLUMN 2: SIDEBAR LIST -->
        <x-shell.sidebar-list title="{{ $classroom ? $classroom->name . ' assignments' : 'Assignments' }}"
            count-text="{{ $assignments->count() }} total"
            search-placeholder="Search assignments..."
            :classrooms="$classrooms"
            :filters="[
                ['value' => 'all', 'label' => 'All', 'count' => $filterCounts['all']],
                ['value' => 'active', 'label' => 'Active', 'count' => $filterCounts['active']],
                ['value' => 'overdue', 'label' => 'Overdue', 'count' => $filterCounts['overdue']],
                ['value' => 'completed', 'label' => 'Completed', 'count' => $filterCounts['completed']],
                ['value' => 'closed', 'label' => 'Closed', 'count' => $filterCounts['closed']],
            ]"
            :items="$itemsData->all()" />

        <!-- COLUMN 3: LEDGER PANE -->
        <main class="ledger-pane no-corkboard" id="main-content">
            <x-ui.flash />

            <!-- MAIN LEDGER COLUMN -->
            <div class="binder-panel">
                @if ($assignment)
                    @php
                        $attempts = $assignment->attempts;
                        $resolved = \App\Support\AssignmentStatusResolver::resolve($assignment, $attempts, $recipient);
                        $state = $resolved['state'];
                        $completed = $resolved['completed'];
                        $inProgress = $resolved['inProgress'];
                        $used = $resolved['used'];
                        $statusVariant = $resolved['variant'];
                        $maxAllowed = $resolved['maxAllowed'];
                        $canStart = $resolved['canStart'];
                        $pillClass = match ($statusVariant) {
                            'success' => 'ok',
                            'brand' => 'in-progress',
                            'danger' => 'danger',
                            default => 'pending',
                        };
                    @endphp

                    <!-- MOBILE CONTEXT BAR -->
                    <div class="sm:hidden mb-4 p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between gap-3 text-xs">
                        <div class="flex items-center gap-2 truncate">
                            <x-ui.icon name="file-earmark-text" class="w-4 h-4 text-blue-700 flex-shrink-0" />
                            <span class="font-bold text-slate-900 truncate">{{ $assignment->title }}</span>
                            <span class="status-pill {{ $pillClass }} text-3xs shrink-0"><span class="d"></span>{{ $state }}</span>
                        </div>
                        @if ($assignment->classroom)
                            <a href="{{ route('student.classes.show', $assignment->classroom) }}" class="text-blue-700 hover:text-blue-800 font-semibold shrink-0 flex items-center gap-1">
                                <span>{{ $assignment->classroom->name }}</span> &rarr;
                            </a>
                        @endif
                    </div>

                    <!-- LEDGER HEADER -->
                    <div class="ledger-header mb-5">
                        <div class="dh-left w-full">
                            @if ($assignment->classroom)
                                <a href="{{ route('student.classes.show', $assignment->classroom) }}" class="back-link inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:underline mb-2">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                    <span>Back to {{ $assignment->classroom->name }} assignments</span>
                                </a>
                            @endif
                            <div class="flex items-center gap-3 flex-wrap">
                                <h2 class="text-2xl font-extrabold text-slate-900 m-0">{{ $assignment->title }}</h2>
                                <span class="status-pill {{ $pillClass }}"><span class="d"></span>{{ $state }}</span>
                            </div>
                            <div class="dh-desc text-slate-500 text-xs sm:text-sm mt-1">
                                {{ $assignment->test->title }}{{ $assignment->sectionSuffix() }} &middot; Classroom: <strong>{{ $assignment->classroom->name }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- OVERVIEW DETAILS CARD -->
                    <div class="bg-slate-50/70 border border-slate-200 rounded-xl p-4 sm:p-5 mb-5">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
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
                                <div class="text-3xs uppercase font-bold text-slate-500 tracking-wider mb-1">Test Scope</div>
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

                    <!-- ATTEMPT STATS -->
                    <x-shell.stat-row :stats="[
                        ['value' => $maxAllowed, 'label' => 'Allowed attempts'],
                        ['value' => $used, 'label' => 'Attempts used'],
                        ['value' => $assignment->isSectionAssignment()
                            ? ($assignment->section_type === 'reading_writing' ? ($completed->max('score_reading_writing') ?: '—') : ($completed->max('score_math') ?: '—'))
                            : ($completed->max('total_score') ?: '—'), 'label' => 'Best score'],
                    ]" />

                    <!-- BONUS RETRY CALLOUT -->
                    @if (($recipient?->extra_attempts ?? 0) > 0)
                        <div class="p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs my-4 flex items-center gap-2">
                            <x-ui.icon name="award" class="w-4 h-4 text-emerald-700 flex-shrink-0" />
                            <span><strong>Bonus attempt granted!</strong> You have +{{ $recipient->extra_attempts }} bonus attempt(s) on this test.</span>
                        </div>
                    @endif

                    <!-- ACTIONS SECTION -->
                    <div class="settings-card my-5 flex flex-col gap-3">
                        <h3 class="settings-title text-sm font-bold text-slate-900">Start Practice</h3>
                        <p class="text-xs sm:text-sm text-slate-600">Ensure you have a stable internet connection before launching the test. The test will open in our adaptive browser engine.</p>

                        @if ($canStart)
                            <div>
                                <button type="button" @click="$dispatch('open-ready-modal', {
                                    title: '{{ $inProgress ? 'Ready to Resume Attempt?' : 'Ready to Start Attempt?' }}',
                                    testTitle: '{{ addslashes($assignment->title) }}',
                                    subtitle: '{{ addslashes($assignment->test->title . $assignment->sectionSuffix() . ' • Classroom: ' . $assignment->classroom->name) }}',
                                    actionRoute: '{{ route('student.assignments.start', $assignment) }}',
                                    inProgress: {{ $inProgress ? 'true' : 'false' }},
                                    attemptInfo: 'Attempt {{ $inProgress ? $used : ($used + 1) }} of {{ $maxAllowed }}'
                                })" class="btn-sm-primary btn-px-16 px-4 py-2 text-xs sm:text-sm font-semibold rounded-lg bg-blue-700 hover:bg-blue-800 text-white cursor-pointer transition shadow-xs">
                                    {{ $inProgress ? 'Resume attempt' : 'Start attempt ' . ($used + 1) }}
                                </button>
                            </div>
                        @else
                            <div class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-xs font-medium inline-flex items-center gap-2 self-start">
                                <x-ui.icon name="alert-triangle" class="w-4 h-4 text-amber-700 flex-shrink-0" />
                                <span>No new attempt is currently available for this assignment.</span>
                            </div>
                        @endif
                    </div>

                    <!-- INSTRUCTIONS SECTION -->
                    @if ($assignment->instructions)
                        <div class="settings-card mb-5">
                            <h3 class="settings-title text-sm font-bold text-slate-900 mb-1.5">Instructions from teacher</h3>
                            <p class="text-xs sm:text-sm text-slate-700 leading-relaxed whitespace-pre-line">{{ $assignment->instructions }}</p>
                        </div>
                    @endif

                    <!-- ATTEMPT HISTORY PANEL -->
                    <div class="mt-6">
                        <h3 class="text-base font-bold text-slate-900 mb-3">Attempt history</h3>
                        <div class="table-responsive-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Attempt</th>
                                        <th>Status</th>
                                        <th>Date / Time</th>
                                        <th>Score</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($attempts as $attempt)
                                        <tr>
                                            <td><strong>Attempt {{ $attempt->attempt_number }}</strong></td>
                                            <td>
                                                <span class="status-pill {{ $attempt->status === 'completed' ? 'ok' : ($attempt->status === 'in_progress' ? 'in-progress' : 'pending') }}">
                                                    <span class="d"></span>{{ ucfirst(str_replace('_', ' ', $attempt->status)) }}
                                                </span>
                                            </td>
                                            <td class="text-xs text-slate-600">
                                                {{ ($attempt->completed_at ?? $attempt->created_at)?->format('M j, Y g:i A') ?? '—' }}
                                            </td>
                                            <td>
                                                @if ($attempt->status === 'in_progress')
                                                    <span class="badge-in-progress inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                                        In progress...
                                                    </span>
                                                @else
                                                    <strong>
                                                        @if ($attempt->attempt_type === 'section')
                                                            {{ $attempt->section_type === 'reading_writing' ? ($attempt->score_reading_writing ?? '—') : ($attempt->score_math ?? '—') }} / 800
                                                        @else
                                                            {{ $attempt->total_score ?? '—' }} / 1600
                                                        @endif
                                                    </strong>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                @if ($attempt->status === 'in_progress')
                                                    <button type="button" @click="$dispatch('open-ready-modal', {
                                                        title: 'Ready to Resume Attempt?',
                                                        testTitle: '{{ addslashes($assignment->title) }}',
                                                        subtitle: '{{ addslashes($assignment->test->title . $assignment->sectionSuffix() . ' • Classroom: ' . $assignment->classroom->name) }}',
                                                        actionRoute: '{{ route('student.assignments.start', $assignment) }}',
                                                        inProgress: true,
                                                        attemptInfo: 'Attempt {{ $attempt->attempt_number }} of {{ $maxAllowed }}'
                                                    })" class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-md bg-blue-700 hover:bg-blue-800 text-white cursor-pointer transition shadow-xs">
                                                        Resume attempt
                                                    </button>
                                                @elseif ($attempt->status === 'completed')
                                                    <a href="{{ route('student.scores.show', $attempt) }}" class="link-action font-semibold text-blue-700 hover:underline text-xs">
                                                        Review result
                                                    </a>
                                                @else
                                                    <span class="text-xs text-slate-400">Review locked</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @if (filled($attempt->teacher_feedback))
                                            <tr class="feedback-row">
                                                <td colspan="5" class="pt-0 pb-3 border-b border-slate-100 bg-slate-50/40">
                                                    <div class="teacher-feedback-bubble mx-2">
                                                        <div class="feedback-icon">
                                                            <x-ui.icon name="chat-left-quote" class="w-4 h-4 text-amber-800 flex-shrink-0 mt-0.5" />
                                                        </div>
                                                        <div class="flex-1">
                                                            <div class="flex items-center justify-between gap-2 flex-wrap mb-1">
                                                                <div class="feedback-author">
                                                                    Teacher Feedback &middot; {{ $attempt->feedbackAuthor?->name ?? 'Teacher' }}
                                                                </div>
                                                                @if ($attempt->feedback_at)
                                                                    <span class="text-3xs text-amber-800/80">{{ $attempt->feedback_at->format('M j, Y g:i A') }}</span>
                                                                @endif
                                                            </div>
                                                            <div class="feedback-text">{{ $attempt->teacher_feedback }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="5" class="empty-row py-8 text-center text-slate-500 text-sm">No attempts recorded yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="py-16 px-6 text-center max-w-lg mx-auto">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-blue-50 text-blue-700 flex items-center justify-center">
                            <x-ui.icon name="journal-open" class="w-8 h-8" />
                        </div>
                        <h2 class="text-xl font-bold text-slate-900 mb-2">No Assignment Selected</h2>
                        <p class="text-sm text-slate-600 mb-6 leading-relaxed">
                            @if ($assignments->isNotEmpty())
                                Select an assignment from the sidebar list to inspect instructions, schedule details, and launch practice attempts.
                            @else
                                You have no assignments published yet. You can practice with standard full-length tests or check your enrolled classes.
                            @endif
                        </p>
                        <div class="flex items-center justify-center gap-3 flex-wrap">
                            <a href="{{ route('home.practice') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-700 hover:bg-blue-800 text-white text-xs sm:text-sm font-bold transition shadow-xs">
                                <x-ui.icon name="journal-open" class="w-4 h-4" />
                                <span>Browse Test Library</span>
                            </a>
                            <a href="{{ route('student.classes.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-bold transition border border-slate-300 shadow-2xs">
                                <x-ui.icon name="people" class="w-4 h-4" />
                                <span>My Classes</span>
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </main>
    </div>

    <x-ui.ready-modal />
</x-layouts.student>
