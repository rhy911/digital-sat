<x-layouts.student :user="$user" title="My classes" header-type="progress">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
    @endpush
    <div class="student-workspace">
        <div class="page-heading">
            <div>
                <h1>My classes</h1>
                <p>Join a teacher's class with the eight-character code they shared.</p>
            </div>
        </div>
        @if (session('success'))
            <div class="class-alert class-alert--success" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div id="join-code-error" class="class-alert class-alert--error" role="alert">{{ $errors->first() }}</div>
        @endif
        <section class="class-panel join-panel" aria-labelledby="join-class-label">
            <form method="POST" action="{{ route('student.classes.join') }}">
                @csrf
                <label id="join-class-label">Class code
                    <input name="join_code" value="{{ request('code') }}" minlength="8" maxlength="8" required
                        autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="AB12CD34"
                        @if ($errors->any()) aria-invalid="true" aria-describedby="join-code-error" @endif>
                </label>
                <button type="submit" class="class-button class-button--primary">Request to join</button>
            </form>
        </section>
        <div class="class-list">
            @forelse($memberships as $membership)
                <div class="class-row class-row--static">
                    <div><span
                            class="status-chip status-chip--{{ $membership->status }}">{{ ucfirst($membership->status) }}</span>
                        <h3>{{ $membership->classroom->name }}</h3>
                        <p>Teacher: {{ $membership->classroom->owner->name }}</p>
                    </div>
                    <div class="row-actions">
                        @if ($membership->status === 'active')
                            <a class="class-button" wire:navigate
                                href="{{ route('student.assignments.index', ['classroom' => $membership->classroom_id]) }}">View
                                assignments</a>
                            <form method="POST" action="{{ route('student.classes.leave', $membership) }}"
                                data-confirm="Leave this class? Your result history will remain.">@csrf<button
                                    type="submit" class="text-button text-button--danger">Leave</button></form>
                        @endif
                    </div>
            </div>@empty<div class="class-empty">
                    <h2>No classes yet</h2>
                    <p>Enter a teacher's join code above. Your request will appear here while awaiting approval.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.student>
