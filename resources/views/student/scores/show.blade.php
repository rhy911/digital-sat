@php
    $viewer = $authUser ?? auth()->user();
    $isTeacherView = $viewer && $viewer->role !== 'student';
    $userInitials = $viewer->initials ?? 'U';
@endphp

<x-layouts.student :user="$viewer" title="Score Details - {{ $user->name }} — {{ $userTest->test->title }}" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/scores.css', 'resources/js/student/scores-katex.js'])
    @endpush

    <div class="app-shell" x-data="{ searchQuery: '', statusFilter: 'all' }">

        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="\App\Support\NavRail::logoHrefForUser($viewer)" :avatar-label="$userInitials"
            :items="\App\Support\NavRail::forUser($viewer, $isTeacherView ? 'reports' : 'scores')" />

        <!-- COLUMN 2: ATTEMPTS LIST -->
        <x-shell.sidebar-list :title="$isTeacherView ? $user->name . ' Scores' : 'My Scores'" count-text="{{ $attempts->count() }} completed"
            search-placeholder="Search tests..." :filters="[
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
                                ? ($a->section_type === 'reading_writing' ? $a->score_reading_writing : $a->score_math) ?? '—'
                                : $a->total_score ?? '—',
                        ],
                    ],
                )
                ->all()">
        </x-shell.sidebar-list>

        <!-- COLUMN 3: LEDGER PANE (report workspace + corkboard) -->
        <div class="ledger-pane">
            <x-ui.flash />

            <!-- MAIN LEDGER COLUMN -->
            <div class="binder-panel">
                <div class="ledger-header">
                    <div class="dh-left">
                        <h2>{{ $userTest->test->title }}</h2>
                        <div class="dh-desc">
                            Student: <strong>{{ $user->name }}</strong> ({{ $user->email }}) &middot;
                            {{ $userTest->completed_at ? $userTest->completed_at->format('F j, Y') : 'In progress' }}
                            @if ($userTest->assignment)
                                &middot; via {{ $userTest->assignment->classroom->name }}
                            @endif
                        </div>
                    </div>
                </div>

                <x-shell.stat-row :stats="[
                    ['value' => $attempts->count(), 'label' => 'Attempts'],
                    ['value' => $attempts->max('total_score') ?? '—', 'label' => 'Best score'],
                    ['value' => $userTest->total_score ?? ($correct . '/' . $totalQ), 'label' => 'This attempt'],
                    ['value' => $accuracyPercent . '%', 'label' => 'Accuracy'],
                ]" />

                @include('student.scores.partials.report')
            </div>

            <!-- CORKBOARD -->
            <x-shell.corkboard header="Digest">
                <div class="cork-note">
                    <h3>Latest score</h3>
                    @php($latest = $attempts->first())
                    @if ($latest)
                        <p class="digest-line"><strong>{{ $latest->test->title }}</strong></p>
                        <p class="cork-empty mt-2">{{ $latest->total_score ?? '—' }} ·
                            {{ optional($latest->completed_at)->format('M j, Y') }}</p>
                    @else
                        <p class="cork-empty">No completed tests yet.</p>
                    @endif
                </div>

                <div class="cork-note">
                    <h3>Weakest domain</h3>
                    @php($weakest = collect($domainSummaries)->sortBy('percentCorrect')->first())
                    @if ($weakest)
                        <p class="digest-line"><strong>{{ $weakest['domain'] }}</strong></p>
                        <p class="cork-empty mt-2">{{ $weakest['percentCorrect'] }}% correct ·
                            {{ $weakest['section'] }}</p>
                    @else
                        <p class="cork-empty">No scored domains for this attempt.</p>
                    @endif
                </div>
            </x-shell.corkboard>
        </div>
    </div>

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
