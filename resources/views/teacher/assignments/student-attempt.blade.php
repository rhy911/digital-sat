<x-layouts.student :user="auth()->user()" :title="$student->name . ' — ' . $assignment->title" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css'])
    @endpush

    @php
        $userInitials = auth()->user()?->initials ?? 'U';
    @endphp

    <div class="app-shell" x-data="{
        searchQuery: '',
        statusFilter: 'all'
    }">
        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="route('teacher.progress')" :avatar-label="$userInitials" :items="[
            [
                'route' => route('teacher.progress'),
                'label' => 'Progress',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M4 19V5M4 19h16M8 15l3-4 3 3 5-7\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>',
            ],
            [
                'route' => route('teacher.classes.index'),
                'label' => 'Classes',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><rect x=\'3.5\' y=\'5\' width=\'17\' height=\'14\' rx=\'2\' /><path d=\'M3.5 9.5h17M8 5v-1M16 5v-1\' stroke-linecap=\'round\' /></svg>',
            ],
            [
                'route' => route('teacher.assignments.index'),
                'label' => 'Reports',
                'active' => true,
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M5 3v18h16\' stroke-linecap=\'round\' /><rect x=\'8\' y=\'12\' width=\'3\' height=\'6\' /><rect x=\'13\' y=\'8\' width=\'3\' height=\'10\' /><rect x=\'18\' y=\'5\' width=\'3\' height=\'13\' /></svg>',
            ],
            [
                'route' => route('home.practice'),
                'label' => 'Test Library',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M12 6.5c-1.6-1.2-3.7-1.8-6-1.8-.7 0-1.4.05-2 .15v13.5c.6-.1 1.3-.15 2-.15 2.3 0 4.4.6 6 1.8m0-13.5c1.6-1.2 3.7-1.8 6-1.8.7 0-1.4.05-2 .15v13.5c-.6-.1-1.3-.15-2-.15-2.3 0-4.4.6-6 1.8m0-13.5v13.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>',
            ],
            [
                'route' => route('home-dashboard.index'),
                'label' => 'Test Builder',
                'target' => '_blank',
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M14.7 6.3a3 3 0 0 0 4 4L14 15l-4 1 1-4Z\' stroke-linejoin=\'round\' /></svg>',
            ],
        ]" />

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

        <div class="ledger-pane ledger-pane--no-scroll">
            <!-- MAIN LEDGER COLUMN -->
            <div class="binder-panel">
                <div class="ledger-header">
                    <div class="ledger-header__identity">
                        <!-- Student Avatar Stamp -->
                        <div class="ledger-header__avatar-stamp">
                            {{ strtoupper(substr($student->name, 0, 1)) }}
                        </div>
                        <div class="ledger-header__title-group">
                            <h2 class="ledger-header__title">{{ $student->name }}</h2>
                            <div class="ledger-header__subtitle">
                                {{ $student->email }} &middot; Assignment: <strong>{{ $assignment->title }}</strong>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ledger-header__actions">
                        <a href="{{ $prevStudentId ? route('teacher.assignments.students.show', [$assignment, $prevStudentId]) : '#' }}"
                            class="btn-sm-ghost ledger-header__action-btn @if(!$prevStudentId) is-disabled @endif">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="19" y1="12" x2="5" y2="12"></line>
                                <polyline points="12 19 5 12 12 5"></polyline>
                            </svg>
                            Prev Student
                        </a>
                        <a href="{{ $nextStudentId ? route('teacher.assignments.students.show', [$assignment, $nextStudentId]) : '#' }}"
                            class="btn-sm-ghost ledger-header__action-btn @if(!$nextStudentId) is-disabled @endif">
                            Next Student
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="student-attempt-wrapper">
                    @include('teacher.assignments.partials.attempt-monitor', [
                        'assignment' => $assignment, 
                        'row' => $row, 
                        'initialAttempt' => $initialAttempt, 
                        'attemptModalId' => $attemptModalId,
                        'hideHeader' => true
                    ])
                </div>
            </div>

            <!-- CORKBOARD -->
            <x-shell.corkboard header="Student Attempt">
                <div class="cork-note">
                    <h3>Quick Navigator</h3>
                    <p class="digest-line">View other student attempts using navigation controls above.</p>
                </div>
                <div class="cork-note">
                    <h3>Reviewing</h3>
                    <p class="digest-line">You are currently monitoring: <strong>{{ $student->name }}</strong>.</p>
                    <a href="{{ route('teacher.assignments.show', $assignment) }}" class="btn-sm-primary btn-pin text-center ledger-corkboard-action">Back to Report</a>
                </div>
            </x-shell.corkboard>
        </div>
    </div>
</x-layouts.student>
