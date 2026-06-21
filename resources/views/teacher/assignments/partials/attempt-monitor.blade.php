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
                                    {{ $currentModule->section?->name ?? 'Current section' }} &middot; Module {{ $currentModule->module_number }}
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
                        <div><dt>{{ $isInProgress ? 'Started' : 'Score' }}</dt><dd>@if($isInProgress){{ $attempt->created_at->format('M j, g:i A') }}@elseif($attempt->total_score !== null){{ $attempt->total_score }}@else&mdash;@endif</dd></div>
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
                                            <strong class="{{ $answer->is_correct ? 'answer-correct' : 'answer-wrong' }}">Student: {{ $answer->selected_answer ?: 'Omitted' }} &middot; Correct: {{ $correct ?: 'N/A' }}</strong>
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
                            <span>Activity will appear after student saves an answer.</span>
                        </div>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</div>
