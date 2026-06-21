<x-layouts.student :user="auth()->user()" :title="$assignment->title" header-type="progress">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
    @endpush
    <div class="ds-teacher-workspace teacher-detail">
        @if(session('success'))<div class="class-alert class-alert--success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="class-alert class-alert--error" role="alert">{{ $errors->first() }}</div>@endif

        @if($origin === 'workspace')
            <a class="back-link" href="{{ route('teacher.assignments.index') }}">Back to assignments &amp; reports</a>
        @else
            <a class="back-link" href="{{ route('teacher.classes.show', $assignment->classroom) }}#assignments">Back to {{ $assignment->classroom->name }}</a>
        @endif

        <div class="page-heading">
            <div>
                <span class="status-chip status-chip--{{ $assignment->status }}">{{ ucfirst($assignment->status) }}</span>
                <h1>{{ $assignment->title }}</h1>
                <p>{{ $assignment->test->title }} · {{ $assignment->attempt_limit }} allowed attempt(s)</p>
            </div>
            @if($assignment->classroom->status === 'active')
                <div class="row-actions">
                    @if($assignment->status === 'draft')
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

        @if($assignment->classroom->status === 'active')
            <details class="create-disclosure class-panel">
                <summary>Edit assignment settings</summary>
                <form method="POST" action="{{ route('teacher.assignments.update', $assignment) }}" class="form-grid">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="test_id" value="{{ $assignment->test_id }}">
                    <label>Title<input name="title" value="{{ $assignment->title }}" required maxlength="180"></label>
                    <label>Attempt limit<input type="number" name="attempt_limit" value="{{ $assignment->attempt_limit }}" min="1" max="10" required></label>
                    <label class="span-2">Instructions<textarea name="instructions" rows="3">{{ $assignment->instructions }}</textarea></label>
                    <label>Available from (Asia/Ho_Chi_Minh)<input type="datetime-local" name="available_at" value="{{ $assignment->available_at?->format('Y-m-d\TH:i') }}"></label>
                    <label>Due at (Asia/Ho_Chi_Minh)<input type="datetime-local" name="due_at" value="{{ $assignment->due_at?->format('Y-m-d\TH:i') }}"></label>
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
                            <th>Best</th>
                            <th>R&W</th>
                            <th>Math</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['rows'] as $row)
                            <tr>
                                <td><strong>{{ $row['recipient']->student->name }}</strong><small>{{ $row['recipient']->student->email }}</small></td>
                                <td>{{ $row['recipient']->status === 'withdrawn' ? 'Withdrawn' : ($row['in_progress'] ? 'In progress' : ($row['best'] ? ($row['late'] ? 'Completed late' : 'Completed') : 'Not started')) }}</td>
                                <td>{{ $row['completed_count'] }} / {{ $assignment->attempt_limit }}</td>
                                <td>{{ $row['best']?->total_score ?? '—' }}</td>
                                <td>{{ $row['best']?->score_reading_writing ?? '—' }}</td>
                                <td>{{ $row['best']?->score_math ?? '—' }}</td>
                                <td>
                                    @if($row['attempts']->isNotEmpty())
                                        @php($attemptModalId = 'attempts-'.$assignment->id.'-'.$row['recipient']->student_id)
                                        @php($initialAttempt = $row['attempts']->firstWhere('status', 'in_progress') ?? $row['attempts']->sortByDesc('attempt_number')->first())
                                        <button
                                            type="button"
                                            class="attempt-detail-trigger"
                                            x-data
                                            x-on:click.prevent="$dispatch('open-modal', '{{ $attemptModalId }}')"
                                            aria-haspopup="dialog"
                                        >
                                            View attempts
                                        </button>
                                        <x-ui.modal
                                            :id="$attemptModalId"
                                            :title="'Attempts for '.$row['recipient']->student->name"
                                            max-width="4xl"
                                        >
                                            <div
                                                class="attempt-monitor"
                                                x-data="{ activeAttempt: {{ $initialAttempt->id }} }"
                                                data-attempt-monitor
                                                data-poll-url="{{ route('teacher.assignments.attempt-monitor', [$assignment, $row['recipient']->student]) }}"
                                                data-active-attempt="{{ $initialAttempt->id }}"
                                            >
                                                <header class="attempt-monitor__student">
                                                    <div>
                                                        <p>{{ $row['recipient']->student->email }}</p>
                                                        <span>{{ $row['attempts']->count() }} {{ \Illuminate\Support\Str::plural('attempt', $row['attempts']->count()) }} recorded</span>
                                                    </div>
                                                    <div class="attempt-monitor__presence">
                                                        @if($row['in_progress'])
                                                            <span class="attempt-monitor__live"><i aria-hidden="true"></i> In progress</span>
                                                        @else
                                                            <span class="attempt-monitor__complete">No active attempt</span>
                                                        @endif
                                                        <small data-monitor-update-status aria-live="polite">Live updates</small>
                                                    </div>
                                                </header>

                                                <nav class="attempt-monitor__tabs" role="tablist" aria-label="Student attempts">
                                                    @foreach($row['attempts'] as $attempt)
                                                        <button
                                                            type="button"
                                                            role="tab"
                                                            id="{{ $attemptModalId }}-tab-{{ $attempt->id }}"
                                                            aria-controls="{{ $attemptModalId }}-panel-{{ $attempt->id }}"
                                                            data-attempt-id="{{ $attempt->id }}"
                                                            x-on:click="activeAttempt = {{ $attempt->id }}"
                                                            x-bind:aria-selected="activeAttempt === {{ $attempt->id }}"
                                                            x-bind:class="{ 'is-active': activeAttempt === {{ $attempt->id }} }"
                                                        >
                                                            <span>Attempt {{ $attempt->attempt_number }}</span>
                                                            <small>{{ $attempt->status === 'in_progress' ? 'Active now' : ($attempt->total_score ? $attempt->total_score.' points' : ucfirst(str_replace('_', ' ', $attempt->status))) }}</small>
                                                        </button>
                                                    @endforeach
                                                </nav>

                                                <div class="attempt-monitor__panels">
                                                    @foreach($row['attempts'] as $attempt)
                                                        @php($isInProgress = $attempt->status === 'in_progress')
                                                        @php($currentModule = $attempt->currentModule)
                                                        @php($savedResponses = $attempt->userAnswers->count())
                                                        @php($answeredResponses = $attempt->userAnswers->filter(fn ($answer) => filled($answer->selected_answer))->count())
                                                        @php($moduleResponses = $currentModule ? $attempt->userAnswers->where('module_id', $currentModule->id)->filter(fn ($answer) => filled($answer->selected_answer))->count() : $answeredResponses)
                                                        @php($questionTotal = $currentModule?->total_questions ?: max($savedResponses, 1))
                                                        @php($progress = min(100, (int) round(($moduleResponses / max($questionTotal, 1)) * 100)))
                                                        @php($elapsedSeconds = (int) $attempt->current_module_elapsed_seconds)
                                                        <section
                                                            class="attempt-monitor__panel"
                                                            id="{{ $attemptModalId }}-panel-{{ $attempt->id }}"
                                                            role="tabpanel"
                                                            aria-labelledby="{{ $attemptModalId }}-tab-{{ $attempt->id }}"
                                                            x-show="activeAttempt === {{ $attempt->id }}"
                                                            x-cloak
                                                        >
                                                            <div class="attempt-monitor__summary">
                                                                <div class="attempt-monitor__status-line">
                                                                    <div>
                                                                        <span class="attempt-monitor__status attempt-monitor__status--{{ $isInProgress ? 'active' : 'complete' }}">{{ $isInProgress ? 'In progress' : ucfirst(str_replace('_', ' ', $attempt->status)) }}</span>
                                                                        <h4>
                                                                            @if($currentModule)
                                                                                {{ $currentModule->section?->name ?? 'Current section' }} · Module {{ $currentModule->module_number }}
                                                                            @else
                                                                                Attempt {{ $attempt->attempt_number }} overview
                                                                            @endif
                                                                        </h4>
                                                                    </div>
                                                                    <span class="attempt-monitor__activity">Last activity {{ $attempt->updated_at->diffForHumans() }}</span>
                                                                </div>

                                                                <dl class="attempt-monitor__metrics">
                                                                    <div><dt>Answered</dt><dd>{{ $answeredResponses }}</dd></div>
                                                                    <div><dt>{{ $isInProgress ? 'Module progress' : 'Responses saved' }}</dt><dd>{{ $isInProgress && $currentModule ? $moduleResponses.' / '.$questionTotal : $savedResponses }}</dd></div>
                                                                    <div><dt>Module time</dt><dd>{{ intdiv($elapsedSeconds, 60) }}:{{ str_pad((string) ($elapsedSeconds % 60), 2, '0', STR_PAD_LEFT) }}</dd></div>
                                                                    <div><dt>{{ $isInProgress ? 'Started' : 'Score' }}</dt><dd>{{ $isInProgress ? $attempt->created_at->format('M j, g:i A') : ($attempt->total_score ?? '—') }}</dd></div>
                                                                </dl>

                                                                @if($isInProgress && $currentModule)
                                                                    <div class="attempt-monitor__progress">
                                                                        <div><span>Current module completion</span><strong>{{ $progress }}%</strong></div>
                                                                        <span role="progressbar" aria-label="Current module completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}"><i style="width: {{ $progress }}%"></i></span>
                                                                    </div>
                                                                @elseif($assignment->due_at && $attempt->completed_at?->gt($assignment->due_at))
                                                                    <p class="attempt-monitor__notice">Submitted after the assignment deadline.</p>
                                                                @endif
                                                            </div>

                                                            <div class="attempt-monitor__responses">
                                                                <div class="attempt-monitor__responses-heading">
                                                                    <h5>{{ $isInProgress ? 'Saved responses' : 'Response review' }}</h5>
                                                                    <span>{{ $savedResponses }} {{ \Illuminate\Support\Str::plural('question', $savedResponses) }}</span>
                                                                </div>
                                                                @if($attempt->userAnswers->isNotEmpty())
                                                                    <ol>
                                                                        @foreach($attempt->userAnswers as $answer)
                                                                            @php($correct = $answer->question?->sprCorrectAnswers->pluck('answer')->implode(', ') ?: $answer->question?->answerChoices->firstWhere('is_correct', true)?->label)
                                                                            <li>
                                                                                <span class="attempt-monitor__question-number">{{ $loop->iteration }}</span>
                                                                                <div>
                                                                                    <p>{{ \Illuminate\Support\Str::limit(strip_tags($answer->question?->stem), 120) }}</p>
                                                                                    @if($isInProgress)
                                                                                        <strong class="{{ filled($answer->selected_answer) ? 'response-saved' : 'response-omitted' }}">{{ filled($answer->selected_answer) ? 'Answered: '.$answer->selected_answer : 'Omitted' }}</strong>
                                                                                    @else
                                                                                        <strong class="{{ $answer->is_correct ? 'answer-correct' : 'answer-wrong' }}">Student: {{ $answer->selected_answer ?: 'Omitted' }} · Correct: {{ $correct ?: 'N/A' }}</strong>
                                                                                        @if($answer->question?->explanation?->explanation)
                                                                                            <small>{{ \Illuminate\Support\Str::limit(strip_tags($answer->question->explanation->explanation), 180) }}</small>
                                                                                        @endif
                                                                                    @endif
                                                                                </div>
                                                                            </li>
                                                                        @endforeach
                                                                    </ol>
                                                                @else
                                                                    <div class="attempt-monitor__empty">
                                                                        <strong>No responses saved yet</strong>
                                                                        <span>Activity will appear after the student saves an answer.</span>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </section>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </x-ui.modal>
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

        @if($assignment->classroom->status === 'active')
            <div class="danger-zone" style="margin-top: 2rem;">
                <form method="POST" action="{{ route('teacher.assignments.destroy', $assignment) }}" data-confirm="Delete this assignment? All student attempt records for this assignment will be detached but preserved in the database.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="class-button class-button--danger">Delete assignment</button>
                </form>
            </div>
        @endif
    </div>
</x-layouts.student>
