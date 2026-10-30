@php
    $viewer = $authUser ?? auth()->user();
    $isGuest = (bool) ($viewer?->is_guest);
    $isTeacherView = $viewer && $viewer->role !== 'student' && ! $isGuest;
    $userInitials = $viewer->initials ?? 'U';
@endphp

<x-layouts.student :user="$viewer" title="Score Details - {{ $user->name }} — {{ $userTest->test->title }}" header-type="none" ui="product" :body-class="$isTeacherView ? 'antialiased teacher-workspace' : 'antialiased'">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/scores.css', 'resources/js/question-renderer.js'])
    @endpush

    <div @class(['app-shell', 'app-shell--guest' => $isGuest]) x-data="{ searchQuery: '', statusFilter: 'all' }">

        @if (! $isGuest)
            <!-- COLUMN 1: ICON RAIL -->
            <x-shell.icon-rail :logo-href="\App\Support\NavRail::logoHrefForUser($viewer)" :avatar-label="$userInitials"
                :items="\App\Support\NavRail::forUser($viewer, $isTeacherView ? 'reports' : 'scores')" />

            <!-- COLUMN 2: ATTEMPTS LIST -->
            <x-shell.sidebar-list :title="$isTeacherView ? $user->name . ' Scores' : 'My Scores'"
                item-label="attempts"
                count-text="{{ $attempts->count() }} completed"
                search-placeholder="Search attempts..." :filters="[
                    ['value' => 'all', 'label' => 'All'],
                    ['value' => 'full', 'label' => 'Full tests'],
                    ['value' => 'section', 'label' => 'Sections'],
                ]" :items="$attempts
                    ->map(
                        fn($a) => [
                            'route' => route('student.scores.show', $a),
                            'name' => $a->test->title,
                            'status' => $a->attempt_type,
                            'selected' => $a->ulid === $selectedUlid,
                            'meta' => [
                                optional($a->completed_at)->format('M j, Y'),
                                $a->attempt_type === 'section'
                                    ? (($a->section_type === 'reading_writing' ? $a->score_reading_writing : $a->score_math) !== null
                                        ? (($a->section_type === 'reading_writing' ? $a->score_reading_writing : $a->score_math) . ' / 800')
                                        : 'Practice attempt')
                                    : ($a->total_score !== null ? ($a->total_score . ' / 1600') : 'Practice attempt'),
                            ],
                        ],
                    )
                    ->all()">
            </x-shell.sidebar-list>
        @endif

        <!-- COLUMN 3: LEDGER PANE (continuous paper workspace, no corkboard) -->
        <div class="ledger-pane no-corkboard">
            <x-ui.flash />

            <!-- MAIN LEDGER COLUMN -->
            <div class="binder-panel">
                @if ($isGuest)
                    <div class="sd-guest-top-bar">
                        <x-brand.wordmark href="/" size="md" tone="brand" />
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <a href="{{ route('exam-join.link-account', ['destination' => 'signup']) }}"
                               class="sd-guest-btn sd-guest-btn-primary">
                                Sign Up
                            </a>
                            <a href="{{ route('exam-join.link-account', ['destination' => 'signin']) }}"
                               class="sd-guest-btn sd-guest-btn-secondary">
                                Sign In
                            </a>
                        </div>
                    </div>

                    <div class="sd-guest-banner">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink: 0;">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="16" x2="12" y2="12" />
                            <line x1="12" y1="8" x2="12.01" y2="8" />
                        </svg>
                        <span style="font-size: 0.875rem; color: #1e3a8a;">
                            <strong>This result is not saved yet.</strong>
                            <a href="{{ route('exam-join.link-account', ['destination' => 'signin']) }}" style="color: #1d4ed8; font-weight: 600; text-decoration: underline;">Sign in</a> or
                            <a href="{{ route('exam-join.link-account', ['destination' => 'signup']) }}" style="color: #1d4ed8; font-weight: 600; text-decoration: underline;">sign up</a> and it moves to your account, alongside your other scores.
                        </span>
                    </div>
                @endif

                <div class="ledger-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--ds-border-default, #e2e8f0);">
                    <div class="dh-left" style="flex: 1 1 16rem; min-width: 0;">
                        @if ($isTeacherView)
                            <div class="sd-teacher-student-card">
                                <span class="sd-teacher-student-badge">Student</span>
                                <span class="sd-teacher-student-name">{{ $user->name }}</span>
                                <span class="sd-teacher-student-email">({{ $user->email }})</span>
                            </div>
                        @endif

                        <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--ds-text-primary, #0f172a); line-height: 1.25; margin: 0;">{{ $userTest->test->title }}</h1>
                        <div class="dh-desc" style="font-size: 0.85rem; color: var(--ds-text-secondary, #64748b); margin-top: 0.35rem;">
                            <span>{{ $userTest->attempt_type === 'section' ? ($userTest->section_type === 'reading_writing' ? 'Reading & Writing section' : 'Math section') : 'Full-length practice test' }}</span>
                            &middot;
                            <span>Completed {{ optional($userTest->completed_at)->format('F j, Y') ?: 'In progress' }}</span>
                            @if ($userTest->assignment)
                                &middot;
                                <span>via {{ $userTest->assignment->classroom?->name ?: ($userTest->assignment->title ?: 'Assignment') }}</span>
                            @elseif ($userTest->examSession)
                                &middot;
                                <span>via {{ $userTest->examSession->title ?: 'Exam Session' }}</span>
                            @else
                                &middot;
                                <span>Self-Paced Practice</span>
                            @endif
                        </div>
                    </div>

                    <div class="dh-right" style="display: flex; gap: 0.75rem; align-items: center;">
                        @if (! $isGuest)
                            <a href="{{ $progressUrl }}"
                               class="sd-action-btn secondary"
                               title="View long-term progress analytics">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                    <polyline points="17 6 23 6 23 12"></polyline>
                                </svg>
                                View Progress
                            </a>
                        @endif
                    </div>
                </div>

                @include('student.scores.partials.report')
            </div>
        </div>
    </div>

    @include('student.scores.partials.modal')

    <x-slot name="scripts">
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof window.initScoreDetailsPage === 'function') {
                    window.initScoreDetailsPage();
                }
            });
        </script>
    </x-slot>
</x-layouts.student>
