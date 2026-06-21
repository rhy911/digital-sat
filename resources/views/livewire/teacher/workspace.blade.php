<div id="teacher-workspace-content" class="teacher-workspace-panel">
    <div class="workspace-loading" wire:loading.flex wire:target="showSection,showClassStatus,gotoPage,previousPage,nextPage" role="status">
        Updating workspace...
    </div>

    @if($section === 'classes')
        <div class="page-heading"><div><h1>Classes</h1><p>Organize students, assign SAT tests, and follow results.</p></div></div>

        @if(auth()->user()->role === 'teacher')
            <section class="class-panel class-create" aria-labelledby="create-class-title">
                <div class="class-create__intro"><h2 id="create-class-title">Create a class</h2><p>Start with a clear class name. You can share its join code after creation.</p></div>
                <form method="POST" action="{{ route('teacher.classes.store') }}" class="inline-form">
                    @csrf
                    <label>Class name<input name="name" required maxlength="150" placeholder="SAT Prep - Summer"></label>
                    <label>Description<input name="description" maxlength="2000" placeholder="Optional context for students"></label>
                    <button type="submit" class="class-button class-button--primary">Create class</button>
                </form>
            </section>
        @endif

        <div class="class-tabs" role="tablist" aria-label="Class status" x-data @keydown.left.prevent="$refs.activeTab.click(); $refs.activeTab.focus()" @keydown.right.prevent="$refs.archivedTab.click(); $refs.archivedTab.focus()">
            <button x-ref="activeTab" type="button" role="tab" wire:click="showClassStatus('active')" wire:loading.attr="disabled" wire:target="showClassStatus" aria-selected="{{ $classStatus === 'active' ? 'true' : 'false' }}" @class(['is-active' => $classStatus === 'active'])>Active</button>
            <button x-ref="archivedTab" type="button" role="tab" wire:click="showClassStatus('archived')" wire:loading.attr="disabled" wire:target="showClassStatus" aria-selected="{{ $classStatus === 'archived' ? 'true' : 'false' }}" @class(['is-active' => $classStatus === 'archived'])>Archived</button>
        </div>

        <div class="section-heading"><h2>{{ auth()->user()->role === 'admin' ? 'All '.$classStatus.' classes' : 'Your '.$classStatus.' classes' }}</h2><span>{{ $classes->total() }} total</span></div>
        @if($classes->isEmpty())
            <div class="class-empty"><h2>No {{ $classStatus }} classes</h2><p>{{ $classStatus === 'active' ? 'Create your first class above, then share its join code with students.' : 'Archived classes remain here with their complete history.' }}</p></div>
        @else
            <div class="class-list">
                @foreach($classes as $classroom)
                    <a class="class-row" href="{{ route('teacher.classes.show', $classroom) }}">
                        <div class="class-row__identity">
                            <div class="class-row__title"><h3>{{ $classroom->name }}</h3><span class="status-chip status-chip--{{ $classroom->status }}">{{ ucfirst($classroom->status) }}</span></div>
                            <p>{{ auth()->user()->role === 'admin' ? 'Owner: '.$classroom->owner->name : ($classroom->description ?: 'No description') }}</p>
                        </div>
                        <dl class="class-row__metrics"><div><dt>Students</dt><dd>{{ $classroom->active_memberships_count }}</dd></div><div><dt>Pending</dt><dd>{{ $classroom->pending_memberships_count }}</dd></div><div><dt>Assignments</dt><dd>{{ $classroom->assignments_count }}</dd></div><div class="class-row__join-code"><dt>Join code</dt><dd>{{ $classroom->join_code }}</dd></div></dl>
                    </a>
                @endforeach
            </div>
            {{ $classes->links() }}
        @endif
    @else
        <div class="page-heading"><div><h1>Assignments &amp; reports</h1><p>Track work across every class and open detailed student results.</p></div></div>

        @forelse($assignments as $assignment)
            <a class="assignment-row class-panel" href="{{ route('teacher.assignments.show', ['assignment' => $assignment, 'from' => 'workspace']) }}">
                <div><span class="status-chip status-chip--{{ $assignment->status }}">{{ ucfirst($assignment->status) }}</span><strong>{{ $assignment->title }}</strong><span>{{ $assignment->classroom->name }} · {{ $assignment->test->title }}</span></div>
                <div><span>{{ $assignment->recipients_count }} students</span><span>{{ $assignment->attempts_count }} attempts</span><span>{{ $assignment->due_at?->format('M j, g:i A') ?: 'No due time' }}</span></div>
            </a>
        @empty
            <div class="class-empty"><h2>No assignments yet</h2><p>Open a class and create its first assignment.</p><button type="button" class="class-button class-button--primary" wire:click="showSection('classes')" wire:loading.attr="disabled" wire:target="showSection">Open classes</button></div>
        @endforelse
        {{ $assignments->links() }}
    @endif
</div>
