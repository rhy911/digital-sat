@php
    $user = auth()->user();
    // Counted off the already-loaded collection rather than re-queried: the
    // controller eager-loads candidate_count, so these cost nothing.
    $activeCount = $sessions->where('status', 'active')->count();
    $candidateTotal = $sessions->sum('candidate_count');
@endphp

<x-layouts.student :user="$user" title="Exam Sessions" header-type="none">
    @push('styles')
        {{-- Same trio /home loads: classroom-workspace.css owns the app shell
             (.app-shell/.shell-content), analytics.css the shared `.home*`
             dashboard vocabulary, and exam-sessions.css only what /home lacks. --}}
        @vite([
            'resources/css/classroom-workspace.css',
            'resources/css/student/analytics.css',
            'resources/css/teacher/exam-sessions.css',
        ])
    @endpush

    <div class="app-shell app-shell--no-list">
        <x-shell.icon-rail :logo-href="route('teacher.progress')" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::teacher('exam-sessions')" />

        <main class="shell-content">
            <x-ui.flash />

            <div class="home">
                <header class="home-head">
                    <div class="home-head__lead">
                        <div class="home-head__meta">
                            <span class="home-date">{{ now()->format('l, M j') }}</span>
                            <x-ui.status-badge status="brand">
                                <x-slot:icon><x-ui.icon name="mortarboard" class="w-3.5 h-3.5" /></x-slot:icon>
                                Teacher
                            </x-ui.status-badge>
                        </div>
                        <h1 class="home-title">Exam sessions</h1>
                        <p class="home-subtitle">
                            Host a live test with a share code — no classroom, no rosters. Candidates join by
                            link and you watch their progress as it happens.
                        </p>
                    </div>
                </header>

                <x-ui.card :padded="false" class="home-stats" role="group" aria-label="Exam sessions at a glance">
                    <div class="home-stats__grid">
                        <div class="home-stat">
                            <span class="home-stat__label">Total sessions</span>
                            <span class="home-stat__value home-num">{{ $sessions->count() }}</span>
                        </div>

                        <div class="home-stat">
                            <span class="home-stat__label">Open now</span>
                            <span class="home-stat__value home-num">{{ $activeCount }}</span>
                            <span class="home-stat__note">
                                {{ $activeCount > 0 ? 'Accepting candidates' : 'None accepting candidates' }}
                            </span>
                        </div>

                        <div class="home-stat">
                            <span class="home-stat__label">Candidates</span>
                            <span class="home-stat__value home-num">{{ $candidateTotal }}</span>
                            <span class="home-stat__note">Across all sessions</span>
                        </div>
                    </div>
                </x-ui.card>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-7">
                        <x-ui.card stretch aria-labelledby="sessions-title">
                            <x-slot:header>
                                <div class="home-panel__headline">
                                    <h2 id="sessions-title" class="home-panel__title">Your sessions</h2>
                                    <p class="home-panel__sub">
                                        {{ $sessions->count() }} {{ Str::plural('session', $sessions->count()) }}
                                    </p>
                                </div>
                            </x-slot:header>

                            @if ($sessions->isEmpty())
                                <div class="home-empty">
                                    <x-ui.icon name="inbox" class="home-empty__icon w-6 h-6" />
                                    <p class="home-empty__title">No sessions yet</p>
                                    <p class="home-empty__body">
                                        Create one to get a six-character code you can read out to a room, or
                                        share as a link.
                                    </p>
                                </div>
                            @else
                                <ul class="home-list">
                                    @foreach ($sessions as $session)
                                        <li>
                                            <a href="{{ route('teacher.exam-sessions.show', $session) }}" class="home-row">
                                                <span class="home-row__main">
                                                    <span class="home-row__title">{{ $session->title }}</span>
                                                    <span class="home-row__meta">
                                                        {{ $session->test->title }}
                                                        <span aria-hidden="true">&middot;</span>
                                                        <span class="home-num">{{ $session->candidate_count }}</span>
                                                        {{ Str::plural('candidate', $session->candidate_count) }}
                                                        @if ($session->expires_at)
                                                            <span aria-hidden="true">&middot;</span>
                                                            {{ $session->isExpired() ? 'Expired' : 'Expires' }}
                                                            {{ $session->expires_at->diffForHumans() }}
                                                        @endif
                                                    </span>
                                                </span>
                                                <span class="home-row__aside">
                                                    <span class="home-code home-num">{{ $session->code }}</span>
                                                    <x-ui.status-badge
                                                        :status="match ($session->status) {
                                                            'active' => 'success',
                                                            'paused' => 'warning',
                                                            default => 'neutral',
                                                        }">
                                                        {{ ucfirst($session->status) }}
                                                    </x-ui.status-badge>
                                                    <x-ui.icon name="chevron-right" class="home-row__chevron w-4 h-4" />
                                                </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </x-ui.card>
                    </div>

                    <div class="lg:col-span-5">
                        <x-ui.card stretch aria-labelledby="create-session-title">
                            <x-slot:header>
                                <div class="home-panel__headline">
                                    <h2 id="create-session-title" class="home-panel__title">Start a new session</h2>
                                    <p class="home-panel__sub">Opens immediately once created</p>
                                </div>
                            </x-slot:header>

                            @if ($tests->isEmpty())
                                <div class="home-empty">
                                    <x-ui.icon name="journal-text" class="home-empty__icon w-6 h-6" />
                                    <p class="home-empty__title">No tests available</p>
                                    <p class="home-empty__body">
                                        You need a published test you own or one shared with you before you can
                                        host a session.
                                    </p>
                                    <x-ui.button size="sm" variant="secondary" :href="route('home-dashboard.index')">
                                        Open the test builder
                                    </x-ui.button>
                                </div>
                            @else
                                <form method="POST" action="{{ route('teacher.exam-sessions.store') }}" class="exam-form">
                                    @csrf

                                    <div class="exam-field">
                                        <label class="exam-field__label" for="test_id">Test</label>
                                        <select id="test_id" name="test_id" required class="exam-input">
                                            <option value="">Select a test…</option>
                                            @foreach ($tests as $test)
                                                <option value="{{ $test->id }}" @selected(old('test_id') == $test->id)>
                                                    {{ $test->title }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="exam-field">
                                        <label class="exam-field__label" for="title">Session title</label>
                                        <input id="title" name="title" required maxlength="180" class="exam-input"
                                            value="{{ old('title') }}"
                                            placeholder="Mock Exam — {{ now()->format('M j') }}">
                                    </div>

                                    <div class="exam-field">
                                        <label class="exam-field__label" for="expires_at">Expires at</label>
                                        <input id="expires_at" type="datetime-local" name="expires_at" class="exam-input"
                                            value="{{ old('expires_at') }}">
                                        <span class="exam-field__hint">
                                            Optional. After this time nobody new can join.
                                        </span>
                                    </div>

                                    <x-ui.button type="submit" size="sm" class="self-start">
                                        <x-slot:icon><x-ui.icon name="plus-lg" class="w-4 h-4" /></x-slot:icon>
                                        Create session
                                    </x-ui.button>
                                </form>
                            @endif
                        </x-ui.card>
                    </div>
                </div>
            </div>
        </main>
    </div>
</x-layouts.student>
