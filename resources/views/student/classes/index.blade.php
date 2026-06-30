<x-layouts.student :user="$user" title="My classes" header-type="progress">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
    @endpush
    <div class="student-workspace student-classes-workspace">
        <div class="page-heading">
            <div>
                <h1>My classes</h1>
                <p>Join a teacher's class with the eight-character code they shared.</p>
            </div>
            <form class="join-inline" method="POST" action="{{ route('student.classes.join') }}"
                onsubmit="const btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerText = 'Requesting...';">
                @csrf
                <label id="join-class-label">Class code
                    <input name="join_code" value="{{ request('code') }}" minlength="8" maxlength="8" required
                        autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="AB12CD34"
                        @if ($errors->any()) aria-invalid="true" aria-describedby="join-code-error" @endif>
                </label>
                <button type="submit" class="class-button class-button--primary">Request to join</button>
            </form>
        </div>
        @if (session('success'))
            <div class="class-alert class-alert--success" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div id="join-code-error" class="class-alert class-alert--error" role="alert">{{ $errors->first() }}</div>
        @endif
        <div class="class-list">
            @forelse($memberships as $membership)
                @if ($membership->status === 'active')
                    <a class="class-row student-class-card" wire:navigate
                        href="{{ route('student.classes.show', $membership->classroom) }}"
                        aria-label="Open {{ $membership->classroom->name }} class">
                @else
                    <div class="class-row class-row--static student-class-card">
                @endif
                    <div>
                        <span class="status-chip status-chip--{{ $membership->status }}">{{ ucfirst($membership->status) }}</span>
                        <h3>{{ $membership->classroom->name }}</h3>
                        <p>Teacher: {{ $membership->classroom->owner->name }}@if ($membership->classroom->coTeachers->isNotEmpty())
                                &middot; {{ $membership->classroom->coTeachers->count() }} co-teacher{{ $membership->classroom->coTeachers->count() === 1 ? '' : 's' }}
                            @endif
                        </p>
                    </div>
                @if ($membership->status === 'active')
                    </a>
                @else
                    </div>
                @endif
            @empty
                <div class="document-empty-state">
                    <div class="document-empty-state__graphic">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                            <path d="M6 6h10"/>
                            <path d="M6 10h10"/>
                        </svg>
                    </div>
                    <h3>No classes yet</h3>
                    <p>Enter a teacher's join code above to request to join a class. Your requests and active classes will appear here.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.student>
