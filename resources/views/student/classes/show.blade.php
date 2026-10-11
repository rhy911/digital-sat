<x-layouts.student :user="$user" :title="$classroom->name" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css'])
    @endpush

    @php
        $userInitials = auth()->user()?->initials ?? 'U';
        $activeMembers = $classroom->memberships->where('status', 'active');
    @endphp

    <div class="app-shell app-shell--no-list" x-data="{
        activeTab: new URLSearchParams(location.search).get('tab')
            || (new URLSearchParams(location.search).get('announce_page') ? 'stream' : null)
            || (new URLSearchParams(location.search).get('assign_page') ? 'assign' : null)
            || (new URLSearchParams(location.search).get('docs_page') ? 'materials' : null)
            || (new URLSearchParams(location.search).get('mates_page') ? 'classmates' : null)
            || 'stream'
    }">

        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="route('home')" :avatar-label="$userInitials"
            :items="\App\Support\NavRail::student('classes')" />

        <!-- COLUMN 2: FULL-WIDTH MAIN WORKSPACE -->
        <main class="ledger-pane no-corkboard">
            <x-ui.flash />

            <div class="binder-panel">
                <!-- TOP BAR: BREADCRUMB & CLASS SWITCHER -->
                <div class="workspace-top-bar">
                    <a href="{{ route('student.classes.index', ['all' => 1]) }}" class="back-nav-link">
                        <svg class="w-4 h-4 text-slate-500 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>{{ __('classroom.my_classes') }}</span>
                    </a>

                    <div class="class-switcher" x-data="{ switcherOpen: false }">
                        <button type="button" class="class-switcher-btn" @click="switcherOpen = !switcherOpen" aria-haspopup="true" :aria-expanded="switcherOpen ? 'true' : 'false'">
                            <span>{{ $classroom->name }}</span>
                            <span class="status-pill {{ $classroom->status === 'active' ? 'ok' : 'draft' }}">
                                <span class="d"></span>{{ ucfirst($classroom->status) }}
                            </span>
                            <svg class="chevron" width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div class="class-switcher-menu" x-show="switcherOpen" @click.outside="switcherOpen = false" x-cloak>
                            <div class="class-switcher-header">Switch Class</div>
                            <div class="class-switcher-list">
                                @foreach ($classrooms as $c)
                                    <a href="{{ route('student.classes.show', $c) }}" class="class-switcher-item {{ $c->id === $classroom->id ? 'is-active' : '' }}">
                                        <span class="class-switcher-item-name">{{ $c->name }}</span>
                                        <span class="class-switcher-item-meta">{{ $c->assignments_count }} assignments</span>
                                    </a>
                                @endforeach
                            </div>
                            <div class="class-switcher-footer">
                                <a href="{{ route('student.classes.index', ['all' => 1]) }}" class="class-switcher-all">View All My Classes →</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STAT ROW -->
                <x-shell.stat-row :stats="[
                    ['value' => $completedCount . '/' . $classroom->assignments->count(), 'label' => __('classroom.stat_completed')],
                    ['value' => $latestScore ?? '—', 'label' => __('classroom.stat_latest_score')],
                    ['value' => $classroom->documents_count + $classroom->exclusiveTests->count(), 'label' => __('classroom.stat_documents')],
                    ['value' => $activeMembers->count(), 'label' => __('classroom.stat_classmates')],
                ]" />

                <!-- 4 LMS TABS + SETTINGS -->
                <x-shell.tab-bar :tabs="[
                    ['key' => 'stream', 'label' => 'Stream', 'icon' => 'inbox', 'count' => $announcementsPage->total()],
                    ['key' => 'assign', 'label' => 'Assignments', 'icon' => 'file-text', 'count' => $classroom->assignments->count()],
                    ['key' => 'materials', 'label' => 'Class Materials', 'icon' => 'journal-open', 'count' => $classroom->documents_count + $classroom->exclusiveTests->count()],
                    ['key' => 'classmates', 'label' => 'Classmates', 'icon' => 'people', 'count' => $activeMembers->count()],
                    ['key' => 'settings', 'label' => 'Settings', 'icon' => 'sliders'],
                ]" />

                <!-- TAB 1: STREAM PANEL -->
                <div id="panel-stream" class="section-panel" role="tabpanel" aria-labelledby="tab-stream"
                    :class="{ 'active': activeTab === 'stream' || activeTab === 'announce' }">
                    @if (filled($classroom->note?->body))
                        <div class="pinned-note-card mb-6">
                            <div class="flex items-center gap-2 font-bold text-sm text-slate-900 mb-1.5">
                                <x-ui.icon name="pin" class="w-4 h-4 text-brand" />
                                <span>{{ __('classroom.teacher_note_header') }}</span>
                            </div>
                            <p class="digest-line whitespace-pre-wrap">{{ $classroom->note->body }}</p>
                        </div>
                    @endif

                    @if ($dueSoon->isNotEmpty())
                        <div class="mb-6 p-4 rounded-xl bg-amber-50/70 border border-amber-200">
                            <div class="text-xs font-bold uppercase tracking-wider text-amber-900 mb-2.5 flex items-center gap-1.5">
                                <x-ui.icon name="zap" class="w-3.5 h-3.5 text-amber-700" />
                                <span>Due Soon</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                @foreach ($dueSoon as $assignment)
                                    @php $status = $assignmentStatuses[$assignment->id] ?? null; @endphp
                                    <div class="p-3.5 bg-white rounded-lg border border-amber-200 shadow-xs flex flex-col justify-between">
                                        <div>
                                            <div class="text-xs font-bold text-slate-900 truncate" title="{{ $assignment->title }}">{{ $assignment->title }}</div>
                                            <div class="text-2xs text-amber-800 font-mono mt-1">Due {{ $assignment->due_at->format('M j, g:i A') }}</div>
                                        </div>
                                        <div class="mt-3">
                                            @if ($status && $status['canStart'])
                                                <button type="button" @click.stop="$dispatch('open-ready-modal', {
                                                    title: '{{ $status['inProgress'] ? 'Ready to Resume Attempt?' : 'Ready to Start Attempt?' }}',
                                                    testTitle: '{{ addslashes($assignment->title) }}',
                                                    subtitle: '{{ addslashes($assignment->test->title . $assignment->sectionSuffix()) }}',
                                                    actionRoute: '{{ route('student.assignments.start', $assignment) }}',
                                                    inProgress: {{ $status['inProgress'] ? 'true' : 'false' }},
                                                    attemptInfo: 'Attempt {{ $status['used'] + ($status['inProgress'] ? 0 : 1) }} of {{ $assignment->attempt_limit }}'
                                                })" class="px-3 py-1 text-xs font-bold rounded-md bg-amber-600 hover:bg-amber-700 text-white cursor-pointer transition">
                                                    {{ $status['inProgress'] ? 'Resume' : 'Start' }}
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @forelse ($announcementsPage as $announcement)
                        <x-classroom.announcement-card :announcement="$announcement" :classroom="$classroom" />
                    @empty
                        <div class="empty-grid-cell" style="margin-top: 8px;">
                            <strong>{{ __('classroom.no_announcements_yet') }}</strong>
                            <p style="margin-top: 4px;">{{ __('classroom.no_announcements_body') }}</p>
                        </div>
                    @endforelse

                    @if ($announcementsPage->hasPages())
                        <div class="mt-4">{{ $announcementsPage->links() }}</div>
                    @endif
                </div>

                <!-- TAB 2: ASSIGNMENTS PANEL -->
                <div id="panel-assign" class="section-panel" role="tabpanel" aria-labelledby="tab-assign"
                    :class="{ 'active': activeTab === 'assign' }">
                    <div class="sp-head mb-4">
                        <div>
                            <p>{{ __('classroom.student_assignments_desc') }}</p>
                        </div>
                    </div>

                    <div class="assignments-grid">
                        @forelse ($assignmentsPage as $assignment)
                            @php
                                $status = $assignmentStatuses[$assignment->id];
                                $recipient = $assignment->recipients->first();
                                $feedbackAttempt = $assignment->attempts->first(fn($a) => filled($a->teacher_feedback));
                                $isOverdue = $assignment->due_at && $assignment->due_at->isPast() && $status['state'] !== 'Completed';
                                $isDueSoon = $assignment->due_at && !$assignment->due_at->isPast() && $assignment->due_at->diffInHours(now()) <= 48 && $status['state'] !== 'Completed';
                            @endphp
                            <div class="index-card text-inherit no-underline relative group">
                                <div class="card-due {{ $isOverdue ? 'is-overdue' : ($isDueSoon ? 'is-due-soon' : '') }}">
                                    <x-ui.icon name="clock" class="w-3.5 h-3.5 inline text-slate-400" />
                                    <span>{{ $assignment->due_at ? $assignment->due_at->format('D, M j · g:i A') : __('classroom.no_deadline') }}</span>
                                </div>
                                <a href="{{ route('student.assignments.show', $assignment) }}" class="card-title text-inherit no-underline hover:text-blue-700 block focus:outline-none">
                                    {{ $assignment->title }}
                                </a>
                                <div class="card-sub truncate" title="{{ $assignment->test->title }}{{ $assignment->sectionSuffix() }}">
                                    {{ $assignment->test->title }}{{ $assignment->sectionSuffix() }}
                                </div>

                                <div class="flex items-center gap-2 flex-wrap my-2">
                                    <!-- Clock Mode Badge -->
                                    <span class="clock-mode-pill {{ $assignment->clock_mode === 'paused' ? 'practice' : 'strict' }} inline-flex items-center gap-1.5">
                                        <x-ui.icon :name="$assignment->clock_mode === 'paused' ? 'play' : 'clock'" class="w-3 h-3" />
                                        <span>{{ $assignment->clock_mode === 'paused' ? 'Practice Homework' : 'Timed Exam' }}</span>
                                    </span>
                                    <x-ui.status-badge :status="$status['variant']">{{ $status['state'] }}</x-ui.status-badge>
                                    @if ($status['state'] === 'Completed')
                                        <span class="mono score-value font-bold text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                            Score: {{ $status['completed']->max('total_score') ?: '—' }}
                                        </span>
                                    @endif
                                </div>

                                <!-- TEACHER FEEDBACK CALLOUT -->
                                @if ($feedbackAttempt)
                                    <div class="teacher-feedback-bubble">
                                        <x-ui.icon name="chat-left-quote" class="w-4 h-4 text-amber-800 flex-shrink-0 mt-0.5" />
                                        <div>
                                            <div class="feedback-author">
                                                Teacher Feedback ({{ $feedbackAttempt->feedbackAuthor?->name ?? 'Teacher' }}):
                                            </div>
                                            <div class="feedback-text">{{ $feedbackAttempt->teacher_feedback }}</div>
                                        </div>
                                    </div>
                                @endif

                                <!-- BONUS RETRY CALLOUT -->
                                @if (($recipient->extra_attempts ?? 0) > 0)
                                    <div class="p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs mt-2 flex items-center gap-2">
                                        <x-ui.icon name="award" class="w-4 h-4 text-emerald-700 flex-shrink-0" />
                                        <span><strong>Bonus attempt granted!</strong> You have +{{ $recipient->extra_attempts }} bonus attempt(s) on this test.</span>
                                    </div>
                                @endif

                                <div class="card-meta relative z-10" @click.stop>
                                    @if ($status['canStart'])
                                        <button type="button" @click.stop="$dispatch('open-ready-modal', {
                                            title: '{{ $status['inProgress'] ? 'Ready to Resume Attempt?' : 'Ready to Start Attempt?' }}',
                                            testTitle: '{{ addslashes($assignment->title) }}',
                                            subtitle: '{{ addslashes($assignment->test->title . $assignment->sectionSuffix() . ' • Classroom: ' . $classroom->name) }}',
                                            actionRoute: '{{ route('student.assignments.start', $assignment) }}',
                                            inProgress: {{ $status['inProgress'] ? 'true' : 'false' }},
                                            attemptInfo: 'Attempt {{ $status['used'] + ($status['inProgress'] ? 0 : 1) }} of {{ $assignment->attempt_limit + ($recipient->extra_attempts ?? 0) }}'
                                        })" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-blue-700 hover:bg-blue-800 text-white cursor-pointer transition shadow-xs">
                                            {{ $status['inProgress'] ? __('classroom.resume_attempt') : __('classroom.start_attempt') }}
                                        </button>
                                    @elseif ($status['state'] === 'Completed')
                                        <a href="{{ route('student.assignments.show', $assignment) }}" class="text-xs font-bold text-blue-700 hover:underline">
                                            {{ __('classroom.view_results') }} →
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400 font-medium">{{ __('classroom.no_attempts_left') }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="empty-grid-cell">
                                {{ __('classroom.no_assignments_yet') }}
                            </div>
                        @endforelse
                    </div>

                    @if ($assignmentsPage->hasPages())
                        <div class="mt-6">{{ $assignmentsPage->links() }}</div>
                    @endif
                </div>

                <!-- TAB 3: CLASS MATERIALS (STUDY UNITS) -->
                <div id="panel-materials" class="section-panel" role="tabpanel" aria-labelledby="tab-materials"
                    :class="{ 'active': activeTab === 'materials' || activeTab === 'docs' }">
                    <div class="sp-head mb-4">
                        <div>
                            <p>{{ __('classroom.student_docs_desc') }}</p>
                        </div>
                    </div>

                    @forelse ($units as $unitKey => $unit)
                        <div class="study-unit-card">
                            <div class="study-unit-header">
                                <div class="flex items-center gap-2">
                                    <x-ui.icon name="folder2-open" class="w-5 h-5 text-slate-500" />
                                    <h3 class="study-unit-title">{{ $unit['title'] }}</h3>
                                </div>
                                <span class="text-xs text-slate-500 font-semibold">
                                    {{ count($unit['tests']) + count($unit['documents']) }} items
                                </span>
                            </div>
                            <div class="study-unit-body">
                                <!-- EXCLUSIVE TESTS SECTION -->
                                @if (!empty($unit['tests']) && count($unit['tests']) > 0)
                                    <div class="mb-4">
                                        <div class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Class Exclusive Tests</div>
                                        <div class="exclusive-tests-list">
                                            @foreach ($unit['tests'] as $test)
                                                <div class="exclusive-test-card">
                                                    <div class="flex items-center gap-3">
                                                        <x-ui.icon name="file-text" class="w-5 h-5 text-blue-600" />
                                                        <div>
                                                            <div class="exclusive-test-title">{{ $test->title }}</div>
                                                            <div class="text-xs text-slate-500">
                                                                {{ $test->totalQuestions() }} questions · Exclusive practice test
                                                            </div>
                                                        </div>
                                                        <span class="exclusive-badge">Class Exclusive</span>
                                                    </div>
                                                    <a href="{{ route('home.practice') }}" class="btn-sm-primary btn-px-16 text-xs">
                                                        Practice Test →
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- DOCUMENTS SECTION -->
                                @if (!empty($unit['documents']) && count($unit['documents']) > 0)
                                    <div>
                                        <div class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Documents & Links</div>
                                        <div class="table-responsive-wrapper overflow-x-auto">
                                            <table>
                                            <thead>
                                                <tr>
                                                    <th>{{ __('classroom.col_document') }}</th>
                                                    <th>{{ __('classroom.col_type') }}</th>
                                                    <th class="num">{{ __('classroom.col_shared') }}</th>
                                                    <th class="text-right">{{ __('classroom.col_actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($unit['documents'] as $document)
                                                    @php
                                                        $ext = $document->isFile() ? strtolower(pathinfo($document->original_name ?? '', PATHINFO_EXTENSION)) : '';
                                                        $typeClass = match($ext) {
                                                            'pdf' => 'type-tag-pdf',
                                                            'doc', 'docx' => 'type-tag-doc',
                                                            'ppt', 'pptx' => 'type-tag-ppt',
                                                            default => $document->isFile() ? 'type-tag-doc' : 'type-tag-link',
                                                        };
                                                    @endphp
                                                    <tr>
                                                        <td class="name-cell">
                                                            <div class="font-bold text-sm text-slate-900">{{ $document->title }}</div>
                                                            <div class="text-xs text-slate-500 mt-0.5">
                                                                @if ($document->isFile())
                                                                    {{ Str::limit($document->original_name, 32) }} · {{ $document->displaySize() }}
                                                                @else
                                                                    {{ parse_url($document->external_url ?? '', PHP_URL_HOST) }}
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td><span class="type-tag {{ $typeClass }} font-bold text-2xs uppercase">{{ $document->isFile() ? strtoupper($ext ?: 'file') : 'LINK' }}</span></td>
                                                        <td class="num tabular-nums font-mono text-xs text-slate-600">{{ $document->created_at->format('d/m/Y') }}</td>
                                                        <td class="text-right">
                                                            <div class="flex items-center justify-end gap-2">
                                                                @if ($document->isFile())
                                                                    <a class="px-2.5 py-1 text-xs font-semibold rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 transition inline-flex items-center gap-1" href="{{ route('class-documents.open', $document) }}" target="_blank" rel="noopener">{{ __('classroom.action_open') }} <x-ui.icon name="external-link" class="w-3 h-3 text-slate-500" /></a>
                                                                    <a class="px-2.5 py-1 text-xs font-semibold rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 transition inline-flex items-center gap-1" href="{{ route('class-documents.download', $document) }}">{{ __('classroom.action_download') }} <x-ui.icon name="download" class="w-3 h-3 text-slate-500" /></a>
                                                                @else
                                                                    <a class="px-2.5 py-1 text-xs font-semibold rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 transition inline-flex items-center gap-1" href="{{ $document->external_url }}" target="_blank" rel="noopener noreferrer">{{ __('classroom.action_open_link') }} <x-ui.icon name="external-link" class="w-3 h-3 text-slate-500" /></a>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                @endif

                                @if ((empty($unit['tests']) || count($unit['tests']) === 0) && (empty($unit['documents']) || count($unit['documents']) === 0))
                                    <div class="p-4 text-center text-xs text-slate-500">
                                        No materials in this unit yet.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="empty-grid-cell">
                            <strong>{{ __('classroom.no_documents_shared_yet') }}</strong>
                            <p style="margin-top: 4px;">Your teaching team hasn't shared any study units or files yet.</p>
                        </div>
                    @endforelse
                </div>

                <!-- TAB 4: CLASSMATES & LEADERBOARD -->
                <div id="panel-classmates" class="section-panel" role="tabpanel" aria-labelledby="tab-classmates"
                    :class="{ 'active': activeTab === 'classmates' }">
                    @if (!empty($leaderboard))
                        <div class="mb-6">
                            <h3 class="font-bold text-sm text-slate-800 mb-3">{{ __('classroom.leaderboard_header') }}</h3>
                            <div class="podium-grid">
                                @foreach ($leaderboard as $index => $entry)
                                    @php
                                        $medalBadge = match($index) {
                                            0 => 'bg-amber-100 text-amber-900 border-amber-300 font-black',
                                            1 => 'bg-slate-200 text-slate-800 border-slate-300 font-bold',
                                            2 => 'bg-orange-100 text-orange-900 border-orange-300 font-bold',
                                            default => 'bg-slate-50 text-slate-600 border-slate-200 font-medium',
                                        };
                                    @endphp
                                    <div class="podium-card rank-{{ $index }}">
                                        <div class="podium-medal text-xs px-2 py-0.5 rounded border {{ $medalBadge }}">#{{ $index + 1 }}</div>
                                        <div class="podium-info">
                                            <div class="podium-name" title="{{ $entry['name'] }}">{{ $entry['name'] }}</div>
                                            <div class="podium-score">{{ $entry['score'] }} <span class="text-xs font-normal text-slate-500">pts</span></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="sp-head mb-4">
                        <div>
                            <p>{{ __('classroom.classmates_desc') }}</p>
                        </div>
                    </div>

                    <div class="classmates-grid">
                        @forelse ($classmatesPage as $classmate)
                            @php
                                $isSelf = $classmate->user_id === auth()->id();
                                $displayName = $classmate->display_name ?: $classmate->student->name;
                                $initials = substr($displayName, 0, 2);
                            @endphp
                            <div class="classmate-card">
                                <div class="classmate-avatar {{ $isSelf ? 'is-self' : '' }}">
                                    {{ strtoupper($initials) }}
                                </div>
                                <div class="classmate-details">
                                    <div class="classmate-name">
                                        <span>{{ $displayName }}</span>
                                        @if ($isSelf)
                                            <span class="badge-self">You</span>
                                        @endif
                                    </div>
                                    <div class="classmate-date">Joined {{ ($classmate->decided_at ?? $classmate->created_at)->format('M d, Y') }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-grid-cell col-span-full">
                                {{ __('classroom.no_classmates_yet') }}
                            </div>
                        @endforelse
                    </div>

                    @if ($classmatesPage->hasPages())
                        <div class="mt-4">{{ $classmatesPage->links() }}</div>
                    @endif
                </div>

                <!-- TAB 5: SETTINGS PANEL -->
                <div id="panel-settings" class="section-panel" role="tabpanel" aria-labelledby="tab-settings"
                    :class="{ 'active': activeTab === 'settings' }">
                    <div class="sp-head mb-4">
                        <div>
                            <p>{{ __('classroom.student_settings_desc') }}</p>
                        </div>
                    </div>

                    <div class="settings-section-container">
                        <div class="settings-card">
                            <h3 class="settings-title">{{ __('classroom.nickname_label') }}</h3>
                            <p class="settings-help">{{ __('classroom.nickname_help') }}</p>
                            <form method="POST" action="{{ route('student.classes.nickname.update', $classroom) }}" class="flex gap-3 max-w-md">
                                @csrf @method('PUT')
                                <label for="student_display_name" class="sr-only">{{ __('classroom.nickname_label') }}</label>
                                <input id="student_display_name" name="display_name" value="{{ $membership->display_name }}" maxlength="100"
                                    placeholder="{{ $user->name }}" class="settings-input flex-1">
                                <button type="submit" class="btn-sm-primary btn-px-16 flex-none w-auto">{{ __('classroom.save') }}</button>
                            </form>
                        </div>

                        <div class="settings-danger-card">
                            <div class="flex items-start gap-3">
                                <x-ui.icon name="alert-triangle" class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <h3 class="font-bold text-sm text-red-900 mb-1">{{ __('classroom.leave_class') }}</h3>
                                    <p class="text-xs text-red-700 leading-relaxed mb-4">{{ __('classroom.leave_class_desc') }}</p>
                                    <form method="POST" action="{{ route('student.classes.leave', $membership) }}"
                                        onsubmit="return confirm('{{ __('classroom.leave_class_confirm') }}')">
                                        @csrf
                                        <button type="submit" class="px-4 py-2 text-xs font-bold rounded-lg bg-red-600 hover:bg-red-700 text-white transition shadow-sm">
                                            {{ __('classroom.leave_class') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL: Join class by code -->
    <x-ui.modal id="modal-join-class" title="{{ __('classroom.join_modal_title') }}" maxWidth="sm">
        <form method="POST" action="{{ route('student.classes.join') }}" class="space-y-4 text-center">
            @csrf
            <div class="w-12 h-12 rounded-2xl bg-blue-50 border border-blue-200/80 text-brand flex items-center justify-center mx-auto shadow-sm">
                <x-ui.icon name="people" class="w-6 h-6" />
            </div>
            <div>
                <label for="modal_join_code" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">
                    {{ __('classroom.modal_join_code_label') }}
                </label>
                <input id="modal_join_code" type="text" name="join_code" minlength="8" maxlength="8" required autocomplete="off"
                    autocapitalize="characters" spellcheck="false" autofocus placeholder="AB12CD34"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-lg font-mono font-bold tracking-[0.25em] uppercase text-center text-slate-900 placeholder:text-slate-300 placeholder:tracking-normal focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/20 transition-all">
                <p class="text-xs text-slate-500 mt-2">
                    Enter the 8-character code shared by your teacher.
                </p>
            </div>
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-3 border-t border-slate-200/80">
                <button type="button" class="w-full sm:w-auto px-4 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-medium transition cursor-pointer"
                    x-on:click="$dispatch('close-modal', 'modal-join-class')">
                    {{ __('common.cancel') }}
                </button>
                <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-brand hover:bg-brand/90 active:bg-brand/95 text-white text-sm font-semibold shadow-sm transition cursor-pointer">
                    {{ __('classroom.request_to_join') }}
                </button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.ready-modal />
    <x-ui.confirm-delete-modal />
</x-layouts.student>
