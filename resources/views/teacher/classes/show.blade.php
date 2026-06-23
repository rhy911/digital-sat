<x-layouts.student :user="auth()->user()" :title="$classroom->name" header-type="progress">
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
        <a class="back-link" href="{{ route('teacher.classes.index') }}">Back to classes</a>
        <div class="page-heading">
            <div><span class="status-chip status-chip--{{ $classroom->status }}">{{ ucfirst($classroom->status) }}</span>
                <h1>{{ $classroom->name }}</h1>
                <p>{{ $classroom->description ?: 'Manage roster and assigned work.' }}</p>
            </div>
            <div class="join-code"><span>Student join code</span><strong>{{ $classroom->join_code }}</strong>
                @if ($classroom->status === 'active')
                    <label>Join link<input readonly
                            value="{{ route('student.classes.join-link', $classroom->join_code) }}"
                            onclick="this.select()"></label>
                    <form method="POST" action="{{ route('teacher.classes.rotate-code', $classroom) }}">@csrf<button
                            class="text-button">Rotate code</button></form>
                @endif
            </div>
        </div>
        @if ($classroom->status === 'active')
            <details class="create-disclosure">
                <summary>Edit class details</summary>
                <form method="POST" action="{{ route('teacher.classes.update', $classroom) }}" class="inline-form">
                    @csrf @method('PUT')<label>Class name<input name="name" value="{{ $classroom->name }}"
                            required maxlength="150"></label><label>Description<input name="description"
                            value="{{ $classroom->description }}" maxlength="2000"></label><button
                        class="class-button class-button--primary">Save</button></form>
            </details>
        @endif
        <div class="class-tabs" role="navigation" aria-label="Class sections"><a href="#roster">Roster</a><a
                href="#assignments">Assignments</a></div>
        <section id="roster" class="class-panel">
            <div class="section-heading">
                <div>
                    <h2>Roster</h2>
                    <p>{{ $classroom->active_memberships_count }} active students</p>
                </div>
            </div>
            @php($pending = $classroom->memberships->where('status', 'pending'))
            @if ($pending->isNotEmpty())
                <div class="pending-block">
                    <h3>Pending requests</h3>
                    @foreach ($pending as $membership)
                        <div class="roster-row">
                            <div>
                                <strong>{{ $membership->student->name }}</strong><span>{{ $membership->student->email }}</span>
                            </div>
                            @if ($classroom->status === 'active')
                                <div class="row-actions">
                                    <form method="POST"
                                        action="{{ route('teacher.memberships.approve', $membership) }}">@csrf<button
                                            class="class-button class-button--primary">Approve</button></form>
                                    <form method="POST"
                                        action="{{ route('teacher.memberships.reject', $membership) }}">@csrf<button
                                            class="class-button">Reject</button></form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
            @forelse($classroom->memberships->where('status', 'active') as $membership)
                <div class="roster-row">
                    <div>
                        <strong>{{ $membership->student->name }}</strong><span>{{ $membership->student->email }}</span>
                    </div>
                    @if ($classroom->status === 'active')
                        <form method="POST" action="{{ route('teacher.memberships.remove', $membership) }}"
                            data-confirm="Remove this student? Result history will remain.">@csrf<button
                                class="text-button text-button--danger">Remove</button></form>
                    @endif
                </div>
            @empty <div class="class-empty class-empty--compact">
                    <p>No active students. Share code <strong>{{ $classroom->join_code }}</strong>.</p>
                </div>
            @endforelse
        </section>
        <section id="assignments" class="class-panel">
            <div class="section-heading">
                <div>
                    <h2>Assignments</h2>
                    <p>Publish an owned active test to everyone in this class.</p>
                </div>
            </div>
            @if ($classroom->status === 'active')
                <details class="create-disclosure">
                    <summary>Create assignment</summary>
                    <form method="POST" action="{{ route('teacher.assignments.store', $classroom) }}"
                        class="form-grid">@csrf
                        <label>Test<select name="test_id" required>
                                <option value="">Select an owned test</option>
                                @foreach ($tests as $test)
                                    <option value="{{ $test->id }}">
                                        {{ $test->title }}{{ $test->isContentLocked() ? ' (locked)' : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Assignment title<input name="title" required maxlength="180"></label>
                        <label class="span-2">Instructions
                            <textarea name="instructions" rows="3"></textarea>
                        </label>
                        <label>Available from (Asia/Ho_Chi_Minh)<input type="datetime-local"
                                name="available_at"></label><label>Due at (Asia/Ho_Chi_Minh)<input type="datetime-local"
                                name="due_at"></label>
                        <label>Attempt limit<input type="number" name="attempt_limit" min="1" max="10"
                                value="1" required></label>
                        <div class="form-action"><button class="class-button class-button--primary">Save draft</button>
                        </div>
                    </form>
                </details>
            @endif
            @forelse($classroom->assignments->sortByDesc('created_at') as $assignment)
                <a class="assignment-row" wire:navigate href="{{ route('teacher.assignments.show', $assignment) }}">
                    <div><span
                            class="status-chip status-chip--{{ $assignment->status }}">{{ ucfirst($assignment->status) }}</span><strong>{{ $assignment->title }}</strong><span>{{ $assignment->test->title }}</span>
                    </div>
                    <div><span>{{ $assignment->attempt_limit }}
                            attempt{{ $assignment->attempt_limit === 1 ? '' : 's' }}</span><span>{{ $assignment->due_at?->format('M j, g:i A') ?: 'No due time' }}</span>
                    </div>
                </a>
            @empty <div class="class-empty class-empty--compact">
                    <p>No assignments. Create a draft when your test is ready.</p>
                </div>
            @endforelse
        </section>
        <div class="danger-zone">
            @if ($classroom->status === 'active')
                <form method="POST" action="{{ route('teacher.classes.archive', $classroom) }}"
                    data-confirm="Archive class and close published assignments?">@csrf<button
                    class="class-button class-button--danger">Archive class</button></form>@else<form method="POST"
                    action="{{ route('teacher.classes.restore', $classroom) }}">@csrf<button
                        class="class-button">Restore class</button></form>
            @endif
        </div>
    </div>
</x-layouts.student>
