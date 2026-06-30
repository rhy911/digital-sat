<x-layouts.student :user="auth()->user()" :title="$classroom->name" header-type="progress">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
    @endpush
    <div class="ds-teacher-workspace teacher-detail" x-data="{
        tabs: ['team', 'roster', 'documents', 'assignments'],
        activeTab: 'roster',
        init() {
            const hashTab = window.location.hash.replace('#', '');
            if (this.tabs.includes(hashTab)) this.activeTab = hashTab;
        },
        setTab(tab) {
            if (!this.tabs.includes(tab)) return;
            this.activeTab = tab;
            history.replaceState(null, '', `${window.location.pathname}${window.location.search}#${tab}`);
        },
        moveTab(direction) {
            const current = this.tabs.indexOf(this.activeTab);
            const next = (current + direction + this.tabs.length) % this.tabs.length;
            this.setTab(this.tabs[next]);
            this.$nextTick(() => this.$refs[`${this.activeTab}Tab`]?.focus());
        }
    }">
        @if (session('success'))
            <div class="class-alert class-alert--success" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="class-alert class-alert--error" role="alert">{{ $errors->first() }}</div>
        @endif
        @php
            $activeStudents = $classroom->active_memberships_count;
            $pendingStudents = $classroom->pending_memberships_count;
            $teacherCount = 1 + $classroom->co_teachers_count;
            $documentCount = $classroom->documents_count;
            $assignmentCount = $classroom->assignments_count;
        @endphp

        <a class="back-link" href="{{ route('teacher.classes.index') }}">Back to classes</a>
        <div class="class-command">
            <div class="class-command__main">
                <div class="class-title-row">
                    <span class="status-chip status-chip--{{ $classroom->status }}">{{ ucfirst($classroom->status) }}</span>
                    <h1>{{ $classroom->name }}</h1>
                </div>
                <p>{{ $classroom->description ?: 'Manage roster, resources, assignments, and class access.' }}</p>
                <dl class="class-command__metrics" aria-label="Class summary">
                    <div><dt>Students</dt><dd>{{ $activeStudents }}</dd></div>
                    <div><dt>Pending</dt><dd>{{ $pendingStudents }}</dd></div>
                    <div><dt>Teachers</dt><dd>{{ $teacherCount }}</dd></div>
                    <div><dt>Resources</dt><dd>{{ $documentCount }}</dd></div>
                    <div><dt>Assignments</dt><dd>{{ $assignmentCount }}</dd></div>
                </dl>
            </div>
            <div class="class-command__actions">
                <div class="join-code join-code--compact">
                    <span>Student join code</span>
                    <strong>{{ $classroom->join_code }}</strong>
                    @if ($classroom->status === 'active')
                        <label>Join link<input readonly
                                value="{{ route('student.classes.join-link', $classroom->join_code) }}"
                                onclick="this.select()"></label>
                    @endif
                </div>
                <div class="class-command__buttons">
                    @if ($classroom->status === 'active')
                        <form method="POST" action="{{ route('teacher.classes.rotate-code', $classroom) }}">
                            @csrf
                            <button class="class-button">Rotate code</button>
                        </form>
                    @endif
                    @can('manageTeam', $classroom)
                        @if ($classroom->status === 'active')
                            <form method="POST" action="{{ route('teacher.classes.archive', $classroom) }}"
                                data-confirm="Archive class and close published assignments?">
                                @csrf
                                <button class="class-button class-button--danger">Archive</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('teacher.classes.restore', $classroom) }}">
                                @csrf
                                <button class="class-button">Restore</button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
        @if ($classroom->status === 'active')
            <details class="create-disclosure create-disclosure--compact">
                <summary>Edit class details</summary>
                <form method="POST" action="{{ route('teacher.classes.update', $classroom) }}" class="inline-form">
                    @csrf @method('PUT')<label>Class name<input name="name" value="{{ $classroom->name }}"
                            required maxlength="150"></label><label>Description<input name="description"
                            value="{{ $classroom->description }}" maxlength="2000"></label><button
                        class="class-button class-button--primary">Save</button></form>
            </details>
        @endif
        <div class="class-tabs class-tabs--segmented" role="tablist" aria-label="Class sections"
            @keydown.left.prevent="moveTab(-1)" @keydown.right.prevent="moveTab(1)"
            @keydown.home.prevent="setTab(tabs[0]); $nextTick(() => $refs.teamTab.focus())"
            @keydown.end.prevent="setTab(tabs[tabs.length - 1]); $nextTick(() => $refs.assignmentsTab.focus())">
            <button x-ref="teamTab" type="button" role="tab" id="class-tab-team"
                aria-controls="class-panel-team" :aria-selected="activeTab === 'team' ? 'true' : 'false'"
                :tabindex="activeTab === 'team' ? 0 : -1" :class="{ 'is-active': activeTab === 'team' }"
                @click="setTab('team')">Teaching team <span>{{ $teacherCount }}</span></button>
            <button x-ref="rosterTab" type="button" role="tab" id="class-tab-roster"
                aria-controls="class-panel-roster" :aria-selected="activeTab === 'roster' ? 'true' : 'false'"
                :tabindex="activeTab === 'roster' ? 0 : -1" :class="{ 'is-active': activeTab === 'roster' }"
                @click="setTab('roster')">Roster <span>{{ $activeStudents }}</span></button>
            <button x-ref="documentsTab" type="button" role="tab" id="class-tab-documents"
                aria-controls="class-panel-documents" :aria-selected="activeTab === 'documents' ? 'true' : 'false'"
                :tabindex="activeTab === 'documents' ? 0 : -1" :class="{ 'is-active': activeTab === 'documents' }"
                @click="setTab('documents')">Documents <span>{{ $documentCount }}</span></button>
            <button x-ref="assignmentsTab" type="button" role="tab" id="class-tab-assignments"
                aria-controls="class-panel-assignments" :aria-selected="activeTab === 'assignments' ? 'true' : 'false'"
                :tabindex="activeTab === 'assignments' ? 0 : -1" :class="{ 'is-active': activeTab === 'assignments' }"
                @click="setTab('assignments')">Assignments <span>{{ $assignmentCount }}</span></button>
        </div>
        <section id="class-panel-team" class="class-panel class-tab-panel" role="tabpanel"
            aria-labelledby="class-tab-team" x-show="activeTab === 'team'" x-cloak>
            <div class="section-heading section-heading--tight class-section-bar">
                <div>
                    <h2>Teaching team</h2>
                    <p>Owners and co-teachers can manage daily class work. Owners keep team and archive control.</p>
                </div>
                <span>{{ $teacherCount }} total</span>
            </div>
            <div class="teacher-team-list">
                <div class="teacher-team-row">
                    <div>
                        <strong>{{ $classroom->owner->name }}</strong><span>{{ $classroom->owner->email }}</span>
                    </div>
                    <span class="status-chip">Owner</span>
                </div>
                @foreach ($classroom->coTeachers as $teacher)
                    <div class="teacher-team-row">
                        <div>
                            <strong>{{ $teacher->name }}</strong><span>{{ $teacher->email }}</span>
                        </div>
                        <div class="row-actions">
                            <span class="status-chip">Co-teacher</span>
                            @can('manageTeam', $classroom)
                                @if ($classroom->status === 'active')
                                    <form method="POST"
                                        action="{{ route('teacher.classes.co-teachers.destroy', [$classroom, $teacher]) }}"
                                        data-confirm="Remove this co-teacher from the class?">
                                        @csrf @method('DELETE')
                                        <button class="text-button text-button--danger">Remove</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
            @can('manageTeam', $classroom)
                @if ($classroom->status === 'active')
                    <details class="create-disclosure create-disclosure--compact">
                        <summary>Add co-teacher</summary>
                        <form method="POST" action="{{ route('teacher.classes.co-teachers.store', $classroom) }}"
                            class="inline-form inline-form--compact">
                            @csrf
                            <label class="teacher-search">Approved teacher
                                <input id="coTeacherSearch" type="search" name="email" required
                                    placeholder="Search name or email" autocomplete="off">
                                <input id="coTeacherId" type="hidden" name="teacher_id">
                                <div id="coTeacherSearchResults" class="teacher-search-results" role="listbox" hidden></div>
                            </label>
                            <button class="class-button class-button--primary">Add co-teacher</button>
                        </form>
                    </details>
                @endif
            @endcan
        </section>
        <section id="class-panel-roster" class="class-panel class-tab-panel" role="tabpanel"
            aria-labelledby="class-tab-roster" x-show="activeTab === 'roster'" x-cloak>
            <div class="section-heading class-section-bar">
                <div>
                    <h2>Roster</h2>
                    <p>{{ $activeStudents }} active students{{ $pendingStudents ? ' · '.$pendingStudents.' pending' : '' }}</p>
                </div>
                <span>{{ $classroom->memberships->count() }} memberships</span>
            </div>
            @php
                $pending = $classroom->memberships->where('status', 'pending');
            @endphp
            @if ($pending->isNotEmpty())
                <div class="pending-block pending-block--priority">
                    <h3>Pending requests <span>{{ $pending->count() }}</span></h3>
                    @foreach ($pending as $membership)
                        <div class="roster-row">
                            <div>
                                <strong>{{ $membership->student->name }}</strong><span>{{ $membership->student->email }}</span>
                            </div>
                            @if ($classroom->status === 'active')
                                <div class="row-actions">
                                    <form method="POST"
                                        action="{{ route('teacher.memberships.approve', $membership) }}"
                                        onsubmit="const btn = this.querySelector('button'); btn.disabled = true; btn.innerText = 'Approving...';">@csrf<button
                                            class="class-button class-button--primary">Approve</button></form>
                                    <form method="POST"
                                        action="{{ route('teacher.memberships.reject', $membership) }}"
                                        onsubmit="const btn = this.querySelector('button'); btn.disabled = true; btn.innerText = 'Rejecting...';">@csrf<button
                                            class="class-button">Reject</button></form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="list-heading">
                <span>Active students</span>
                <span>Actions</span>
            </div>
            @forelse($classroom->memberships->where('status', 'active') as $membership)
                <div class="roster-row">
                    <div>
                        <strong>{{ $membership->student->name }}</strong><span>{{ $membership->student->email }}</span>
                    </div>
                    @if ($classroom->status === 'active')
                        <form method="POST" action="{{ route('teacher.memberships.remove', $membership) }}"
                            data-confirm="Remove this student? Result history will remain.">@csrf<button
                                class="text-button text-button--danger">Remove</button></form>
                    @endif
                </div>
            @empty <div class="class-empty class-empty--compact">
                    <p>No active students. Share code <strong>{{ $classroom->join_code }}</strong>.</p>
                </div>
            @endforelse
        </section>
        <section id="class-panel-documents" class="class-panel class-tab-panel" role="tabpanel"
            aria-labelledby="class-tab-documents" x-show="activeTab === 'documents'" x-cloak>
            <div class="section-heading class-section-bar">
                <div>
                    <h2>Study documents</h2>
                    <p>Share reference files and links with active students in this class.</p>
                </div>
                <span>{{ $documentCount }} resources</span>
            </div>
            <div class="resource-console">
                <div class="resource-list">
                    @forelse($classroom->documents->sortByDesc('created_at') as $document)
                        @php
                            $ext = $document->isFile() ? strtolower(pathinfo($document->original_name ?? '', PATHINFO_EXTENSION)) : '';
                        @endphp
                        <div class="document-row">
                            <div class="document-row__left">
                                <div class="document-row__icon-container document-row__icon--{{ $document->isFile() ? ($ext ?: 'file') : 'link' }}">
                                    @if ($document->isFile())
                                        @if ($ext === 'pdf')
                                            <!-- PDF icon -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                                        @elseif (in_array($ext, ['doc', 'docx']))
                                            <!-- Word doc icon -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v9.5"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10.4 12.6a2 2 0 1 1 2.8 2.8L6 22l-3 1 1-3Z"/></svg>
                                        @elseif (in_array($ext, ['ppt', 'pptx']))
                                            <!-- PPT/Presentation icon -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="m7 21 5-5 5 5"/></svg>
                                        @elseif (in_array($ext, ['xls', 'xlsx']))
                                            <!-- Spreadsheet icon -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 12h8"/><path d="M8 16h8"/><path d="M12 12v8"/></svg>
                                        @elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'webp']))
                                            <!-- Image icon -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                        @else
                                            <!-- Generic document file icon -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                                        @endif
                                    @else
                                        <!-- External Web link icon -->
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                    @endif
                                </div>
                                <div class="document-row__details">
                                    <div class="document-row__header">
                                        <strong class="document-row__title">{{ $document->title }}</strong>
                                        @if ($document->isFile())
                                            <span class="document-row__badge document-row__badge--file">{{ strtoupper($ext ?: 'file') }}</span>
                                        @else
                                            <span class="document-row__badge document-row__badge--link">LINK</span>
                                        @endif
                                    </div>
                                    @if ($document->description)
                                        <p class="document-row__description">{{ $document->description }}</p>
                                    @endif
                                    <div class="document-row__meta">
                                        @if ($document->isFile())
                                            <span class="document-row__meta-item" title="Filename: {{ $document->original_name }}">{{ Str::limit($document->original_name, 28) }}</span>
                                            @if ($document->displaySize())
                                                <span class="document-row__meta-dot">&middot;</span>
                                                <span class="document-row__meta-item">{{ $document->displaySize() }}</span>
                                            @endif
                                        @else
                                            <span class="document-row__meta-item">{{ parse_url($document->external_url ?? '', PHP_URL_HOST) }}</span>
                                        @endif
                                        <span class="document-row__meta-dot">&middot;</span>
                                        <span class="document-row__meta-item">Shared {{ $document->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="row-actions document-row__actions" role="group" aria-label="Document actions">
                                @if ($document->isFile())
                                    <a class="document-action document-action--primary" href="{{ route('class-documents.open', $document) }}" target="_blank"
                                        rel="noopener">
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M15 3h6v6" />
                                            <path d="M10 14 21 3" />
                                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                                        </svg>
                                        <span>Open</span>
                                    </a>
                                    <a class="document-action document-action--icon" href="{{ route('class-documents.download', $document) }}"
                                        aria-label="Download {{ $document->title }}" title="Download">
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                            <path d="M7 10l5 5 5-5" />
                                            <path d="M12 15V3" />
                                        </svg>
                                    </a>
                                @else
                                    <a class="document-action document-action--primary" href="{{ $document->external_url }}" target="_blank"
                                        rel="noopener noreferrer">
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M15 3h6v6" />
                                            <path d="M10 14 21 3" />
                                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                                        </svg>
                                        <span>Open</span>
                                    </a>
                                @endif
                                @if ($classroom->status === 'active')
                                    <form method="POST" action="{{ route('teacher.classes.documents.destroy', [$classroom, $document]) }}"
                                        data-confirm="Remove this study document?">
                                        @csrf @method('DELETE')
                                        <button class="document-action document-action--icon document-action--danger"
                                            aria-label="Remove {{ $document->title }}" title="Remove">
                                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 6h18" />
                                                <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                                <path d="M10 11v6" />
                                                <path d="M14 11v6" />
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="document-empty-state">
                            <div class="document-empty-state__graphic">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><circle cx="9" cy="13" r="1"/><circle cx="15" cy="13" r="1"/><path d="M8 17s1 1.5 4 1.5 4-1.5 4-1.5"/></svg>
                            </div>
                            <h3>No study documents</h3>
                            <p>Upload worksheets, formulas, or link online reading materials to help students prepare for the exam.</p>
                        </div>
                    @endforelse
                </div>
                @if ($classroom->status === 'active')
                    <aside class="resource-actions" x-data="{ activeForm: 'file' }" aria-label="Add study resource">
                        <div class="resource-panel-header">
                            <h3>Add resource</h3>
                            <p>Share files or external links with your class.</p>
                        </div>
                        
                        <div class="resource-tab-toggle">
                            <button type="button" 
                                :class="{ 'toggle-active': activeForm === 'file' }" 
                                x-on:click="activeForm = 'file'">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                File
                            </button>
                            <button type="button" 
                                :class="{ 'toggle-active': activeForm === 'link' }" 
                                x-on:click="activeForm = 'link'">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                Link
                            </button>
                        </div>

                        <!-- Upload File form -->
                        <div x-show="activeForm === 'file'" class="fade-enter">
                            <form method="POST" action="{{ route('teacher.classes.documents.store', $classroom) }}"
                                class="resource-form" enctype="multipart/form-data"
                                onsubmit="const btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerText = 'Uploading...';">
                                @csrf
                                <input type="hidden" name="source_type" value="file">
                                
                                <div class="form-group">
                                    <label for="doc-title-file">Title</label>
                                    <input type="text" id="doc-title-file" name="title" required maxlength="180" placeholder="e.g. SAT Reading Tips">
                                </div>
                                
                                <div class="form-group" x-data="{ fileName: '' }">
                                    <label for="doc-file-upload">Choose File</label>
                                    <div class="file-dropzone-wrapper">
                                        <input type="file" id="doc-file-upload" name="document_file" required
                                            accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.png,.jpg,.jpeg,.webp"
                                            x-on:change="fileName = $event.target.files[0]?.name">
                                        <div class="file-dropzone-custom">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m15 15-3-3-3 3"/></svg>
                                            <span x-text="fileName || 'Click to browse files'">Click to browse files</span>
                                            <small>PDF, Word, PPT, Excel, Images up to 10MB</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="doc-desc-file">Description</label>
                                    <textarea id="doc-desc-file" name="description" rows="2" maxlength="2000" placeholder="A short description of this document (optional)"></textarea>
                                </div>
                                
                                <button type="submit" class="class-button class-button--primary resource-submit-btn">Upload Resource</button>
                            </form>
                        </div>

                        <!-- Add Link form -->
                        <div x-show="activeForm === 'link'" class="fade-enter" x-cloak>
                            <form method="POST" action="{{ route('teacher.classes.documents.store', $classroom) }}"
                                class="resource-form"
                                onsubmit="const btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerText = 'Adding...';">
                                @csrf
                                <input type="hidden" name="source_type" value="link">
                                
                                <div class="form-group">
                                    <label for="doc-title-link">Title</label>
                                    <input type="text" id="doc-title-link" name="title" required maxlength="180" placeholder="e.g. Desmos Online Practice">
                                </div>
                                
                                <div class="form-group">
                                    <label for="doc-url-link">URL</label>
                                    <input type="url" id="doc-url-link" name="external_url" required maxlength="2000" placeholder="https://example.com/resource">
                                </div>
                                
                                <div class="form-group">
                                    <label for="doc-desc-link">Description</label>
                                    <textarea id="doc-desc-link" name="description" rows="2" maxlength="2000" placeholder="A short description of this link (optional)"></textarea>
                                </div>
                                
                                <button type="submit" class="class-button class-button--primary resource-submit-btn">Add Link</button>
                            </form>
                        </div>
                    </aside>
                @endif
            </div>
        </section>
        <section id="class-panel-assignments" class="class-panel class-tab-panel" role="tabpanel"
            aria-labelledby="class-tab-assignments" x-show="activeTab === 'assignments'" x-cloak>
            <div class="section-heading class-section-bar">
                <div>
                    <h2>Assignments</h2>
                    <p>Publish an owned or shared active test to everyone in this class.</p>
                </div>
                <span>{{ $assignmentCount }} total</span>
            </div>
            @if ($classroom->status === 'active')
                <details class="create-disclosure create-disclosure--compact action-disclosure">
                    <summary>Create assignment</summary>
                    <form method="POST" action="{{ route('teacher.assignments.store', $classroom) }}"
                        class="form-grid">@csrf
                        <label>Test<select name="test_id" required>
                                <option value="">Select an active test</option>
                                @foreach ($tests as $test)
                                    @php($isShared = auth()->user()->role !== 'admin' && $test->created_by !== auth()->id())
                                    <option value="{{ $test->id }}">
                                        {{ $test->title }}{{ $isShared ? ' (shared)' : '' }}{{ $test->isContentLocked() ? ' (locked)' : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Assignment title<input name="title" required maxlength="180"></label>
                        <label class="span-2">Instructions
                            <textarea name="instructions" rows="3"></textarea>
                        </label>
                        <fieldset class="assignment-window span-2">
                            <legend>Assignment window</legend>
                            <p class="form-hint" id="assignment-window-help">
                                Times use Asia/Ho_Chi_Minh. Leave available empty to open now, or due empty for no deadline.
                            </p>
                            <div class="assignment-window__grid">
                                <label class="time-field" for="assignment_available_at">
                                    <span class="time-field__label">Available from</span>
                                    <span class="time-field__control">
                                        <input id="assignment_available_at" type="text"
                                            class="datetime-picker time-field__input" name="available_at"
                                            placeholder="Open immediately" autocomplete="off"
                                            aria-describedby="assignment-window-help assignment_available_hint"
                                            data-window-role="start" data-window-pair="assignment_due_at">
                                    </span>
                                    <span class="time-field__hint" id="assignment_available_hint">Students can start after this time.</span>
                                </label>
                                <label class="time-field" for="assignment_due_at">
                                    <span class="time-field__label">Due at</span>
                                    <span class="time-field__control">
                                        <input id="assignment_due_at" type="text"
                                            class="datetime-picker time-field__input" name="due_at"
                                            placeholder="No deadline" autocomplete="off"
                                            aria-describedby="assignment-window-help assignment_due_hint"
                                            data-window-role="end" data-window-pair="assignment_available_at">
                                    </span>
                                    <span class="time-field__hint" id="assignment_due_hint">Due time must be after availability.</span>
                                </label>
                            </div>
                        </fieldset>
                        <label>Attempt limit<input type="number" name="attempt_limit" min="1" max="10"
                                value="1" required></label>
                        <div class="form-action"><button class="class-button class-button--primary">Create assignment</button>
                        </div>
                    </form>
                </details>
            @endif
            <div class="list-heading">
                <span>Assignment</span>
                <span>Window</span>
            </div>
            @forelse($classroom->assignments->sortByDesc('created_at') as $assignment)
                <a class="assignment-row" wire:navigate href="{{ route('teacher.assignments.show', $assignment) }}">
                    <div><span
                            class="status-chip status-chip--{{ $assignment->status }}">{{ ucfirst($assignment->status) }}</span><strong>{{ $assignment->title }}</strong><span>{{ $assignment->test->title }}</span>
                    </div>
                    <div><span>{{ $assignment->attempt_limit }}
                            attempt{{ $assignment->attempt_limit === 1 ? '' : 's' }}</span><span>{{ $assignment->due_at?->format('M j, g:i A') ?: 'No due time' }}</span>
                    </div>
                </a>
            @empty <div class="class-empty class-empty--compact">
                    <p>No assignments. Create a draft when your test is ready.</p>
                </div>
            @endforelse
        </section>
    </div>
    @push('scripts')
        <script>
            (() => {
                const initCoTeacherSearch = () => {
                    const input = document.getElementById('coTeacherSearch');
                    const hidden = document.getElementById('coTeacherId');
                    const results = document.getElementById('coTeacherSearchResults');
                    if (!input || !hidden || !results || input.dataset.searchReady === 'true') return;
                    input.dataset.searchReady = 'true';

                    const endpoint = @json(route('home-dashboard.teachers.search'));
                    let timer = null;

                    const clearResults = () => {
                        results.innerHTML = '';
                        results.hidden = true;
                    };

                    const renderResults = (teachers) => {
                        results.innerHTML = '';
                        if (!teachers.length) {
                            const empty = document.createElement('div');
                            empty.className = 'teacher-search-empty';
                            empty.textContent = 'No approved teachers found.';
                            results.appendChild(empty);
                            results.hidden = false;
                            return;
                        }

                        teachers.forEach((teacher) => {
                            const option = document.createElement('button');
                            option.type = 'button';
                            option.className = 'teacher-search-option';
                            option.setAttribute('role', 'option');
                            option.innerHTML = `<strong></strong><span></span>`;
                            option.querySelector('strong').textContent = teacher.name || teacher.email;
                            option.querySelector('span').textContent = teacher.email || '';
                            option.addEventListener('click', () => {
                                hidden.value = teacher.id;
                                input.value = teacher.email || teacher.name || '';
                                clearResults();
                            });
                            results.appendChild(option);
                        });
                        results.hidden = false;
                    };

                    input.addEventListener('input', () => {
                        hidden.value = '';
                        window.clearTimeout(timer);
                        const q = input.value.trim();
                        if (q.length < 2) {
                            clearResults();
                            return;
                        }

                        timer = window.setTimeout(async () => {
                            const response = await fetch(`${endpoint}?q=${encodeURIComponent(q)}`, {
                                headers: { 'Accept': 'application/json' },
                            });
                            if (!response.ok) {
                                clearResults();
                                return;
                            }
                            const data = await response.json();
                            renderResults(data.data || []);
                        }, 220);
                    });

                    document.addEventListener('click', (event) => {
                        if (!results.contains(event.target) && event.target !== input) clearResults();
                    });
                };

                document.addEventListener('DOMContentLoaded', initCoTeacherSearch);
                document.addEventListener('livewire:navigated', initCoTeacherSearch);
            })();
        </script>
    @endpush
</x-layouts.student>
