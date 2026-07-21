<div id="teacher-workspace-content" class="teacher-workspace-panel">
    @if ($section === 'classes')
        <div class="flex flex-col gap-3" wire:loading.flex
            wire:target="showSection,showClassStatus,gotoPage,previousPage,nextPage" role="status"
            aria-label="Updating workspace">
            @for ($i = 0; $i < 3; $i++)
                <div class="rounded-xl border border-slate-200 p-4 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <x-ui.skeleton class="h-5 w-1/3" />
                        <x-ui.skeleton class="h-5 w-16 rounded-full" />
                    </div>
                    <div class="grid grid-cols-4 gap-3">
                        <x-ui.skeleton class="h-8 w-full" />
                        <x-ui.skeleton class="h-8 w-full" />
                        <x-ui.skeleton class="h-8 w-full" />
                        <x-ui.skeleton class="h-8 w-full" />
                    </div>
                </div>
            @endfor
        </div>
    @else
        <div class="flex flex-col gap-2" wire:loading.flex
            wire:target="showSection,showClassStatus,gotoPage,previousPage,nextPage" role="status"
            aria-label="Updating workspace">
            <x-ui.skeleton count="5" class="h-14 w-full rounded-lg" />
        </div>
    @endif

    <div class="binder-panel">
        @if ($section === 'classes')
            <div class="ledger-header">
                <div class="dh-left">
                    <h2>Classes</h2>
                    <div class="dh-desc">Organize students, assign SAT tests, and follow results.</div>
                </div>
            </div>

            @if (auth()->user()->role === 'teacher')
                <div class="settings-card mb-16" aria-labelledby="create-class-title">
                    <h3 class="settings-title lg" id="create-class-title">Create a class</h3>
                    <p class="settings-help lg">Start with a clear class name. You can share its join code after
                        creation.</p>
                    <form method="POST" action="{{ route('teacher.classes.store') }}"
                        class="flex flex-col gap-4 max-w-420">
                        @csrf
                        <label class="form-field-label">Class name
                            <input name="name" required maxlength="150" placeholder="SAT Prep - Summer"
                                class="settings-input w-full mt-4">
                        </label>
                        <label class="form-field-label">Description
                            <input name="description" maxlength="2000" placeholder="Optional context for students"
                                class="settings-input w-full mt-4">
                        </label>
                        <button type="submit" class="btn-sm-primary btn-px-16 self-start flex-none w-auto">Create
                            class</button>
                    </form>
                </div>
            @endif

            <div class="class-tabs" role="tablist" aria-label="Class status" x-data
                @keydown.left.prevent="$refs.activeTab.click(); $refs.activeTab.focus()"
                @keydown.right.prevent="$refs.archivedTab.click(); $refs.archivedTab.focus()">
                <button x-ref="activeTab" type="button" role="tab" class="seg-btn"
                    wire:click="showClassStatus('active')" wire:loading.attr="disabled" wire:target="showClassStatus"
                    aria-selected="{{ $classStatus === 'active' ? 'true' : 'false' }}"
                    @class(['active' => $classStatus === 'active'])>Active</button>
                <button x-ref="archivedTab" type="button" role="tab" class="seg-btn"
                    wire:click="showClassStatus('archived')" wire:loading.attr="disabled" wire:target="showClassStatus"
                    aria-selected="{{ $classStatus === 'archived' ? 'true' : 'false' }}"
                    @class(['active' => $classStatus === 'archived'])>Archived</button>
            </div>

            <div class="sp-head">
                <div>
                    <p>{{ auth()->user()->role === 'admin' ? 'All ' . $classStatus . ' classes' : 'Your ' . $classStatus . ' classes' }}
                        &bull; {{ $classes->total() }} total</p>
                </div>
            </div>

            @if ($classes->isEmpty())
                <div class="empty-grid-cell">
                    No {{ $classStatus }} classes.
                    {{ $classStatus === 'active' ? 'Create your first class above, then share its join code with students.' : 'Archived classes remain here with their complete history.' }}
                </div>
            @else
                <div class="assignments-grid">
                    @foreach ($classes as $classroom)
                        <a class="index-card" href="{{ route('teacher.classes.show', $classroom) }}">
                            <div class="card-title">{{ $classroom->name }}</div>
                            <div class="card-sub">
                                {{ auth()->user()->role === 'admin' ? 'Owner: ' . $classroom->owner->name : ($classroom->description ?: 'No description') }}
                            </div>
                            <div class="card-meta">
                                <span class="type-tag">{{ $classroom->active_memberships_count }} students</span>
                                <span class="status-pill {{ $classroom->status === 'active' ? 'ok' : 'pending' }}">
                                    <span class="d"></span>{{ ucfirst($classroom->status) }}
                                </span>
                            </div>
                            <div class="card-meta card-meta-divider">
                                <span>{{ $classroom->pending_memberships_count }} pending</span>
                                <span>{{ $classroom->assignments_count }} assignments</span>
                                <span class="mono text-faint text-2xs">{{ $classroom->join_code }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
                @if ($classes->hasPages())
                    <div class="mt-12">{{ $classes->links() }}</div>
                @endif
            @endif
        @else
            <div class="ledger-header">
                <div class="dh-left">
                    <h2>Assignments &amp; reports</h2>
                    <div class="dh-desc">Track work across every class and open detailed student results.</div>
                </div>
            </div>

            <div class="assignments-grid">
                @forelse($assignments as $assignment)
                    <a class="index-card"
                        href="{{ route('teacher.assignments.show', ['assignment' => $assignment, 'from' => 'workspace']) }}">
                        <div class="card-due">
                            {{ $assignment->due_at ? $assignment->due_at->format('D, M j') : 'No due time' }}
                        </div>
                        <div class="card-title">{{ $assignment->title }}</div>
                        <div class="card-sub">{{ $assignment->classroom->name }} &middot;
                            {{ $assignment->test->title }}
                        </div>
                        <div class="card-meta">
                            <span
                                class="status-pill {{ in_array($assignment->status, ['published', 'completed']) ? 'ok' : 'pending' }}">
                                <span class="d"></span>{{ ucfirst($assignment->status) }}
                            </span>
                        </div>
                        <div class="card-meta card-meta-divider">
                            <span>{{ $assignment->recipients_count }} students</span>
                            <span>{{ $assignment->attempts_count }} attempts</span>
                        </div>
                    </a>
                @empty
                    <div class="empty-grid-cell">
                        No assignments yet. Open a class and create its first assignment.
                        <button type="button" class="btn-sm-primary mt-12" wire:click="showSection('classes')"
                            wire:loading.attr="disabled" wire:target="showSection">Open classes</button>
                    </div>
                @endforelse
            </div>
            @if ($assignments->hasPages())
                <div class="mt-12">{{ $assignments->links() }}</div>
            @endif
        @endif
    </div>
</div>
