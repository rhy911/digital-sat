<x-layouts.student :user="$user" title="Home" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/analytics.css'])
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

        $featuredDuration = $featuredTest?->total_duration_minutes
            ?: $featuredTest?->sections?->sum(fn ($section) => $section->modules->unique('module_number')->sum('duration_minutes'));
        $featuredSections = $featuredTest?->sections?->count();
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

        // A section-only attempt scores out of 800, not 1600, and its score is not
        // comparable to $bestScore (which aggregates full-length attempts only).
        $isSectionAttempt = $lastCompletedAttempt?->attempt_type === 'section';
        $lastSectionLabel = $isSectionAttempt
            ? ($lastCompletedAttempt->section_type === 'reading_writing' ? 'Reading & Writing' : 'Math')
            : null;

        if ($lastCompletedAttempt) {
            $lastScore = $isSectionAttempt
                ? ($lastCompletedAttempt->section_type === 'reading_writing' ? $lastCompletedAttempt->score_reading_writing : $lastCompletedAttempt->score_math)
                : $lastCompletedAttempt->total_score;
            $mathScore = $lastCompletedAttempt->score_math;
            $rwScore = $lastCompletedAttempt->score_reading_writing;
        } else {
            $lastScore = null;
            $mathScore = null;
            $rwScore = null;
        }

        $lastScoreMax = $isSectionAttempt ? 800 : 1600;
        $isPersonalBest = !$isSectionAttempt && $bestScore && $lastScore && $lastScore >= $bestScore;

        // Resuming goes through the same readiness confirmation the assignment
        // and classroom pages use, so the timer never restarts on a stray click.
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
            if ($event.key.toLowerCase() === 'p') { window.location.href = '{{ route('home.practice') }}'; }
            else if ($event.key.toLowerCase() === 'q') { $dispatch('open-modal', 'modal-ask-question'); $event.preventDefault(); }
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
                            <x-ui.button size="sm" :href="route('teacher.classes.index', ['new' => 1])">
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

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                            <div class="lg:col-span-7">
                                <x-ui.card stretch aria-labelledby="classes-title">
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
                                            <x-ui.button size="sm" variant="secondary" :href="route('teacher.classes.index', ['new' => 1])">
                                                Create your first class
                                            </x-ui.button>
                                        </div>
                                    @endif
                                </x-ui.card>
                            </div>

                            <div class="lg:col-span-5">
                                <x-ui.card stretch aria-labelledby="recent-assignments-title">
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
            <div class="home" @if($canUseTeacherWorkspace) x-show="tab === 'progress'" x-cloak @endif>
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
                                hasAnyAssignment: $hasActiveAssignments,
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

                        @if($hasActiveAssignments)
                            <x-ui.button size="sm" variant="secondary" :href="route('student.assignments.index')">
                                My class assignments
                            </x-ui.button>
                        @else
                            <x-ui.button size="sm" variant="secondary" :href="route('home.practice')">
                                Explore the test library
                            </x-ui.button>
                        @endif
                    </div>
                </header>

                {{-- Row 1: what to do next + where you stand --}}
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-7">
                        @if ($hasActiveAssignments)
                            <x-ui.card id="classroom-assignments" stretch aria-labelledby="assignments-title">
                                <x-slot:header>
                                    <div class="home-panel__headline">
                                        <h2 id="assignments-title" class="home-panel__title">Assigned by your teacher</h2>
                                        <p class="home-panel__sub">{{ $openAssignments > 0 ? $openAssignments.' open' : 'All caught up' }}</p>
                                    </div>
                                    <x-ui.status-badge status="brand">Class work</x-ui.status-badge>
                                </x-slot:header>

                                <ul class="home-list">
                                    @foreach($assignments->take(2) as $a)
                                        @php
                                            // The resolver is the single source of truth for state: it also
                                            // reports Overdue / Upcoming / Closed, which a completed-vs-in-progress
                                            // check silently rendered as "no badge at all".
                                            $resolved = \App\Support\AssignmentStatusResolver::resolve($a, $a->attempts);
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
                                                    <x-ui.button size="sm" :href="route('student.assignments.show', ['assignment' => $a->ulid])">
                                                        {{ $actionLabel }}
                                                    </x-ui.button>
                                                </span>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>

                                <x-slot:footer>
                                    <div class="home-panel__foot">
                                        <span>Track every classroom task and score</span>
                                        <a href="{{ route('student.assignments.index') }}" class="home-link">
                                            View all assignments
                                            <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                        </a>
                                    </div>
                                </x-slot:footer>
                            </x-ui.card>
                        @elseif ($inProgressAttempt)
                            <x-ui.card stretch aria-labelledby="active-task-title">
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
                                        Module in progress
                                    </li>
                                    <li class="home-chip">
                                        <x-ui.icon name="layers" class="w-3.5 h-3.5" />
                                        Adaptive theta routing
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
                        @else
                            <x-ui.card stretch aria-labelledby="ledger-title">
                                <x-slot:header>
                                    <h2 id="ledger-title" class="home-panel__title">My study ledger</h2>
                                </x-slot:header>

                                @if($completedCount === 0)
                                    {{-- Nothing to tally yet: point at the first step instead of three zeroes. --}}
                                    <div class="home-empty">
                                        <x-ui.icon name="list-stars" class="home-empty__icon w-6 h-6" />
                                        <p class="home-empty__title">Your ledger starts with one test</p>
                                        <p class="home-empty__body">Take a full-length adaptive practice test to unlock your score, section breakdown, and study tips.</p>
                                        <x-ui.button size="sm" variant="secondary" :href="route('home.practice')">Start your first test</x-ui.button>
                                    </div>
                                @else
                                    <dl class="home-ledger">
                                        <div class="home-ledger__row">
                                            <dt>Personal best</dt>
                                            <dd class="home-num">{{ $bestScore ?: '—' }}</dd>
                                        </div>
                                        <div class="home-ledger__row">
                                            <dt>Practice completed</dt>
                                            <dd class="home-num">{{ $completedCount }} {{ \Illuminate\Support\Str::plural('test', $completedCount) }}</dd>
                                        </div>
                                        <div class="home-ledger__row">
                                            <dt>Target goal</dt>
                                            <dd class="home-num">1600</dd>
                                        </div>
                                    </dl>
                                @endif

                                <x-slot:footer>
                                    <div class="home-panel__foot">
                                        <span>See section and domain trends</span>
                                        <a href="{{ route('student.progress') }}" class="home-link">
                                            Detailed progress
                                            <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                        </a>
                                    </div>
                                </x-slot:footer>
                            </x-ui.card>
                        @endif
                    </div>

                    <div class="lg:col-span-5">
                        <x-ui.card stretch aria-labelledby="score-anchor-title">
                            <x-slot:header>
                                <h2 id="score-anchor-title" class="home-panel__title">Latest score</h2>
                                @if ($lastCompletedAttempt)
                                    <span class="home-panel__sub home-num">{{ $lastCompletedAttempt->completed_at?->format('M j, Y') ?? 'Recent' }}</span>
                                @endif
                            </x-slot:header>

                            @if ($lastScore)
                                <div class="home-score">
                                    <span class="home-score__value home-num">{{ $lastScore }}</span>
                                    <span class="home-score__max home-num">/ {{ $lastScoreMax }}</span>
                                    @if($isPersonalBest)
                                        <x-ui.status-badge status="success" class="home-score__badge">Personal best</x-ui.status-badge>
                                    @elseif($isSectionAttempt)
                                        <x-ui.status-badge status="neutral" class="home-score__badge">{{ $lastSectionLabel }} only</x-ui.status-badge>
                                    @endif
                                </div>

                                @if(!$isSectionAttempt && ($mathScore || $rwScore))
                                    <dl class="home-split">
                                        <div class="home-split__cell">
                                            <dt>Reading &amp; Writing</dt>
                                            <dd class="home-num">{{ $rwScore ?? '—' }} <span>/ 800</span></dd>
                                        </div>
                                        <div class="home-split__cell">
                                            <dt>Math</dt>
                                            <dd class="home-num">{{ $mathScore ?? '—' }} <span>/ 800</span></dd>
                                        </div>
                                    </dl>
                                @endif
                            @else
                                <div class="home-empty">
                                    <x-ui.icon name="graph-up-arrow" class="home-empty__icon w-6 h-6" />
                                    <p class="home-empty__title">No scores yet</p>
                                    <p class="home-empty__body">Finish a practice test and your score breakdown appears here.</p>
                                </div>
                            @endif

                            <x-slot:footer>
                                <div class="home-panel__foot">
                                    @if($lastCompletedAttempt)
                                        <span>Question-by-question review</span>
                                        <a href="{{ route('student.scores.show', $lastCompletedAttempt) }}" class="home-link">
                                            View breakdown
                                            <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                        </a>
                                    @else
                                        <span>Start with a full-length test</span>
                                        <a href="{{ route('home.practice') }}" class="home-link">
                                            Test library
                                            <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                        </a>
                                    @endif
                                </div>
                            </x-slot:footer>
                        </x-ui.card>
                    </div>
                </div>

                {{-- A practice attempt left open while class work is also due --}}
                @if ($hasActiveAssignments && $inProgressAttempt)
                    <section class="home-resume" aria-labelledby="active-practice-title">
                        <span class="home-resume__dot" aria-hidden="true"></span>
                        <div class="home-resume__body">
                            <p class="home-resume__label">Practice attempt still open</p>
                            <h2 id="active-practice-title" class="home-resume__title">
                                {{ $formatTitle($inProgressAttempt->test->title, 'Adaptive Full Practice Test 1') }}
                            </h2>
                        </div>
                        <x-ui.button size="sm" variant="secondary" @click="$dispatch('open-ready-modal', {{ \Illuminate\Support\Js::from($resumeModalPayload) }})">
                            Continue practice
                        </x-ui.button>
                    </section>
                @endif

                {{-- Recommended self-study test --}}
                @if ($featuredTest)
                    <x-ui.card aria-labelledby="featured-title">
                        <div class="home-featured">
                            <div class="home-featured__body">
                                <div class="home-head__meta">
                                    <x-ui.status-badge status="neutral">Self-study</x-ui.status-badge>
                                    <span class="home-date">Recommended from your recent practice</span>
                                </div>
                                <h2 id="featured-title" class="home-featured__title">
                                    {{ $formatTitle($featuredTest->title, 'Adaptive Full Length Practice Test 1') }}
                                </h2>
                                <p class="home-featured__desc">
                                    {{ $featuredTest->description ? \Illuminate\Support\Str::limit($featuredTest->description, 140) : 'A full-length adaptive SAT practice exam that matches Bluebook structure and theta scoring curves.' }}
                                </p>
                                <ul class="home-chips">
                                    <li class="home-chip">Full-length</li>
                                    <li class="home-chip"><span class="home-num">{{ $featuredDuration ?: '134' }}</span> min</li>
                                    <li class="home-chip"><span class="home-num">{{ $featuredSections ?: 2 }}</span> {{ \Illuminate\Support\Str::plural('section', $featuredSections ?: 2) }}</li>
                                    <li class="home-chip">IRT adaptive</li>
                                </ul>
                            </div>
                            <div class="home-featured__action">
                                <x-ui.button :href="route('home.practice')">Start practice</x-ui.button>
                            </div>
                        </div>
                    </x-ui.card>
                @endif

                {{-- Guidance and community --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="home-stack">
                        <section class="home-callout home-callout--tip" aria-labelledby="tip-title">
                            <x-ui.icon name="lightbulb" class="home-callout__icon w-4 h-4" />
                            <div>
                                <h2 id="tip-title" class="home-callout__title">Quick tip</h2>
                                <p class="home-callout__body">{!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($tip)) !!}</p>
                            </div>
                        </section>

                        <section class="home-callout home-callout--info" aria-labelledby="adaptive-title">
                            <x-ui.icon name="layers" class="home-callout__icon w-4 h-4" />
                            <div>
                                <h2 id="adaptive-title" class="home-callout__title">Your test adapts to you</h2>
                                <p class="home-callout__body">
                                    Adaptive full tests route you to an easier or harder second module based on your answers, then use IRT (EAP 3PL) scoring to estimate your score range.
                                </p>
                            </div>
                        </section>
                    </div>

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
                                    @foreach($moreposts as $post)
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
                            <div class="home-empty">
                                <x-ui.icon name="journal-text" class="home-empty__icon w-6 h-6" />
                                <p class="home-empty__title">No posts yet</p>
                                <p class="home-empty__body">Study tips written by teachers will show up here.</p>
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
                                @foreach($latestThreads as $thread)
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
                            <div class="home-empty">
                                <x-ui.icon name="chat-left-quote" class="home-empty__icon w-6 h-6" />
                                <p class="home-empty__title">No discussions yet</p>
                                <p class="home-empty__body">Ask the first question and a teacher will answer it.</p>
                                <x-ui.button size="sm" variant="secondary" @click="$dispatch('open-modal', 'modal-ask-question')">
                                    Ask a question
                                </x-ui.button>
                            </div>
                        @endif
                    </x-ui.card>
                </div>
            </div>

        </main>

        @include('forum.partials.ask-modal')
    </div>

    @if ($inProgressAttempt)
        <x-ui.ready-modal />
    @endif
</x-layouts.student>
