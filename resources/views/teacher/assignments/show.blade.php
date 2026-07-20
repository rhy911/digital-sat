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
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M12 6.5c-1.6-1.2-3.7-1.8-6-1.8-.7 0-1.4.05-2 .15v13.5c.6-.1 1.3-.15 2-.15 2.3 0 4.4.6 6 1.8m0-13.5c1.6-1.2 3.7-1.8 6-1.8.7 0 1.4.05 2 .15v13.5c-.6-.1-1.3-.15-2-.15-2.3 0-4.4.6-6 1.8m0-13.5v13.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>',
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

        <!-- COLUMN 3: LEDGER PANE -->
        <div class="ledger-pane">
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
                                            <div class="flex-name">
                                                <div class="avatar-sm">{{ $row['recipient']->student->initials }}</div>
                                                <div>
                                                    <div class="n">{{ $row['recipient']->student->name }}</div>
                                                    <div class="m">{{ $row['recipient']->student->email }}</div>
                                                </div>
                                            </div>
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
                    <div class="section-panel" :class="{ 'active': activeTab === 'analysis' }">
                        <div class="sp-head">
                            <div>
                                <p>Identify which questions students found most challenging. Ordered by incorrect rate descending.</p>
                            </div>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 45%;">Question / Stem</th>
                                    <th style="width: 15%;">Module</th>
                                    <th style="width: 15%;">Incorrect Rate</th>
                                    <th style="width: 25%;">Students with wrong/omitted answers</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($report['questionAnalysis'] as $analysis)
                                    @php($q = $analysis['question'])
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--ink); margin-bottom: 4px;">
                                                {{ $analysis['module_label'] }} &middot; Question {{ $analysis['position'] }} &middot; <span style="font-weight: normal; font-size: 11.5px; color: var(--ink-soft);">{{ ucfirst($q->difficulty) }}</span>
                                            </div>
                                            <div style="font-size: 12.5px; color: var(--ink); line-height: 1.4; max-height: 4.2em; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                                                {{ Str::limit(strip_tags($q->stem), 160) }}
                                            </div>
                                            <small style="color: var(--ink-soft); margin-top: 4px; display: block;">
                                                Domain: {{ $q->skill_domain }} &middot; Correct Answer: <strong>{{ $analysis['correct_answer'] }}</strong>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="role-tag">{{ $analysis['module_label'] }}</span>
                                        </td>
                                        <td>
                                            <strong style="color: {{ $analysis['incorrect_rate'] >= 60 ? 'var(--red)' : ($analysis['incorrect_rate'] >= 30 ? 'var(--amber)' : 'var(--green)') }}; font-size: 15px;">
                                                {{ $analysis['incorrect_rate'] }}%
                                            </strong>
                                            <small style="display: block; color: var(--ink-soft); font-size: 11px;">
                                                {{ count($analysis['incorrect_students']) }} / {{ $analysis['total_presented'] }} students
                                            </small>
                                        </td>
                                        <td>
                                            @if(count($analysis['incorrect_students']) > 0)
                                                <details class="action-disclosure" style="font-size: 12.5px; margin: 0;">
                                                    <summary style="font-weight: 600; color: var(--accent); cursor: pointer; padding: 4px 0;">
                                                        View list ({{ count($analysis['incorrect_students']) }})
                                                    </summary>
                                                    <ul style="list-style: none; padding-left: 4px; margin-top: 8px; margin-bottom: 0; display: flex; flex-direction: column; gap: 4px;">
                                                        @foreach($analysis['incorrect_students'] as $item)
                                                            <li style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 4px;">
                                                                <span style="font-weight: 550; color: var(--ink);">{{ $item['student']->name }}</span>
                                                                <span class="status-pill {{ $item['status'] === 'omitted' ? 'pending' : 'error' }}">
                                                                    <span class="d"></span>{{ $item['status'] === 'omitted' ? 'Omit' : 'Wrong (' . $item['selected'] . ')' }}
                                                                </span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </details>
                                            @else
                                                <span style="color: var(--green); font-weight: 600; font-size: 12.5px;">All correct!</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="empty-row">No item analysis available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- SETTINGS PANEL -->
                    <div class="section-panel" :class="{ 'active': activeTab === 'settings' }">
                        @if ($assignment->classroom->status === 'active')
                            <div class="settings-card" style="margin-bottom: 16px;">
                                <h3 class="settings-title lg">Edit details</h3>
                                <form method="POST" action="{{ route('teacher.assignments.update', $assignment) }}" class="flex flex-col gap-10 max-w-420">
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
                                    <button type="submit" class="btn-sm-primary btn-px-16 self-start flex-none w-auto mt-8">Save settings</button>
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
