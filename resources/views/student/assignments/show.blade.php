<x-layouts.student :user="auth()->user()" :title="$assignment ? $assignment->title : 'Assignments'" header-type="none">
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
        <x-shell.icon-rail :logo-href="route('home')" :avatar-label="$userInitials"
            :items="\App\Support\NavRail::student('assignments')" />

        <!-- COLUMN 2: SIDEBAR LIST -->
        <x-shell.sidebar-list title="{{ $classroom ? $classroom->name . ' assignments' : 'Assignments' }}"
            count-text="{{ $assignments->count() }} total"
            search-placeholder="Search assignments..."
            :filters="[
                ['value' => 'all', 'label' => 'All'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'completed', 'label' => 'Completed'],
            ]"
            :items="$assignments
                ->map(function($a) use ($assignment) {
                    $attempts = $a->attempts;
                    $resolved = \App\Support\AssignmentStatusResolver::resolve($a, $attempts);
                    $state = $resolved['state'];
                    $status = $state === 'Completed' ? 'completed' : 'active';
                    
                    return [
                        'route' => route('student.assignments.show', ['assignment' => $a->ulid] + (request()->has('classroom') ? ['classroom' => request('classroom')] : [])),
                        'name' => $a->title,
                        'status' => $status,
                        'selected' => $assignment && $assignment->id === $a->id,
                        'meta' => [
                            $a->classroom->name,
                            $state,
                        ],
                    ];
                })
                ->all()" />

        <!-- COLUMN 3: LEDGER PANE -->
        <div class="ledger-pane">
            <x-ui.flash />

            <!-- MAIN LEDGER COLUMN -->
            <div class="binder-panel">
                @if ($assignment)
                    @php
                        $attempts = $assignment->attempts;
                        $resolved = \App\Support\AssignmentStatusResolver::resolve($assignment, $attempts);
                        $state = $resolved['state'];
                        $completed = $resolved['completed'];
                        $inProgress = $resolved['inProgress'];
                        $used = $resolved['used'];
                        $statusVariant = $resolved['variant'];
                    @endphp

                    <div class="ledger-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div class="dh-left">
                            @if ($classroom)
                                <a href="{{ route('student.classes.show', $classroom) }}" wire:navigate class="back-link" style="font-size: 11.5px; font-weight: 600; text-decoration: none; color: var(--accent); margin-bottom: 6px; display: inline-block;">&larr; Back to {{ $classroom->name }} assignments</a>
                            @endif
                            <h2>{{ $assignment->title }} <span class="handwriting status-quote">"{{ $state }}"</span></h2>
                            <div class="dh-desc">
                                {{ $assignment->test->title }}{{ $assignment->assign_type === 'section' ? ($assignment->section_type === 'reading_writing' ? ' (Reading & Writing Only)' : ' (Math Only)') : '' }} &middot; Classroom: <strong>{{ $assignment->classroom->name }}</strong>
                            </div>
                        </div>
                    </div>

                    <x-shell.stat-row :stats="[
                        ['value' => $assignment->attempt_limit, 'label' => 'Allowed attempts'],
                        ['value' => $used, 'label' => 'Attempts used'],
                        ['value' => $assignment->assign_type === 'section'
                            ? ($assignment->section_type === 'reading_writing' ? ($completed->max('score_reading_writing') ?: '—') : ($completed->max('score_math') ?: '—'))
                            : ($completed->max('total_score') ?: '—'), 'label' => 'Best score'],
                    ]" />

                    @if ($errors->any())
                        <div class="settings-card" style="border-color: var(--red); background: var(--red-soft); color: var(--red); padding: 12px; margin-bottom: 16px;">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <!-- ACTIONS SECTION -->
                    <div class="settings-card" style="margin-bottom: 20px; display: flex; flex-direction: column; gap: 12px;">
                        <h3 class="settings-title">Start Practice</h3>
                        <p style="font-size: 13px; color: var(--ink-soft); margin-bottom: 8px;">Ensure you have a stable internet connection before launching the test. The test will open in our adaptive browser engine.</p>
                        
                        @if ($inProgress || ($assignment->acceptsNewStarts() && $used < $assignment->attempt_limit))
                            <form method="POST" action="{{ route('student.assignments.start', $assignment) }}">
                                @csrf
                                <button type="submit" class="btn-sm-primary btn-px-16" style="padding: 8px 18px; font-size: 13px;">
                                    {{ $inProgress ? 'Resume attempt' : 'Start attempt ' . ($used + 1) }}
                                </button>
                            </form>
                        @else
                            <div style="background-color: var(--amber-soft); border: 1px solid var(--amber); color: var(--amber); padding: 10px 14px; border-radius: 8px; font-weight: 600; font-size: 12.5px; display: inline-block; align-self: flex-start;">
                                No new attempt is currently available for this assignment.
                            </div>
                        @endif
                    </div>

                    <!-- INSTRUCTIONS SECTION -->
                    @if ($assignment->instructions)
                        <div class="settings-card" style="margin-bottom: 20px;">
                            <h3 class="settings-title" style="margin-bottom: 6px;">Instructions from teacher</h3>
                            <p style="font-size: 13px; color: var(--ink); line-height: 1.5; white-space: pre-line;">{{ $assignment->instructions }}</p>
                        </div>
                    @endif

                    <!-- ATTEMPT HISTORY PANEL -->
                    <div style="margin-top: 24px;">
                        <h3 style="font-size: 15px; font-weight: 700; color: var(--ink); margin-bottom: 12px;">Attempt history</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Attempt</th>
                                    <th>Status</th>
                                    <th>Score</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($attempts as $attempt)
                                    <tr>
                                        <td><strong>Attempt {{ $attempt->attempt_number }}</strong></td>
                                        <td>
                                            <span class="status-pill {{ $attempt->status === 'completed' ? 'ok' : 'pending' }}">
                                                <span class="d"></span>{{ ucfirst(str_replace('_', ' ', $attempt->status)) }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong>
                                                @if($attempt->attempt_type === 'section')
                                                    {{ $attempt->section_type === 'reading_writing' ? ($attempt->score_reading_writing ?? '—') : ($attempt->score_math ?? '—') }} / 800
                                                @else
                                                    {{ $attempt->total_score ?? '—' }} / 1600
                                                @endif
                                            </strong>

                                        </td>
                                        <td class="text-right">
                                            @if ($attempt->status === 'completed')
                                                <a href="{{ route('student.scores.show', $attempt) }}" class="link-action">Review result</a>
                                            @else
                                                <span style="color: var(--ink-faint);">Review locked</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="empty-row">No attempts recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="ledger-header">
                        <h2>No Assignment Selected</h2>
                    </div>
                    <div class="empty-row" style="padding: 40px; text-align: center;">
                        Select an assignment from the sidebar list to view details and start attempts.
                    </div>
                @endif
            </div>

            <!-- CORKBOARD -->
            <x-shell.corkboard header="Assignment Info">
                @if ($assignment)
                    <div class="cork-note">
                        <h3>Schedule</h3>
                        <p class="digest-line"><strong>Available:</strong> {{ $assignment->available_at?->format('M j, Y g:i A') ?: 'Now' }}</p>
                        <p class="digest-line" style="margin-top: 4px;"><strong>Due at:</strong> {{ $assignment->due_at?->format('M j, Y g:i A') ?: 'No deadline' }}</p>
                    </div>

                    @if ($assignment->acceptsNewStarts() && $used < $assignment->attempt_limit)
                        <div class="cork-note">
                            <h3>Test Reminder</h3>
                            <p class="digest-line">You have <strong>{{ $assignment->attempt_limit - $used }}</strong> remaining attempts.</p>
                        </div>
                    @endif
                @else
                    <div class="cork-note">
                        <h3>Assignments</h3>
                        <p class="digest-line">View your scheduled tests, instructions, and review previous attempts in one place.</p>
                    </div>
                @endif
            </x-shell.corkboard>
        </div>
    </div>
</x-layouts.student>
