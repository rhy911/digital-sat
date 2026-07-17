<x-layouts.student :user="$user" :title="$assignment->title" header-type="progress">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
    @endpush
    <div class="student-workspace student-workspace--narrow"><a class="back-link" wire:navigate
            href="{{ route('student.assignments.index', ['classroom' => $assignment->classroom_id]) }}">Back to
            {{ $assignment->classroom->name }} assignments</a>
        <div class="page-heading">
            <div><span
                    class="status-chip status-chip--{{ $assignment->status }}">{{ ucfirst($assignment->status) }}</span>
                <h1>{{ $assignment->title }}</h1>
                <p>{{ $assignment->classroom->name }} · {{ $assignment->test->title }}{{ $assignment->assign_type === 'section' ? ($assignment->section_type === 'reading_writing' ? ' (Reading & Writing Only)' : ' (Math Only)') : '' }}</p>
            </div>
        </div>
        @if ($errors->any())
            <div class="class-alert class-alert--error">{{ $errors->first() }}</div>
        @endif
        <section class="class-panel assignment-brief">
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
                <div>
                    <h2>Instructions</h2>
                    <p>{{ $assignment->instructions }}</p>
                </div>
            @endif
            <div>
                @php($inProgress = $assignment->attempts->firstWhere('status', 'in_progress'))@php($used = $assignment->attempts->count())@if ($inProgress || ($assignment->acceptsNewStarts() && $used < $assignment->attempt_limit))
                    <form method="POST" action="{{ route('student.assignments.start', $assignment) }}">@csrf<button
                            class="class-button class-button--primary">{{ $inProgress ? 'Resume attempt' : 'Start attempt ' . ($used + 1) }}</button>
                </form>@else<div class="class-alert">No new attempt is currently available.</div>
                @endif
            </div>
        </section>
        @if ($assignment->attempts->isNotEmpty())
            <section class="class-panel">
                <h2>Attempt history</h2>
                @foreach ($assignment->attempts as $attempt)
                    <div class="attempt-row">
                        <div><strong>Attempt
                                {{ $attempt->attempt_number }}</strong><span>{{ ucfirst(str_replace('_', ' ', $attempt->status)) }}</span>
                        </div>
                        <div><strong>
                            @if($attempt->attempt_type === 'section')
                                {{ $attempt->section_type === 'reading_writing' ? ($attempt->score_reading_writing ?? '—') : ($attempt->score_math ?? '—') }}
                            @else
                                {{ $attempt->total_score ?? '—' }}
                            @endif
                            </strong>
                            @if(($attempt->attempt_type === 'section' && ($attempt->score_reading_writing !== null || $attempt->score_math !== null)) || ($attempt->attempt_type !== 'section' && $attempt->total_score !== null))
                                <x-scoring.estimate-label compact />
                            @endif
                            @if ($attempt->status === 'completed')
                                <a href="{{ route('student.scores.show', $attempt) }}">Review result</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </section>
        @endif
    </div>
</x-layouts.student>
