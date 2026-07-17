<x-layouts.student :user="$user" :title="$classroom->name" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css'])
    @endpush

    @php
        $userInitials = auth()->user()?->initials ?? 'U';
        $activeMembers = $classroom->memberships->where('status', 'active');
        $lastDocument = $classroom->documents->sortByDesc('created_at')->first();
    @endphp

    <div class="app-shell" x-data="{
        searchQuery: '',
        statusFilter: 'active',
        activeTab: new URLSearchParams(location.search).get('docs_page') ? 'docs'
            : new URLSearchParams(location.search).get('mates_page') ? 'classmates'
            : 'assign'
    }">

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
                'active' => true,
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
                'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><line x1=\'18\' y1=\'20\' x2=\'18\' y2=\'10\'/><line x1=\'12\' y1=\'20\' x2=\'12\' y2=\'4\'/><line x1=\'6\' y1=\'20\' x2=\'6\' y2=\'14\'/></svg>',
            ],
        ]" />

        <!-- COLUMN 2: CLASS LIST COLUMN -->
        <x-shell.sidebar-list title="{{ __('classroom.my_classes') }}"
            count-text="{{ $activeClassroomsCount }} active · {{ $archivedClassroomsCount }} archived"
            search-placeholder="{{ __('classroom.search_placeholder') }}" :items="$classrooms
                ->map(
                    fn($c) => [
                        'route' => route('student.classes.show', $c),
                        'name' => $c->name,
                        'status' => $c->status,
                        'selected' => $classroom->id === $c->id,
                        'meta' => [
                            __('classroom.students_count', ['count' => $c->active_memberships_count]),
                            __('classroom.assignments_count', ['count' => $c->assignments_count]),
                        ],
                    ],
                )
                ->all()">
            <x-slot:primaryAction>
                <button class="new-class-btn" @click="$dispatch('open-modal', 'modal-join-class')">{{ __('classroom.join_class_cta') }}</button>
            </x-slot:primaryAction>
        </x-shell.sidebar-list>

        <!-- COLUMN 3: LEDGER PANE (main workspace + corkboard) -->
        <div class="ledger-pane">
            <x-ui.flash />

            <!-- MAIN LEDGER COLUMN -->
            <div class="binder-panel">
                <div class="ledger-header">
                    <div class="dh-left">
                        <h2>{{ $classroom->name }} <span
                                class="handwriting status-quote">"{{ ucfirst($classroom->status) }}"</span>
                        </h2>
                        <div class="dh-desc">
                            {{ $classroom->description ?: 'Study resources and assigned work from your teaching team.' }}
                        </div>
                    </div>
                </div>

                <x-shell.stat-row :stats="[
                    ['value' => $completedCount . '/' . $classroom->assignments->count(), 'label' => __('classroom.stat_completed')],
                    ['value' => $latestScore ?? '—', 'label' => __('classroom.stat_latest_score')],
                    ['value' => $classroom->documents_count, 'label' => __('classroom.stat_documents')],
                    ['value' => $activeMembers->count(), 'label' => __('classroom.stat_classmates')],
                ]" />

                <!-- PINNED TAB BAR -->
                <x-shell.tab-bar :tabs="[
                    ['key' => 'assign', 'label' => __('classroom.tab_assignments'), 'count' => $classroom->assignments->count()],
                    ['key' => 'docs', 'label' => __('classroom.tab_documents'), 'count' => $classroom->documents_count],
                    ['key' => 'team', 'label' => __('classroom.tab_team'), 'count' => 1 + $classroom->coTeachers->count()],
                    ['key' => 'classmates', 'label' => __('classroom.tab_classmates'), 'count' => $activeMembers->count()],
                    ['key' => 'settings', 'label' => __('classroom.tab_settings')],
                ]" />

                <!-- ASSIGNMENTS TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'assign' }">
                    <div class="sp-head">
                        <div>
                            <p>{{ __('classroom.student_assignments_desc') }}</p>
                        </div>
                    </div>

                    <div class="assignments-grid">
                        @forelse ($assignmentsPage as $assignment)
                            @php $status = $assignmentStatuses[$assignment->id]; @endphp
                            <div class="index-card">
                                <div class="card-due">
                                    {{ $assignment->due_at ? $assignment->due_at->format('D, M j') : 'No deadline' }}
                                </div>
                                <div class="card-title">{{ $assignment->title }}</div>
                                <div class="card-sub">
                                    {{ $assignment->test->title }}{{ $assignment->sectionSuffix() }}
                                </div>
                                <div class="card-meta mt-10">
                                    <x-ui.status-badge :status="$status['variant']">{{ $status['state'] }}</x-ui.status-badge>
                                    @if ($status['state'] === 'Completed')
                                        <span class="mono score-value">
                                            {{ $status['completed']->max('total_score') ?: '—' }}
                                        </span>
                                    @endif
                                </div>
                                <div class="card-meta card-meta-divider">
                                    @if ($status['canStart'])
                                        <form method="POST" action="{{ route('student.assignments.start', $assignment) }}">
                                            @csrf
                                            <button type="submit" class="btn-sm-primary btn-compact flex-none w-auto">
                                                {{ $status['inProgress'] ? 'Resume' : 'Start attempt' }}
                                            </button>
                                        </form>
                                    @elseif ($status['state'] === 'Completed')
                                        <a href="{{ route('student.assignments.show', $assignment) }}" class="link-action">View results</a>
                                    @else
                                        <span class="text-xs text-muted">No attempts left</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="empty-grid-cell">
                                {{ __('classroom.no_assignments_yet') }}
                            </div>
                        @endforelse
                    </div>
                    @if ($assignmentsPage->hasPages())
                        <div class="mt-12">{{ $assignmentsPage->links() }}</div>
                    @endif
                </div>

                <!-- DOCUMENTS TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'docs' }">
                    <div class="sp-head">
                        <div>
                            <p>{{ __('classroom.student_docs_desc') }}</p>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Type</th>
                                <th class="num">Shared</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($documentsPage as $document)
                                @php
                                    $ext = $document->isFile() ? strtolower(pathinfo($document->original_name ?? '', PATHINFO_EXTENSION)) : '';
                                @endphp
                                <tr>
                                    <td class="name-cell">
                                        <div class="n">{{ $document->title }}</div>
                                        <div class="m">
                                            @if ($document->isFile())
                                                {{ Str::limit($document->original_name, 28) }} ·
                                                {{ $document->displaySize() }}
                                            @else
                                                {{ parse_url($document->external_url ?? '', PHP_URL_HOST) }}
                                            @endif
                                        </div>
                                    </td>
                                    <td><span
                                            class="type-tag">{{ $document->isFile() ? strtoupper($ext ?: 'file') : 'LINK' }}</span>
                                    </td>
                                    <td class="num">{{ $document->created_at->format('d/m/Y') }}</td>
                                    <td class="text-right">
                                        @if ($document->isFile())
                                            <a class="link-action" href="{{ route('class-documents.open', $document) }}"
                                                target="_blank" rel="noopener">Open</a>
                                            <span class="dot-sep">·</span>
                                            <a class="link-action"
                                                href="{{ route('class-documents.download', $document) }}">Download</a>
                                        @else
                                            <a class="link-action" href="{{ $document->external_url }}" target="_blank"
                                                rel="noopener noreferrer">Open link</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="empty-row">
                                        {{ __('classroom.no_documents_shared_yet') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if ($documentsPage->hasPages())
                        <div class="mt-12">{{ $documentsPage->links() }}</div>
                    @endif
                </div>

                <!-- TEAM TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'team' }">
                    <div class="sp-head">
                        <div>
                            <p>{{ __('classroom.student_team_desc') }}</p>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="name-cell">
                                    <div class="n">{{ $classroom->owner->name }}</div>
                                </td>
                                <td><span class="role-tag">Owner</span></td>
                                <td class="mono">{{ $classroom->owner->email }}</td>
                            </tr>
                            @foreach ($classroom->coTeachers as $teacher)
                                <tr>
                                    <td class="name-cell">
                                        <div class="n">{{ $teacher->name }}</div>
                                    </td>
                                    <td><span class="role-tag">Co-teacher</span></td>
                                    <td class="mono">{{ $teacher->email }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- CLASSMATES TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'classmates' }">
                    <div class="sp-head">
                        <div>
                            <p>{{ __('classroom.classmates_desc') }}</p>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($classmatesPage as $classmate)
                                <tr>
                                    <td class="name-cell">
                                        <div class="n">{{ $classmate->display_name ?: $classmate->student->name }}</div>
                                    </td>
                                    <td>{{ ($classmate->decided_at ?? $classmate->created_at)->format('d/m/Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="empty-row">
                                        {{ __('classroom.no_classmates_yet') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if ($classmatesPage->hasPages())
                        <div class="mt-12">{{ $classmatesPage->links() }}</div>
                    @endif
                </div>

                <!-- SETTINGS TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'settings' }">
                    <div class="sp-head">
                        <div>
                            <p>{{ __('classroom.student_settings_desc') }}</p>
                        </div>
                    </div>

                    <div class="settings-card mb-16">
                        <h3 class="settings-title">{{ __('classroom.nickname_label') }}
                        </h3>
                        <p class="settings-help">{{ __('classroom.nickname_help') }}</p>
                        <form method="POST" action="{{ route('student.classes.nickname.update', $classroom) }}"
                            class="flex gap-8 max-w-420">
                            @csrf @method('PUT')
                            <input name="display_name" value="{{ $membership->display_name }}" maxlength="100"
                                placeholder="{{ $user->name }}" class="settings-input flex-1">
                            <button type="submit" class="btn-sm-primary btn-px-16 flex-none w-auto">Save</button>
                        </form>
                    </div>

                    <div class="settings-card">
                        <h3 class="settings-title danger">Leave class
                        </h3>
                        <p class="settings-help lg">You'll lose access to
                            class materials. Your assignment history will remain recorded.</p>
                        <form method="POST" action="{{ route('student.classes.leave', $membership) }}"
                            onsubmit="return confirm('Are you sure you want to leave this class? All assignment histories will remain, but you will lose access to class materials.')">
                            @csrf
                            <button type="submit" class="btn-sm-ghost danger">Leave
                                class</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- CORKBOARD -->
            <x-shell.corkboard header="{{ __('classroom.pinned_header') }}">
                <div x-show="activeTab === 'assign'" class="cork-note">
                    <h3>{{ __('classroom.due_soon_header') }}</h3>
                    @if ($dueSoon->isNotEmpty())
                        <div class="digest-stack">
                            @foreach ($dueSoon as $assignment)
                                <div class="digest-line">
                                    <strong>{{ $assignment->title }}</strong>
                                    <span class="digest-meta">· due
                                        {{ $assignment->due_at->format('D, M j') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="cork-empty">{{ __('classroom.due_soon_empty') }}</p>
                    @endif
                </div>

                <div x-show="activeTab === 'docs'" x-cloak class="cork-note">
                    <h3>{{ __('classroom.shared_documents') }}</h3>
                    @if ($lastDocument)
                        <p class="digest-line"><strong>{{ Str::limit($lastDocument->title, 32) }}</strong></p>
                        <p class="cork-empty mt-2">
                            Posted {{ $lastDocument->created_at->format('d/m/Y') }} ·
                            {{ $classroom->documents_count }} documents
                        </p>
                    @else
                        <p class="cork-empty">{{ __('classroom.no_documents_yet') }}</p>
                    @endif
                </div>

                <div x-show="activeTab === 'team'" x-cloak class="cork-note">
                    <h3>{{ __('classroom.teaching_team') }}</h3>
                    <p class="digest-line">{{ __('classroom.teachers_managing', ['count' => 1 + $classroom->coTeachers->count()]) }}</p>
                </div>

                <div x-show="activeTab === 'classmates'" x-cloak class="cork-note">
                    <h3>{{ __('classroom.leaderboard_header') }}</h3>
                    @if (!empty($leaderboard))
                        <div class="digest-stack tight">
                            @foreach ($leaderboard as $index => $entry)
                                <div class="digest-line row">
                                    <span>{{ ['🥇', '🥈', '🥉'][$index] ?? ($index + 1) . '.' }} {{ $entry['name'] }}</span>
                                    <strong class="mono">{{ $entry['score'] }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="cork-empty">{{ __('classroom.no_leaderboard_data') }}</p>
                    @endif
                </div>

                <div x-show="activeTab === 'settings'" x-cloak class="cork-note">
                    <h3>{{ __('classroom.teacher_note_header') }}</h3>
                    @if ($classroom->note?->body)
                        <p class="digest-line">{{ $classroom->note->body }}</p>
                    @else
                        <p class="cork-empty">{{ __('classroom.no_note_yet') }}</p>
                    @endif
                </div>
            </x-shell.corkboard>
        </div>
    </div>

    <!-- MODAL: Join class by code -->
    <x-ui.modal id="modal-join-class" title="{{ __('classroom.join_modal_title') }}" maxWidth="sm">
        <div class="form-note">
            <form method="POST" action="{{ route('student.classes.join') }}">
                @csrf
                <div class="field">
                    <label>Class code</label>
                    <input type="text" name="join_code" minlength="8" maxlength="8" required autocomplete="off"
                        autocapitalize="characters" spellcheck="false" placeholder="AB12CD34">
                </div>
                <button type="submit" class="btn-sm-primary btn-full">Request to join</button>
            </form>
        </div>
    </x-ui.modal>
</x-layouts.student>
