<x-layouts.student :user="$user" title="Score Details - {{ $userTest->test->title }}" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/scores.css', 'resources/js/student/scores-katex.js'])
    @endpush

    @php
        $userInitials = $user->initials ?? 'U';
    @endphp

    <div class="app-shell" x-data="{ searchQuery: '', statusFilter: 'all' }">

        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="route('home')" :avatar-label="$userInitials" :items="[
            [
                'route' => route('home'),
                'label' => __('classroom.nav_dashboard'),
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\'/><polyline points=\'9 22 9 12 15 12 15 22\'/></svg>',
            ],
            [
                'route' => route('student.classes.index'),
                'label' => __('classroom.nav_my_classes'),
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><rect x=\'3.5\' y=\'5\' width=\'17\' height=\'14\' rx=\'2\' /><path d=\'M3.5 9.5h17M8 5v-1M16 5v-1\' stroke-linecap=\'round\' /></svg>',
            ],
            [
                'route' => route('home.practice'),
                'label' => __('classroom.nav_practice'),
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M4 19.5A2.5 2.5 0 0 1 6.5 17H20V4H6.5A2.5 2.5 0 0 0 4 6.5v13Z\' stroke-linejoin=\'round\' /><path d=\'M4 17h16\' /></svg>',
            ],
            [
                'route' => route('student.scores.index'),
                'label' => __('classroom.nav_progress_scores'),
                'active' => true,
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><line x1=\'18\' y1=\'20\' x2=\'18\' y2=\'10\'/><line x1=\'12\' y1=\'20\' x2=\'12\' y2=\'4\'/><line x1=\'6\' y1=\'20\' x2=\'6\' y2=\'14\'/></svg>',
            ],
        ]" />

        <!-- COLUMN 2: ATTEMPTS LIST -->
        <x-shell.sidebar-list title="My Scores" count-text="{{ $attempts->count() }} completed"
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
                            {{ $userTest->completed_at ? $userTest->completed_at->format('F j, Y') : 'In progress' }}
                            @if ($userTest->assignment)
                                · via {{ $userTest->assignment->classroom->name }}
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

                <div class="cork-note">
                    <h3>Combine section scores</h3>
                    <p class="cork-empty">Merge a separate Reading & Writing and Math attempt into one SAT-style
                        score.</p>
                    <a class="btn-sm-primary btn-pin" style="text-decoration:none;display:block;text-align:center;"
                        href="{{ route('student.scores.merge') }}">Go to score merge</a>
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
