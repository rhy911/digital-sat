<x-layouts.student :user="$user" :title="$post->title" header-type="none">
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
                        <a href="{{ route('blog.index') }}" class="btn-outline" style="margin-bottom: 12px; display: inline-block;">&larr; All posts</a>
                        <h2 style="margin-top: 8px;">{{ $post->title }}</h2>
                        <div class="dh-desc">
                            By {{ $post->teacher->name }} &bull; {{ optional($post->published_at)->format('M j, Y') }}
                        </div>
                    </div>
                </div>

                <div style="margin-top: 24px; font-size: 14px; line-height: 1.75; color: var(--ink);">
                    {!! nl2br(e($post->body)) !!}
                </div>
            </div>
        </div>
    </div>
</x-layouts.student>
