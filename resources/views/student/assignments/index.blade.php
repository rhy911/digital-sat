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
                @php($completed = $attempts->where('status', 'completed'))
                @php($state = $completed->isNotEmpty() ? 'Completed' : ($attempts->firstWhere('status', 'in_progress') ? 'In progress' : ($assignment->available_at && now()->lt($assignment->available_at) ? 'Upcoming' : ($assignment->due_at && now()->gte($assignment->due_at) ? 'Overdue' : 'Open'))))
                <a href="#" x-data x-on:click.prevent="$dispatch('open-modal', 'assignment-modal-{{ $assignment->id }}')" class="student-assignment">
                    <div><span class="status-chip status-chip--{{ strtolower(str_replace(' ', '-', $state)) }}">{{ $state }}</span>
                        <h2>{{ $assignment->title }}</h2>
                        <p>{{ $assignment->classroom->name }} · {{ $assignment->test->title }}</p>
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
                            <dd>{{ $completed->max('total_score') ?: '—' }}</dd>
                        </div>
                    </dl>
                </a>
                @php($inProgress = $attempts->firstWhere('status', 'in_progress'))
                @php($used = $attempts->count())
                <x-ui.modal :id="'assignment-modal-' . $assignment->id" :title="$assignment->title" maxWidth="3xl">
                    <div class="assignment-modal-content">
                        <div class="assignment-modal-summary">
                            <span class="status-chip status-chip--{{ strtolower(str_replace(' ', '-', $state)) }}">{{ $state }}</span>
                            <p>
                                {{ $assignment->classroom->name }} · {{ $assignment->test->title }}
                            </p>
                        </div>

                        <section class="assignment-brief assignment-brief--modal">
                            <dl>
                                <div>
                                    <dt>Available</dt>
                                    <dd>{{ $assignment->available_at?->format('M j, Y g:i A') ?: 'Now' }}</dd>
                                </div>
                                <div>
                                    <dt>Due</dt>
                                    <dd>{{ $assignment->due_at?->format('M j, Y g:i A') ?: 'No due time' }}</dd>
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
                                                    <span class="status-chip status-chip--{{ $attempt->status }}">{{ ucfirst(str_replace('_', ' ', $attempt->status)) }}</span>
                                                </div>
                                                <div class="attempt-row__result">
                                                    <strong class="attempt-row__score">{{ $attempt->total_score ?? '—' }}</strong>
                                                    @if($attempt->total_score !== null)
                                                        <x-scoring.estimate-label compact class="attempt-row__score-label" />
                                                    @endif
                                                    @if ($attempt->status === 'completed')
                                                        <a class="attempt-row__review" href="{{ route('my-practice.score', $attempt) }}">Review result</a>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="assignment-modal-actions">
                                <button type="button" x-on:click="$dispatch('close-modal', 'assignment-modal-{{ $assignment->id }}')" class="class-button class-button--secondary">Cancel</button>
                                @if ($inProgress || ($assignment->acceptsNewStarts() && $used < $assignment->attempt_limit))
                                    <form method="POST" action="{{ route('student.assignments.start', $assignment) }}">
                                        @csrf
                                        <button class="class-button class-button--primary">
                                            {{ $inProgress ? 'Resume attempt' : 'Start attempt ' . ($used + 1) }}
                                        </button>
                                    </form>
                                @else
                                    <div class="class-alert assignment-modal-alert">No new attempt is currently available.</div>
                                @endif
                            </div>
                        </section>
                    </div>
                </x-ui.modal>
            @empty<div class="class-empty">
                    <h2>No assignments</h2>
                    <p>Published work from approved classes will appear here.</p>
                </div>
            @endforelse
        </div>{{ $assignments->withQueryString()->links() }}
    </div>
</x-layouts.student>
