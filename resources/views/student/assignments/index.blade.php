<x-layouts.student :user="$user" title="Assignments" header-type="progress">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
    @endpush
    <div class="student-workspace">
        @if ($classroom)
            <a class="back-link" wire:navigate href="{{ route('student.classes.index') }}">Back to my classes</a>
        @endif
        <div class="page-heading">
            <div>
                <h1>{{ $classroom ? $classroom->name . ' assignments' : 'Assignments' }}</h1>
                <p>{{ $classroom ? 'Class work and completed SAT results for this class.' : 'Scheduled class work and completed SAT results in one place.' }}
                </p>
            </div>
        </div>
        <div class="student-assignment-list">
            @forelse($assignments as $assignment)
                @php($attempts = $assignment->attempts)
                @php($resolved = \App\Support\AssignmentStatusResolver::resolve($assignment, $attempts))
                @php($state = $resolved['state'])
                @php($completed = $resolved['completed'])
                @php($inProgress = $resolved['inProgress'])
                @php($used = $resolved['used'])
                @php($statusVariant = $resolved['variant'])
                <article class="student-assignment">
                    <div class="student-assignment__row">
                        <div>
                            <x-ui.status-badge :status="$statusVariant">{{ $state }}</x-ui.status-badge>
                            <h2>{{ $assignment->title }}</h2>
                            <p>{{ $assignment->classroom->name }} · {{ $assignment->test->title }}{{ $assignment->assign_type === 'section' ? ($assignment->section_type === 'reading_writing' ? ' (Reading & Writing Only)' : ' (Math Only)') : '' }}</p>
                        </div>
                        <dl>
                            <div>
                                <dt>Due</dt>
                                <dd>{{ $assignment->due_at?->format('M j, g:i A') ?: 'No due time' }}</dd>
                            </div>
                            <div>
                                <dt>Attempts</dt>
                                <dd>{{ $attempts->count() }} / {{ $assignment->attempt_limit }}</dd>
                            </div>
                            <div>
                                <dt>Best estimated score</dt>
                                <dd>
                                    @if($assignment->assign_type === 'section')
                                        {{ $assignment->section_type === 'reading_writing' ? ($completed->max('score_reading_writing') ?: '—') : ($completed->max('score_math') ?: '—') }}
                                    @else
                                        {{ $completed->max('total_score') ?: '—' }}
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="assignment-modal-actions">
                        @if ($inProgress || ($assignment->acceptsNewStarts() && $used < $assignment->attempt_limit))
                            <form method="POST" action="{{ route('student.assignments.start', $assignment) }}">
                                @csrf
                                <x-ui.button type="submit" variant="primary" size="sm">
                                    {{ $inProgress ? 'Resume attempt' : 'Start attempt ' . ($used + 1) }}
                                </x-ui.button>
                            </form>
                        @else
                            <x-ui.alert type="warning" :dismissible="false">No new attempt is currently available.</x-ui.alert>
                        @endif
                    </div>

                    <details class="assignment-brief assignment-brief--modal">
                        <summary>Assignment details</summary>

                        <dl>
                            <div>
                                <dt>Available</dt>
                                <dd>{{ $assignment->available_at?->format('M j, Y g:i A') ?: 'Now' }}</dd>
                            </div>
                            <div>
                                <dt>Attempts allowed</dt>
                                <dd>{{ $assignment->attempt_limit }}</dd>
                            </div>
                        </dl>

                        @if ($assignment->instructions)
                            <div class="assignment-brief__section">
                                <h2>Instructions</h2>
                                <p>{{ $assignment->instructions }}</p>
                            </div>
                        @endif

                        @if ($attempts->isNotEmpty())
                            <div class="assignment-brief__section">
                                <h2>Attempt history</h2>
                                <div class="assignment-attempt-list">
                                    @foreach ($attempts as $attempt)
                                        <div class="attempt-row attempt-row--card">
                                            <div class="attempt-row__identity">
                                                <strong>Attempt {{ $attempt->attempt_number }}</strong>
                                                <x-ui.status-badge :status="$attempt->status === 'completed' ? 'success' : 'brand'">{{ ucfirst(str_replace('_', ' ', $attempt->status)) }}</x-ui.status-badge>
                                            </div>
                                            <div class="attempt-row__result">
                                                <strong class="attempt-row__score">
                                                    @if($attempt->attempt_type === 'section')
                                                        {{ $attempt->section_type === 'reading_writing' ? ($attempt->score_reading_writing ?? '—') : ($attempt->score_math ?? '—') }}
                                                    @else
                                                        {{ $attempt->total_score ?? '—' }}
                                                    @endif
                                                </strong>
                                                @if(($attempt->attempt_type === 'section' && ($attempt->score_reading_writing !== null || $attempt->score_math !== null)) || ($attempt->attempt_type !== 'section' && $attempt->total_score !== null))
                                                    <x-scoring.estimate-label compact class="attempt-row__score-label" />
                                                @endif
                                                @if ($attempt->status === 'completed')
                                                    <a class="attempt-row__review" href="{{ route('student.scores.show', $attempt) }}">Review result</a>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </details>
                </article>
            @empty<div class="class-empty">
                    <h2>No assignments</h2>
                    <p>Published work from approved classes will appear here.</p>
                </div>
            @endforelse
        </div>{{ $assignments->withQueryString()->links() }}
    </div>
</x-layouts.student>
