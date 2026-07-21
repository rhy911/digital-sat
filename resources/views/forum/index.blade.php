<x-layouts.student :user="$user" title="Forum" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/progress.css'])
    @endpush

    <div class="app-shell app-shell--no-list" x-data="{}">
        <x-shell.icon-rail :logo-href="\App\Support\NavRail::logoHrefForUser($user)" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::forUser($user)" />

        <div class="shell-content">
            <div class="binder-panel">
                <div class="ledger-header">
                    <div class="dh-left">
                        <h2>Forum <span class="handwriting status-quote">"Ask, help, discuss"</span></h2>
                        <div class="dh-desc">Open discussion between students and teachers on the platform.</div>
                    </div>
                    <div>
                        <button type="button" class="btn-sm-primary" @click="$dispatch('open-modal', 'modal-ask-question')">Ask a Question</button>
                        <a href="{{ route('home') }}" class="btn-outline">Back to Home</a>
                    </div>
                </div>

                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 20px;">
                    <a href="{{ route('forum.index') }}" class="type-tag" style="{{ !$activeCategory ? 'background: var(--accent-soft); border-color: var(--accent); color: var(--accent);' : '' }}">All</a>
                    @foreach ($categories as $category)
                        <a href="{{ route('forum.index', ['category' => $category]) }}" class="type-tag" style="{{ $activeCategory === $category ? 'background: var(--accent-soft); border-color: var(--accent); color: var(--accent);' : '' }}">{{ $category }}</a>
                    @endforeach
                </div>

                @if ($threads->isNotEmpty())
                    <div style="margin-top: 20px;">
                        @foreach ($threads as $thread)
                            <a href="{{ route('forum.show', $thread) }}" class="index-card" style="display: flex; align-items: center; justify-content: space-between; gap: 16px; text-decoration: none; color: inherit; margin-bottom: 12px;">
                                <div style="min-width: 0;">
                                    <div class="card-meta" style="margin-top: 0;">
                                        <span class="type-tag">{{ $thread->category }}</span>
                                    </div>
                                    <div class="card-title" style="margin-top: 8px; min-height: 0;">{{ $thread->title }}</div>
                                    <div class="card-sub">by {{ $thread->user->name }} &bull; {{ $thread->created_at->diffForHumans() }}</div>
                                </div>
                                <div style="flex-shrink: 0; text-align: center;">
                                    <div class="mono" style="font-size: 18px; font-weight: 800; color: var(--ink);">{{ $thread->replies_count }}</div>
                                    <div style="font-size: 10.5px; color: var(--ink-faint);">replies</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    <div style="margin-top: 8px;">{{ $threads->links() }}</div>
                @else
                    <div class="empty-grid-cell" style="margin-top: 24px;">
                        <strong>No threads yet</strong>
                        <p style="margin-top: 4px;">Be the first to start a discussion.</p>
                    </div>
                @endif
            </div>
        </div>

        @include('forum.partials.ask-modal')
    </div>
</x-layouts.student>
