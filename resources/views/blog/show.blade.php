<x-layouts.student :user="$user" :title="$post->title" header-type="none">
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
