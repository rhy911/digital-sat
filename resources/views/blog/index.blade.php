<x-layouts.student :user="$user" title="Blog" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/progress.css'])
    @endpush

    @php
        $isTeacher = $user->role === 'teacher';
        $railItems = $isTeacher ? [
            ['route' => route('teacher.progress'), 'label' => 'Progress', 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M4 19V5M4 19h16M8 15l3-4 3 3 5-7\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>'],
            ['route' => route('teacher.classes.index'), 'label' => 'Classes', 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><rect x=\'3.5\' y=\'5\' width=\'17\' height=\'14\' rx=\'2\' /><path d=\'M3.5 9.5h17M8 5v-1M16 5v-1\' stroke-linecap=\'round\' /></svg>'],
            ['route' => route('teacher.assignments.index'), 'label' => 'Reports', 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M5 3v18h16\' stroke-linecap=\'round\' /><rect x=\'8\' y=\'12\' width=\'3\' height=\'6\' /><rect x=\'13\' y=\'8\' width=\'3\' height=\'10\' /><rect x=\'18\' y=\'5\' width=\'3\' height=\'13\' /></svg>'],
            ['route' => route('home.practice'), 'label' => 'Test Library', 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M12 6.5c-1.6-1.2-3.7-1.8-6-1.8-.7 0-1.4.05-2 .15v13.5c.6-.1 1.3-.15 2-.15 2.3 0 4.4.6 6 1.8m0-13.5c1.6-1.2 3.7-1.8 6-1.8.7 0 1.4.05 2 .15v13.5c-.6-.1-1.3-.15-2-.15-2.3 0-4.4.6-6 1.8m0-13.5v13.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>'],
            ['route' => route('home-dashboard.index'), 'label' => 'Test Builder', 'target' => '_blank', 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M14.7 6.3a3 3 0 0 0 4 4L14 15l-4 1 1-4Z\' stroke-linejoin=\'round\' /></svg>'],
        ] : [
            ['route' => route('home'), 'label' => __('classroom.nav_dashboard'), 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\'/><polyline points=\'9 22 9 12 15 12 15 22\'/></svg>'],
            ['route' => route('student.classes.index'), 'label' => __('classroom.nav_my_classes'), 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><rect x=\'3.5\' y=\'5\' width=\'17\' height=\'14\' rx=\'2\' /><path d=\'M3.5 9.5h17M8 5v-1M16 5v-1\' stroke-linecap=\'round\' /></svg>'],
            ['route' => route('student.progress'), 'label' => __('classroom.nav_progress'), 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M4 19V5M4 19h16M8 15l3-4 3 3 5-7\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>'],
            ['route' => route('home.practice'), 'label' => __('classroom.nav_practice'), 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M12 6.5c-1.6-1.2-3.7-1.8-6-1.8-.7 0-1.4.05-2 .15v13.5c.6-.1 1.3-.15 2-.15 2.3 0 4.4.6 6 1.8m0-13.5c1.6-1.2 3.7-1.8 6-1.8.7 0 1.4.05 2 .15v13.5c-.6-.1-1.3-.15-2-.15-2.3 0-4.4.6-6 1.8m0-13.5v13.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>'],
            ['route' => route('student.scores.index'), 'label' => __('classroom.nav_scores'), 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><line x1=\'18\' y1=\'20\' x2=\'18\' y2=\'10\'/><line x1=\'12\' y1=\'20\' x2=\'12\' y2=\'4\'/><line x1=\'6\' y1=\'20\' x2=\'6\' y2=\'14\'/></svg>'],
        ];
    @endphp

    <div class="app-shell app-shell--no-list">
        <x-shell.icon-rail :logo-href="$isTeacher ? route('teacher.progress') : route('home')" :avatar-label="$user->initials" :items="$railItems" />

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
