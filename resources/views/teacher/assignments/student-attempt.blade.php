<x-layouts.student :user="auth()->user()" :title="$student->name . ' — ' . $assignment->title" header-type="progress">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
    @endpush
    <div class="ds-teacher-workspace teacher-detail">
        <a class="back-link" href="{{ route('teacher.assignments.show', $assignment) }}">Back to {{ $assignment->title }}</a>

        <div class="page-heading">
            <div>
                <h1>{{ $student->name }}</h1>
                <p>{{ $student->email }} · {{ $assignment->title }}</p>
            </div>
            <div class="row-actions">
                <x-ui.button
                    href="{{ $prevStudentId ? route('teacher.assignments.students.show', [$assignment, $prevStudentId]) : null }}"
                    variant="secondary" size="sm" :disabled="! $prevStudentId">
                    &larr; Previous student
                </x-ui.button>
                <x-ui.button
                    href="{{ $nextStudentId ? route('teacher.assignments.students.show', [$assignment, $nextStudentId]) : null }}"
                    variant="secondary" size="sm" :disabled="! $nextStudentId">
                    Next student &rarr;
                </x-ui.button>
            </div>
        </div>

        <section class="class-panel">
            @include('teacher.assignments.partials.attempt-monitor', compact('assignment', 'row', 'initialAttempt', 'attemptModalId'))
        </section>
    </div>
</x-layouts.student>
