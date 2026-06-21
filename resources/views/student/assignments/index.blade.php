<x-layouts.student :user="$user" title="Assignments" header-type="progress">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
    @endpush
    <div class="student-workspace">
        @if($classroom)<a class="back-link" wire:navigate href="{{ route('student.classes.index') }}">Back to my classes</a>@endif
        <div class="page-heading"><div><h1>{{ $classroom ? $classroom->name.' assignments' : 'Assignments' }}</h1><p>{{ $classroom ? 'Class work and completed SAT results for this class.' : 'Scheduled class work and completed SAT results in one place.' }}</p></div></div>
        <div class="student-assignment-list">@forelse($assignments as $assignment)
            @php($attempts = $assignment->attempts)
            @php($completed = $attempts->where('status', 'completed'))
            @php($state = $completed->isNotEmpty() ? 'Completed' : ($attempts->firstWhere('status', 'in_progress') ? 'In progress' : ($assignment->available_at && now()->lt($assignment->available_at) ? 'Upcoming' : ($assignment->due_at && now()->gte($assignment->due_at) ? 'Overdue' : 'Open'))))
            <a wire:navigate href="{{ route('student.assignments.show', $assignment) }}" class="student-assignment"><div><span class="status-chip">{{ $state }}</span><h2>{{ $assignment->title }}</h2><p>{{ $assignment->classroom->name }} · {{ $assignment->test->title }}</p></div><dl><div><dt>Due</dt><dd>{{ $assignment->due_at?->format('M j, g:i A') ?: 'No due time' }}</dd></div><div><dt>Attempts</dt><dd>{{ $attempts->count() }} / {{ $assignment->attempt_limit }}</dd></div><div><dt>Best score</dt><dd>{{ $completed->max('total_score') ?: '—' }}</dd></div></dl></a>
        @empty<div class="class-empty"><h2>No assignments</h2><p>Published work from approved classes will appear here.</p></div>@endforelse</div>{{ $assignments->withQueryString()->links() }}
    </div>
</x-layouts.student>
