<x-layouts.student :user="$user" title="Home" header-type="none">
    @push('styles')
        @if($user->role === 'teacher' && $user->isApprovedTeacher())
            @vite(['resources/css/classroom-workspace.css', 'resources/css/student/analytics.css', 'resources/css/student/progress.css', 'resources/css/classroom.css'])
        @else
            @vite(['resources/css/classroom-workspace.css', 'resources/css/student/analytics.css', 'resources/css/student/progress.css'])
        @endif
    @endpush

    @php
        $displayName = $user->name ?? $user->username ?? 'student';
        $todayLabel = now()->format('l, M j');
        $canUseTeacherWorkspace = $user->role === 'teacher' && $user->isApprovedTeacher();
        $storedHomeTab = session('teacher_home.tab', 'progress');
        $initialHomeTab = $canUseTeacherWorkspace && in_array($storedHomeTab, ['classes', 'reports'], true) ? $storedHomeTab : 'progress';

        $featuredDuration = $featuredTest?->total_duration_minutes
            ?: $featuredTest?->sections?->sum(fn ($section) => $section->modules->unique('module_number')->sum('duration_minutes'));
        $featuredSections = $featuredTest?->sections?->count();
        $featuredModules = $featuredTest?->sections?->flatMap->modules->unique('id')->count();

        $featuredPost = $latestPosts->first();
        $moreposts = $latestPosts->skip(1);

        $isTeacher = $canUseTeacherWorkspace;
        $railItems = $isTeacher ? [
            ['route' => route('teacher.progress'), 'label' => 'Progress', 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M4 19V5M4 19h16M8 15l3-4 3 3 5-7\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>'],
            ['route' => route('teacher.classes.index'), 'label' => 'Classes', 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><rect x=\'3.5\' y=\'5\' width=\'17\' height=\'14\' rx=\'2\' /><path d=\'M3.5 9.5h17M8 5v-1M16 5v-1\' stroke-linecap=\'round\' /></svg>'],
            ['route' => route('teacher.assignments.index'), 'label' => 'Reports', 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M5 3v18h16\' stroke-linecap=\'round\' /><rect x=\'8\' y=\'12\' width=\'3\' height=\'6\' /><rect x=\'13\' y=\'8\' width=\'3\' height=\'10\' /><rect x=\'18\' y=\'5\' width=\'3\' height=\'13\' /></svg>'],
            ['route' => route('home.practice'), 'label' => 'Test Library', 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M12 6.5c-1.6-1.2-3.7-1.8-6-1.8-.7 0-1.4.05-2 .15v13.5c.6-.1 1.3-.15 2-.15 2.3 0 4.4.6 6 1.8m0-13.5c1.6-1.2 3.7-1.8 6-1.8.7 0 1.4.05 2 .15v13.5c-.6-.1-1.3-.15-2-.15-2.3 0-4.4.6-6 1.8m0-13.5v13.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>'],
            ['route' => route('home-dashboard.index'), 'label' => 'Test Builder', 'target' => '_blank', 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M14.7 6.3a3 3 0 0 0 4 4L14 15l-4 1 1-4Z\' stroke-linejoin=\'round\' /></svg>'],
        ] : [
            ['route' => route('home'), 'label' => __('classroom.nav_dashboard'), 'active' => true, 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\'/><polyline points=\'9 22 9 12 15 12 15 22\'/></svg>'],
            ['route' => route('student.classes.index'), 'label' => __('classroom.nav_my_classes'), 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><rect x=\'3.5\' y=\'5\' width=\'17\' height=\'14\' rx=\'2\' /><path d=\'M3.5 9.5h17M8 5v-1M16 5v-1\' stroke-linecap=\'round\' /></svg>'],
            ['route' => route('student.assignments.index'), 'label' => 'Assignments', 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><rect x=\'3\' y=\'4\' width=\'18\' height=\'16\' rx=\'2\' /><path d=\'M7 8h10M7 12h10M7 16h6\' stroke-linecap=\'round\' /></svg>'],
            ['route' => route('student.progress'), 'label' => __('classroom.nav_progress'), 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M4 19V5M4 19h16M8 15l3-4 3 3 5-7\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>'],
            ['route' => route('home.practice'), 'label' => __('classroom.nav_practice'), 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><path d=\'M12 6.5c-1.6-1.2-3.7-1.8-6-1.8-.7 0-1.4.05-2 .15v13.5c.6-.1 1.3-.15 2-.15 2.3 0 4.4.6 6 1.8m0-13.5c1.6-1.2 3.7-1.8 6-1.8.7 0 1.4.05 2 .15v13.5c-.6-.1-1.3-.15-2-.15-2.3 0-4.4.6-6 1.8m0-13.5v13.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\' /></svg>'],
            ['route' => route('student.scores.index'), 'label' => __('classroom.nav_scores'), 'icon' => '<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.8\'><line x1=\'18\' y1=\'20\' x2=\'18\' y2=\'10\'/><line x1=\'12\' y1=\'20\' x2=\'12\' y2=\'4\'/><line x1=\'6\' y1=\'20\' x2=\'6\' y2=\'14\'/></svg>'],
        ];
    @endphp

    <div class="app-shell app-shell--no-list" x-data="{ tab: '{{ $initialHomeTab }}' }" @teacher-home-tab-requested.window="tab = $event.detail.tab" @teacher-home-tab-changed.window="tab = $event.detail.tab">
        <x-shell.icon-rail :logo-href="$isTeacher ? route('teacher.progress') : route('home')" :avatar-label="$user->initials" :items="$railItems" />

        <div class="ledger-pane ds-desk-workspace-view" x-show="tab === 'progress'">
            <div class="ds-desk-board">
                <x-ui.flash />

                {{-- Pinned Header Manila Sheet --}}
                <header class="ds-desk-header-sheet">
                    <div class="ds-desk-header__row">
                        <div>
                            <span class="ds-page-kicker">{{ $todayLabel }}</span>
                            <h1 class="ds-desk-title">Discover, {{ $displayName }}</h1>
                            <p class="ds-desk-subtitle">
                                Fresh practice picks, study tips from teachers, and what the community is talking about.
                            </p>
                        </div>
                        <div class="ds-desk-header__actions">
                            <a href="{{ route('home.practice') }}" class="btn-outline">Explore Test Library</a>
                            <a href="{{ route('forum.index') }}" class="btn-outline">Open Forum</a>
                        </div>
                    </div>
                </header>

                {{-- The Desk Grid --}}
                <div class="ds-desk-grid">

                    {{-- Column 1: Study Session Binder & Test Booklet --}}
                    <div class="ds-desk-col">

                        {{-- Spiral Notebook (Active Session or Stats Ledger) --}}
                        <section class="ds-spiral-notebook" aria-labelledby="ledger-title">
                            <div class="ds-spiral-spine" aria-hidden="true">
                                @for ($i = 0; $i < 7; $i++)
                                    <div class="ds-spiral-ring"></div>
                                @endfor
                            </div>

                            @if ($inProgressAttempt)
                                <h2 id="ledger-title" class="ds-hand-title">Resume active practice</h2>
                                <p class="ds-notebook-lead">
                                    You have an unfinished attempt in progress for:
                                    <br>
                                    <strong>{{ $inProgressAttempt->test->title }}</strong>
                                </p>
                                <div class="ds-notebook-resume">
                                    <span class="ds-pulse-dot" aria-hidden="true"></span>
                                    <a href="{{ route('my-practice', $inProgressAttempt) }}" class="btn-sm-primary btn-pin ds-btn-paper">
                                        Resume attempt
                                    </a>
                                </div>
                            @else
                                <h2 id="ledger-title" class="ds-hand-title">My study ledger</h2>
                                <ul class="ds-ledger-list">
                                    <li>&bull; Personal best: <strong>{{ $bestScore ?: 'No attempts yet' }}</strong></li>
                                    <li>&bull; Practice completed: <strong>{{ $completedCount }} {{ \Illuminate\Support\Str::plural('test', $completedCount) }}</strong></li>
                                    <li>&bull; Goal target: <strong>1600 (max SAT)</strong></li>
                                </ul>
                                <a href="{{ route('student.progress') }}" class="btn-outline ds-btn-paper">View detailed progress</a>
                            @endif
                        </section>

                        {{-- Recommended Practice Booklet --}}
                        <div class="ds-booklet-container">
                            @if ($featuredTest)
                                <div class="ds-booklet">
                                    <div class="ds-booklet-inner">
                                        <span class="ds-booklet-tag">Recommended pick</span>
                                        <h2 id="featured-title" class="ds-booklet-title" style="font-size: 1.5rem; line-height: 1.25;">{{ $featuredTest->title }}</h2>
                                        <p class="ds-booklet-meta">
                                            <span class="ds-chip">{{ $featuredTest->test_type ? \Illuminate\Support\Str::headline($featuredTest->test_type) : 'Full-length' }}</span>
                                            <span>&bull;</span>
                                            <span>{{ $featuredDuration ?: '--' }} mins</span>
                                            <span>&bull;</span>
                                            <span>{{ $featuredSections ?: '--' }} Sections</span>
                                        </p>
                                        @if($featuredTest->description)
                                            <p class="ds-booklet-desc">{{ \Illuminate\Support\Str::limit($featuredTest->description, 110) }}</p>
                                        @endif
                                        <a href="{{ route('home.practice') }}" class="btn-sm-primary btn-pin ds-btn-paper">
                                            Start practice
                                        </a>
                                    </div>
                                </div>
                            @else
                                <div class="ds-booklet" style="text-align: center; padding: 2rem;">
                                    <div class="ds-booklet-inner">
                                        <h2 id="featured-title" class="ds-booklet-title" style="font-size: 1.3rem;">No active tests</h2>
                                        <p class="ds-booklet-desc">Check back soon, or preview the digital interface.</p>
                                        <a href="{{ route('test.preview') }}" class="btn-sm-primary btn-pin ds-btn-paper">
                                            Open test preview
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- My Assignments Pinned Card --}}
                        @if(!$isTeacher && $assignments->isNotEmpty())
                            <div class="ds-booklet-container" style="margin-top: 20px;">
                                <div class="ds-booklet" style="background: #faf9f6; border-left: 4px solid var(--accent); padding: 1.5rem;">
                                    <div class="ds-booklet-inner">
                                        <span class="ds-booklet-tag" style="background: var(--accent-soft); color: var(--accent);">My assignments</span>
                                        <h2 class="ds-booklet-title" style="font-size: 1.3rem; margin-bottom: 0.75rem;">Class assignments</h2>
                                        
                                        <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 1.25rem;">
                                            @foreach($assignments as $a)
                                                @php
                                                    $attempts = $a->attempts;
                                                    $resolved = \App\Support\AssignmentStatusResolver::resolve($a, $attempts);
                                                    $state = $resolved['state'];
                                                @endphp
                                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed var(--ink-faint); padding-bottom: 6px; font-size: 13px;">
                                                    <div>
                                                        <strong>{{ \Illuminate\Support\Str::limit($a->title, 35) }}</strong>
                                                        <span style="display: block; font-size: 11px; color: var(--ink-soft);">{{ $a->classroom->name }} &middot; {{ $state }}</span>
                                                    </div>
                                                    <a href="{{ route('student.assignments.show', ['assignment' => $a->ulid]) }}" class="ds-link" style="font-weight: 600;">Open &rarr;</a>
                                                </div>
                                            @endforeach
                                        </div>
                                        <a href="{{ route('student.assignments.index') }}" class="btn-sm-primary btn-pin ds-btn-paper">
                                            View all assignments
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>

                    {{-- Column 2: Blog & Forum Pinned Postcards --}}
                    <div class="ds-desk-col">

                        {{-- Blog Pinned Postcard --}}
                        <section class="progress-card ds-postcard" aria-labelledby="blog-title">
                            <div class="ds-thumbtack red center" aria-hidden="true"></div>

                            <div class="ds-card__header" style="margin-top: 0.5rem;">
                                <div><h2 id="blog-title" class="ds-card-title">From the blog</h2></div>
                                <a href="{{ route('blog.index') }}" class="ds-link">Browse all</a>
                            </div>
                            @if($featuredPost)
                                <div class="ds-postcard__lead">
                                    <div style="flex: 1;">
                                        <span class="type-tag" style="margin-bottom: 4px; display: inline-block;">Latest Post</span>
                                        <a href="{{ route('blog.show', $featuredPost) }}" class="ds-postcard__lead-title">{{ $featuredPost->title }}</a>
                                        @if($featuredPost->excerpt)
                                            <p class="ds-postcard__lead-excerpt">{{ \Illuminate\Support\Str::limit($featuredPost->excerpt, 110) }}</p>
                                        @endif
                                        <span class="ds-postcard__byline">by {{ $featuredPost->teacher->name }} &bull; {{ optional($featuredPost->published_at)->diffForHumans() }}</span>
                                    </div>

                                    {{-- Postage Stamp --}}
                                    <div class="ds-postmark" aria-hidden="true">
                                        <span style="font-size: 0.425rem; font-weight: 800; opacity: 0.7;">POSTAGE</span>
                                        <span style="font-size: 0.85rem; font-weight: 900; letter-spacing: -0.02em; margin: 1px 0;">{{ $featuredPost->teacher->initials ?? 'T' }}</span>
                                        <span style="font-size: 0.4rem; border-top: 1px solid rgba(220, 38, 38, 0.4); padding-top: 1px; width: 100%; text-align: center;">{{ optional($featuredPost->published_at)->format('M d') }}</span>
                                    </div>
                                </div>
                                @foreach($moreposts as $post)
                                    <div class="ds-attempt-row" style="padding: 0.5rem 0;">
                                        <div>
                                            <strong><a href="{{ route('blog.show', $post) }}" style="color: inherit; text-decoration: none; font-size: 0.85rem;">{{ $post->title }}</a></strong>
                                            <span style="font-size: 0.75rem; color: var(--ink-soft); display: block;">by {{ $post->teacher->name }} &bull; {{ optional($post->published_at)->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="ds-empty ds-empty--compact">
                                    <h3>No posts yet</h3>
                                    <p>Study tips from teachers will show up here.</p>
                                </div>
                            @endif
                        </section>

                        {{-- Forum highlights Pinned Postcard --}}
                        <section class="progress-card ds-postcard" aria-labelledby="forum-title">
                            <div class="ds-thumbtack blue center" aria-hidden="true"></div>

                            <div class="ds-card__header" style="margin-top: 0.5rem;">
                                <div><h2 id="forum-title" class="ds-card-title">Forum highlights</h2></div>
                                <div style="display: flex; gap: 0.75rem; align-items: center;">
                                    <button type="button" class="ds-link" style="background: none; border: none; cursor: pointer; padding: 0; font: inherit;" @click="$dispatch('open-modal', 'modal-ask-question')">Ask a question</button>
                                    <a href="{{ route('forum.index') }}" class="ds-link">Open forum</a>
                                </div>
                            </div>
                            @forelse($latestThreads as $thread)
                                <div class="ds-attempt-row" style="padding: 0.5rem 0;">
                                    <div>
                                        <span class="type-tag" style="margin-bottom: 2px; display: inline-block; font-size: 0.65rem; padding: 0.05rem 0.35rem;">{{ $thread->category }}</span>
                                        <strong><a href="{{ route('forum.show', $thread) }}" style="color: inherit; text-decoration: none; font-size: 0.85rem; display: block; margin: 0.15rem 0;">{{ $thread->title }}</a></strong>
                                        <span style="font-size: 0.75rem; color: var(--ink-soft);">by {{ $thread->user->name }} &bull; {{ $thread->replies_count }} {{ \Illuminate\Support\Str::plural('reply', $thread->replies_count) }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="ds-empty ds-empty--compact">
                                    <h3>No threads yet</h3>
                                    <p>Discussions will show up here.</p>
                                </div>
                            @endforelse
                        </section>

                    </div>

                </div>

                {{-- Desk Footer: Sticky Notes --}}
                <div class="ds-note-grid">

                    {{-- Quick Tip --}}
                    <div class="cork-note">
                        <h2 class="ds-note__title">Quick tip</h2>
                        <p class="ds-note__body"><em>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($tip)) !!}</em></p>
                    </div>

                    {{-- Community Digest --}}
                    <div class="cork-note cork-note--yellow">
                        <h2 class="ds-note__title">Community</h2>
                        <p class="ds-note__body">
                            {{ $latestPosts->count() }} recent {{ \Illuminate\Support\Str::plural('post', $latestPosts->count()) }} &bull;
                            {{ $latestThreads->count() }} active discussions.
                            <br>
                            <a href="{{ route('forum.index') }}" class="ds-link ds-note__link">Join the conversation</a>
                        </p>
                    </div>

                    {{-- Adaptive Explainer --}}
                    <div class="cork-note cork-note--indigo">
                        <h2 class="ds-note__title">Your test adapts to you</h2>
                        <p class="ds-note__body">
                            Adaptive full tests route you to an easier or harder second module based on your answers, then use IRT (EAP 3PL) scoring &mdash; the same math behind real adaptive testing &mdash; to estimate your score range.
                        </p>
                    </div>

                    {{-- Detailed Result Teaser --}}
                    @if ($lastCompletedAttempt)
                        @php
                            $lastScore = $lastCompletedAttempt->attempt_type === 'section'
                                ? ($lastCompletedAttempt->section_type === 'reading_writing' ? $lastCompletedAttempt->score_reading_writing : $lastCompletedAttempt->score_math)
                                : $lastCompletedAttempt->total_score;
                        @endphp
                        <div class="cork-note">
                            <h2 class="ds-note__title">Your last score: {{ $lastScore ?? '—' }}</h2>
                            <p class="ds-note__body">
                                See the skill-by-skill breakdown driving that number.
                                <br>
                                <a href="{{ route('student.scores.show', $lastCompletedAttempt) }}" class="ds-link ds-note__link">See what's driving your score &rarr;</a>
                            </p>
                        </div>
                    @endif

                    {{-- Connect With Your Teacher --}}
                    @if ($hasClassroom && $primaryClassroom)
                        <div class="cork-note cork-note--yellow">
                            <h2 class="ds-note__title">Your teacher</h2>
                            <p class="ds-note__body">
                                {{ $primaryClassroom->owner->name }} is guiding your prep in {{ $primaryClassroom->name }}.
                                <br>
                                <a href="{{ route('student.classes.show', $primaryClassroom) }}" class="ds-link ds-note__link">View class &rarr;</a>
                            </p>
                        </div>
                    @else
                        <div class="cork-note cork-note--yellow">
                            <h2 class="ds-note__title">Get a teacher's eyes on your prep</h2>
                            <p class="ds-note__body">
                                Join a class with a code from your teacher to get assignments, progress tracking, and feedback.
                                <br>
                                <a href="{{ route('student.classes.index') }}" class="ds-link ds-note__link">Join a class &rarr;</a>
                            </p>
                        </div>
                    @endif

                </div>

            </div>
        </div>

        @include('forum.partials.ask-modal')
        </div>

        @if($canUseTeacherWorkspace)
            <div x-show="tab === 'classes' || tab === 'reports'" x-cloak class="shell-content">
                <x-ui.flash />
                <div class="ds-teacher-workspace">
                    <livewire:teacher.workspace />
                </div>
            </div>
        @endif
    </div>
</x-layouts.student>
