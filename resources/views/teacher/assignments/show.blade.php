<x-layouts.teacher :title="$assignment->title">
    @if($origin === 'workspace')
        <a class="back-link" href="{{ route('teacher.assignments.index') }}">Back to assignments &amp; reports</a>
    @else
        <a class="back-link" href="{{ route('teacher.classes.show', $assignment->classroom) }}#assignments">Back to {{ $assignment->classroom->name }}</a>
    @endif
    <div class="page-heading"><div><span class="status-chip status-chip--{{ $assignment->status }}">{{ ucfirst($assignment->status) }}</span><h1>{{ $assignment->title }}</h1><p>{{ $assignment->test->title }} · {{ $assignment->attempt_limit }} allowed attempt(s)</p></div>@if($assignment->classroom->status === 'active')<div class="row-actions">@if($assignment->status === 'draft')<form method="POST" action="{{ route('teacher.assignments.publish', $assignment) }}">@csrf<button class="class-button class-button--primary">Publish</button></form>@elseif($assignment->status === 'published')<form method="POST" action="{{ route('teacher.assignments.close', $assignment) }}">@csrf<button class="class-button">Close</button></form>@else<form method="POST" action="{{ route('teacher.assignments.reopen', $assignment) }}">@csrf<button class="class-button">Reopen</button></form>@endif</div>@endif</div>
    <div class="metric-strip"><div><span>Assigned</span><strong>{{ $report['metrics']['assigned'] }}</strong></div><div><span>Completed</span><strong>{{ $report['metrics']['completed'] }}</strong></div><div><span>In progress</span><strong>{{ $report['metrics']['in_progress'] }}</strong></div><div><span>Average total</span><strong>{{ $report['metrics']['average_score'] ?? '—' }}</strong></div><div><span>Average R&W</span><strong>{{ $report['metrics']['average_rw'] ?? '—' }}</strong></div><div><span>Average Math</span><strong>{{ $report['metrics']['average_math'] ?? '—' }}</strong></div></div>
    @if($assignment->classroom->status === 'active')<details class="create-disclosure class-panel"><summary>Edit assignment settings</summary>
        <form method="POST" action="{{ route('teacher.assignments.update', $assignment) }}" class="form-grid">@csrf @method('PUT')
            <input type="hidden" name="test_id" value="{{ $assignment->test_id }}">
            <label>Title<input name="title" value="{{ $assignment->title }}" required maxlength="180"></label>
            <label>Attempt limit<input type="number" name="attempt_limit" value="{{ $assignment->attempt_limit }}" min="1" max="10" required></label>
            <label class="span-2">Instructions<textarea name="instructions" rows="3">{{ $assignment->instructions }}</textarea></label>
            <label>Available from (Asia/Ho_Chi_Minh)<input type="datetime-local" name="available_at" value="{{ $assignment->available_at?->format('Y-m-d\TH:i') }}"></label>
            <label>Due at (Asia/Ho_Chi_Minh)<input type="datetime-local" name="due_at" value="{{ $assignment->due_at?->format('Y-m-d\TH:i') }}"></label>
            <div class="form-action span-2"><button class="class-button class-button--primary">Save settings</button></div>
        </form>
    </details>@endif
    <section class="class-panel">
        <div class="section-heading"><div><h2>Student results</h2><p>Best completed score represents each student; every attempt remains available.</p></div></div>
        <div class="report-table-wrap">
            <table class="report-table">
                <thead><tr><th>Student</th><th>Status</th><th>Attempts</th><th>Best</th><th>R&W</th><th>Math</th><th>Detail</th></tr></thead>
                <tbody>
                @forelse($report['rows'] as $row)
                    <tr>
                        <td><strong>{{ $row['recipient']->student->name }}</strong><small>{{ $row['recipient']->student->email }}</small></td>
                        <td>{{ $row['recipient']->status === 'withdrawn' ? 'Withdrawn' : ($row['best'] ? ($row['late'] ? 'Completed late' : 'Completed') : ($row['in_progress'] ? 'In progress' : 'Not started')) }}</td>
                        <td>{{ $row['completed_count'] }} / {{ $assignment->attempt_limit }}</td>
                        <td>{{ $row['best']?->total_score ?? '—' }}</td>
                        <td>{{ $row['best']?->score_reading_writing ?? '—' }}</td>
                        <td>{{ $row['best']?->score_math ?? '—' }}</td>
                        <td>
                            @if($row['attempts']->isNotEmpty())
                                <details class="attempt-detail">
                                    <summary>View attempts</summary>
                                    <div>
                                    @foreach($row['attempts'] as $attempt)
                                        <h4>
                                            Attempt {{ $attempt->attempt_number }} · {{ ucfirst(str_replace('_', ' ', $attempt->status)) }}
                                            @if($attempt->total_score) · {{ $attempt->total_score }} @endif
                                            @if($assignment->due_at && $attempt->completed_at?->gt($assignment->due_at)) · Late @endif
                                        </h4>
                                        @if($attempt->userAnswers->isNotEmpty())
                                            <ol>
                                            @foreach($attempt->userAnswers as $answer)
                                                @php($correct = $answer->question?->sprCorrectAnswers->pluck('answer')->implode(', ') ?: $answer->question?->answerChoices->firstWhere('is_correct', true)?->label)
                                                <li>
                                                    <span>{{ \Illuminate\Support\Str::limit(strip_tags($answer->question?->stem), 90) }}</span>
                                                    <strong class="{{ $answer->is_correct ? 'answer-correct' : 'answer-wrong' }}">Student: {{ $answer->selected_answer ?: 'Omitted' }} · Correct: {{ $correct ?: 'N/A' }}</strong>
                                                    @if($answer->question?->explanation?->explanation)
                                                        <small>{{ \Illuminate\Support\Str::limit(strip_tags($answer->question->explanation->explanation), 180) }}</small>
                                                    @endif
                                                </li>
                                            @endforeach
                                            </ol>
                                        @endif
                                    @endforeach
                                    </div>
                                </details>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No recipients yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.teacher>
