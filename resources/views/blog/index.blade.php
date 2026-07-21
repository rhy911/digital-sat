<x-layouts.student :user="$user" title="Blog" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/progress.css'])
    @endpush

    <div class="app-shell app-shell--no-list">
        <x-shell.icon-rail :logo-href="\App\Support\NavRail::logoHrefForUser($user)" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::forUser($user)" />

        <div class="shell-content">
            <div class="binder-panel">
                <div class="ledger-header">
                    <div class="dh-left">
                        <h2>Blog <span class="handwriting status-quote">"Notes from your teachers"</span></h2>
                        <div class="dh-desc">Study tips and strategy notes published by teachers on the platform.</div>
                    </div>
                    <div>
                        <a href="{{ route('home') }}" class="btn-outline">Back to Home</a>
                    </div>
                </div>

                @if ($posts->isNotEmpty())
                    <div class="progress-cards" style="margin-top: 24px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
                        @foreach ($posts as $post)
                            <a href="{{ route('blog.show', $post) }}" class="index-card" style="text-decoration: none; color: inherit;">
                                <div class="card-due">{{ optional($post->published_at)->format('M j, Y') }}</div>
                                <div class="card-title">{{ $post->title }}</div>
                                <div class="card-sub">by {{ $post->teacher->name }}</div>
                                @if ($post->excerpt)
                                    <p style="font-size: 12.5px; color: var(--ink-soft); margin-top: 10px; line-height: 1.5;">{{ \Illuminate\Support\Str::limit($post->excerpt, 120) }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                    <div style="margin-top: 24px;">{{ $posts->links() }}</div>
                @else
                    <div class="empty-grid-cell" style="margin-top: 24px;">
                        <strong>No posts yet</strong>
                        <p style="margin-top: 4px;">Check back soon for study tips from teachers.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.student>
