<x-layouts.student :user="auth()->user()" :title="$assignment->title" header-type="progress">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
    @endpush
    <div class="ds-teacher-workspace teacher-detail">
        @if (session('success'))
            <div class="class-alert class-alert--success" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="class-alert class-alert--error" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($origin === 'workspace')
            <a class="back-link" href="{{ route('teacher.assignments.index') }}">Back to assignments &amp; reports</a>
        @else
            <a class="back-link" href="{{ route('teacher.classes.show', $assignment->classroom) }}#assignments">Back to {{ $assignment->classroom->name }}</a>
        @endif

        <div class="page-heading">
            <div>
                <span
                    class="status-chip status-chip--{{ $assignment->status }}">{{ ucfirst($assignment->status) }}</span>
                <h1>{{ $assignment->title }}</h1>
                <p>{{ $assignment->test->title }} · {{ $assignment->attempt_limit }} allowed attempt(s)</p>
            </div>
            @if ($assignment->classroom->status === 'active')
                <div class="row-actions">
                    @if ($assignment->status === 'draft')
                        <form method="POST" action="{{ route('teacher.assignments.publish', $assignment) }}">
                            @csrf
                            <button class="class-button class-button--primary">Publish</button>
                        </form>
                    @elseif($assignment->status === 'published')
                        <form method="POST" action="{{ route('teacher.assignments.close', $assignment) }}">
                            @csrf
                            <button class="class-button">Close</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('teacher.assignments.reopen', $assignment) }}">
                            @csrf
                            <button class="class-button">Reopen</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        <div class="metric-strip">
            <div><span>Assigned</span><strong>{{ $report['metrics']['assigned'] }}</strong></div>
            <div><span>Completed</span><strong>{{ $report['metrics']['completed'] }}</strong></div>
            <div><span>In progress</span><strong>{{ $report['metrics']['in_progress'] }}</strong></div>
            <div><span>Average total</span>
                <strong>
                    @if ($assignment->assign_type === 'section')
                        {{ $assignment->section_type === 'reading_writing' ? ($report['metrics']['average_rw'] ? $report['metrics']['average_rw'] . ' / 800' : '—') : ($report['metrics']['average_math'] ? $report['metrics']['average_math'] . ' / 800' : '—') }}
                    @else
                        {{ $report['metrics']['average_score'] ? $report['metrics']['average_score'] . ' / 1600' : '—' }}
                    @endif
                </strong>
            </div>
            @if ($assignment->assign_type !== 'section')
                <div><span>Average R&W</span><strong>{{ $report['metrics']['average_rw'] ?? '—' }}</strong></div>
                <div><span>Average Math</span><strong>{{ $report['metrics']['average_math'] ?? '—' }}</strong></div>
            @endif
        </div>

        @if ($assignment->classroom->status === 'active')
            <details class="create-disclosure class-panel">
                <summary>Edit assignment settings</summary>
                <form method="POST" action="{{ route('teacher.assignments.update', $assignment) }}" class="form-grid">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="test_id" value="{{ $assignment->test_id }}">
                    <label>Title<input name="title" value="{{ $assignment->title }}" required
                            maxlength="180"></label>
                    <label>Attempt limit<input type="number" name="attempt_limit"
                            value="{{ $assignment->attempt_limit }}" min="1" max="10" required></label>
                    <label class="span-2">Instructions
                        <textarea name="instructions" rows="3">{{ $assignment->instructions }}</textarea>
                    </label>
                    <label>Available from (Asia/Ho_Chi_Minh)<input type="text" class="datetime-picker" name="available_at"
                            value="{{ $assignment->available_at?->format('Y-m-d\\TH:i') }}" placeholder="Select date and time..."></label>
                    <label>Due at (Asia/Ho_Chi_Minh)<input type="text" class="datetime-picker" name="due_at"
                            value="{{ $assignment->due_at?->format('Y-m-d\\TH:i') }}" placeholder="Select date and time..."></label>
                    <div class="form-action span-2">
                        <button class="class-button class-button--primary">Save settings</button>
                    </div>
                </form>
            </details>
        @endif

        <section class="class-panel">
            <div class="section-heading">
                <div>
                    <h2>Student results</h2>
                    <p>Best completed score represents each student; every attempt remains available.</p>
                </div>
            </div>
            <div class="report-table-wrap">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Status</th>
                            <th>Attempts</th>
                            <th>Best estimate</th>
                            @if($assignment->assign_type !== 'section')
                                <th>Est. R&amp;W</th>
                                <th>Est. Math</th>
                            @endif
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['rows'] as $row)
                            <tr>
                                <td><strong>{{ $row['recipient']->student->name }}</strong><small>{{ $row['recipient']->student->email }}</small>
                                </td>
                                <td>{{ $row['recipient']->status === 'withdrawn' ? 'Withdrawn' : ($row['in_progress'] ? 'In progress' : ($row['best'] ? ($row['late'] ? 'Completed late' : 'Completed') : 'Not started')) }}
                                </td>
                                <td>{{ $row['completed_count'] }} / {{ $assignment->attempt_limit }}</td>
                                <td>
                                    @if ($row['best'])
                                        @if ($assignment->assign_type === 'section')
                                            <strong>{{ $assignment->section_type === 'reading_writing' ? ($row['best']->score_reading_writing ?? '—') : ($row['best']->score_math ?? '—') }} / 800</strong>
                                        @else
                                            <strong>{{ $row['best']->total_score ?? '—' }} / 1600</strong>
                                        @endif
                                        <small class="block text-slate-500 mt-0.5" style="font-size: 0.75rem; font-weight: normal; color: var(--cw-muted-strong);">
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
                                <td>
                                    @if ($row['attempts']->isNotEmpty())
                                        @php($attemptModalId = 'attempts-' . $assignment->id . '-' . $row['recipient']->student_id)
                                        @php($initialAttempt = $row['attempts']->firstWhere('status', 'in_progress') ?? $row['attempts']->sortByDesc('attempt_number')->first())
                                        <button type="button" class="attempt-detail-trigger" x-data
                                            x-on:click.prevent="$dispatch('open-modal', '{{ $attemptModalId }}')"
                                            aria-haspopup="dialog">
                                            View attempts
                                        </button>
                                        <x-ui.modal :id="$attemptModalId" :title="'Attempts for ' . $row['recipient']->student->name" max-width="7xl">
                                            <div class="attempt-monitor" data-attempt-monitor
                                                data-poll-url="{{ route('teacher.assignments.attempt-monitor', [$assignment, $row['recipient']->student]) }}"
                                                data-active-attempt="{{ $initialAttempt?->id }}">
                                                <div style="padding: 3rem; text-align: center; color: var(--color-gray-500);">
                                                    Loading attempt details...
                                                </div>
                                            </div>
                                        </x-ui.modal>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $assignment->assign_type === 'section' ? 5 : 7 }}">No recipients yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @if(isset($report['paginator']) && $report['paginator']->hasPages())
                    <div style="margin-top: 1.5rem;">
                        {{ $report['paginator']->links() }}
                    </div>
                @endif
            </div>
        </section>

        <section class="class-panel" style="margin-top: 2rem;">
            <div class="section-heading">
                <div>
                    <h2>Item &amp; Question Analysis</h2>
                    <p>Identify which questions students found most challenging. Ordered by incorrect rate descending.</p>
                </div>
            </div>
            <div class="report-table-wrap">
                <table class="report-table">
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
                                    <div style="font-weight: 600; color: var(--cw-ink-strong); margin-bottom: 0.25rem;">
                                        {{ $analysis['module_label'] }} &middot; Question {{ $analysis['position'] }} &middot; <span style="font-weight: normal; font-size: 0.85rem; color: var(--cw-muted-strong);">{{ ucfirst($q->difficulty) }}</span>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--cw-ink); line-height: 1.4; max-height: 4.2em; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                                        {{ Str::limit(strip_tags($q->stem), 160) }}
                                    </div>
                                    <small style="color: var(--cw-muted-strong); margin-top: 0.25rem; display: block;">
                                        Domain: {{ $q->skill_domain }} &middot; Correct Answer: <strong>{{ $analysis['correct_answer'] }}</strong>
                                    </small>
                                </td>
                                <td>
                                    <span class="status-chip status-chip--draft" style="background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 0.75rem;">
                                        {{ $analysis['module_label'] }}
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: {{ $analysis['incorrect_rate'] >= 60 ? 'var(--cw-danger)' : ($analysis['incorrect_rate'] >= 30 ? 'var(--cw-warning, #d97706)' : 'var(--cw-success, #16a34a)') }}; font-size: 1.1rem;">
                                        {{ $analysis['incorrect_rate'] }}%
                                    </strong>
                                    <small style="display: block; color: var(--cw-muted-strong); font-size: 0.75rem;">
                                        {{ count($analysis['incorrect_students']) }} / {{ $analysis['total_presented'] }} students
                                    </small>
                                </td>
                                <td>
                                    @if(count($analysis['incorrect_students']) > 0)
                                        <details class="action-disclosure" style="font-size: 0.85rem; margin: 0;">
                                            <summary style="font-weight: 600; color: var(--color-brand); cursor: pointer; padding: 0.25rem 0;">
                                                View list ({{ count($analysis['incorrect_students']) }})
                                            </summary>
                                            <ul style="list-style: none; padding-left: 0.25rem; margin-top: 0.5rem; margin-bottom: 0; display: flex; flex-direction: column; gap: 0.375rem;">
                                                @foreach($analysis['incorrect_students'] as $item)
                                                    <li style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--cw-line-soft, #f3f4f6); padding-bottom: 0.25rem;">
                                                        <span style="font-weight: 550; color: var(--cw-ink-strong);">{{ $item['student']->name }}</span>
                                                        <span class="status-chip" style="font-size: 0.7rem; padding: 1px 6px; background-color: {{ $item['status'] === 'omitted' ? '#f1f5f9' : '#fee2e2' }}; color: {{ $item['status'] === 'omitted' ? '#475569' : '#b91c1c' }}; border: none;">
                                                            {{ $item['status'] === 'omitted' ? 'Omit' : 'Wrong (' . $item['selected'] . ')' }}
                                                        </span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @else
                                        <span style="color: var(--cw-success, #16a34a); font-weight: 600; font-size: 0.85rem;">All correct! 🎉</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">No item analytics available yet (requires completed attempts).</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($assignment->classroom->status === 'active')
            <div class="danger-zone" style="margin-top: 2rem;">
                <form method="POST" action="{{ route('teacher.assignments.destroy', $assignment) }}"
                    data-confirm="Delete this assignment? All student attempt records for this assignment will be detached but preserved in the database.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="class-button class-button--danger">Delete assignment</button>
                </form>
            </div>
        @endif
    </div>
</x-layouts.student>
