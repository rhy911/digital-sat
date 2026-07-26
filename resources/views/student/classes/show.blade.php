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
        activeTab: new URLSearchParams(location.search).get('tab')
            || (new URLSearchParams(location.search).get('announce_page') ? 'announce' : null)
            || (new URLSearchParams(location.search).get('docs_page') ? 'docs' : null)
            || (new URLSearchParams(location.search).get('mates_page') ? 'classmates' : null)
            || 'announce'
    }">

        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="route('home')" :avatar-label="$userInitials"
            :items="\App\Support\NavRail::student('classes')" />

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
                        <h2>{{ $classroom->name }} <span class="status-pill ok ml-2"><span class="d"></span>{{ ucfirst($classroom->status) }}</span></h2>
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
                    ['key' => 'announce', 'label' => 'Announcements', 'count' => $announcementsPage->total()],
                    ['key' => 'calendar', 'label' => 'Calendar', 'count' => $classroom->events->count()],
                    ['key' => 'assign', 'label' => __('classroom.tab_assignments'), 'count' => $classroom->assignments->count()],
                    ['key' => 'docs', 'label' => __('classroom.tab_documents'), 'count' => $classroom->documents_count],
                    ['key' => 'team', 'label' => __('classroom.tab_team'), 'count' => 1 + $classroom->coTeachers->count()],
                    ['key' => 'classmates', 'label' => __('classroom.tab_classmates'), 'count' => $activeMembers->count()],
                    ['key' => 'settings', 'label' => __('classroom.tab_settings')],
                ]" />

                <!-- CALENDAR TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'calendar' }">
                    <x-shell.class-calendar :items="$calendarItems" :can-manage="false" />
                </div>

                <!-- ANNOUNCEMENTS TAB PANEL -->
                <div class="section-panel" :class="{ 'active': activeTab === 'announce' }">
                    @forelse ($announcementsPage as $announcement)
                        <article class="announce-card {{ $announcement->pinned ? 'is-pinned' : '' }}">
                            <div class="announce-card__head">
                                <div class="announce-card__meta">
                                    <span class="announce-avatar">{{ substr($announcement->author?->name ?? 'T', 0, 1) }}</span>
                                    <div>
                                        <div class="announce-card__author">{{ $announcement->author?->name ?? 'Teacher' }}</div>
                                        <div class="announce-card__time">{{ $announcement->created_at->diffForHumans() }}</div>
                                    </div>
                                    @if ($announcement->pinned)
                                        <span class="announce-pin-badge">📌 Pinned</span>
                                    @endif
                                </div>
                            </div>
                            <div class="announce-card__body">{!! nl2br(e($announcement->body)) !!}</div>

                            <div class="announce-comments">
                                <div class="announce-comment-list">
                                    @foreach ($announcement->comments as $comment)
                                        <div class="announce-comment">
                                            <div class="announce-comment__left">
                                                <span class="announce-comment__avatar">{{ substr($comment->author?->name ?? 'U', 0, 1) }}</span>
                                                <div class="announce-comment__content">
                                                    <span class="announce-comment__author">{{ $comment->author?->name ?? 'User' }}</span>
                                                    @if(in_array($comment->author_id, [$classroom->owner_id, ...$classroom->coTeachers->pluck('user_id')->all()], true))
                                                        <span class="announce-comment__role-tag">Teacher</span>
                                                    @endif
                                                    <span class="announce-comment__body">{{ $comment->body }}</span>
                                                    <div class="announce-comment__time">{{ $comment->created_at->diffForHumans() }}</div>
                                                </div>
                                            </div>
                                            @if($comment->author_id === auth()->id())
                                                <div class="announce-comment__actions">
                                                    <button type="button" @click="$dispatch('open-confirm-delete', {
                                                        title: 'Delete Comment?',
                                                        message: 'Are you sure you want to delete your comment?',
                                                        actionUrl: '{{ route('announcements.comments.destroy', [$classroom, $announcement, $comment]) }}'
                                                    })" class="announce-comment-del-btn" title="Delete your comment">&times;</button>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                @if ($classroom->status === 'active')
                                    <form method="POST"
                                        action="{{ route('announcements.comments.store', [$classroom, $announcement]) }}"
                                        class="announce-comment-form" x-data="{ commentText: '' }" autocomplete="off">
                                        @csrf
                                        <input type="text" name="body" maxlength="2000" required x-model="commentText" autocomplete="off"
                                            placeholder="Write a comment…" class="announce-comment-input">
                                        <button type="submit" class="btn-sm-primary btn-compact announce-send-btn" :disabled="!commentText.trim()" :class="{ 'is-active': commentText.trim().length > 0 }">Send</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="empty-grid-cell" style="margin-top: 8px;">
                            <strong>No announcements yet</strong>
                            <p style="margin-top: 4px;">Updates from your teaching team will appear here.</p>
                        </div>
                    @endforelse

                    @if ($announcementsPage->hasPages())
                        <div style="margin-top: 12px;">{{ $announcementsPage->links() }}</div>
                    @endif
                </div>

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
                            <a href="{{ route('student.assignments.show', $assignment) }}" class="index-card text-inherit no-underline">
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
                                <div class="card-meta card-meta-divider" @click.stop>
                                    @if ($status['canStart'])
                                        <button type="button" @click.prevent.stop="$dispatch('open-ready-modal', {
                                            title: '{{ $status['inProgress'] ? 'Ready to Resume Attempt?' : 'Ready to Start Attempt?' }}',
                                            testTitle: '{{ addslashes($assignment->title) }}',
                                            subtitle: '{{ addslashes($assignment->test->title . $assignment->sectionSuffix() . ' • Classroom: ' . $classroom->name) }}',
                                            actionRoute: '{{ route('student.assignments.start', $assignment) }}',
                                            inProgress: {{ $status['inProgress'] ? 'true' : 'false' }},
                                            attemptInfo: 'Attempt {{ $status['used'] + ($status['inProgress'] ? 0 : 1) }} of {{ $assignment->attempt_limit }}'
                                        })" class="btn-sm-primary btn-compact flex-none w-auto">
                                            {{ $status['inProgress'] ? 'Resume' : 'Start attempt' }}
                                        </button>
                                    @elseif ($status['state'] === 'Completed')
                                        <span class="link-action">View results</span>
                                    @else
                                        <span class="text-xs text-muted">No attempts left</span>
                                    @endif
                                </div>
                            </a>
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
                @if (filled($classroom->note?->body))
                    <div class="cork-note">
                        <h3>{{ __('classroom.teacher_note_header') }}</h3>
                        <p class="digest-line whitespace-pre-wrap">{{ $classroom->note->body }}</p>
                    </div>
                @endif

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

                @if (!filled($classroom->note?->body))
                    <div x-show="activeTab === 'settings'" x-cloak class="cork-note">
                        <h3>{{ __('classroom.teacher_note_header') }}</h3>
                        <p class="cork-empty">{{ __('classroom.no_note_yet') }}</p>
                    </div>
                @endif
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

    <x-ui.ready-modal />
    <x-ui.confirm-delete-modal />
</x-layouts.student>
