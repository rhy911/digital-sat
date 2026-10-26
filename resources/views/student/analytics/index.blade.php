<x-layouts.student :user="$user" title="Home" header-type="none" ui="product" :body-class="$user->role === 'student' ? 'antialiased student-home-page' : 'antialiased teacher-workspace'">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/analytics.css', 'resources/css/student/study-home.css'])
    @endpush

    @php
        $displayName = $user->name ?? $user->username ?? 'student';
        $todayLabel = now()->format('l, M j');
        $greeting = now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening');
        $canUseTeacherWorkspace = $user->role === 'teacher' && $user->isApprovedTeacher();
        $storedHomeTab = session('teacher_home.tab', 'overview');
        // 'classes' and 'reports' used to open an embedded workspace pane here.
        // They are real pages now, so a session left on either falls back to the
        // overview rather than rendering an empty shell.
        $initialHomeTab = $canUseTeacherWorkspace
            ? (in_array($storedHomeTab, ['overview', 'progress'], true) ? $storedHomeTab : 'overview')
            : 'progress';

        $featuredPost = $latestPosts->first();
        $moreposts = $latestPosts->skip(1);
        $isTeacher = $canUseTeacherWorkspace;
        $hasActiveAssignments = !$isTeacher && $assignments->isNotEmpty();

        // Approved teachers browse the student view for the practice side only —
        // their own class work stays out of it, so its counts do too.
        $openAssignments = $isTeacher ? 0 : $assignmentFocus['open'];
        $overdueAssignments = $isTeacher ? 0 : $assignmentFocus['overdue'];
        $dueSoonAssignments = $isTeacher ? 0 : $assignmentFocus['dueSoon'];

        $formatTitle = function (?string $title, string $fallback = 'Digital SAT Practice Test 1') {
            if (!$title) return $fallback;
            if (\Illuminate\Support\Str::startsWith($title, '#') || (strlen($title) >= 10 && preg_match('/^[0-9A-Z]+$/i', ltrim($title, '#')))) {
                return 'Digital SAT Practice Test 1';
            }
            return $title;
        };

        // Emphasize the latest comparable full-test score; fall back to latest completed activity if none
        $overviewAttempt = $latestComparableAttempt ?? $lastCompletedAttempt;
        $isSectionAttempt = $overviewAttempt?->attempt_type === 'section';
        $lastSectionLabel = $isSectionAttempt
            ? ($overviewAttempt->section_type === 'reading_writing' ? 'Reading & Writing' : 'Math')
            : null;

        if ($overviewAttempt) {
            $lastScore = $isSectionAttempt
                ? ($overviewAttempt->section_type === 'reading_writing' ? $overviewAttempt->score_reading_writing : $overviewAttempt->score_math)
                : $overviewAttempt->total_score;
            $mathScore = $overviewAttempt->score_math;
            $rwScore = $overviewAttempt->score_reading_writing;
        } else {
            $lastScore = null;
            $mathScore = null;
            $rwScore = null;
        }

        $lastScoreMax = $isSectionAttempt ? 800 : 1600;
        $isPersonalBest = !$isSectionAttempt && $bestScore && $lastScore && $lastScore >= $bestScore;

        // Resuming goes through the same readiness confirmation the assignment
        // and classroom pages use, so the timer never restarts on a stray click.
        $promotedAssignment = !$isTeacher && !$inProgressAttempt ? $assignments->first() : null;
        $remainingAssignments = $promotedAssignment
            ? $assignments->reject(fn ($assignment) => $assignment->id === $promotedAssignment->id)
            : $assignments;

        $resumeModule = $inProgressAttempt?->currentModule;
        $resumeModalPayload = $inProgressAttempt
            ? [
                'title' => 'Ready to Resume Attempt?',
                'testTitle' => $formatTitle($inProgressAttempt->test->title, 'Adaptive Full Practice Test 1'),
                'subtitle' => 'Last active ' . $inProgressAttempt->updated_at->diffForHumans(),
                'actionRoute' => route('my-practice.resume', $inProgressAttempt),
                'inProgress' => true,
                'attemptInfo' => $resumeModule && $resumeModule->section
                    ? $resumeModule->section->name . ' • Module ' . $resumeModule->module_number
                    : 'Ready to start: Section 1, Module 1',
            ]
            : null;
    @endphp

    <div class="app-shell app-shell--no-list" x-data="{ tab: '{{ $initialHomeTab }}' }"
        @teacher-home-tab-requested.window="tab = $event.detail.tab"
        @teacher-home-tab-changed.window="tab = $event.detail.tab"
        @keydown.window="
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName) || $event.target.isContentEditable) return;
            if (!$event.altKey) return;
            if ($event.key.toLowerCase() === 'p') { $event.preventDefault(); window.location.href = '{{ route('home.practice') }}'; }
            else if ($event.key.toLowerCase() === 'q') { $event.preventDefault(); $dispatch('open-modal', 'modal-ask-question'); }
        ">
        <x-shell.icon-rail :logo-href="\App\Support\NavRail::logoHrefForUser($user)" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::forUser($user, 'home')" />

        <main class="shell-content">
            <x-ui.flash />

            {{-- ================= TEACHER OVERVIEW ================= --}}
            @if($canUseTeacherWorkspace)
                <div class="home" x-show="tab === 'overview'" x-cloak>
                    <header class="home-head">
                        <div class="home-head__lead">
                            <div class="home-head__meta">
                                <span class="home-date">{{ $todayLabel }}</span>
                                <x-ui.status-badge status="brand">
                                    <x-slot:icon><x-ui.icon name="mortarboard" class="w-3.5 h-3.5" /></x-slot:icon>
                                    Teacher
                                </x-ui.status-badge>
                            </div>
                            <h1 class="home-title">Good {{ $greeting }}, {{ $displayName }}</h1>
                            <p class="home-subtitle">
                                {{ $teacherStats
                                    ? \App\Support\HomeFocus::forTeacher($teacherStats)
                                    : 'An overview of your classes, students, and assignments.' }}
                            </p>
                        </div>

                        <div class="home-head__actions">
                            @include('student.analytics.partials.view-switcher')
                            <x-ui.button size="sm" :href="route('teacher.classes.index')">
                                <x-slot:icon><x-ui.icon name="plus-lg" class="w-4 h-4" /></x-slot:icon>
                                Create a class
                            </x-ui.button>
                        </div>
                    </header>

                    @if($teacherStats)
                        <x-ui.card :padded="false" class="home-stats" role="group" aria-label="Class overview at a glance">
                            <div class="home-stats__grid">
                                <div class="home-stat">
                                    <span class="home-stat__label">Active classes</span>
                                    <span class="home-stat__value home-num">{{ $teacherStats['activeClassesCount'] }}</span>
                                </div>

                                <div class="home-stat">
                                    <span class="home-stat__label">Enrolled students</span>
                                    <span class="home-stat__value home-num">{{ $teacherStats['enrolledStudentsCount'] }}</span>
                                </div>

                                @if($teacherStats['pendingRequestsCount'] > 0)
                                    <a href="{{ route('teacher.classes.index') }}" class="home-stat home-stat--action">
                                        <span class="home-stat__label">Pending approvals</span>
                                        <span class="home-stat__value home-num home-stat__value--warning">{{ $teacherStats['pendingRequestsCount'] }}</span>
                                        <span class="home-stat__note">
                                            <x-ui.icon name="exclamation-triangle-fill" class="w-3.5 h-3.5" />
                                            Review requests
                                        </span>
                                    </a>
                                @else
                                    <div class="home-stat">
                                        <span class="home-stat__label">Pending approvals</span>
                                        <span class="home-stat__value home-num">0</span>
                                        <span class="home-stat__note">All caught up</span>
                                    </div>
                                @endif

                                <div class="home-stat">
                                    <span class="home-stat__label">Published assignments</span>
                                    <span class="home-stat__value home-num">{{ $teacherStats['publishedAssignmentsCount'] }}</span>
                                </div>
                            </div>
                        </x-ui.card>

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                            <div class="lg:col-span-7">
                                <x-ui.card aria-labelledby="classes-title">
                                    <x-slot:header>
                                        <h2 id="classes-title" class="home-panel__title">Your active classes</h2>
                                        <a href="{{ route('teacher.classes.index') }}" class="home-link">
                                            View all classes
                                            <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                        </a>
                                    </x-slot:header>

                                    @if($teacherStats['recentClasses']->isNotEmpty())
                                        <ul class="home-list">
                                            @foreach($teacherStats['recentClasses'] as $c)
                                                <li>
                                                    <a href="{{ route('teacher.classes.show', $c) }}" class="home-row">
                                                        <span class="home-row__main">
                                                            <span class="home-row__title">{{ $c->name }}</span>
                                                            <span class="home-row__meta">
                                                                {{ $c->active_memberships_count }} {{ \Illuminate\Support\Str::plural('student', $c->active_memberships_count) }}
                                                                <span aria-hidden="true">&middot;</span>
                                                                {{ $c->assignments_count }} {{ \Illuminate\Support\Str::plural('assignment', $c->assignments_count) }}
                                                                @if($c->pending_memberships_count > 0)
                                                                    <span aria-hidden="true">&middot;</span>
                                                                    <span class="home-row__flag">{{ $c->pending_memberships_count }} pending</span>
                                                                @endif
                                                            </span>
                                                        </span>
                                                        <span class="home-row__aside">
                                                            <span class="home-code home-num">{{ $c->join_code }}</span>
                                                            <x-ui.icon name="chevron-right" class="home-row__chevron w-4 h-4" />
                                                        </span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <div class="home-empty">
                                            <x-ui.icon name="people" class="home-empty__icon w-6 h-6" />
                                            <p class="home-empty__title">No active classes yet</p>
                                            <p class="home-empty__body">Create a class to invite students, then assign a test to track their scores.</p>
                                            <x-ui.button size="sm" variant="secondary" :href="route('teacher.classes.index')">
                                                Create your first class
                                            </x-ui.button>
                                        </div>
                                    @endif
                                </x-ui.card>
                            </div>

                            <div class="lg:col-span-5">
                                <x-ui.card aria-labelledby="recent-assignments-title">
                                    <x-slot:header>
                                        <h2 id="recent-assignments-title" class="home-panel__title">Recent assignments</h2>
                                        <a href="{{ route('teacher.assignments.index') }}" class="home-link">
                                            All reports
                                            <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                        </a>
                                    </x-slot:header>

                                    @if($teacherStats['recentAssignments']->isNotEmpty())
                                        <ul class="home-list">
                                            @foreach($teacherStats['recentAssignments'] as $asgn)
                                                <li>
                                                    <a href="{{ route('teacher.assignments.show', ['assignment' => $asgn, 'from' => 'workspace']) }}" class="home-row">
                                                        <span class="home-row__main">
                                                            <span class="home-row__title">{{ $asgn->title }}</span>
                                                            <span class="home-row__meta">
                                                                {{ $asgn->classroom->name }}
                                                                <span aria-hidden="true">&middot;</span>
                                                                <span class="home-num">{{ $asgn->attempts_count }}/{{ $asgn->recipients_count }}</span> submitted
                                                            </span>
                                                        </span>
                                                        <span class="home-row__aside">
                                                            <x-ui.status-badge :status="$asgn->status === 'published' ? 'success' : 'neutral'">
                                                                {{ ucfirst($asgn->status) }}
                                                            </x-ui.status-badge>
                                                        </span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <div class="home-empty">
                                            <x-ui.icon name="journal-text" class="home-empty__icon w-6 h-6" />
                                            <p class="home-empty__title">No assignments yet</p>
                                            <p class="home-empty__body">Pick a test from the library and assign it to one of your classes.</p>
                                            <x-ui.button size="sm" variant="secondary" :href="route('home.practice')">
                                                Browse the test library
                                            </x-ui.button>
                                        </div>
                                    @endif

                                    <x-slot:footer>
                                        <div class="home-panel__foot">
                                            <span>Assign tests to your classes</span>
                                            <a href="{{ route('home.practice') }}" class="home-link">
                                                Test library
                                                <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                            </a>
                                        </div>
                                    </x-slot:footer>
                                </x-ui.card>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ================= STUDENT PRACTICE & PROGRESS ================= --}}
            @if($user->role === 'student')
                @include('student.analytics.partials.study-home')
            @elseif($canUseTeacherWorkspace)
                <div x-show="tab === 'progress'" x-cloak>
                    @include('student.analytics.partials.study-home')
                </div>
            @else
            <div @class(['home', 'home--guided' => !$isTeacher])
                @if(!$isTeacher) x-data="studentHome(@js(['endpoint' => route('student.progress.goal'), 'csrf' => csrf_token(), 'goal' => $user->target_score ? (int) $user->target_score : null, 'latestScore' => $latestComparableAttempt ? (int) $latestComparableAttempt->total_score : null]))" @endif
                @if($canUseTeacherWorkspace) x-show="tab === 'progress'" x-cloak @endif>
                <header class="home-head">
                    <div class="home-head__lead">
                        <div class="home-head__meta">
                            <span class="home-date">{{ $todayLabel }}</span>
                            @if($primaryClassroom)
                                <x-ui.status-badge status="brand">
                                    <x-slot:icon><x-ui.icon name="journal-open" class="w-3.5 h-3.5" /></x-slot:icon>
                                    {{ $primaryClassroom->name }}
                                </x-ui.status-badge>
                            @endif
                        </div>
                        <h1 class="home-title">Good {{ $greeting }}, {{ $displayName }}</h1>
                        <p class="home-subtitle">
                            {{ \App\Support\HomeFocus::forStudent(
                                open: $openAssignments,
                                overdue: $overdueAssignments,
                                dueSoon: $dueSoonAssignments,
                                hasAnyAssignment: !$isTeacher && ($hasAnyAssignment ?? false),
                                hasAttemptInProgress: (bool) $inProgressAttempt,
                                completedCount: $completedCount,
                                bestScore: $bestScore,
                            ) }}
                        </p>
                    </div>

                    <div class="home-head__actions">
                        @if($canUseTeacherWorkspace)
                            @include('student.analytics.partials.view-switcher')
                        @endif

                        <x-ui.button size="sm" variant="secondary" :href="route('exam-join.entry')">
                            <x-slot:icon><x-ui.icon name="box-arrow-right" class="w-4 h-4" /></x-slot:icon>
                            Join an exam
                        </x-ui.button>

                        <x-ui.button size="sm" variant="secondary" :href="route('student.classes.index')">
                            <x-slot:icon><x-ui.icon name="mortarboard" class="w-4 h-4" /></x-slot:icon>
                            Join a class
                        </x-ui.button>
                    </div>
                </header>

                {{-- ================= A. CURRENT WORK ================= --}}
                @if(!$isTeacher)
                    @include('student.analytics.partials.next-step')
                @else
                @if ($inProgressAttempt || $hasActiveAssignments)
                    <div class="home-current">
                        <div @class([
                            'grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch' => $inProgressAttempt && $hasActiveAssignments,
                        ])>
                            {{-- Resumable Attempt (Primary emphasis) --}}
                            @if ($inProgressAttempt)
                                <div @class(['lg:col-span-7' => $hasActiveAssignments])>
                                    <x-ui.card :stretch="(bool) $hasActiveAssignments" class="home-current__card home-current__card--primary" aria-labelledby="active-task-title">
                                        <x-slot:header>
                                            <div class="home-panel__headline">
                                                <h2 id="active-task-title" class="home-panel__title">{{ $formatTitle($inProgressAttempt->test->title, 'Adaptive Full Practice Test 1') }}</h2>
                                                <p class="home-panel__sub">Started {{ $inProgressAttempt->created_at->diffForHumans() }}</p>
                                            </div>
                                            <x-ui.status-badge status="warning">In progress</x-ui.status-badge>
                                        </x-slot:header>

                                        <ul class="home-chips">
                                            <li class="home-chip">
                                                <x-ui.icon name="clock" class="w-3.5 h-3.5" />
                                                {{ $resumeModule && $resumeModule->section ? $resumeModule->section->name . ' • Module ' . $resumeModule->module_number : 'Module in progress' }}
                                            </li>
                                            <li class="home-chip">
                                                <x-ui.icon name="layers" class="w-3.5 h-3.5" />
                                                Adaptive practice
                                            </li>
                                        </ul>
                                        <p class="home-note">Your responses are saved automatically as you go.</p>

                                        <x-slot:footer>
                                            <div class="home-panel__foot">
                                                <span>Pick up where you left off</span>
                                                <x-ui.button @click="$dispatch('open-ready-modal', {{ \Illuminate\Support\Js::from($resumeModalPayload) }})">
                                                    Continue practice
                                                </x-ui.button>
                                            </div>
                                        </x-slot:footer>
                                    </x-ui.card>
                                </div>
                            @endif

                            {{-- Active Assignments --}}
                            @if ($hasActiveAssignments)
                                <div @class(['lg:col-span-5' => $inProgressAttempt])>
                                    <x-ui.card :stretch="(bool) $inProgressAttempt" id="classroom-assignments" @class([
                                        'home-current__card',
                                        'home-current__card--primary' => ! $inProgressAttempt,
                                    ]) aria-labelledby="assignments-title">
                                        <x-slot:header>
                                            <div class="home-panel__headline">
                                                <h2 id="assignments-title" class="home-panel__title">Assigned by your teacher</h2>
                                                <p class="home-panel__sub">{{ $openAssignments > 0 ? $openAssignments.' open' : 'All caught up' }}</p>
                                            </div>
                                            <x-ui.status-badge status="brand">Class work</x-ui.status-badge>
                                        </x-slot:header>

                                        <ul class="home-list">
                                            @foreach($assignments->take(3) as $a)
                                                @php
                                                    $resolved = $a->resolved ?? \App\Support\AssignmentStatusResolver::resolve($a, $a->attempts);
                                                    $state = $resolved['state'];
                                                    $isCompleted = $state === 'Completed';
                                                    $isInProgress = $resolved['inProgress'] !== null;
                                                    $actionLabel = $isCompleted ? 'Review'
                                                        : ($isInProgress ? 'Resume'
                                                        : ($resolved['canStart'] ? 'Start' : 'View'));
                                                @endphp
                                                <li>
                                                    <div class="home-row home-row--static">
                                                        <span class="home-row__main">
                                                            <span class="home-row__title">{{ $a->title }}</span>
                                                            <span class="home-row__meta">
                                                                {{ $a->classroom->name }}
                                                                <span aria-hidden="true">&middot;</span>
                                                                {{ $a->due_at ? 'Due ' . $a->due_at->diffForHumans() : 'No due date' }}
                                                            </span>
                                                        </span>
                                                        <span class="home-row__aside">
                                                            <x-ui.status-badge :status="$resolved['variant']">{{ $state }}</x-ui.status-badge>
                                                            <x-ui.button size="sm" variant="secondary" :href="route('student.assignments.show', ['assignment' => $a->ulid])">
                                                                {{ $actionLabel }}
                                                            </x-ui.button>
                                                        </span>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>

                                        <x-slot:footer>
                                            <div class="home-panel__foot">
                                                <span>All classroom tasks and scores</span>
                                                <a href="{{ route('student.assignments.index') }}" class="home-link">
                                                    View all assignments
                                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                                </a>
                                            </div>
                                        </x-slot:footer>
                                    </x-ui.card>
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif ($completedCount === 0)
                    {{-- Fresh student with no completed tests and no active work: give Test Preview primary emphasis --}}
                    <div class="home-current">
                        <x-ui.card class="home-current__card home-current__card--preview" aria-labelledby="preview-launch-title">
                            <x-slot:header>
                                <div class="home-panel__headline">
                                    <h2 id="preview-launch-title" class="home-panel__title">Get started with Test Preview</h2>
                                    <p class="home-panel__sub">Explore the digital testing experience before taking a scored test</p>
                                </div>
                                <x-ui.status-badge status="brand">Recommended start</x-ui.status-badge>
                            </x-slot:header>

                            <p class="home-note text-slate-700 m-0">
                                Explore sample questions, the built-in Desmos graphing calculator, reference sheet, and testing navigation tools without time limits or a recorded score.
                            </p>

                            <x-slot:footer>
                                <div class="home-panel__foot">
                                    <span>Ready to see the test tools in action?</span>
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('home.practice') }}" class="home-link">
                                            Browse practice tests
                                            <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                        </a>
                                        <x-ui.button :href="route('test.preview')">
                                            Try Test Preview
                                        </x-ui.button>
                                    </div>
                                </div>
                            </x-slot:footer>
                        </x-ui.card>
                    </div>
                @endif
                @endif

                {{-- ================= B. YOUR PROGRESS PERFORMANCE OVERVIEW ================= --}}
                <x-ui.card class="home-portal" aria-labelledby="progress-portal-title">
                    <x-slot:header>
                        <div class="home-panel__headline">
                            <h2 id="progress-portal-title" class="home-panel__title">Progress at a glance</h2>
                            <p class="home-panel__sub">Recent performance and score progression</p>
                        </div>
                        <a href="{{ route('student.progress') }}" class="home-link">
                            View full progress
                            <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                        </a>
                    </x-slot:header>

                    @if($lastScore !== null || !empty($homeScoreTrend['attempts']) || $completedCount > 0)
                        <div class="home-portal__metrics">
                            <div class="home-portal__metric">
                                <span class="home-portal__metric-label">Latest score</span>
                                <div class="flex items-baseline gap-1.5 flex-wrap">
                                    <span class="home-portal__metric-value home-num">{{ $lastScore ?? (end($homeScoreTrend['attempts'])['score'] ?? '—') }}</span>
                                    <span class="text-xs text-slate-600 font-semibold">/ {{ $lastScoreMax }}</span>
                                    @if($isPersonalBest)
                                        <x-ui.status-badge status="success" class="ml-auto">Personal best</x-ui.status-badge>
                                    @elseif($isSectionAttempt)
                                        <x-ui.status-badge status="neutral" class="ml-auto">{{ $lastSectionLabel }} only</x-ui.status-badge>
                                    @endif
                                </div>
                                @if(!$isSectionAttempt && ($mathScore || $rwScore))
                                    <div class="text-xs text-slate-600 mt-1 flex items-center gap-2">
                                        <span>RW: <strong class="text-slate-700">{{ $rwScore ?? '—' }}</strong></span>
                                        <span aria-hidden="true">&middot;</span>
                                        <span>Math: <strong class="text-slate-700">{{ $mathScore ?? '—' }}</strong></span>
                                    </div>
                                @endif
                            </div>

                            <div class="home-portal__metric">
                                <span class="home-portal__metric-label">Personal best</span>
                                <span class="home-portal__metric-value home-num">{{ $bestScore ?? '—' }}</span>
                                @if($bestScore)
                                    <span class="text-xs text-slate-600">out of 1600</span>
                                @endif
                            </div>

                            <div class="home-portal__metric">
                                <span class="home-portal__metric-label">Practice completed</span>
                                <span class="home-portal__metric-value home-num">{{ $completedCount }}</span>
                                <span class="text-xs text-slate-600">{{ \Illuminate\Support\Str::plural('test', $completedCount) }} finished</span>
                            </div>

                            @if($isTeacher)
                            <div class="home-portal__metric">
                                <span class="home-portal__metric-label">Target goal</span>
                                @if($user->target_score)
                                    <span class="home-portal__metric-value home-num">{{ $user->target_score }}</span>
                                    <a href="{{ route('student.progress') }}" class="home-portal__target-tag">Target set &middot; Edit</a>
                                @else
                                    <span class="home-portal__metric-value text-slate-400">
                                        <a href="{{ route('student.progress') }}" class="home-link font-semibold">Not set</a>
                                    </span>
                                    <a href="{{ route('student.progress') }}" class="home-portal__target-tag">Set target goal</a>
                                @endif
                            </div>
                            @endif
                        </div>
                    @else
                        <div class="home-empty home-empty--portal">
                            <x-ui.icon name="graph-up-arrow" class="home-empty__icon w-6 h-6" />
                            <p class="home-empty__title">No score trend yet</p>
                            <p class="home-empty__body">
                                Your score history, section subscores, and progress trajectory will appear here after you finish your first practice test. Set a target score at any time to monitor your goal.
                            </p>

                            <div class="home-portal__metrics-preview">
                                <div class="home-portal__metric">
                                    <span class="home-portal__metric-label">Latest score</span>
                                    <span class="home-portal__metric-value home-num">—</span>
                                </div>
                                <div class="home-portal__metric">
                                    <span class="home-portal__metric-label">Personal best</span>
                                    <span class="home-portal__metric-value home-num">—</span>
                                </div>
                                <div class="home-portal__metric">
                                    <span class="home-portal__metric-label">Practice completed</span>
                                    <span class="home-portal__metric-value home-num">0</span>
                                </div>
                                @if($isTeacher)
                                <div class="home-portal__metric">
                                    <span class="home-portal__metric-label">Target goal</span>
                                    @if($user->target_score)
                                        <span class="home-portal__metric-value home-num">{{ $user->target_score }}</span>
                                    @else
                                        <span class="home-portal__metric-value text-slate-400">
                                            <a href="{{ route('student.progress') }}" class="home-link font-semibold">Not set</a>
                                        </span>
                                    @endif
                                </div>
                                @endif
                            </div>

                            <div class="flex items-center gap-3 mt-4 flex-wrap justify-center">
                                <x-ui.button size="sm" variant="secondary" :href="route('home.practice')">
                                    Explore the test library
                                </x-ui.button>
                                @if($isTeacher)
                                    <a href="{{ route('student.progress') }}" class="home-link">Set target goal</a>
                                @endif
                            </div>
                        </div>
                    @endif
                </x-ui.card>

                {{-- ================= C. SCORE HISTORY & RECENT WORK ================= --}}
                @if($completedCount > 0 || $recentWorkAttempts->isNotEmpty())
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                        {{-- 2/3 Score History Chart --}}
                        <div class="lg:col-span-8">
                            <x-ui.card stretch aria-labelledby="score-history-title">
                                <x-slot:header>
                                    <div class="home-panel__headline">
                                        <h2 id="score-history-title" class="home-panel__title">Score history</h2>
                                        <p class="home-panel__sub">Last {{ min(5, count($homeScoreTrend['attempts'])) }} comparable full tests</p>
                                    </div>
                                    @if($user->target_score || !$isTeacher)
                                        <span class="home-portal__target-indicator" @if(!$isTeacher) x-show="savedGoal !== null" @endif>
                                            <span class="home-portal__target-line-sample"></span>
                                            Target (<span @if(!$isTeacher) x-text="savedGoal" @endif>{{ $user->target_score }}</span>)
                                        </span>
                                    @endif
                                </x-slot:header>

                                @include('student.analytics.partials.score-history')
                            </x-ui.card>
                        </div>

                        {{-- 1/3 Recent Work --}}
                        <div class="lg:col-span-4">
                            <x-ui.card stretch aria-labelledby="recent-work-title">
                                <x-slot:header>
                                    <div class="home-panel__headline">
                                        <h2 id="recent-work-title" class="home-panel__title">Recent work</h2>
                                        <p class="home-panel__sub">Latest practice sessions</p>
                                    </div>
                                    <a href="{{ route('student.scores.index') }}" class="home-link">
                                        All score reports
                                        <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                    </a>
                                </x-slot:header>

                                @if($recentWorkAttempts->isNotEmpty())
                                    <ul class="home-list">
                                        @foreach($recentWorkAttempts as $rw)
                                            @php
                                                $rwIsSection = $rw->attempt_type === 'section';
                                                $rwScore = $rwIsSection
                                                    ? ($rw->section_type === 'reading_writing' ? $rw->score_reading_writing : $rw->score_math)
                                                    : $rw->total_score;
                                                $rwMax = $rwIsSection ? 800 : 1600;
                                                $rwFormat = $rwIsSection
                                                    ? ($rw->section_type === 'reading_writing' ? 'Reading & Writing' : 'Math')
                                                    : 'Full test';
                                            @endphp
                                            <li>
                                                <div class="home-row home-row--static">
                                                    <span class="home-row__main">
                                                        <span class="home-row__title">{{ $formatTitle($rw->test?->title, 'Practice Test') }}</span>
                                                        <span class="home-row__meta">
                                                            <span class="font-medium text-slate-700">{{ $rwFormat }}</span>
                                                            <span aria-hidden="true">&middot;</span>
                                                            <span>{{ $rw->completed_at ? $rw->completed_at->diffForHumans() : 'Recently' }}</span>
                                                        </span>
                                                    </span>
                                                    <span class="home-row__aside">
                                                        <span class="flex items-baseline gap-1">
                                                            <span class="home-num font-bold text-base text-slate-900">{{ $rwScore ?? '—' }}</span>
                                                            <span class="text-xs text-slate-500">/ {{ $rwMax }}</span>
                                                        </span>
                                                        <x-ui.button size="sm" variant="secondary" :href="route('student.scores.show', $rw)">
                                                            Review
                                                        </x-ui.button>
                                                    </span>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>

                                    <x-slot:footer>
                                        <div class="home-panel__foot">
                                            @if($lastCompletedAttempt)
                                                <a href="{{ route('student.scores.show', $lastCompletedAttempt) }}" class="home-link">
                                                    Review latest test
                                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                                </a>
                                            @endif
                                            <a href="{{ route('student.scores.index') }}" class="home-link">
                                                All score reports
                                                <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                            </a>
                                        </div>
                                    </x-slot:footer>
                                @else
                                    <div class="home-empty">
                                        <x-ui.icon name="journal-check" class="home-empty__icon w-6 h-6" />
                                        <p class="home-empty__title">No completed practice yet</p>
                                        <p class="home-empty__body">Take a test from the library to see your score reports here.</p>
                                        <x-ui.button size="sm" variant="secondary" :href="route('home.practice')">
                                            Browse practice tests
                                        </x-ui.button>
                                    </div>
                                @endif
                            </x-ui.card>
                        </div>
                    </div>
                @endif

                {{-- ================= D. WEEKLY ACTIVITY & STUDY GUIDANCE ================= --}}
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                    {{-- Left: Weekly activity --}}
                    <div class="lg:col-span-5">
                        <x-ui.card stretch aria-labelledby="weekly-activity-title">
                            <x-slot:header>
                                <div class="home-panel__headline">
                                    <h2 id="weekly-activity-title" class="home-panel__title">Weekly activity</h2>
                                    <p class="home-panel__sub">Effort this week (Mon &ndash; Sun, VN time)</p>
                                </div>
                                <x-ui.status-badge status="brand">This week</x-ui.status-badge>
                            </x-slot:header>

                            <div class="home-activity-grid">
                                <div class="home-activity-tile">
                                    <div class="home-activity-tile__icon">
                                        <x-ui.icon name="check2-circle" class="w-5 h-5 text-indigo-600" />
                                    </div>
                                    <div>
                                        <div class="home-stat__value home-num text-2xl font-bold text-slate-900">{{ $weeklyActivity['practiceCount'] }}</div>
                                        <div class="home-activity-tile__label">{{ \Illuminate\Support\Str::plural('test', $weeklyActivity['practiceCount']) }} completed</div>
                                    </div>
                                </div>

                                <div class="home-activity-tile">
                                    <div class="home-activity-tile__icon">
                                        <x-ui.icon name="pencil-square" class="w-5 h-5 text-indigo-600" />
                                    </div>
                                    <div>
                                        <div class="home-stat__value home-num text-2xl font-bold text-slate-900">{{ $weeklyActivity['questionsCount'] }}</div>
                                        <div class="home-activity-tile__label">questions answered</div>
                                    </div>
                                </div>
                            </div>

                            <p class="home-note mt-3 text-xs text-slate-500">
                                Tracks tests and session answers recorded between Monday 00:00 and next Monday 00:00 (Asia/Ho_Chi_Minh).
                            </p>
                        </x-ui.card>
                    </div>

                    {{-- Right: Study guidance --}}
                    <div class="lg:col-span-7">
                        <div class="home-stack h-full justify-between">
                            <section class="home-suggestion" aria-labelledby="tip-title">
                                <div>
                                    <h2 id="tip-title" class="home-panel__title">Suggested focus</h2>
                                    @if($suggestion)
                                        <h3 class="home-suggestion__skill">{{ $suggestion['skill'] }}</h3>
                                        <p class="home-note">{{ $suggestion['domain'] }} · Correct on {{ $suggestion['correct'] }} of {{ $suggestion['total'] }} recent {{ \Illuminate\Support\Str::plural('question', $suggestion['total']) }}.</p>
                                        @if($suggestion['total'] < 5)
                                            <p class="home-note">This is limited evidence. Try more questions to understand whether this is a consistent gap.</p>
                                        @else
                                            <p class="home-note">Revisit this topic in your next practice, then review the answer explanations.</p>
                                        @endif
                                    @else
                                        <h3 class="home-suggestion__skill">Keep building your practice</h3>
                                        <p class="home-note">Complete practice and review your answers to build evidence for a suggested focus.</p>
                                    @endif
                                </div>
                                <x-ui.button variant="secondary" :href="route('home.practice')">Browse practice tests</x-ui.button>
                            </section>
                        </div>
                    </div>
                </div>

                {{-- ================= E. PERMANENT TOOL SHOWCASE ================= --}}
                <section class="home-tools-section" aria-labelledby="study-tools-title">
                    <div class="home-section-head">
                        <h2 id="study-tools-title" class="home-section-title">Explore your study tools</h2>
                        <p class="home-section-sub">Five core areas to help you master the Digital SAT</p>
                    </div>

                    <div class="home-tools-grid">
                        {{-- 1. Test Preview (modest brand-tinted emphasis) --}}
                        <div class="home-tool-card home-tool-card--featured">
                            <div class="home-tool-card__header">
                                <div class="home-tool-card__icon home-tool-card__icon--brand">
                                    <x-ui.icon name="compass" class="w-5 h-5" />
                                </div>
                                <div class="home-tool-card__heading">
                                    <h3 class="home-tool-card__title">Test Preview</h3>
                                    <x-ui.status-badge status="brand">Untimed</x-ui.status-badge>
                                </div>
                            </div>
                            <p class="home-tool-card__purpose">
                                Explore sample questions and testing tools without timing or a scored result.
                            </p>
                            <p class="home-tool-card__context">
                                Practice using the built-in Desmos graphing calculator, formula sheets, highlighter, and answer eliminator in a low-stakes environment.
                            </p>
                            <div class="home-tool-demo">
                                <span class="home-tool-demo__pill"><x-ui.icon name="calculator" class="w-3.5 h-3.5" /> Desmos</span>
                                <span class="home-tool-demo__pill"><x-ui.icon name="file-earmark-text" class="w-3.5 h-3.5" /> Reference</span>
                                <span class="home-tool-demo__pill"><x-ui.icon name="slash-circle" class="w-3.5 h-3.5" /> Eliminator</span>
                            </div>
                            <div class="home-tool-card__actions">
                                <a href="{{ route('test.preview') }}" class="home-link">
                                    Launch Test Preview
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </div>

                        {{-- 2. Practice --}}
                        <div class="home-tool-card">
                            <div class="home-tool-card__header">
                                <div class="home-tool-card__icon">
                                    <x-ui.icon name="journal-text" class="w-5 h-5" />
                                </div>
                                <div class="home-tool-card__heading">
                                    <h3 class="home-tool-card__title">Practice</h3>
                                </div>
                            </div>
                            <p class="home-tool-card__purpose">
                                Browse available tests and choose a suitable practice format.
                            </p>
                            <p class="home-tool-card__context">
                                Choose full-length adaptive tests with realistic scoring or focused single-section practice.
                            </p>
                            <div class="home-tool-demo">
                                <span class="home-tool-demo__pill"><x-ui.icon name="clock" class="w-3.5 h-3.5" /> Adaptive Full &middot; 134m</span>
                                <span class="home-tool-demo__pill"><x-ui.icon name="journal-text" class="w-3.5 h-3.5" /> Section &middot; 32m</span>
                            </div>
                            <div class="home-tool-card__actions">
                                <a href="{{ route('home.practice') }}" class="home-link">
                                    Explore test library
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </div>

                        {{-- 3. Classes & Assignments --}}
                        <div class="home-tool-card">
                            <div class="home-tool-card__header">
                                <div class="home-tool-card__icon">
                                    <x-ui.icon name="mortarboard" class="w-5 h-5" />
                                </div>
                                <div class="home-tool-card__heading">
                                    <h3 class="home-tool-card__title">Classes &amp; Assignments</h3>
                                </div>
                            </div>
                            <p class="home-tool-card__purpose">
                                Join a teacher’s class, find assigned work, and access class materials.
                            </p>
                            <p class="home-tool-card__context">
                                Submit teacher-assigned tests before deadlines, track completion status, and access study materials shared by your teacher.
                            </p>
                            <div class="home-tool-demo">
                                @if($primaryClassroom)
                                    <span class="home-tool-demo__pill"><x-ui.icon name="mortarboard" class="w-3.5 h-3.5" /> {{ \Illuminate\Support\Str::limit($primaryClassroom->name, 22) }}</span>
                                    <span class="home-tool-demo__pill home-tool-demo__pill--brand"><x-ui.icon name="calendar-check" class="w-3.5 h-3.5" /> {{ $assignments->isNotEmpty() && $assignments->first()->due_at ? 'Due ' . $assignments->first()->due_at->isoFormat('ddd') : 'Enrolled' }}</span>
                                @else
                                    <span class="home-tool-demo__pill"><x-ui.icon name="mortarboard" class="w-3.5 h-3.5" /> Example: Cohort Class</span>
                                    <span class="home-tool-demo__pill home-tool-demo__pill--brand"><x-ui.icon name="calendar-check" class="w-3.5 h-3.5" /> Example: Due Friday</span>
                                @endif
                            </div>
                            <div class="home-tool-card__actions">
                                <a href="{{ route('student.classes.index') }}" class="home-link">
                                    My classes
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                </a>
                                <span class="home-tool-card__sep" aria-hidden="true">&middot;</span>
                                <a href="{{ route('student.assignments.index') }}" class="home-link">
                                    View assignments
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </div>

                        {{-- 4. Scores & Progress --}}
                        <div class="home-tool-card">
                            <div class="home-tool-card__header">
                                <div class="home-tool-card__icon">
                                    <x-ui.icon name="graph-up-arrow" class="w-5 h-5" />
                                </div>
                                <div class="home-tool-card__heading">
                                    <h3 class="home-tool-card__title">Scores &amp; Progress</h3>
                                </div>
                            </div>
                            <p class="home-tool-card__purpose">
                                Review completed answers, understand performance, and track a target score.
                            </p>
                            <p class="home-tool-card__context">
                                Inspect detailed explanations for every question, review domain and skill accuracy, and monitor your score trajectory.
                            </p>
                            <div class="home-tool-demo">
                                <span class="home-tool-demo__pill"><x-ui.icon name="graph-up-arrow" class="w-3.5 h-3.5" /> Domain Analysis</span>
                                <span class="home-tool-demo__pill home-tool-demo__pill--brand"><x-ui.icon name="bullseye" class="w-3.5 h-3.5" /> Target Tracking</span>
                            </div>
                            <div class="home-tool-card__actions">
                                <a href="{{ route('student.scores.index') }}" class="home-link">
                                    Score history
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                </a>
                                <span class="home-tool-card__sep" aria-hidden="true">&middot;</span>
                                <a href="{{ route('student.progress') }}" class="home-link">
                                    Detailed progress
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </div>

                        {{-- 5. Guides & Community --}}
                        <div class="home-tool-card home-tool-card--wide">
                            <div class="home-tool-card__header">
                                <div class="home-tool-card__icon">
                                    <x-ui.icon name="chat-left-quote" class="w-5 h-5" />
                                </div>
                                <div class="home-tool-card__heading">
                                    <h3 class="home-tool-card__title">Guides &amp; Community</h3>
                                </div>
                            </div>
                            <p class="home-tool-card__purpose">
                                Read teacher articles or ask questions in the forum.
                            </p>
                            <p class="home-tool-card__context">
                                Read expert strategy breakdowns and subject walkthroughs from verified teachers, or discuss difficult problems with peers in the forum.
                            </p>
                            <div class="home-tool-demo">
                                <span class="home-tool-demo__pill"><x-ui.icon name="book" class="w-3.5 h-3.5" /> Strategy Guides</span>
                                <span class="home-tool-demo__pill"><x-ui.icon name="chat-dots" class="w-3.5 h-3.5" /> Peer Discussions</span>
                            </div>
                            <div class="home-tool-card__actions">
                                <a href="{{ route('blog.index') }}" class="home-link">
                                    Read articles
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                </a>
                                <span class="home-tool-card__sep" aria-hidden="true">&middot;</span>
                                <a href="{{ route('forum.index') }}" class="home-link">
                                    Community forum
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ================= HOW YOUR PRACTICE CONNECTS ================= --}}
                <section class="home-sequence" aria-labelledby="sequence-title">
                    <div class="home-section-head">
                        <h2 id="sequence-title" class="home-section-title">How your practice connects</h2>
                        <p class="home-section-sub">A continuous cycle to build test confidence and improve your score</p>
                    </div>

                    <div class="home-sequence__track">
                        <div class="home-sequence__step">
                            <div class="home-sequence__step-head">
                                <span class="home-sequence__num">1</span>
                                <h3 class="home-sequence__title">Choose practice</h3>
                            </div>
                            <p class="home-sequence__desc">
                                Select a full-length adaptive test or focused section that matches your preparation goals.
                            </p>
                        </div>

                        <div class="home-sequence__divider" aria-hidden="true">
                            <x-ui.icon name="arrow-right" class="w-4 h-4" />
                        </div>

                        <div class="home-sequence__step">
                            <div class="home-sequence__step-head">
                                <span class="home-sequence__num">2</span>
                                <h3 class="home-sequence__title">Review your answers</h3>
                            </div>
                            <p class="home-sequence__desc">
                                Inspect question explanations, timing per question, and mistake patterns after each session.
                            </p>
                        </div>

                        <div class="home-sequence__divider" aria-hidden="true">
                            <x-ui.icon name="arrow-right" class="w-4 h-4" />
                        </div>

                        <div class="home-sequence__step">
                            <div class="home-sequence__step-head">
                                <span class="home-sequence__num">3</span>
                                <h3 class="home-sequence__title">Track progress</h3>
                            </div>
                            <p class="home-sequence__desc">
                                Monitor your score trajectory across domains and work consistently toward your target score.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="home-help" aria-labelledby="home-help-title">
                    <h2 id="home-help-title" class="home-section-title">Understand your practice</h2>
                    <details>
                        <summary>What is Test Preview?</summary>
                        <p>Test Preview lets you explore sample questions, Desmos, the reference sheet, and answer tools without time limits or a recorded score.</p>
                        <a href="{{ route('test.preview') }}" class="home-link">Open Test Preview <x-ui.icon name="arrow-right" class="w-4 h-4" /></a>
                    </details>
                    <details>
                        <summary>How does adaptive practice work?</summary>
                        <p>Full adaptive practice has two modules per section. Your answers in the first module help determine the difficulty of the second. Review your score report after completion to understand your results.</p>
                        <a href="{{ route('home.practice') }}" class="home-link">Explore adaptive practice <x-ui.icon name="arrow-right" class="w-4 h-4" /></a>
                    </details>
                </section>

                {{-- ================= F. RESOURCES AND COMMUNITY ================= --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-ui.card stretch aria-labelledby="blog-title">
                        <x-slot:header>
                            <h2 id="blog-title" class="home-panel__title">From the blog</h2>
                            <a href="{{ route('blog.index') }}" class="home-link">
                                Browse all
                                <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                            </a>
                        </x-slot:header>

                        @if($featuredPost)
                            <a href="{{ route('blog.show', $featuredPost) }}" class="home-feature-link">
                                <span class="home-feature-link__title">{{ $featuredPost->title }}</span>
                                @if($featuredPost->excerpt)
                                    <span class="home-feature-link__excerpt">{{ \Illuminate\Support\Str::limit($featuredPost->excerpt, 90) }}</span>
                                @endif
                                <span class="home-feature-link__byline">{{ $featuredPost->teacher->name }} &middot; {{ optional($featuredPost->published_at)->diffForHumans() }}</span>
                            </a>

                            @if($moreposts->isNotEmpty())
                                <ul class="home-minilist">
                                    @foreach($moreposts->take(2) as $post)
                                        <li>
                                            <a href="{{ route('blog.show', $post) }}" class="home-minilist__link">
                                                <span class="home-minilist__title">{{ $post->title }}</span>
                                                <span class="home-minilist__meta">{{ $post->teacher->name }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @else
                            <div class="home-feed-empty">
                                <p class="home-feed-empty__text">Study tips written by teachers will show up here.</p>
                                <a href="{{ route('blog.index') }}" class="home-link">
                                    Browse blog archive
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        @endif
                    </x-ui.card>

                    <x-ui.card stretch aria-labelledby="forum-title">
                        <x-slot:header>
                            <h2 id="forum-title" class="home-panel__title">Forum highlights</h2>
                            <div class="home-panel__links">
                                <button type="button" class="home-link" @click="$dispatch('open-modal', 'modal-ask-question')">Ask</button>
                                <a href="{{ route('forum.index') }}" class="home-link">
                                    Open
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </x-slot:header>

                        @if($latestThreads->isNotEmpty())
                            <ul class="home-minilist home-minilist--flush">
                                @foreach($latestThreads->take(4) as $thread)
                                    <li>
                                        <a href="{{ route('forum.show', $thread) }}" class="home-minilist__link">
                                            <span class="home-minilist__title">{{ $thread->title }}</span>
                                            <span class="home-minilist__meta">
                                                {{ $thread->category }}
                                                <span aria-hidden="true">&middot;</span>
                                                {{ $thread->replies_count }} {{ \Illuminate\Support\Str::plural('reply', $thread->replies_count) }}
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="home-feed-empty">
                                <p class="home-feed-empty__text">Ask the first question and a teacher will answer it.</p>
                                <div class="home-feed-empty__actions">
                                    <a href="{{ route('forum.index') }}" class="home-link">
                                        Open forum
                                        <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                    </a>
                                    <span aria-hidden="true">&middot;</span>
                                    <button type="button" class="home-link" @click="$dispatch('open-modal', 'modal-ask-question')">
                                        Ask a question
                                    </button>
                                </div>
                            </div>
                        @endif
                    </x-ui.card>
                </div>
            </div>

            @endif
        </main>

        @include('forum.partials.ask-modal')
    </div>

    @if ($inProgressAttempt)
        <x-ui.ready-modal />
    @endif
</x-layouts.student>
