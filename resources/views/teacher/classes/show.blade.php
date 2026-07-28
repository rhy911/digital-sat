<x-layouts.student :user="auth()->user()" :title="$classroom->name" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css'])
    @endpush

    @php
        $userInitials = auth()->user()?->initials ?? 'U';
        $pending = $classroom->memberships->where('status', 'pending');
        $dueSoon = $classroom->assignments
            ->whereNotNull('due_at')
            ->where('due_at', '>=', now())
            ->sortBy('due_at')
            ->take(3);

        // Corkboard digest data
        $recentJoins = $classroom->memberships
            ->where('status', 'active')
            ->sortByDesc(fn($m) => $m->decided_at ?? $m->created_at)
            ->take(3);

        $lastCoTeacher = $classroom->coTeachers->sortByDesc(fn($t) => $t->pivot->created_at)->first();
        $lastDocument = $classroom->documents->sortByDesc('created_at')->first();
    @endphp

    <div class="app-shell" x-data="{
        searchQuery: '',
        statusFilter: 'all',
        newClassOpen: false,
        activeTab: new URLSearchParams(location.search).get('tab')
            || (new URLSearchParams(location.search).get('announce_page') ? 'announce' : null)
            || (new URLSearchParams(location.search).get('assign_page') ? 'assign' : null)
            || (new URLSearchParams(location.search).get('docs_page') ? 'docs' : null)
            || 'announce'
    }">

        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="route('teacher.progress')" :avatar-label="$userInitials" :items="\App\Support\NavRail::teacher('classes')" />

        <x-shell.sidebar-list title="{{ __('classroom.my_classes') }}"
            count-text="{{ $activeClassroomsCount }} active · {{ $archivedClassroomsCount }} archived"
            search-placeholder="{{ __('classroom.search_placeholder') }}" :filters="[
                ['value' => 'all', 'label' => 'All'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'archived', 'label' => 'Archived'],
            ]" :items="$classrooms
                ->map(
                    fn($c) => [
                        'route' => route('teacher.classes.show', $c),
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
                <button class="new-class-btn"
                    @click="newClassOpen = !newClassOpen">{{ __('classroom.create_new_class') }}</button>
                <div class="new-class-form" :class="{ 'open': newClassOpen }">
                    <form method="POST" action="{{ route('teacher.classes.store') }}" autocomplete="off">
                        @csrf
                        <label>Class name</label>
                        <input type="text" name="name" required placeholder="SAT Prep — Summer" maxlength="150" autocomplete="off">
                        <label>Description</label>
                        <input type="text" name="description" placeholder="Optional" maxlength="2000" autocomplete="off">
                        <div class="actions">
                            <button type="button" class="btn-sm-ghost"
                                @click="newClassOpen = false">{{ __('classroom.cancel') }}</button>
                            <button type="submit" class="btn-sm-primary">{{ __('classroom.create_class') }}</button>
                        </div>
                    </form>
                </div>
            </x-slot:primaryAction>
        </x-shell.sidebar-list>

        <!-- COLUMN 3: LEDGER PANE (main workspace + corkboard) -->
        <div class="ledger-pane">
            <x-ui.flash />

            <!-- MAIN LEDGER COLUMN -->
            <div class="binder-panel">
                <div class="ledger-header">
                    <div class="dh-left">
                        <h2>{{ $classroom->name }} <span class="status-pill ok ml-2"><span class="d"></span>{{ ucfirst($classroom->status) }}</span></h2>
                        <div class="dh-desc">
                            {{ $classroom->description ?: 'Manage roster, resources, assignments, and class access.' }}
                        </div>
                    </div>
                </div>

                <x-shell.stat-row :stats="[
                    ['value' => $classroom->activeMemberships->count(), 'label' => 'Students'],
                    ['value' => $classroom->pending_memberships_count, 'label' => 'Pending'],
                    ['value' => 1 + $classroom->co_teachers_count, 'label' => 'Teachers'],
                    ['value' => $classroom->documents_count, 'label' => 'Resources'],
                    ['value' => $classroom->assignments_count, 'label' => 'Assignments'],
                ]" />

                <!-- PINNED TAB BAR (Teams-style: swaps the whole panel below, lazy via x-show) -->
                <x-shell.tab-bar :tabs="[
                    ['key' => 'announce', 'label' => 'Announcements', 'count' => $announcementsPage->total()],
                    ['key' => 'calendar', 'label' => 'Calendar', 'count' => $classroom->events->count()],
                    ['key' => 'roster', 'label' => 'Roster', 'count' => $classroom->activeMemberships->count()],
                    ['key' => 'team', 'label' => 'Teaching team', 'count' => 1 + $classroom->co_teachers_count],
                    ['key' => 'docs', 'label' => 'Documents', 'count' => $classroom->documents_count],
                    ['key' => 'assign', 'label' => 'Assignments', 'count' => $classroom->assignments_count],
                    ['key' => 'settings', 'label' => 'Settings'],
                ]" />

                <!-- ANNOUNCEMENTS TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'announce' }">
                    @if ($classroom->status === 'active')
                        <form method="POST" action="{{ route('teacher.announcements.store', $classroom) }}"
                            class="announce-composer" x-data="{ text: '' }" autocomplete="off">
                            @csrf
                            <div class="announce-composer__header">
                                <span class="announce-composer__title">
                                    <span class="announce-avatar">{{ substr(auth()->user()->name ?? 'T', 0, 1) }}</span>
                                    Share an update with your class
                                </span>
                            </div>
                            <textarea name="body" rows="3" maxlength="5000" required x-model="text" autocomplete="off"
                                placeholder="Write an announcement for all active students in this class…" class="announce-textarea"></textarea>
                            <div class="announce-composer__actions">
                                <span class="announce-composer__hint" x-text="text.length + ' / 5000'">0 / 5000</span>
                                <button type="submit" class="btn-sm-primary announce-post-btn" :disabled="!text.trim()" :class="{ 'is-active': text.trim().length > 0 }">Post announcement</button>
                            </div>
                        </form>
                    @endif

                    @forelse ($announcementsPage as $announcement)
                        <x-classroom.announcement-card :announcement="$announcement" :classroom="$classroom"
                            :can-manage="true" />
                    @empty
                        <div class="empty-grid-cell" style="margin-top: 8px;">
                            <strong>No announcements yet</strong>
                            <p style="margin-top: 4px;">Post the first update for your class.</p>
                        </div>
                    @endforelse

                    @if ($announcementsPage->hasPages())
                        <div style="margin-top: 12px;">{{ $announcementsPage->links() }}</div>
                    @endif
                </div>

                <!-- CALENDAR TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'calendar' }">
                    <x-shell.class-calendar :items="$calendarItems" :can-manage="true" :classroom="$classroom"
                        :events="$classroom->events" />
                </div>

                <!-- ROSTER TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'roster' }">
                    @if ($pending->isNotEmpty())
                        <div class="pending-block" x-data="{ selected: [] }">
                            <div class="pending-head">
                                <h3>Pending requests
                                    ({{ $pending->count() }})</h3>
                                @if ($classroom->status === 'active')
                                    <form method="POST"
                                        action="{{ route('teacher.memberships.bulk-approve', $classroom) }}"
                                        class="d-inline">
                                        @csrf
                                        <template x-for="id in selected" :key="id">
                                            <input type="hidden" name="membership_ids[]" :value="id">
                                        </template>
                                        <button type="submit" class="btn-sm-primary btn-compact flex-none w-auto"
                                            :disabled="selected.length === 0">
                                            Approve selected (<span x-text="selected.length"></span>)
                                        </button>
                                    </form>
                                @endif
                            </div>
                            @if ($classroom->status === 'active')
                                <label class="pending-select-all">
                                    <input type="checkbox"
                                        x-bind:checked="selected.length === {{ $pending->count() }} && selected.length > 0"
                                        x-on:change="selected = $event.target.checked ? [{{ $pending->pluck('id')->implode(',') }}] : []">
                                    Select all
                                </label>
                            @endif
                            <div class="flex flex-col gap-8">
                                @foreach ($pending as $membership)
                                    <div class="pending-row">
                                        <div class="flex items-center gap-8">
                                            @if ($classroom->status === 'active')
                                                <input type="checkbox" x-model.number="selected"
                                                    value="{{ $membership->id }}">
                                            @endif
                                            <div>
                                                <strong>{{ $membership->student->name }}</strong>
                                                <span
                                                    class="pending-row-email">({{ $membership->student->email }})</span>
                                            </div>
                                        </div>
                                        @if ($classroom->status === 'active')
                                            <div class="flex gap-6">
                                                <form method="POST"
                                                    action="{{ route('teacher.memberships.approve', $membership) }}"
                                                    class="d-inline">
                                                    @csrf
                                                    <button type="submit"
                                                        class="btn-sm-primary btn-tight flex-none w-auto">Approve</button>
                                                </form>
                                                <form method="POST"
                                                    action="{{ route('teacher.memberships.reject', $membership) }}"
                                                    class="d-inline">
                                                    @csrf
                                                    <button type="submit"
                                                        class="btn-sm-ghost btn-tight flex-none w-auto">Reject</button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="sp-head">
                        <div>
                            <p>{{ __('classroom.roster_desc') }}</p>
                        </div>
                        <button class="btn-outline" onclick="copyJoinCode('{{ $classroom->join_code }}', this)">Invite
                            code: {{ $classroom->join_code }}</button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Joined</th>
                                <th></th>
                                @if ($classroom->status === 'active')
                                    <th class="text-right">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rosterPage as $membership)
                                <tr>
                                    <td class="name-cell">
                                        <x-ui.person-cell :name="$membership->student->name" :email="$membership->student->email" :initials="$membership->student->initials" />
                                    </td>
                                    <td>{{ $membership->decided_at?->format('d/m/Y') ?? $membership->created_at->format('d/m/Y') }}
                                    </td>
                                    <td>
                                        <a href="{{ route('teacher.classes.students.progress', [$classroom, $membership->student]) }}"
                                            class="link-action">View progress</a>
                                    </td>
                                    @if ($classroom->status === 'active')
                                        <td class="text-right">
                                            <form method="POST"
                                                action="{{ route('teacher.memberships.remove', $membership) }}"
                                                onsubmit="return confirm('Remove this student? Result history will remain.');"
                                                class="d-inline">
                                                @csrf
                                                <button type="submit"
                                                    class="link-action danger btn-link-plain font-bold text-xs-plus">Remove</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $classroom->status === 'active' ? 4 : 3 }}" class="empty-row">
                                        No active students. Share code <strong>{{ $classroom->join_code }}</strong>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if ($rosterPage->hasPages())
                        <div class="mt-12">{{ $rosterPage->links() }}</div>
                    @endif
                </div>

                <!-- TEACHING TEAM TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'team' }">
                    <div class="sp-head">
                        <div>
                            <p>{{ __('classroom.team_desc') }}</p>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Added</th>
                                @if ($classroom->status === 'active')
                                    <th class="text-right">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="name-cell">
                                    <x-ui.person-cell :name="$classroom->owner->name" :email="$classroom->owner->email" :initials="$classroom->owner->initials" />
                                </td>
                                <td><span class="role-tag">Owner</span></td>
                                <td>{{ $classroom->created_at->format('d/m/Y') }}</td>
                                @if ($classroom->status === 'active')
                                    <td></td>
                                @endif
                            </tr>

                            @foreach ($classroom->coTeachers as $teacher)
                                <tr>
                                    <td class="name-cell">
                                        <x-ui.person-cell :name="$teacher->name" :email="$teacher->email" :initials="$teacher->initials" />
                                    </td>
                                    <td><span class="role-tag">Co-teacher</span></td>
                                    <td>{{ $teacher->pivot->created_at?->format('d/m/Y') ?? '—' }}</td>
                                    @if ($classroom->status === 'active')
                                        <td class="text-right">
                                            @can('manageTeam', $classroom)
                                                <form method="POST"
                                                    action="{{ route('teacher.classes.co-teachers.destroy', [$classroom, $teacher]) }}"
                                                    onsubmit="return confirm('Remove this co-teacher from the class?');"
                                                    class="d-inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                        class="link-action danger btn-link-plain font-bold text-xs-plus">Remove</button>
                                                </form>
                                            @endcan
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- DOCUMENTS TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'docs' }">
                    <div class="sp-head">
                        <div>
                            <p>{{ __('classroom.docs_desc') }}</p>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Type</th>
                                <th class="num">Uploaded</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($documentsPage as $document)
                                @php
                                    $ext = $document->isFile()
                                        ? strtolower(pathinfo($document->original_name ?? '', PATHINFO_EXTENSION))
                                        : '';
                                @endphp
                                <tr class="row-clickable"
                                    onclick="if (!event.target.closest('[data-row-menu]')) { window.open('{{ $document->isFile() ? route('class-documents.open', $document) : $document->external_url }}', '_blank'); }">
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
                                        <div data-row-menu class="row-menu-wrap" x-data="{ menuOpen: false, top: 0, left: 0 }" @click.stop>
                                            <button type="button" class="row-menu-btn" title="Actions"
                                                @click="const r = $el.getBoundingClientRect(); top = r.bottom + 4; left = r.right - 140; menuOpen = !menuOpen">
                                                <svg viewBox="0 0 24 24" fill="currentColor">
                                                    <circle cx="5" cy="12" r="1.8" />
                                                    <circle cx="12" cy="12" r="1.8" />
                                                    <circle cx="19" cy="12" r="1.8" />
                                                </svg>
                                            </button>
                                            <template x-teleport="body">
                                                <div class="row-menu-popover fixed" x-show="menuOpen" x-cloak
                                                    :style="`top:${top}px; left:${left}px;`"
                                                    @click.away="menuOpen = false" @click="menuOpen = false">
                                                    @if ($document->isFile())
                                                        <a href="{{ route('class-documents.download', $document) }}"
                                                            class="row-menu-item">Download</a>
                                                    @endif
                                                    @if ($classroom->status === 'active')
                                                        <form method="POST"
                                                            action="{{ route('teacher.classes.documents.destroy', [$classroom, $document]) }}"
                                                            onsubmit="return confirm('Remove this study document?');">
                                                            @csrf @method('DELETE')
                                                            <button type="submit"
                                                                class="row-menu-item danger">Remove</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="empty-row">
                                        No study documents. Upload reference files or web links for this class.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if ($documentsPage->hasPages())
                        <div class="mt-12">{{ $documentsPage->links() }}</div>
                    @endif
                </div>

                <!-- ASSIGNMENTS TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'assign' }">
                    @if ($dueSoon->isNotEmpty())
                        <div class="due-soon-row">
                            @foreach ($dueSoon as $assignment)
                                <div class="due-soon-card">
                                    <div class="dsc-title">{{ $assignment->title }}</div>
                                    <div class="dsc-due">Due {{ $assignment->due_at->format('D, M j') }}</div>
                                    <div class="dsc-meta">{{ $assignment->attempt_limit }}
                                        attempt{{ $assignment->attempt_limit === 1 ? '' : 's' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="sp-head">
                        <div>
                            <p>Publish an owned or shared active test to everyone in this class.</p>
                        </div>
                    </div>

                    <div class="assignments-grid">
                        @forelse($assignmentsPage as $assignment)
                            <a href="{{ route('teacher.assignments.show', $assignment) }}" class="index-card">
                                <div class="card-due">
                                    {{ $assignment->due_at ? $assignment->due_at->format('D, M j') : 'No deadline' }}
                                </div>
                                <div class="card-title">{{ $assignment->title }}</div>
                                <div class="card-sub">
                                    {{ $assignment->test->title }}{{ $assignment->sectionSuffix() }}
                                </div>
                                <div class="card-meta">
                                    <span class="type-tag">{{ $assignment->sectionShort() }}</span>
                                    <span
                                        class="status-pill {{ in_array($assignment->status, ['published', 'completed']) ? 'ok' : 'pending' }}">
                                        <span class="d"></span>{{ ucfirst($assignment->status) }}
                                    </span>
                                </div>
                                <div class="card-meta card-meta-divider">
                                    <span>{{ $assignment->attempt_limit }}
                                        attempt{{ $assignment->attempt_limit === 1 ? '' : 's' }}</span>
                                    <span
                                        class="mono text-faint text-2xs">{{ $assignment->due_at?->format('g:i A') ?: '' }}</span>
                                </div>
                            </a>
                        @empty
                            <div class="empty-grid-cell">
                                No assignments. Create one to assign tests to students.
                            </div>
                        @endforelse
                    </div>
                    @if ($assignmentsPage->hasPages())
                        <div class="mt-12">{{ $assignmentsPage->links() }}</div>
                    @endif
                </div>

                <!-- SETTINGS TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'settings' }">
                    <div class="sp-head">
                        <div>
                            <p>{{ __('classroom.settings_desc') }}</p>
                        </div>
                    </div>

                    <div class="settings-card mb-16">
                        <div class="join-box join-box-inline">
                            <div class="lbl">Student join code</div>
                            <div class="code-row justify-start">
                                <span class="code">{{ $classroom->join_code }}</span>
                                <button class="copy-btn" title="Copy"
                                    onclick="copyJoinCode('{{ $classroom->join_code }}', this)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="12" height="12" rx="2" />
                                        <path d="M5 15V5a2 2 0 0 1 2-2h10" />
                                    </svg>
                                </button>
                            </div>
                            <div class="sub-actions justify-start mt-6">
                                @if ($classroom->status === 'active')
                                    <form method="POST"
                                        action="{{ route('teacher.classes.rotate-code', $classroom) }}"
                                        class="d-inline">
                                        @csrf
                                        <button type="submit" class="link-action btn-link-plain">Rotate
                                            code</button>
                                    </form>
                                    @can('manageTeam', $classroom)
                                        <form method="POST" action="{{ route('teacher.classes.archive', $classroom) }}"
                                            class="d-inline"
                                            onsubmit="return confirm('Archive class and close published assignments?');">
                                            @csrf
                                            <button type="submit"
                                                class="link-action danger btn-link-plain">Archive</button>
                                        </form>
                                    @endcan
                                @else
                                    @can('manageTeam', $classroom)
                                        <form method="POST" action="{{ route('teacher.classes.restore', $classroom) }}"
                                            class="d-inline">
                                            @csrf
                                            <button type="submit" class="link-action btn-link-plain">Restore</button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($classroom->status === 'active')
                        <div class="settings-card">
                            <h3 class="settings-title lg">Edit
                                class details</h3>
                            <form method="POST" action="{{ route('teacher.classes.update', $classroom) }}"
                                class="flex flex-col gap-4 max-w-420">
                                @csrf @method('PUT')
                                <label class="form-field-label">Class
                                    name
                                    <input name="name" value="{{ $classroom->name }}" required maxlength="150"
                                        class="settings-input w-full mt-4">
                                </label>
                                <label class="form-field-label">Description
                                    <input name="description" value="{{ $classroom->description }}" maxlength="2000"
                                        class="settings-input w-full mt-4">
                                </label>
                                <button type="submit"
                                    class="btn-sm-primary btn-px-16 self-start flex-none w-auto">Save</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            <!-- CORKBOARD: glanceable digest per tab + shortcuts, follows the active tab -->
            <x-shell.corkboard header="{{ __('classroom.pinned_header') }}">
                @if ($classroom->status === 'active')
                    @if (filled($classroom->note?->body))
                        <div class="cork-note">
                            <h3>Note to class (Pinned)</h3>
                            <p class="digest-line whitespace-pre-wrap">{{ $classroom->note->body }}</p>
                        </div>
                    @endif

                    <div x-show="activeTab === 'roster'" class="cork-note">
                        <h3>{{ __('classroom.recent_activity') }}</h3>
                        @if ($recentJoins->isNotEmpty())
                            <div class="digest-stack">
                                @foreach ($recentJoins as $m)
                                    <div class="digest-line">
                                        <strong>{{ $m->student->name }}</strong>
                                        {{ __('classroom.joined_class') }}
                                        <span class="digest-meta">·
                                            {{ ($m->decided_at ?? $m->created_at)->format('d/m') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="cork-empty">{{ __('classroom.no_students_yet') }}</p>
                        @endif
                    </div>

                    <div x-show="activeTab === 'team'" x-cloak class="cork-note">
                        <h3>{{ __('classroom.teaching_team') }}</h3>
                        <p class="digest-line">
                            {{ __('classroom.teachers_managing', ['count' => 1 + $classroom->co_teachers_count]) }}
                        </p>
                        @if ($lastCoTeacher)
                            <p class="cork-empty mt-4">{{ __('classroom.latest') }}:
                                {{ $lastCoTeacher->name }} ·
                                {{ $lastCoTeacher->pivot->created_at?->format('d/m/Y') }}
                            </p>
                        @endif
                        @can('manageTeam', $classroom)
                            <button type="button" class="btn-sm-primary btn-pin"
                                @click="$dispatch('open-modal', 'modal-add-co-teacher')">{{ __('classroom.add_co_teacher') }}</button>
                        @endcan
                    </div>

                    <div x-show="activeTab === 'docs'" x-cloak class="cork-note">
                        <h3>{{ __('classroom.shared_documents') }}</h3>
                        @if ($lastDocument)
                            <p class="digest-line"><strong>{{ Str::limit($lastDocument->title, 32) }}</strong>
                            </p>
                            <p class="cork-empty mt-2">
                                {{ __('classroom.posted_on', ['date' => $lastDocument->created_at->format('d/m/Y'), 'count' => $classroom->documents_count]) }}
                            </p>
                        @else
                            <p class="cork-empty">{{ __('classroom.no_documents_yet') }}</p>
                        @endif
                        <button type="button" class="btn-sm-primary btn-pin"
                            @click="$dispatch('open-modal', 'modal-add-resource')">{{ __('classroom.add_document') }}</button>
                    </div>

                    <div x-show="activeTab === 'assign'" x-cloak class="cork-note">
                        <h3>{{ __('classroom.top_score') }}</h3>
                        @if ($topScore)
                            <p class="digest-line">
                                {{ __('classroom.leads_with_score', ['name' => $topScore['name'], 'score' => $topScore['score']]) }}
                            </p>
                        @else
                            <p class="cork-empty">{{ __('classroom.no_scores_yet') }}</p>
                        @endif
                        <p class="cork-empty mt-6">
                            {{ __('classroom.assignments_given', ['count' => $classroom->assignments_count]) }}{{ $dueSoon->isNotEmpty() ? __('classroom.due_soon_suffix', ['count' => $dueSoon->count()]) : '' }}.
                        </p>
                        <button type="button" class="btn-sm-primary btn-pin"
                            @click="$dispatch('open-modal', 'modal-create-assignment')">{{ __('classroom.create_assignment') }}</button>
                    </div>

                    <div x-show="activeTab === 'settings'" x-cloak class="cork-note">
                        <h3>Class settings</h3>
                        <p class="cork-empty">Rotate the join code if it leaked, or archive the class to close
                            published assignments. Both live in the Settings tab.</p>
                    </div>

                    <div x-show="activeTab === 'settings'" x-cloak class="cork-note mt-neg4">
                        <h3>Note to class</h3>
                        <form method="POST" action="{{ route('teacher.classes.note.update', $classroom) }}">
                            @csrf @method('PUT')
                            <div class="field">
                                <textarea name="body" rows="3" maxlength="2000"
                                    placeholder="Pinned message students will see on their corkboard...">{{ $classroom->note?->body }}</textarea>
                            </div>
                            <button type="submit" class="btn-sm-primary btn-full mt-8">Save note</button>
                        </form>
                    </div>
                @else
                    <div class="cork-note">
                        <h3>Class archived</h3>
                        <p class="cork-empty">This class is archived. Restore it from the Settings tab to add
                            resources, co-teachers, or assignments.</p>
                    </div>
                @endif
            </x-shell.corkboard>
        </div>

    </div>

    @if ($classroom->status === 'active')
        <!-- MODAL: Add co-teacher (opened from the Team tab pin) -->
        @can('manageTeam', $classroom)
            <x-ui.modal id="modal-add-co-teacher" title="{{ __('classroom.modal_add_co_teacher_title') }}"
                maxWidth="sm">
                <div class="form-note" x-data="{ query: '', results: [], selectedId: '', showDropdown: false }">
                    <form method="POST" action="{{ route('teacher.classes.co-teachers.store', $classroom) }}">
                        @csrf
                        <div class="field teacher-search-field">
                            <label>Approved Teacher Email or Name</label>
                            <input type="text" name="email" required placeholder="Search approved teacher..."
                                autocomplete="off" x-model="query" @input="selectedId = ''"
                                @input.debounce.250ms="
                                  if (query.trim().length < 2) { results = []; showDropdown = false; return; }
                                  fetch(`{{ route('home-dashboard.teachers.search') }}?q=${encodeURIComponent(query)}`)
                                    .then(r => r.json())
                                    .then(d => { results = d.data || []; showDropdown = true; })
                               "
                                @click.away="showDropdown = false">
                            <input type="hidden" name="teacher_id" x-model="selectedId">
                            <p class="cork-empty mt-4" x-show="query.trim().length > 0 && !selectedId" x-cloak>
                                Pick a teacher from the list to enable Add.
                            </p>

                            <div x-show="showDropdown && results.length > 0" class="teacher-search-results" x-cloak>
                                <template x-for="t in results" :key="t.id">
                                    <button type="button" class="teacher-search-item"
                                        @click="selectedId = t.id; query = t.email || t.name; showDropdown = false;">
                                        <div class="font-bold" x-text="t.name || t.email"></div>
                                        <div class="teacher-search-item-email" x-text="t.email || ''">
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <button type="submit" class="btn-sm-primary btn-full" :disabled="!selectedId"
                            :class="{ 'is-disabled': !selectedId }">Add
                            teacher</button>
                    </form>
                </div>
            </x-ui.modal>
        @endcan

        <!-- MODAL: Add resource (opened from the Documents tab pin) -->
        <x-ui.modal id="modal-add-resource" title="{{ __('classroom.modal_add_document_title') }}" maxWidth="sm">
            <div class="form-note" x-data="{ docType: 'file' }">
                <div class="type-toggle">
                    <button type="button" class="type-toggle-btn"
                        :class="docType === 'file' ? 'bg-white text-slate-800' : 'bg-transparent text-slate-500'"
                        @click="docType = 'file'">File</button>
                    <button type="button" class="type-toggle-btn"
                        :class="docType === 'link' ? 'bg-white text-slate-800' : 'bg-transparent text-slate-500'"
                        @click="docType = 'link'">Link</button>
                </div>

                <div x-show="docType === 'file'">
                    <form method="POST" action="{{ route('teacher.classes.documents.store', $classroom) }}"
                        enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="source_type" value="file">
                        <div class="field">
                            <label>Title</label>
                            <input type="text" name="title" required placeholder="e.g. SAT Reading Tips">
                        </div>
                        <div class="field">
                            <label>Choose File</label>
                            <input type="file" name="document_file" required class="text-xs">
                        </div>
                        <div class="field">
                            <label>Description</label>
                            <textarea name="description" placeholder="A short description (optional)" rows="2" class="resize-v"></textarea>
                        </div>
                        <button type="submit" class="btn-sm-primary btn-full">Upload
                            Resource</button>
                    </form>
                </div>

                <div x-show="docType === 'link'" x-cloak>
                    <form method="POST" action="{{ route('teacher.classes.documents.store', $classroom) }}">
                        @csrf
                        <input type="hidden" name="source_type" value="link">
                        <div class="field">
                            <label>Title</label>
                            <input type="text" name="title" required placeholder="e.g. Desmos Online Practice">
                        </div>
                        <div class="field">
                            <label>URL</label>
                            <input type="url" name="external_url" required
                                placeholder="https://example.com/resource">
                        </div>
                        <div class="field">
                            <label>Description</label>
                            <textarea name="description" placeholder="A short description (optional)" rows="2" class="resize-v"></textarea>
                        </div>
                        <button type="submit" class="btn-sm-primary btn-full">Add
                            Link</button>
                    </form>
                </div>
            </div>
        </x-ui.modal>

        <!-- MODAL: Create assignment (opened from the Assignments tab pin) -->
        <x-ui.modal id="modal-create-assignment" title="{{ __('classroom.modal_create_assignment_title') }}"
            maxWidth="sm">
            <div class="form-note" x-data="{ assignType: 'full' }">
                <form method="POST" action="{{ route('teacher.assignments.store', $classroom) }}">
                    @csrf
                    <div class="field">
                        <label>Test</label>
                        <select name="test_id" required>
                            <option value="">Select an active test</option>
                            @foreach ($tests as $test)
                                @php($isShared = auth()->user()->role !== 'admin' && $test->created_by !== auth()->id())
                                <option value="{{ $test->id }}">
                                    {{ $test->title }}{{ $isShared ? ' (shared)' : '' }}{{ $test->isContentLocked() ? ' (locked)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>Assign Type</label>
                        <div class="flex gap-16">
                            <label class="radio-inline">
                                <input type="radio" name="assign_type" value="full" x-model="assignType"> Full
                                Test
                            </label>
                            <label class="radio-inline">
                                <input type="radio" name="assign_type" value="section" x-model="assignType">
                                Section Only
                            </label>
                        </div>
                    </div>

                    <div class="field" x-show="assignType === 'section'" x-cloak>
                        <label>Select Section</label>
                        <select name="section_type">
                            <option value="reading_writing">Reading & Writing</option>
                            <option value="math">Math</option>
                        </select>
                    </div>

                    <div class="field">
                        <label>Assignment title</label>
                        <input type="text" name="title" required maxlength="180">
                    </div>

                    <div class="field">
                        <label>Instructions</label>
                        <textarea name="instructions" rows="2" class="resize-v"></textarea>
                    </div>

                    <div class="flex gap-12">
                        <div class="field flex-1">
                            <label>Available from</label>
                            <input type="text" class="datetime-picker" name="available_at"
                                placeholder="Open immediately" autocomplete="off">
                        </div>

                        <div class="field flex-1">
                            <label>Due at</label>
                            <input type="text" class="datetime-picker" name="due_at" placeholder="No deadline"
                                autocomplete="off">
                        </div>
                    </div>

                    <div class="field">
                        <label>Attempt limit</label>
                        <input type="number" name="attempt_limit" min="1" max="10" value="1"
                            required>
                    </div>

                    <button type="submit" class="btn-sm-primary btn-full mt-10">Create
                        assignment</button>
                </form>
            </div>
        </x-ui.modal>
    @endif

    @push('scripts')
        <script>
            function copyJoinCode(code, btn) {
                navigator.clipboard.writeText(code).then(() => {
                    if (!btn) return;
                    const original = btn.innerHTML;
                    btn.innerHTML = '✓';
                    setTimeout(() => {
                        btn.innerHTML = original;
                    }, 1200);
                });
            }

            document.addEventListener('DOMContentLoaded', () => {
                if (typeof window.initDatePickers === 'function') {
                    window.initDatePickers();
                }
            });
        </script>
    @endpush
    <x-ui.confirm-delete-modal />
</x-layouts.student>
