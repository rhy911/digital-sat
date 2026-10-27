<x-layouts.student ui="product" body-class="antialiased teacher-workspace" :user="auth()->user()" :title="$classroom->name" header-type="none">
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
    @endphp

    <div class="app-shell" x-data="{
        activeTab: new URLSearchParams(location.search).get('tab') ||
            (new URLSearchParams(location.search).get('announce_page') ? 'stream' : null) ||
            (new URLSearchParams(location.search).get('assign_page') ? 'assign' : null) ||
            (new URLSearchParams(location.search).get('docs_page') ? 'materials' : null) ||
            'stream'
    }">

        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="route('teacher.progress')" :avatar-label="$userInitials" :items="\App\Support\NavRail::teacher('classes')" />

        <!-- COLUMN 2: FULL-WIDTH MAIN WORKSPACE -->
        <main class="ledger-pane no-corkboard">
            <x-ui.flash />

            <div class="binder-panel">
                <!-- TOP BAR: BREADCRUMB & CLASS SWITCHER -->
                <div class="workspace-top-bar">
                    <a href="{{ route('teacher.classes.index') }}" class="back-nav-link">
                        <svg class="w-4 h-4 text-slate-500 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>{{ __('classroom.classes_title') }}</span>
                    </a>

                    <div class="class-switcher" x-data="{ switcherOpen: false }">
                        <button type="button" class="class-switcher-btn" @click="switcherOpen = !switcherOpen" aria-haspopup="true" :aria-expanded="switcherOpen ? 'true' : 'false'">
                            <span>{{ $classroom->name }}</span>
                            <span class="status-pill {{ $classroom->status === 'active' ? 'ok' : 'draft' }}">
                                <span class="d"></span>{{ ucfirst($classroom->status) }}
                            </span>
                            <svg class="chevron" width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div class="class-switcher-menu" x-show="switcherOpen" @click.outside="switcherOpen = false" x-cloak>
                            <div class="class-switcher-header">Switch Class</div>
                            <div class="class-switcher-list">
                                @foreach ($classrooms as $c)
                                    <a href="{{ route('teacher.classes.show', $c) }}" class="class-switcher-item {{ $c->id === $classroom->id ? 'is-active' : '' }}">
                                        <span class="class-switcher-item-name">{{ $c->name }}</span>
                                        <span class="class-switcher-item-meta">{{ $c->active_memberships_count ?? $c->activeMemberships->count() }} students · {{ $c->assignments_count }} assignments</span>
                                    </a>
                                @endforeach
                            </div>
                            <div class="class-switcher-footer">
                                <a href="{{ route('teacher.classes.index') }}" class="class-switcher-all">View All Classes →</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STAT ROW -->
                <x-shell.stat-row :stats="[
                    ['value' => $classroom->activeMemberships->count(), 'label' => __('classroom.stat_students')],
                    ['value' => $classroom->pending_memberships_count, 'label' => __('classroom.stat_pending')],
                    ['value' => 1 + $classroom->co_teachers_count, 'label' => __('classroom.stat_teachers')],
                    ['value' => $classroom->documents_count + $classroom->exclusiveTests->count(), 'label' => __('classroom.stat_resources')],
                    ['value' => $classroom->assignments_count, 'label' => __('classroom.stat_assignments')],
                ]" />

                <!-- 4 LMS TABS + SETTINGS -->
                <x-shell.tab-bar :tabs="[
                    ['key' => 'stream', 'label' => 'Stream', 'icon' => 'inbox', 'count' => $announcementsPage->total()],
                    ['key' => 'assign', 'label' => 'Assignments', 'icon' => 'file-text', 'count' => $classroom->assignments_count],
                    ['key' => 'materials', 'label' => 'Documents & Materials', 'icon' => 'journal-open', 'count' => $classroom->documents_count + $classroom->exclusiveTests->count()],
                    ['key' => 'roster', 'label' => 'Roster & Analytics', 'icon' => 'people', 'count' => $classroom->activeMemberships->count()],
                    ['key' => 'settings', 'label' => 'Settings', 'icon' => 'sliders'],
                ]" />

                <!-- TAB 1: STREAM PANEL -->
                <div id="panel-stream" class="section-panel" role="tabpanel" aria-labelledby="tab-stream"
                    :class="{ 'active': activeTab === 'stream' || activeTab === 'announce' }">
                    @if (filled($classroom->note?->body))
                        <div class="pinned-note-card mb-6">
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="flex items-center gap-2 font-bold text-sm text-slate-900">
                                    <x-ui.icon name="pin" class="w-4 h-4 text-brand" />
                                    <span>{{ __('classroom.note_to_class_pinned') }}</span>
                                </div>
                                @if ($classroom->status === 'active')
                                    <button type="button" class="text-sm font-semibold text-brand hover:text-brand-hover hover:underline cursor-pointer"
                                        @click="activeTab = 'settings'; $dispatch('set-settings-subtab', 'general')">
                                        Edit Note
                                    </button>
                                @endif
                            </div>
                            <p class="text-sm whitespace-pre-wrap text-slate-700 leading-relaxed">{{ $classroom->note->body }}</p>
                        </div>
                    @endif

                    @if ($dueSoon->isNotEmpty())
                        <div class="mb-6 p-4 rounded-xl bg-amber-50/60 border border-amber-200/80">
                            <div class="text-sm font-bold uppercase tracking-wider text-amber-900 mb-2 flex items-center gap-1.5">
                                <x-ui.icon name="zap" class="w-3.5 h-3.5 text-amber-700" />
                                <span>Due Soon</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                @foreach ($dueSoon as $assignment)
                                    <a href="{{ route('teacher.assignments.show', $assignment) }}"
                                        class="p-3 bg-white rounded-lg border border-amber-200 block hover:border-amber-400 transition-colors">
                                        <div class="text-sm font-bold text-slate-900 truncate">{{ $assignment->title }}</div>
                                        <div class="text-sm text-amber-800 font-mono mt-1">Due {{ $assignment->due_at->format('M j, g:i A') }}</div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($classroom->status === 'active')
                        <form method="POST" action="{{ route('teacher.announcements.store', $classroom) }}"
                            class="announce-composer mb-6" x-data="{ text: '' }" autocomplete="off">
                            @csrf
                            <div class="announce-composer__header">
                                <span class="announce-composer__title">
                                    <span class="announce-avatar">{{ substr(auth()->user()->name ?? 'T', 0, 1) }}</span>
                                    {{ __('classroom.announcement_composer_title') }}
                                </span>
                            </div>
                            <textarea name="body" rows="3" maxlength="5000" required x-model="text" autocomplete="off"
                                placeholder="{{ __('classroom.announcement_composer_placeholder') }}" class="announce-textarea"
                                aria-label="{{ __('classroom.announcement_content_label') }}"></textarea>
                            <div class="announce-composer__actions">
                                <span class="announce-composer__hint" x-text="text.length + ' / 5000'">0 / 5000</span>
                                <button type="submit" class="btn-sm-primary announce-post-btn" :disabled="!text.trim()"
                                    :class="{ 'is-active': text.trim().length > 0 }">{{ __('classroom.post_announcement') }}</button>
                            </div>
                        </form>
                    @endif

                    @forelse ($announcementsPage as $announcement)
                        <x-classroom.announcement-card :announcement="$announcement" :classroom="$classroom" :can-manage="true" />
                    @empty
                        <div class="empty-grid-cell" style="margin-top: 8px;">
                            <strong>{{ __('classroom.no_announcements_yet') }}</strong>
                            <p style="margin-top: 4px;">{{ __('classroom.no_announcements_teacher_body') }}</p>
                        </div>
                    @endforelse

                    @if ($announcementsPage->hasPages())
                        <div class="mt-4">{{ $announcementsPage->links() }}</div>
                    @endif
                </div>

                <!-- TAB 2: ASSIGNMENTS PANEL -->
                <div id="panel-assign" class="section-panel" role="tabpanel" aria-labelledby="tab-assign"
                    :class="{ 'active': activeTab === 'assign' }">
                    <div class="sp-head flex justify-between items-center mb-6">
                        <div>
                            <p>{{ __('classroom.publish_test_desc') }}</p>
                        </div>
                        @if ($classroom->status === 'active')
                            <button type="button" class="btn-sm-primary btn-px-16"
                                @click="$dispatch('open-modal', 'modal-create-assignment')">
                                {{ __('classroom.create_assignment') }}
                            </button>
                        @endif
                    </div>

                    <livewire:teacher.classroom-assignments :classroom="$classroom" />
                </div>

                <!-- TAB 3: CLASS MATERIALS (STUDY UNITS) -->
                <div id="panel-materials" class="section-panel" role="tabpanel" aria-labelledby="tab-materials"
                    :class="{ 'active': activeTab === 'materials' || activeTab === 'docs' }">
                    <div class="sp-head flex justify-between items-center flex-wrap gap-3 mb-6">
                        <div>
                            <p>Study units, curated documents, and exclusive practice tests for this class.</p>
                        </div>
                        @if ($classroom->status === 'active')
                            <div class="flex items-center gap-2">
                                <button type="button" class="btn-sm-ghost"
                                    @click="$dispatch('open-modal', 'modal-link-test')">
                                    + Link Practice Test
                                </button>
                                <button type="button" class="btn-sm-primary btn-px-16"
                                    @click="$dispatch('open-modal', 'modal-add-resource')">
                                    {{ __('classroom.add_document') }}
                                </button>
                            </div>
                        @endif
                    </div>

                    @forelse ($units as $unitKey => $unit)
                        <div class="study-unit-card">
                            <div class="study-unit-header">
                                <div class="flex items-center gap-2">
                                    <x-ui.icon name="folder2-open" class="w-5 h-5 text-slate-500" />
                                    <h3 class="study-unit-title">{{ $unit['title'] }}</h3>
                                </div>
                                <span class="text-sm text-slate-500 font-semibold">
                                    {{ count($unit['tests']) + count($unit['documents']) }} items
                                </span>
                            </div>
                            <div class="study-unit-body">
                                <!-- EXCLUSIVE TESTS SECTION -->
                                @if (!empty($unit['tests']) && count($unit['tests']) > 0)
                                    <div class="mb-4">
                                        <div class="text-sm font-bold uppercase tracking-wider text-slate-500 mb-2">Class Exclusive Tests</div>
                                        <div class="exclusive-tests-list">
                                            @foreach ($unit['tests'] as $test)
                                                <div class="exclusive-test-card">
                                                    <div class="flex items-center gap-3">
                                                        <x-ui.icon name="file-text" class="w-5 h-5 text-blue-600" />
                                                        <div>
                                                            <div class="exclusive-test-title">{{ $test->title }}</div>
                                                            <div class="text-sm text-slate-500">
                                                                {{ $test->totalQuestions() }} questions · Full SAT Format
                                                            </div>
                                                        </div>
                                                        <span class="exclusive-badge">Class Exclusive</span>
                                                    </div>
                                                    @if ($classroom->status === 'active')
                                                        <div class="flex items-center gap-3">
                                                            <form method="POST"
                                                                action="{{ route('teacher.classes.exclusive-tests.destroy', [$classroom, $test]) }}"
                                                                onsubmit="return confirm('Unlink this exclusive test from the class?');">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="link-action danger btn-link-plain text-sm font-semibold">
                                                                    Unlink
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- DOCUMENTS SECTION -->
                                @if (!empty($unit['documents']) && count($unit['documents']) > 0)
                                    <div>
                                        <div class="text-sm font-bold uppercase tracking-wider text-slate-500 mb-2">Documents & Links</div>
                                        <div class="table-responsive-wrapper overflow-x-auto">
                                            <table>
                                            <thead>
                                                <tr>
                                                    <th>{{ __('classroom.col_document') }}</th>
                                                    <th>{{ __('classroom.col_type') }}</th>
                                                    <th class="num">{{ __('classroom.col_uploaded') }}</th>
                                                    <th class="text-right">{{ __('classroom.col_actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($unit['documents'] as $document)
                                                    @php
                                                        $ext = $document->isFile()
                                                            ? strtolower(pathinfo($document->original_name ?? '', PATHINFO_EXTENSION))
                                                            : '';
                                                        $typeClass = match($ext) {
                                                            'pdf' => 'type-tag-pdf',
                                                            'doc', 'docx' => 'type-tag-doc',
                                                            'ppt', 'pptx' => 'type-tag-ppt',
                                                            default => $document->isFile() ? 'type-tag-doc' : 'type-tag-link',
                                                        };
                                                    @endphp
                                                    <tr>
                                                        <td class="name-cell">
                                                            <div class="font-bold text-sm text-slate-900">
                                                                <a href="{{ $document->isFile() ? route('class-documents.open', $document) : $document->external_url }}"
                                                                    target="_blank" rel="noopener noreferrer" class="text-inherit hover:underline">
                                                                    {{ $document->title }}
                                                                </a>
                                                            </div>
                                                            <div class="text-sm text-slate-500 mt-0.5">
                                                                @if ($document->isFile())
                                                                    {{ Str::limit($document->original_name, 32) }} · {{ $document->displaySize() }}
                                                                @else
                                                                    {{ parse_url($document->external_url ?? '', PHP_URL_HOST) }}
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td><span class="type-tag {{ $typeClass }} font-bold text-sm uppercase">{{ $document->isFile() ? strtoupper($ext ?: 'file') : 'LINK' }}</span></td>
                                                        <td class="num tabular-nums font-mono text-sm text-slate-600">{{ $document->created_at->format('d/m/Y') }}</td>
                                                        <td class="text-right">
                                                            <div class="flex items-center justify-end gap-2">
                                                                @if ($document->isFile())
                                                                    <a href="{{ route('class-documents.download', $document) }}"
                                                                        class="px-2.5 py-1 text-sm font-semibold rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 transition inline-flex items-center gap-1">{{ __('classroom.action_download') }} <x-ui.icon name="download" class="w-3 h-3 text-slate-500" /></a>
                                                                @else
                                                                    <a href="{{ $document->external_url }}" target="_blank"
                                                                        class="px-2.5 py-1 text-sm font-semibold rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 transition inline-flex items-center gap-1">{{ __('classroom.action_open_link') }} <x-ui.icon name="external-link" class="w-3 h-3 text-slate-500" /></a>
                                                                @endif
                                                                @if ($classroom->status === 'active')
                                                                    <form method="POST"
                                                                        action="{{ route('teacher.classes.documents.destroy', [$classroom, $document]) }}"
                                                                        onsubmit="return confirm('{{ __('classroom.remove_document_confirm') }}');"
                                                                        class="d-inline">
                                                                        @csrf @method('DELETE')
                                                                        <button type="submit" class="px-2 py-1 text-sm font-semibold rounded-md bg-red-50 hover:bg-red-100 text-red-700 transition">
                                                                            {{ __('classroom.action_remove') }}
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                @endif

                                @if ((empty($unit['tests']) || count($unit['tests']) === 0) && (empty($unit['documents']) || count($unit['documents']) === 0))
                                    <div class="p-4 text-center text-sm text-slate-500">
                                        No items in this unit yet.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="empty-grid-cell">
                            <strong>{{ __('classroom.no_documents_yet') }}</strong>
                            <p style="margin-top: 4px;">Upload documents or link exclusive tests to organize your study units.</p>
                        </div>
                    @endforelse
                </div>

                <!-- TAB 4: ROSTER & COHORT ANALYTICS -->
                <div id="panel-roster" class="section-panel" role="tabpanel" aria-labelledby="tab-roster"
                    :class="{ 'active': activeTab === 'roster' }">
                    <!-- COHORT DIAGNOSTIC SUMMARY -->
                    <div class="cohort-summary-grid">
                        <div class="cohort-card">
                            <span class="cohort-card-label">Class Average Composite</span>
                            <span class="cohort-card-val text-blue-700">
                                {{ $cohortStats['composite_avg'] ? $cohortStats['composite_avg'] . ' / 1600' : '—' }}
                            </span>
                            <span class="cohort-card-sub">Estimated SAT Scale</span>
                        </div>
                        <div class="cohort-card">
                            <span class="cohort-card-label">Section Averages</span>
                            <span class="cohort-card-val text-slate-800" style="font-size: 20px; line-height: 1.6;">
                                M: <strong>{{ $cohortStats['math_avg'] ? $cohortStats['math_avg'] : '—' }}</strong> · R&W: <strong>{{ $cohortStats['rw_avg'] ? $cohortStats['rw_avg'] : '—' }}</strong>
                            </span>
                            <span class="cohort-card-sub">Out of 800 per section</span>
                        </div>
                        <div class="cohort-card">
                            <span class="cohort-card-label">Tests Completed</span>
                            <span class="cohort-card-val text-emerald-700">
                                {{ $cohortStats['completed_tests_count'] }}
                            </span>
                            <span class="cohort-card-sub">Total student submissions</span>
                        </div>
                    </div>

                    <!-- DOMAIN PROFICIENCY GRID -->
                    @if (!empty($cohortStats['domain_proficiencies']))
                        <div class="domain-section-card">
                            <h4 class="domain-section-title">Class Skill Domain Proficiencies</h4>
                            <p class="domain-section-desc">Classwide question accuracy across Digital SAT skill domains.</p>
                            <div class="domain-bars-grid">
                                @foreach ($cohortStats['domain_proficiencies'] as $domain)
                                    <div class="domain-bar-item">
                                        <div class="domain-bar-header">
                                            <span class="domain-bar-name">{{ $domain['name'] }}</span>
                                            <span class="domain-bar-pct">{{ $domain['accuracy_pct'] }}%</span>
                                        </div>
                                        <div class="domain-track">
                                            <div class="domain-fill {{ $domain['accuracy_pct'] >= 75 ? 'high' : ($domain['accuracy_pct'] >= 60 ? 'medium' : 'low') }}"
                                                style="transform: scaleX({{ $domain['accuracy_pct'] / 100 }})"></div>
                                        </div>
                                        <div class="text-sm text-slate-400 font-mono">
                                            {{ $domain['correct'] }} / {{ $domain['total'] }} questions correct
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- STUDENT ROSTER & APPROVAL QUEUE -->
                    <livewire:teacher.classroom-roster :classroom="$classroom" />
                </div>

                <!-- TAB 5: SETTINGS PANEL -->
                <div id="panel-settings" class="section-panel" role="tabpanel" aria-labelledby="tab-settings"
                    :class="{ 'active': activeTab === 'settings' }"
                    x-data="{
                        subTab: new URLSearchParams(location.search).get('subtab') || 'general',
                        setSubTab(tab) {
                            this.subTab = tab;
                            const params = new URLSearchParams(location.search);
                            params.set('tab', 'settings');
                            params.set('subtab', tab);
                            history.replaceState(null, '', location.pathname + '?' + params.toString());
                        }
                    }"
                    @set-settings-subtab.window="setSubTab($event.detail)">

                    <div class="sp-head mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">{{ __('classroom.tab_settings') }}</h2>
                            <p class="text-sm text-slate-500 mt-0.5">{{ __('classroom.settings_desc') }}</p>
                        </div>

                        <!-- Sub-navigation Pills -->
                        <nav class="inline-flex p-1 bg-slate-100 rounded-lg border border-slate-200/80 gap-1 self-start sm:self-auto" role="tablist" aria-label="Settings sections">
                            <button type="button" role="tab"
                                id="subtab-general"
                                aria-controls="subpanel-general"
                                :aria-selected="subTab === 'general'"
                                @click="setSubTab('general')"
                                :class="subTab === 'general' ? 'bg-white text-slate-900 border-slate-200/80 shadow-xs' : 'text-slate-600 hover:text-slate-900 border-transparent'"
                                class="px-3.5 py-1.5 rounded-md text-sm font-semibold border transition-colors flex items-center gap-1.5 focus:outline-hidden focus:ring-2 focus:ring-brand/30 cursor-pointer">
                                <x-ui.icon name="sliders" class="w-3.5 h-3.5 opacity-75" />
                                <span>{{ __('classroom.tab_general') }}</span>
                            </button>

                            <button type="button" role="tab"
                                id="subtab-team"
                                aria-controls="subpanel-team"
                                :aria-selected="subTab === 'team'"
                                @click="setSubTab('team')"
                                :class="subTab === 'team' ? 'bg-white text-slate-900 border-slate-200/80 shadow-xs' : 'text-slate-600 hover:text-slate-900 border-transparent'"
                                class="px-3.5 py-1.5 rounded-md text-sm font-semibold border transition-colors flex items-center gap-1.5 focus:outline-hidden focus:ring-2 focus:ring-brand/30 cursor-pointer">
                                <x-ui.icon name="people" class="w-3.5 h-3.5 opacity-75" />
                                <span>{{ __('classroom.teaching_team') }}</span>
                                <span class="ml-0.5 px-1.5 py-0.2 rounded-full text-sm font-mono font-bold"
                                    :class="subTab === 'team' ? 'bg-brand/10 text-brand' : 'bg-slate-200 text-slate-600'">
                                    {{ 1 + $classroom->coTeachers->count() }}
                                </span>
                            </button>

                            <button type="button" role="tab"
                                id="subtab-danger"
                                aria-controls="subpanel-danger"
                                :aria-selected="subTab === 'danger'"
                                @click="setSubTab('danger')"
                                :class="subTab === 'danger' ? 'bg-red-50 text-red-700 border-red-200/60 shadow-xs' : 'text-slate-600 hover:text-red-600 border-transparent'"
                                class="px-3.5 py-1.5 rounded-md text-sm font-semibold border transition-colors flex items-center gap-1.5 focus:outline-hidden focus:ring-2 focus:ring-red-500/30 cursor-pointer">
                                <x-ui.icon name="alert-triangle" class="w-3.5 h-3.5 text-red-500" />
                                <span>{{ __('classroom.danger_zone') }}</span>
                            </button>
                        </nav>
                    </div>

                    <!-- SUB-PANEL 1: GENERAL -->
                    <div id="subpanel-general" role="tabpanel" aria-labelledby="subtab-general"
                        x-show="subTab === 'general'" x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        class="space-y-6">
                        @if ($classroom->status === 'archived')
                            <div class="p-4 rounded-xl bg-amber-50/70 border border-amber-200/80 text-amber-900 flex items-start gap-3">
                                <x-ui.icon name="archive" class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p class="font-bold text-sm">{{ __('classroom.class_archived') }}</p>
                                    <p class="text-sm text-amber-700 mt-0.5">{{ __('classroom.class_archived_help') }}</p>
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                            <!-- Left 2 Cols: Details & Pinned Note -->
                            <div class="lg:col-span-2 space-y-6">
                                <!-- CLASS DETAILS CARD -->
                                <div class="bg-white border border-slate-200/90 rounded-xl p-5 sm:p-6 shadow-xs">
                                    <div class="border-b border-slate-100 pb-3 mb-4">
                                        <h3 class="text-sm font-bold text-slate-900">{{ __('classroom.edit_class_details') }}</h3>
                                        <p class="text-sm text-slate-500 mt-0.5">{{ __('classroom.settings_desc') }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('teacher.classes.update', [$classroom, 'tab' => 'settings', 'subtab' => 'general']) }}" class="space-y-4">
                                        @csrf @method('PUT')
                                        <div>
                                            <label for="edit_class_name" class="block text-sm font-semibold text-slate-700 mb-1.5">{{ __('classroom.class_name') }} <span class="text-red-500">*</span></label>
                                            <input id="edit_class_name" name="name" value="{{ old('name', $classroom->name) }}" required maxlength="150"
                                                class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-hidden focus:border-brand focus:ring-1 focus:ring-brand shadow-2xs">
                                        </div>
                                        <div>
                                            <label for="edit_class_desc" class="block text-sm font-semibold text-slate-700 mb-1.5">{{ __('classroom.description') }}</label>
                                            <textarea id="edit_class_desc" name="description" rows="3" maxlength="2000"
                                                placeholder="{{ __('classroom.description') }}..."
                                                class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-hidden focus:border-brand focus:ring-1 focus:ring-brand shadow-2xs leading-relaxed">{{ old('description', $classroom->description) }}</textarea>
                                        </div>
                                        <div class="pt-1 flex justify-end">
                                            <button type="submit" class="btn-sm-primary px-4 py-2 text-sm font-semibold cursor-pointer">
                                                {{ __('classroom.save') }}
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- PINNED NOTE CARD -->
                                <div class="bg-white border border-slate-200/90 rounded-xl p-5 sm:p-6 shadow-xs">
                                    <div class="border-b border-slate-100 pb-3 mb-4">
                                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                                            <x-ui.icon name="pin" class="w-4 h-4 text-brand" />
                                            <span>{{ __('classroom.note_to_class') }}</span>
                                        </h3>
                                        <p class="text-sm text-slate-500 mt-0.5">{{ __('classroom.note_desc') }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('teacher.classes.note.update', [$classroom, 'tab' => 'settings', 'subtab' => 'general']) }}" class="space-y-4">
                                        @csrf @method('PUT')
                                        <div>
                                            <label for="teacher_note_body" class="sr-only">{{ __('classroom.note_to_class') }}</label>
                                            <textarea id="teacher_note_body" name="body" rows="4" maxlength="2000"
                                                placeholder="{{ __('classroom.note_placeholder') }}"
                                                class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-hidden focus:border-brand focus:ring-1 focus:ring-brand shadow-2xs leading-relaxed">{{ old('body', $classroom->note?->body) }}</textarea>
                                        </div>
                                        <div class="flex items-center justify-between pt-1">
                                            <span class="text-sm text-slate-400">Max 2,000 characters</span>
                                            <button type="submit" class="btn-sm-primary px-4 py-2 text-sm font-semibold cursor-pointer">
                                                {{ __('classroom.save_note') }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Right 1 Col: Student Join Code -->
                            <div class="space-y-6">
                                <div class="bg-white border border-slate-200/90 rounded-xl p-5 sm:p-6 shadow-xs">
                                    <div class="border-b border-slate-100 pb-3 mb-4">
                                        <h3 class="text-sm font-bold text-slate-900">{{ __('classroom.student_join_code') }}</h3>
                                        <p class="text-sm text-slate-500 mt-0.5">{{ __('classroom.share_join_code_desc') }}</p>
                                    </div>

                                    <div class="bg-slate-50 border border-slate-200/80 rounded-lg p-3.5 flex items-center justify-between mb-4" x-data="{ copied: false }">
                                        <span class="font-mono text-xl font-bold tracking-widest text-slate-900 select-all">{{ $classroom->join_code }}</span>
                                        <button type="button"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-md border border-transparent transition-colors cursor-pointer flex-shrink-0"
                                            :class="copied ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'text-slate-500 hover:text-slate-800 hover:bg-white hover:border-slate-200 shadow-none hover:shadow-2xs'"
                                            :title="copied ? 'Copied!' : 'Copy code'"
                                            aria-label="{{ __('classroom.copy_join_code') }}"
                                            @click="copyJoinCode('{{ $classroom->join_code }}'); copied = true; setTimeout(() => copied = false, 1500)">
                                            <template x-if="!copied">
                                                <x-ui.icon name="copy" class="w-4 h-4" />
                                            </template>
                                            <template x-if="copied">
                                                <x-ui.icon name="check-lg" class="w-4 h-4 text-emerald-600" />
                                            </template>
                                        </button>
                                    </div>

                                    <p class="text-sm text-slate-500 leading-relaxed mb-4">
                                        {{ __('classroom.rotate_code_desc') }}
                                    </p>

                                    @if ($classroom->status === 'active')
                                        <form method="POST" action="{{ route('teacher.classes.rotate-code', [$classroom, 'tab' => 'settings', 'subtab' => 'general']) }}">
                                            @csrf
                                            <button type="submit"
                                                class="w-full flex items-center justify-center gap-1.5 px-3 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 hover:border-slate-400 rounded-lg transition-colors cursor-pointer shadow-2xs">
                                                <x-ui.icon name="arrow-clockwise" class="w-3.5 h-3.5 text-slate-500" />
                                                <span>{{ __('classroom.rotate_code') }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SUB-PANEL 2: TEACHING TEAM -->
                    <div id="subpanel-team" role="tabpanel" aria-labelledby="subtab-team"
                        x-show="subTab === 'team'" x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        class="space-y-6">
                        <div class="bg-white border border-slate-200/90 rounded-xl p-5 sm:p-6 shadow-xs">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 mb-4 border-b border-slate-100 gap-3">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">{{ __('classroom.teaching_team') }}</h3>
                                    <p class="text-sm text-slate-500 mt-0.5">{{ __('classroom.team_desc') }}</p>
                                </div>
                                @can('manageTeam', $classroom)
                                    @if ($classroom->status === 'active')
                                        <button type="button" class="btn-sm-primary px-3.5 py-2 text-sm font-semibold self-start sm:self-auto cursor-pointer"
                                            @click="$dispatch('open-modal', 'modal-add-co-teacher')">
                                            <x-ui.icon name="person-plus" class="w-3.5 h-3.5" />
                                            <span>{{ __('classroom.add_co_teacher') }}</span>
                                        </button>
                                    @endif
                                @endcan
                            </div>

                            <div class="table-responsive-wrapper overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b border-slate-100">
                                            <th class="text-left font-semibold text-sm text-slate-500 pb-2.5">{{ __('classroom.col_name') }}</th>
                                            <th class="text-left font-semibold text-sm text-slate-500 pb-2.5">{{ __('classroom.col_role') }}</th>
                                            <th class="text-left font-semibold text-sm text-slate-500 pb-2.5">{{ __('classroom.col_added') }}</th>
                                            <th class="text-right font-semibold text-sm text-slate-500 pb-2.5">{{ __('classroom.col_actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr>
                                            <td class="name-cell py-3.5">
                                                <x-ui.person-cell :name="$classroom->owner->name" :email="$classroom->owner->email" :initials="$classroom->owner->initials" />
                                            </td>
                                            <td class="py-3.5">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-sm font-semibold bg-brand/10 text-brand">
                                                    {{ __('classroom.role_owner') }}
                                                </span>
                                            </td>
                                            <td class="tabular-nums font-mono text-sm text-slate-500 py-3.5">{{ $classroom->created_at->format('d/m/Y') }}</td>
                                            <td class="text-right py-3.5 text-sm text-slate-400 font-medium">—</td>
                                        </tr>
                                        @foreach ($classroom->coTeachers as $teacher)
                                            <tr>
                                                <td class="name-cell py-3.5">
                                                    <x-ui.person-cell :name="$teacher->name" :email="$teacher->email" :initials="$teacher->initials" />
                                                </td>
                                                <td class="py-3.5">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-sm font-semibold bg-slate-100 text-slate-700">
                                                        {{ __('classroom.role_coteacher') }}
                                                    </span>
                                                </td>
                                                <td class="tabular-nums font-mono text-sm text-slate-500 py-3.5">{{ $teacher->pivot->created_at?->format('d/m/Y') ?? '—' }}</td>
                                                <td class="text-right py-3.5">
                                                    @can('manageTeam', $classroom)
                                                        @if ($classroom->status === 'active')
                                                            <button type="button"
                                                                @click="$dispatch('open-confirm-delete', {
                                                                    title: @js(__('classroom.action_remove') . ' ' . $teacher->name),
                                                                    message: @js(__('classroom.remove_co_teacher_confirm')),
                                                                    actionUrl: @js(route('teacher.classes.co-teachers.destroy', [$classroom, $teacher, 'tab' => 'settings', 'subtab' => 'team'])),
                                                                    confirmButtonText: @js(__('classroom.action_remove'))
                                                                })"
                                                                class="text-sm font-semibold text-red-600 hover:text-red-700 hover:underline cursor-pointer">
                                                                {{ __('classroom.action_remove') }}
                                                            </button>
                                                        @endif
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- SUB-PANEL 3: DANGER ZONE -->
                    <div id="subpanel-danger" role="tabpanel" aria-labelledby="subtab-danger"
                        x-show="subTab === 'danger'" x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        class="space-y-6">
                        <div class="bg-red-50/20 border border-red-200/80 rounded-xl p-5 sm:p-6 shadow-xs">
                            <div class="border-b border-red-100 pb-4 mb-5 flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center text-red-600 flex-shrink-0">
                                    <x-ui.icon name="alert-triangle" class="w-4 h-4" />
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-red-950">{{ __('classroom.danger_zone') }}</h3>
                                    <p class="text-sm text-red-700/80 mt-0.5">{{ __('classroom.danger_zone_desc') }}</p>
                                </div>
                            </div>

                            <div class="divide-y divide-red-100">
                                @if ($classroom->status === 'active')
                                    <div class="py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900">{{ __('classroom.archive_class') }}</h4>
                                            <p class="text-sm text-slate-600 mt-0.5 leading-relaxed">{{ __('classroom.archive_class_desc') }}</p>
                                        </div>
                                        @can('manageTeam', $classroom)
                                            <form method="POST" action="{{ route('teacher.classes.archive', $classroom) }}"
                                                onsubmit="return confirm('{{ __('classroom.archive_class_confirm') }}');" class="flex-shrink-0">
                                                @csrf
                                                <button type="submit"
                                                    class="w-full sm:w-auto px-3.5 py-2 text-sm font-semibold text-red-700 bg-white border border-red-300 hover:bg-red-50 hover:border-red-400 rounded-lg transition-colors cursor-pointer shadow-2xs">
                                                    {{ __('classroom.archive_class') }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                @else
                                    <div class="py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900">{{ __('classroom.restore_class') }}</h4>
                                            <p class="text-sm text-slate-600 mt-0.5 leading-relaxed">{{ __('classroom.class_archived_help') }}</p>
                                        </div>
                                        @can('manageTeam', $classroom)
                                            <form method="POST" action="{{ route('teacher.classes.restore', [$classroom, 'tab' => 'settings', 'subtab' => 'danger']) }}" class="flex-shrink-0">
                                                @csrf
                                                <button type="submit" class="btn-sm-primary px-4 py-2 text-sm font-semibold cursor-pointer">
                                                    {{ __('classroom.restore_class') }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- DATALIST FOR STUDY UNITS -->
    <datalist id="units-list">
        @foreach (array_keys($units) as $uKey)
            @if ($uKey !== '_general')
                <option value="{{ $uKey }}"></option>
            @endif
        @endforeach
    </datalist>

    @if ($classroom->status === 'active')
        @php
            $studentRoster = $classroom->activeMemberships->map(function ($m) {
                $parts = preg_split('/\s+/', trim($m->student->name ?? 'Student'));
                $initials = count($parts) >= 2
                    ? strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1))
                    : strtoupper(mb_substr($parts[0] ?? 'S', 0, 2));
                return [
                    'id' => (int) $m->student_id,
                    'name' => (string) ($m->student->name ?? 'Student'),
                    'email' => (string) ($m->student->email ?? ''),
                    'initials' => $initials ?: 'ST',
                ];
            })->values();
        @endphp
        @php
            $formattedTests = $tests->map(function ($test) {
                $isShared = auth()->user()->role !== 'admin' && $test->created_by !== auth()->id();
                return [
                    'value' => (string) $test->id,
                    'label' => $test->title,
                    'group' => $isShared ? 'Shared with Me' : 'My Tests',
                    'subtext' => $isShared ? '(shared)' : null,
                ];
            })->sortBy(function ($t) {
                return ($t['group'] === 'My Tests' ? '0_' : '1_') . $t['label'];
            }, SORT_NATURAL)->values()->all();
        @endphp
        <!-- MODAL: CREATE ASSIGNMENT -->
        <div x-data="{
            assignType: 'full',
            audience: 'all',
            assignmentTitle: '{{ old('title', '') }}',
            tests: {{ Js::from($formattedTests) }},
            students: {{ Js::from($studentRoster) }},
            filteredStudents: [],
            selectedStudents: [],
            studentSearch: '',

            init() {
                this.filteredStudents = this.students;
            },

            onTestChange(val) {
                const found = this.tests.find(t => String(t.value) === String(val));
                if (found && !this.assignmentTitle.trim()) {
                    this.assignmentTitle = found.label;
                }
            },

            filterStudents() {
                const q = this.studentSearch.trim().toLowerCase();
                if (!q) {
                    this.filteredStudents = this.students;
                } else {
                    this.filteredStudents = this.students.filter(s => s.name.toLowerCase().includes(q) || s.email.toLowerCase().includes(q));
                }
            },

            isSelected(id) {
                return this.selectedStudents.includes(id);
            },

            toggleStudent(id) {
                if (this.isSelected(id)) {
                    this.selectedStudents = this.selectedStudents.filter(x => x !== id);
                } else {
                    this.selectedStudents.push(id);
                }
            },

            selectAll() {
                this.selectedStudents = this.students.map(s => s.id);
            },

            clearAll() {
                this.selectedStudents = [];
            }
        }">
            <x-ui.modal id="modal-create-assignment" title="{{ __('classroom.modal_create_assignment_title') }}" maxWidth="adaptive" dynamicMaxWidth="audience === 'specific' ? 'sm:max-w-[1128px]' : 'sm:max-w-[764px]'">
                <div class="form-note">
                    <form method="POST" action="{{ route('teacher.assignments.store', $classroom) }}" class="space-y-3.5">
                        @csrf

                        <div class="flex flex-col lg:flex-row items-stretch min-h-[380px] overflow-hidden">
                            <!-- COLUMN 1: Test Selection & Assignment Details -->
                            <div class="w-full lg:w-[350px] shrink-0 flex flex-col justify-between p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/90 space-y-3 min-h-[380px]">
                                <div class="space-y-2.5">
                                    <div class="flex items-center gap-1.5 font-bold text-sm uppercase tracking-wider text-slate-500">
                                        <x-ui.icon name="document" class="w-3.5 h-3.5 text-slate-400" />
                                        <span>{{ __('classroom.field_test') }} &amp; Scope</span>
                                    </div>

                                    <div class="field">
                                        <label for="assign_test_id" class="sr-only">{{ __('classroom.field_test') }}</label>
                                        <x-ui.select name="test_id" id="assign_test_id"
                                            :options="$formattedTests"
                                            :placeholder="__('classroom.select_test_placeholder')"
                                            required
                                            @change="onTestChange($event.detail)" />
                                    </div>

                                    <div class="grid grid-cols-2 gap-1 p-0.5 bg-slate-200/70 rounded-lg text-sm font-semibold">
                                        <label class="flex items-center justify-center py-1 rounded cursor-pointer transition text-center"
                                            :class="assignType === 'full' ? 'bg-white text-brand shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'">
                                            <input type="radio" name="assign_type" value="full" x-model="assignType" class="sr-only">
                                            <span>{{ __('classroom.assign_full_test') }}</span>
                                        </label>
                                        <label class="flex items-center justify-center py-1 rounded cursor-pointer transition text-center"
                                            :class="assignType === 'section' ? 'bg-white text-brand shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'">
                                            <input type="radio" name="assign_type" value="section" x-model="assignType" class="sr-only">
                                            <span>{{ __('classroom.assign_section_only') }}</span>
                                        </label>
                                    </div>

                                    <div x-show="assignType === 'section'" x-cloak>
                                        <label for="assign_section_type" class="sr-only">{{ __('classroom.select_section') }}</label>
                                        <x-ui.select name="section_type" id="assign_section_type"
                                            :searchable="false"
                                            value="reading_writing"
                                            :options="[
                                                'reading_writing' => __('classroom.section_reading_writing'),
                                                'math' => __('classroom.section_math'),
                                            ]" />
                                    </div>
                                </div>

                                <div class="space-y-2 pt-2 border-t border-slate-200/70">
                                    <div>
                                        <label for="assign_title" class="font-bold text-sm uppercase tracking-wider text-slate-500 block mb-1">{{ __('classroom.assignment_title') }}</label>
                                        <input id="assign_title" type="text" name="title" required maxlength="180"
                                            x-model="assignmentTitle"
                                            placeholder="e.g. Week 3 Practice: Math &amp; Reading"
                                            class="w-full text-sm bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 focus:border-brand focus:ring-1 focus:ring-brand">
                                    </div>

                                    <div>
                                        <label for="assign_instructions" class="font-bold text-sm uppercase tracking-wider text-slate-500 block mb-1">{{ __('classroom.field_instructions') }}</label>
                                        <textarea id="assign_instructions" name="instructions" rows="2"
                                            placeholder="Optional instructions for students..."
                                            class="resize-none w-full text-sm bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 focus:border-brand focus:ring-1 focus:ring-brand"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- COLUMN 2: Schedule & Audience Targeting -->
                            <div class="w-full lg:w-[350px] shrink-0 flex flex-col justify-between p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/90 space-y-3 min-h-[380px] mt-3.5 lg:mt-0 lg:ml-3.5">
                                <div class="space-y-2.5 pt-2 border-t border-slate-200/70">
                                    <div class="flex items-center justify-between font-bold text-sm uppercase tracking-wider text-slate-500">
                                        <div class="flex items-center gap-1.5">
                                            <x-ui.icon name="calendar" class="w-3.5 h-3.5 text-slate-400" />
                                            <span>Schedule &amp; Attempts</span>
                                        </div>
                                        <span class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 bg-slate-100 border border-slate-200/80 rounded-md px-1.5 py-0.5 normal-case tracking-normal" title="All schedule dates and deadlines use this timezone">
                                            <x-ui.icon name="clock" class="w-3 h-3 text-slate-400" />
                                            <span>Asia/Ho_Chi_Minh</span>
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <label for="assign_available_at" class="font-semibold text-sm uppercase tracking-wider text-slate-500">{{ __('classroom.available_from') }}</label>
                                                <button type="button" onclick="window.clearDatePicker?.('assign_available_at')" class="text-sm text-slate-400 hover:text-slate-600 transition cursor-pointer">Clear</button>
                                            </div>
                                            <input id="assign_available_at" type="datetime-local" step="300" class="datetime-picker w-full text-sm bg-white border border-slate-300 rounded-lg px-2.5 py-1.5" name="available_at"
                                                autocomplete="off"
                                                data-window-role="start" data-window-pair="assign_due_at">
                                        </div>
                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <label for="assign_due_at" class="font-semibold text-sm uppercase tracking-wider text-slate-500">{{ __('classroom.due_at') }}</label>
                                                <button type="button" onclick="window.clearDatePicker?.('assign_due_at')" class="text-sm text-slate-400 hover:text-slate-600 transition cursor-pointer">Clear</button>
                                            </div>
                                            <input id="assign_due_at" type="datetime-local" step="300" class="datetime-picker w-full text-sm bg-white border border-slate-300 rounded-lg px-2.5 py-1.5" name="due_at"
                                                autocomplete="off"
                                                data-window-role="end" data-window-pair="assign_available_at">
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label for="assign_attempt_limit" class="font-bold text-sm uppercase tracking-wider text-slate-500">{{ __('classroom.attempt_limit') }}</label>
                                            <span class="text-sm text-slate-400">1 to 10 allowed</span>
                                        </div>
                                        <input id="assign_attempt_limit" type="number" name="attempt_limit" min="1" max="10" value="1" required
                                            class="w-full text-sm bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 focus:border-brand focus:ring-1 focus:ring-brand">
                                    </div>
                                </div>

                                <!-- Audience Toggle & Class Summary -->
                                <div class="space-y-2 pt-2 border-t border-slate-200/70">
                                    <div class="flex items-center gap-1.5 font-bold text-sm uppercase tracking-wider text-slate-500">
                                        <x-ui.icon name="people" class="w-3.5 h-3.5 text-slate-400" />
                                        <span>Audience Targeting</span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-1 p-0.5 bg-slate-200/70 rounded-lg text-sm font-semibold shrink-0">
                                        <label class="flex items-center justify-center gap-1.5 py-1.5 rounded cursor-pointer transition text-center"
                                            :class="audience === 'all' ? 'bg-white text-brand shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'">
                                            <input type="radio" name="target_audience" value="all" x-model="audience" class="sr-only">
                                            <x-ui.icon name="people" class="w-3.5 h-3.5 shrink-0" />
                                            <span>Whole Class</span>
                                        </label>
                                        <label class="flex items-center justify-center gap-1.5 py-1.5 rounded cursor-pointer transition text-center"
                                            :class="audience === 'specific' ? 'bg-white text-brand shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'">
                                            <input type="radio" name="target_audience" value="specific" x-model="audience" class="sr-only">
                                            <x-ui.icon name="target" class="w-3.5 h-3.5 shrink-0" />
                                            <span>Specific</span>
                                        </label>
                                    </div>

                                    <div x-show="audience === 'all'" class="p-2.5 rounded-lg bg-blue-50/70 border border-blue-200/80 text-sm text-blue-900 flex items-center gap-2">
                                        <x-ui.icon name="shield-check" class="w-4 h-4 text-brand shrink-0" />
                                        <span>Assigning to all <strong>{{ count($studentRoster) }}</strong> active students in this class.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- COLUMN 3: Specific Student Selector (Sliding Drawer with fixed inner width) -->
                            <div class="transition-[width,opacity,margin,padding] duration-300 ease-in-out overflow-hidden flex flex-col justify-between"
                                :class="audience === 'specific'
                                    ? 'w-full lg:w-[350px] shrink-0 opacity-100 p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/90 space-y-2.5 min-h-[380px] mt-3.5 lg:mt-0 lg:ml-3.5'
                                    : 'w-0 max-h-0 lg:max-h-none opacity-0 p-0 border-0 pointer-events-none m-0'">
                                <div class="w-full lg:w-[320px] flex flex-col justify-between h-full space-y-2.5 shrink-0">
                                    <div class="space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-1.5 font-bold text-sm uppercase tracking-wider text-slate-500">
                                                <x-ui.icon name="target" class="w-3.5 h-3.5 text-brand" />
                                                <span>Select Students</span>
                                            </div>
                                            <span class="text-sm text-slate-500 font-medium">
                                                <strong class="text-brand" x-text="selectedStudents.length"></strong> of <span x-text="students.length"></span>
                                            </span>
                                        </div>

                                        <!-- Search Bar -->
                                        <div class="relative shrink-0 flex items-center">
                                            <x-ui.icon name="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                                            <input type="text" x-model="studentSearch" @input="filterStudents()" placeholder="Search by name or email..."
                                                class="w-full pl-8 pr-7 py-1.5 text-sm bg-white border border-slate-300 rounded-lg text-slate-800 placeholder:text-slate-400 focus:border-brand focus:ring-1 focus:ring-brand">
                                            <button type="button" x-show="studentSearch" @click="studentSearch = ''; filterStudents()" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer p-0.5">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </div>

                                        <!-- Action Bar: Counter + Select All / Clear -->
                                        <div class="flex items-center justify-between text-sm text-slate-600 shrink-0 px-0.5">
                                            <span class="text-sm text-slate-400">Click a student to toggle</span>
                                            <div class="flex items-center gap-2">
                                                <button type="button" @click="selectAll()" class="text-brand hover:underline font-semibold cursor-pointer">Select All</button>
                                                <span class="text-slate-300">|</span>
                                                <button type="button" @click="clearAll()" class="text-slate-500 hover:text-slate-800 font-semibold cursor-pointer">Clear</button>
                                            </div>
                                        </div>

                                        <!-- Scrollable Student List Container -->
                                        <div class="h-[210px] overflow-y-auto space-y-1 pr-0.5 border border-slate-200/90 rounded-lg p-1 bg-white overscroll-contain">
                                            <template x-for="student in filteredStudents" :key="student.id">
                                                <div @click="toggleStudent(student.id)"
                                                    class="flex items-center justify-between p-1.5 rounded-md border cursor-pointer transition text-sm"
                                                    :class="isSelected(student.id) ? 'bg-blue-50/80 border-brand/40 text-blue-900 ring-1 ring-brand/20' : 'bg-white border-slate-100 hover:bg-slate-50 text-slate-700'">
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                                                            :class="isSelected(student.id) ? 'bg-brand text-white' : 'bg-slate-200 text-slate-600'"
                                                            x-text="student.initials"></span>
                                                        <div class="min-w-0 leading-tight">
                                                            <div class="font-semibold text-sm truncate" x-text="student.name"></div>
                                                            <div class="text-sm text-slate-400 truncate" x-text="student.email"></div>
                                                        </div>
                                                    </div>
                                                    <div class="shrink-0 ml-1.5">
                                                        <span class="w-3.5 h-3.5 rounded border flex items-center justify-center transition-colors"
                                                            :class="isSelected(student.id) ? 'bg-brand border-brand text-white' : 'border-slate-300 bg-white'">
                                                            <svg x-show="isSelected(student.id)" class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                        </span>
                                                    </div>
                                                </div>
                                            </template>
                                            <div x-show="filteredStudents.length === 0" class="py-8 text-center text-sm text-slate-400">
                                                No students found matching "<span x-text="studentSearch"></span>"
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Validation alert -->
                                    <div>
                                        <p x-show="selectedStudents.length === 0" class="text-sm text-rose-600 font-medium">
                                            ⚠️ Please select at least one student recipient.
                                        </p>
                                    </div>
                                </div>

                                <!-- Hidden Inputs for Form Submission -->
                                <template x-for="id in selectedStudents" :key="id">
                                    <input type="hidden" name="student_ids[]" :value="id">
                                </template>
                            </div>
                        </div>

                        <!-- MODAL ACTIONS BAR -->
                        <div class="flex items-center justify-between pt-3 border-t border-slate-200/80">
                            <div class="text-sm text-slate-500">
                                <span x-show="audience === 'all'">Assigning to <strong>all {{ count($studentRoster) }} students</strong></span>
                                <span x-show="audience === 'specific'">Assigning to <strong class="text-brand" x-text="selectedStudents.length"></strong> selected student(s)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="btn-sm-ghost cursor-pointer"
                                    x-on:click="$dispatch('close-modal', 'modal-create-assignment')">
                                    {{ __('classroom.cancel') }}
                                </button>
                                <button type="submit" class="btn-sm-primary btn-px-16 shadow-xs font-semibold cursor-pointer"
                                    :disabled="audience === 'specific' && selectedStudents.length === 0"
                                    :class="audience === 'specific' && selectedStudents.length === 0 ? 'opacity-50 cursor-not-allowed' : ''">
                                    {{ __('classroom.create_assignment') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </x-ui.modal>
        </div>

        <!-- MODAL: ADD RESOURCE (DOCUMENT / LINK) -->
        <x-ui.modal id="modal-add-resource" title="{{ __('classroom.modal_add_document_title') }}" maxWidth="md">
            <div class="form-note" x-data="{
                docType: 'file',
                fileName: '',
                fileSize: '',
                isDragging: false,
                onFileChosen(e) {
                    const file = e.target.files?.[0];
                    this.setFile(file);
                },
                onFileDropped(e) {
                    this.isDragging = false;
                    const file = e.dataTransfer?.files?.[0];
                    if (file) {
                        this.$refs.fileInput.files = e.dataTransfer.files;
                        this.setFile(file);
                    }
                },
                setFile(file) {
                    if (!file) {
                        this.fileName = '';
                        this.fileSize = '';
                        return;
                    }
                    this.fileName = file.name;
                    const kb = file.size / 1024;
                    this.fileSize = kb > 1024 ? (kb / 1024).toFixed(1) + ' MB' : Math.round(kb) + ' KB';
                },
                clearFile() {
                    this.fileName = '';
                    this.fileSize = '';
                    if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                }
            }">
                <div class="p-1 bg-slate-100 rounded-xl flex border border-slate-200/80 mb-4" role="tablist" aria-label="Resource type">
                    <button type="button" role="tab" id="tab-add-file" :aria-selected="docType === 'file'" aria-controls="panel-add-file"
                        class="flex-1 py-1.5 px-3 rounded-lg text-sm font-semibold transition cursor-pointer flex items-center justify-center gap-1.5"
                        :class="docType === 'file' ? 'bg-white text-brand shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
                        @click="docType = 'file'">
                        <x-ui.icon name="cloud-arrow-up" class="w-3.5 h-3.5" />
                        <span>{{ __('classroom.type_file') }}</span>
                    </button>
                    <button type="button" role="tab" id="tab-add-link" :aria-selected="docType === 'link'" aria-controls="panel-add-link"
                        class="flex-1 py-1.5 px-3 rounded-lg text-sm font-semibold transition cursor-pointer flex items-center justify-center gap-1.5"
                        :class="docType === 'link' ? 'bg-white text-brand shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
                        @click="docType = 'link'">
                        <x-ui.icon name="external-link" class="w-3.5 h-3.5" />
                        <span>{{ __('classroom.type_link') }}</span>
                    </button>
                </div>

                <div x-show="docType === 'file'" id="panel-add-file" role="tabpanel" aria-labelledby="tab-add-file">
                    <form method="POST" action="{{ route('teacher.classes.documents.store', $classroom) }}"
                        enctype="multipart/form-data" class="space-y-3.5">
                        @csrf
                        <input type="hidden" name="source_type" value="file">

                        <div class="field">
                            <label for="doc_file_unit" class="font-bold text-sm uppercase tracking-wider text-slate-500 block mb-1">
                                {{ __('classroom.study_unit_optional') }}
                            </label>
                            <input id="doc_file_unit" type="text" name="unit_name" placeholder="e.g. Unit 1: Advanced Math & Desmos" list="units-list"
                                class="w-full text-sm bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 focus:border-brand focus:ring-1 focus:ring-brand">
                        </div>

                        <div class="field">
                            <label for="doc_file_title" class="font-bold text-sm uppercase tracking-wider text-slate-500 block mb-1">
                                {{ __('classroom.field_title') }}
                            </label>
                            <input id="doc_file_title" type="text" name="title" required placeholder="e.g. SAT Reading Tips & Formula Sheet"
                                class="w-full text-sm bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 focus:border-brand focus:ring-1 focus:ring-brand">
                        </div>

                        <!-- Interactive Drag & Drop File Zone -->
                        <div class="field">
                            <label for="doc_file_input" class="font-bold text-sm uppercase tracking-wider text-slate-500 block mb-1">
                                {{ __('classroom.choose_file') }}
                            </label>
                            <input id="doc_file_input" x-ref="fileInput" type="file" name="document_file" required class="sr-only"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.png,.jpg,.jpeg"
                                @change="onFileChosen($event)">

                            <div @click="$refs.fileInput.click()"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="onFileDropped($event)"
                                class="border-2 border-dashed rounded-xl p-3.5 text-center cursor-pointer transition flex flex-col items-center justify-center gap-1.5 group"
                                :class="isDragging ? 'border-brand bg-blue-50/80 ring-2 ring-brand/20' : 'border-slate-200 bg-slate-50/60 hover:bg-slate-50 hover:border-slate-300'">

                                <template x-if="!fileName">
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-400 group-hover:text-brand transition shadow-2xs">
                                            <x-ui.icon name="cloud-arrow-up" class="w-4 h-4" />
                                        </div>
                                        <div class="text-sm text-slate-700 font-medium">
                                            <span class="text-brand font-semibold hover:underline">Click to browse</span> or drag and drop
                                        </div>
                                        <div class="text-sm text-slate-400">PDF, DOCX, XLSX, PPTX, ZIP up to 25MB</div>
                                    </div>
                                </template>

                                <template x-if="fileName">
                                    <div class="w-full flex items-center justify-between px-2 py-1.5 bg-white border border-slate-200 rounded-lg text-left" @click.stop>
                                        <div class="flex items-center gap-2 truncate pr-2">
                                            <div class="w-7 h-7 rounded-md bg-blue-50 text-brand flex items-center justify-center shrink-0">
                                                <x-ui.icon name="document" class="w-3.5 h-3.5" />
                                            </div>
                                            <div class="truncate leading-tight">
                                                <div class="text-sm font-semibold text-slate-800 truncate" x-text="fileName"></div>
                                                <div class="text-sm text-slate-400" x-text="fileSize"></div>
                                            </div>
                                        </div>
                                        <button type="button" @click.stop="clearFile()" class="text-slate-400 hover:text-rose-600 p-1 rounded-md transition cursor-pointer" title="Remove file" aria-label="Remove file">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="field">
                            <label for="doc_file_desc" class="font-bold text-sm uppercase tracking-wider text-slate-500 block mb-1">
                                {{ __('classroom.description') }}
                            </label>
                            <textarea id="doc_file_desc" name="description" placeholder="A short description for students (optional)" rows="2"
                                class="resize-none w-full text-sm bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 focus:border-brand focus:ring-1 focus:ring-brand"></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                            <button type="button" class="btn-sm-ghost cursor-pointer"
                                x-on:click="$dispatch('close-modal', 'modal-add-resource')">
                                {{ __('classroom.cancel') }}
                            </button>
                            <button type="submit" class="btn-sm-primary btn-px-16 shadow-xs font-semibold cursor-pointer">
                                {{ __('classroom.upload_file') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div x-show="docType === 'link'" id="panel-add-link" role="tabpanel" aria-labelledby="tab-add-link" x-cloak>
                    <form method="POST" action="{{ route('teacher.classes.documents.store', $classroom) }}" class="space-y-3.5">
                        @csrf
                        <input type="hidden" name="source_type" value="link">

                        <div class="field">
                            <label for="doc_link_unit" class="font-bold text-sm uppercase tracking-wider text-slate-500 block mb-1">
                                {{ __('classroom.study_unit_optional') }}
                            </label>
                            <input id="doc_link_unit" type="text" name="unit_name" placeholder="e.g. Unit 1: Advanced Math & Desmos" list="units-list"
                                class="w-full text-sm bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 focus:border-brand focus:ring-1 focus:ring-brand">
                        </div>

                        <div class="field">
                            <label for="doc_link_title" class="font-bold text-sm uppercase tracking-wider text-slate-500 block mb-1">
                                {{ __('classroom.field_title') }}
                            </label>
                            <input id="doc_link_title" type="text" name="title" required placeholder="e.g. Desmos Online Practice & Calculator"
                                class="w-full text-sm bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 focus:border-brand focus:ring-1 focus:ring-brand">
                        </div>

                        <div class="field">
                            <label for="doc_link_url" class="font-bold text-sm uppercase tracking-wider text-slate-500 block mb-1">
                                {{ __('classroom.field_url') }}
                            </label>
                            <div class="relative flex items-center">
                                <x-ui.icon name="external-link" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                                <input id="doc_link_url" type="url" name="external_url" required
                                    placeholder="https://example.com/resource"
                                    class="w-full pl-8 pr-2.5 py-1.5 text-sm bg-white border border-slate-300 rounded-lg text-slate-800 placeholder:text-slate-400 focus:border-brand focus:ring-1 focus:ring-brand">
                            </div>
                        </div>

                        <div class="field">
                            <label for="doc_link_desc" class="font-bold text-sm uppercase tracking-wider text-slate-500 block mb-1">
                                {{ __('classroom.description') }}
                            </label>
                            <textarea id="doc_link_desc" name="description" placeholder="A short description for students (optional)" rows="2"
                                class="resize-none w-full text-sm bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 focus:border-brand focus:ring-1 focus:ring-brand"></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                            <button type="button" class="btn-sm-ghost cursor-pointer"
                                x-on:click="$dispatch('close-modal', 'modal-add-resource')">
                                {{ __('classroom.cancel') }}
                            </button>
                            <button type="submit" class="btn-sm-primary btn-px-16 shadow-xs font-semibold cursor-pointer">
                                {{ __('classroom.add_link') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </x-ui.modal>

        <!-- MODAL: LINK EXCLUSIVE PRACTICE TEST -->
        <x-ui.modal id="modal-link-test" title="Link Exclusive Practice Test" maxWidth="md" overflow="visible">
            <div class="form-note">
                <form method="POST" action="{{ route('teacher.classes.exclusive-tests.store', $classroom) }}" class="space-y-4">
                    @csrf
                    <div class="field">
                        <label for="ex_test_id" class="font-bold text-sm uppercase tracking-wider text-slate-500 block mb-1">Select Test</label>
                        <x-ui.select name="test_id" id="ex_test_id"
                            :options="$formattedTests"
                            placeholder="Select an active test"
                            required />
                        <span class="text-sm text-slate-400 mt-1.5 block">Students enrolled in this class can practice this test anytime from Class Materials.</span>
                    </div>

                    <div class="field">
                        <label for="ex_unit_name" class="font-bold text-sm uppercase tracking-wider text-slate-500 block mb-1">Assign to Study Unit (Optional)</label>
                        <input id="ex_unit_name" type="text" name="unit_name" placeholder="e.g. Unit 1: Advanced Math & Desmos" list="units-list"
                            class="w-full text-sm bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 focus:border-brand focus:ring-1 focus:ring-brand">
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" class="btn-sm-ghost cursor-pointer"
                            x-on:click="$dispatch('close-modal', 'modal-link-test')">
                            Cancel
                        </button>
                        <button type="submit" class="btn-sm-primary btn-px-16 shadow-xs font-semibold cursor-pointer">
                            Add to Class Unit
                        </button>
                    </div>
                </form>
            </div>
        </x-ui.modal>

        <!-- MODAL: ADD CO-TEACHER -->
        @can('manageTeam', $classroom)
            <x-ui.modal id="modal-add-co-teacher" title="{{ __('classroom.modal_add_co_teacher_title') }}" maxWidth="md" overflow="visible">
                <div x-data="{
                    query: '',
                    results: [],
                    selectedTeacher: null,
                    loading: false,
                    showDropdown: false,
                    highlightedIndex: -1,
                    existingIds: @js($classroom->coTeachers->pluck('id')->push($classroom->owner_id)->all()),
                    ownerId: {{ $classroom->owner_id }},
                    getInitials(name, email) {
                        const text = (name || email || '').trim();
                        if (!text) return '?';
                        const parts = text.split(/\s+/).filter(Boolean);
                        if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
                        return text.substring(0, 2).toUpperCase();
                    },
                    isExisting(id) {
                        return this.existingIds.includes(Number(id));
                    },
                    isOwner(id) {
                        return Number(id) === this.ownerId;
                    },
                    search() {
                        const q = this.query.trim();
                        if (q.length < 2) {
                            this.results = [];
                            this.showDropdown = false;
                            this.loading = false;
                            return;
                        }
                        this.loading = true;
                        fetch(`{{ route('home-dashboard.teachers.search') }}?q=${encodeURIComponent(q)}`)
                            .then(r => r.json())
                            .then(d => {
                                this.results = d.data || [];
                                this.showDropdown = true;
                                this.highlightedIndex = -1;
                            })
                            .catch(() => {
                                this.results = [];
                            })
                            .finally(() => {
                                this.loading = false;
                            });
                    },
                    select(teacher) {
                        if (this.isExisting(teacher.id)) return;
                        this.selectedTeacher = teacher;
                        this.query = '';
                        this.showDropdown = false;
                        this.results = [];
                    },
                    clearSelection() {
                        this.selectedTeacher = null;
                        this.query = '';
                        this.$nextTick(() => {
                            this.$refs.searchInput && this.$refs.searchInput.focus();
                        });
                    },
                    navigateDown() {
                        if (!this.showDropdown || !this.results.length) return;
                        this.highlightedIndex = (this.highlightedIndex + 1) % this.results.length;
                    },
                    navigateUp() {
                        if (!this.showDropdown || !this.results.length) return;
                        this.highlightedIndex = (this.highlightedIndex - 1 + this.results.length) % this.results.length;
                    },
                    selectHighlighted() {
                        if (this.highlightedIndex >= 0 && this.highlightedIndex < this.results.length) {
                            this.select(this.results[this.highlightedIndex]);
                        }
                    }
                }"
                @close-modal.window="if ($event.detail === 'modal-add-co-teacher') { clearSelection(); }">
                    <form method="POST" action="{{ route('teacher.classes.co-teachers.store', [$classroom, 'tab' => 'settings', 'subtab' => 'team']) }}">
                        @csrf
                        <input type="hidden" name="teacher_id" :value="selectedTeacher?.id || ''">
                        <input type="hidden" name="email" :value="selectedTeacher?.email || ''">

                        <!-- PHASE 1: SEARCH COMBOBOX -->
                        <div x-show="!selectedTeacher">
                            <label for="co_teacher_search" class="block text-sm font-semibold text-slate-700 mb-1.5">
                                {{ __('classroom.approved_teacher_label') }}
                            </label>

                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <x-ui.icon name="search" class="w-4 h-4" />
                                </div>

                                <input id="co_teacher_search"
                                    x-ref="searchInput"
                                    type="text"
                                    autocomplete="off"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    :aria-expanded="showDropdown && results.length > 0"
                                    aria-controls="teacher-search-results-list"
                                    placeholder="{{ __('classroom.search_teacher_placeholder') }}"
                                    x-model="query"
                                    @input.debounce.250ms="search()"
                                    @keydown.arrow-down.prevent="navigateDown()"
                                    @keydown.arrow-up.prevent="navigateUp()"
                                    @keydown.enter.prevent="selectHighlighted()"
                                    @keydown.escape="showDropdown = false"
                                    class="w-full pl-9 pr-9 py-2 text-sm bg-white border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:outline-hidden focus:border-brand focus:ring-1 focus:ring-brand shadow-2xs">

                                <!-- Loading spinner -->
                                <template x-if="loading">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <svg class="animate-spin h-3.5 w-3.5 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                    </div>
                                </template>

                                <!-- Clear query button -->
                                <template x-if="!loading && query.length > 0">
                                    <button type="button"
                                        @click="query = ''; results = []; showDropdown = false; $refs.searchInput.focus()"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 cursor-pointer"
                                        aria-label="Clear search">
                                        <x-ui.icon name="x-circle" class="w-3.5 h-3.5" />
                                    </button>
                                </template>

                                <!-- DROPDOWN RESULTS -->
                                <div id="teacher-search-results-list"
                                    role="listbox"
                                    x-show="showDropdown && (results.length > 0 || (!loading && query.trim().length >= 2))"
                                    @click.away="showDropdown = false"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    class="absolute left-0 right-0 top-full mt-1.5 bg-white border border-slate-200 rounded-xl shadow-lg max-h-56 overflow-y-auto z-50 divide-y divide-slate-100"
                                    x-cloak>

                                    <!-- Results list -->
                                    <template x-for="(t, index) in results" :key="t.id">
                                        <div role="option"
                                            :aria-selected="highlightedIndex === index"
                                            @click="select(t)"
                                            @mouseenter="highlightedIndex = index"
                                            :class="{
                                                'bg-slate-50': highlightedIndex === index && !isExisting(t.id),
                                                'opacity-50 bg-slate-50/50 cursor-not-allowed': isExisting(t.id),
                                                'cursor-pointer': !isExisting(t.id)
                                            }"
                                            class="p-2.5 flex items-center justify-between gap-3 transition-colors text-left">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <div class="w-7 h-7 rounded-full bg-brand/10 text-brand font-bold text-sm flex items-center justify-center flex-shrink-0"
                                                    x-text="getInitials(t.name, t.email)">
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-sm font-semibold text-slate-900 truncate" x-text="t.name || t.email"></div>
                                                    <div class="text-sm text-slate-500 font-mono truncate" x-text="t.email || ''"></div>
                                                </div>
                                            </div>

                                            <!-- Status badge if existing -->
                                            <template x-if="isExisting(t.id)">
                                                <span class="flex-shrink-0 px-2 py-0.5 rounded-full text-sm font-semibold"
                                                    :class="isOwner(t.id) ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'"
                                                    x-text="isOwner(t.id) ? 'Class Owner' : 'Already Added'">
                                                </span>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- Empty state when no matches -->
                                    <div x-show="!loading && results.length === 0 && query.trim().length >= 2" class="p-4 text-center text-sm text-slate-500">
                                        No approved teachers found matching "<span class="font-semibold text-slate-700" x-text="query"></span>"
                                    </div>
                                </div>
                            </div>

                            <p class="text-sm text-slate-500 mt-2">
                                Search by approved teacher's full name or email address.
                            </p>
                        </div>

                        <!-- PHASE 2: SELECTED TEACHER PREVIEW -->
                        <div x-show="selectedTeacher" x-cloak>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Selected Teacher</label>
                            <div class="bg-blue-50/40 border border-blue-200/70 rounded-xl p-3.5 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-full bg-brand text-white font-bold text-sm flex items-center justify-center flex-shrink-0 shadow-2xs"
                                        x-text="getInitials(selectedTeacher?.name, selectedTeacher?.email)">
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-bold text-slate-900 truncate" x-text="selectedTeacher?.name || selectedTeacher?.email"></div>
                                        <div class="text-sm text-slate-600 font-mono truncate" x-text="selectedTeacher?.email"></div>
                                        <div class="inline-flex items-center gap-1 text-sm font-semibold text-brand mt-0.5">
                                            <x-ui.icon name="shield-check" class="w-3 h-3" />
                                            <span>Will be granted co-teacher access</span>
                                        </div>
                                    </div>
                                </div>

                                <button type="button"
                                    @click="clearSelection()"
                                    class="text-sm font-semibold text-slate-600 hover:text-slate-900 bg-white border border-slate-200 hover:border-slate-300 px-2.5 py-1.5 rounded-lg transition-colors cursor-pointer shadow-2xs flex-shrink-0">
                                    Change
                                </button>
                            </div>
                        </div>

                        <!-- MODAL ACTIONS FOOTER -->
                        <div class="border-t border-slate-100 pt-4 mt-5 flex items-center justify-end gap-2">
                            <button type="button"
                                x-on:click="$dispatch('close-modal', 'modal-add-co-teacher')"
                                class="px-3.5 py-2 text-sm font-semibold text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                class="btn-sm-primary px-4 py-2 text-sm font-semibold shadow-xs cursor-pointer"
                                :disabled="!selectedTeacher"
                                :class="{ 'opacity-40 cursor-not-allowed': !selectedTeacher }">
                                {{ __('classroom.add_teacher') }}
                            </button>
                        </div>
                    </form>
                </div>
            </x-ui.modal>
        @endcan
    @endif

    @push('scripts')
        <script>
            function copyJoinCode(code, btn) {
                function feedback() {
                    if (!btn) return;
                    const original = btn.innerHTML;
                    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-emerald-600" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
                    setTimeout(() => {
                        btn.innerHTML = original;
                    }, 1200);
                }

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(code).then(feedback).catch(() => fallbackCopy());
                } else {
                    fallbackCopy();
                }

                function fallbackCopy() {
                    const textArea = document.createElement('textarea');
                    textArea.value = code;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-999999px';
                    textArea.style.top = '-999999px';
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        feedback();
                    } catch (e) {
                        console.error('Fallback copy failed', e);
                    }
                    textArea.remove();
                }
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
