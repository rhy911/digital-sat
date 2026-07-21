<x-layouts.student :user="$user" :title="$thread->title" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/progress.css'])
    @endpush

    <div class="app-shell app-shell--no-list">
        <x-shell.icon-rail :logo-href="\App\Support\NavRail::logoHrefForUser($user)" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::forUser($user)" />

        <div class="shell-content">
            <div class="binder-panel" style="max-width: 760px; margin: 0 auto;">
                <div class="ledger-header">
                    <div class="dh-left">
                        <a href="{{ route('forum.index') }}" class="btn-outline" style="margin-bottom: 12px; display: inline-block;">&larr; All threads</a>
                        <div class="card-meta" style="margin-top: 8px;">
                            <span class="type-tag">{{ $thread->category }}</span>
                        </div>
                        <h2 style="margin-top: 8px;">{{ $thread->title }}</h2>
                        <div class="dh-desc">
                            By {{ $thread->user->name }} &bull; {{ $thread->created_at->diffForHumans() }}
                        </div>
                    </div>
                </div>

                <div style="margin-top: 20px; font-size: 14px; line-height: 1.7; color: var(--ink);">
                    {!! nl2br(e($thread->body)) !!}
                </div>

                <div class="card-meta-divider" style="margin-top: 28px;">
                    <div class="library-section-heading" style="margin-bottom: 16px;">{{ $replies->total() }} {{ \Illuminate\Support\Str::plural('reply', $replies->total()) }}</div>

                    @forelse ($replies as $reply)
                        <div style="border-top: 1px dashed var(--border-strong); padding: 14px 0;">
                            <div class="card-sub" style="font-weight: 700; color: var(--ink);">{{ $reply->user->name }}
                                <span style="font-weight: 500; color: var(--ink-faint);">&bull; {{ $reply->created_at->diffForHumans() }}</span>
                            </div>
                            <div style="margin-top: 6px; font-size: 13.5px; line-height: 1.6;">{!! nl2br(e($reply->body)) !!}</div>
                        </div>
                    @empty
                        <div class="empty-grid-cell">
                            <strong>No replies yet</strong>
                        </div>
                    @endforelse

                    @if ($replies->hasPages())
                        <div style="margin-top: 16px;">{{ $replies->links() }}</div>
                    @endif
                </div>

                <div class="form-note" style="margin-top: 20px;">
                    <form method="POST" action="{{ route('forum.replies.store', $thread) }}">
                        @csrf
                        <div class="field">
                            <label>Add a reply</label>
                            <textarea name="body" rows="4" maxlength="3000" required placeholder="Write your reply...">{{ old('body') }}</textarea>
                        </div>
                        <button type="submit" class="btn-sm-primary">Post Reply</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.student>
