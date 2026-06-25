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
            <div><span>Average total</span><strong>{{ $report['metrics']['average_score'] ?? '—' }}</strong></div>
            <div><span>Average R&W</span><strong>{{ $report['metrics']['average_rw'] ?? '—' }}</strong></div>
            <div><span>Average Math</span><strong>{{ $report['metrics']['average_math'] ?? '—' }}</strong></div>
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
                            <th>Est. R&amp;W</th>
                            <th>Est. Math</th>
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
                                <td>{{ $row['best']?->total_score ?? '—' }}</td>
                                <td>{{ $row['best']?->score_reading_writing ?? '—' }}</td>
                                <td>{{ $row['best']?->score_math ?? '—' }}</td>
                                <td>
                                    @if ($row['attempts']->isNotEmpty())
                                        @php($attemptModalId = 'attempts-' . $assignment->id . '-' . $row['recipient']->student_id)
                                        @php($initialAttempt = $row['attempts']->firstWhere('status', 'in_progress') ?? $row['attempts']->sortByDesc('attempt_number')->first())
                                        <button type="button" class="attempt-detail-trigger" x-data
                                            x-on:click.prevent="$dispatch('open-modal', '{{ $attemptModalId }}')"
                                            aria-haspopup="dialog">
                                            View attempts
                                        </button>
                                        <x-ui.modal :id="$attemptModalId" :title="'Attempts for ' . $row['recipient']->student->name" max-width="4xl">
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
                                <td colspan="7">No recipients yet.</td>
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
