<x-layouts.student :user="auth()->user()" :title="$student->name . ' — ' . $assignment->title" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/scores.css', 'resources/js/question-renderer.js'])
    @endpush

    @php
        $userInitials = auth()->user()?->initials ?? 'U';
    @endphp

    <div class="app-shell" x-data="{
        searchQuery: '',
        statusFilter: 'all'
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

        <main class="ledger-pane no-corkboard" id="main-content">
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
                    
                    <div class="ledger-header__actions flex-wrap">
                        <a href="{{ route('teacher.assignments.show', $assignment) }}"
                            class="btn-sm-ghost ledger-header__action-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="19" y1="12" x2="5" y2="12"></line>
                                <polyline points="12 19 5 12 12 5"></polyline>
                            </svg>
                            Back to Report
                        </a>
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
                        @if ($initialAttempt && ($initialAttempt->status === 'completed' || $initialAttempt->total_score !== null))
                            <a href="{{ route('student.scores.show', $initialAttempt) }}" target="_blank" rel="noopener noreferrer" class="btn-sm-primary ledger-header__action-btn">
                                Open Score Report &rarr;
                            </a>
                        @else
                            <button type="button" disabled class="btn-sm-ghost is-disabled ledger-header__action-btn cursor-not-allowed opacity-50" title="Score report is available once attempt is completed">
                                Score Report (In Progress)
                            </button>
                        @endif
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
        </main>
    </div>
</x-layouts.student>
