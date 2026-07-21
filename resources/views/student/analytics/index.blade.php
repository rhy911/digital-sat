<x-layouts.student :user="$user" title="Home" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/analytics.css', 'resources/css/student/progress.css'])
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
    @endphp

    <div class="app-shell app-shell--no-list" x-data="{ tab: '{{ $initialHomeTab }}' }" @teacher-home-tab-requested.window="tab = $event.detail.tab" @teacher-home-tab-changed.window="tab = $event.detail.tab">
        <x-shell.icon-rail :logo-href="\App\Support\NavRail::logoHrefForUser($user)" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::forUser($user, 'home')" />

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
                                        <h2 id="featured-title" class="ds-booklet-title text-xl font-bold leading-snug">{{ $featuredTest->title }}</h2>
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
                                <div class="ds-booklet text-center p-8">
                                    <div class="ds-booklet-inner">
                                        <h2 id="featured-title" class="ds-booklet-title text-lg">No active tests</h2>
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
                            <div class="ds-booklet-container mt-5">
                                <div class="ds-booklet ds-assignment-booklet">
                                    <div class="ds-booklet-inner">
                                        <span class="ds-booklet-tag ds-assignment-tag">My assignments</span>
                                        <h2 class="ds-booklet-title text-lg font-bold mb-3">Class assignments</h2>
                                        
                                        <div class="ds-assignment-list">
                                            @foreach($assignments as $a)
                                                @php
                                                    $attempts = $a->attempts;
                                                    $resolved = \App\Support\AssignmentStatusResolver::resolve($a, $attempts);
                                                    $state = $resolved['state'];
                                                @endphp
                                                <div class="ds-assignment-row">
                                                    <div>
                                                        <strong class="text-slate-900 font-semibold">{{ \Illuminate\Support\Str::limit($a->title, 40) }}</strong>
                                                        <span class="block text-2xs text-slate-500 mt-0.5">{{ $a->classroom->name }} &middot; {{ $state }}</span>
                                                    </div>
                                                    <a href="{{ route('student.assignments.show', ['assignment' => $a->ulid]) }}" class="ds-link font-semibold text-xs whitespace-nowrap ml-2">Open &rarr;</a>
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

                            <div class="ds-card__header mt-2">
                                <div><h2 id="blog-title" class="ds-card-title">From the blog</h2></div>
                                <a href="{{ route('blog.index') }}" class="ds-link text-xs font-semibold">Browse all</a>
                            </div>
                            @if($featuredPost)
                                <div class="ds-postcard__lead">
                                    <div class="flex-1">
                                        <span class="type-tag mb-1 inline-block">Latest Post</span>
                                        <a href="{{ route('blog.show', $featuredPost) }}" class="ds-postcard__lead-title hover:text-indigo-700 transition-colors">{{ $featuredPost->title }}</a>
                                        @if($featuredPost->excerpt)
                                            <p class="ds-postcard__lead-excerpt">{{ \Illuminate\Support\Str::limit($featuredPost->excerpt, 110) }}</p>
                                        @endif
                                        <span class="ds-postcard__byline">by {{ $featuredPost->teacher->name }} &bull; {{ optional($featuredPost->published_at)->diffForHumans() }}</span>
                                    </div>

                                    {{-- Postage Stamp --}}
                                    <div class="ds-postmark" aria-hidden="true">
                                        <span class="text-3xs font-extrabold opacity-70">POSTAGE</span>
                                        <span class="text-xs font-black tracking-tight my-0.5">{{ $featuredPost->teacher->initials ?? 'T' }}</span>
                                        <span class="text-3xs border-t border-red-600/40 pt-0.5 w-full text-center">{{ optional($featuredPost->published_at)->format('M d') }}</span>
                                    </div>
                                </div>
                                @foreach($moreposts as $post)
                                    <div class="ds-attempt-row py-2">
                                        <div>
                                            <strong><a href="{{ route('blog.show', $post) }}" class="text-xs text-slate-800 font-semibold hover:text-indigo-700 no-underline transition-colors">{{ $post->title }}</a></strong>
                                            <span class="text-2xs text-slate-500 block mt-0.5">by {{ $post->teacher->name }} &bull; {{ optional($post->published_at)->diffForHumans() }}</span>
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

                            <div class="ds-card__header mt-2">
                                <div><h2 id="forum-title" class="ds-card-title">Forum highlights</h2></div>
                                <div class="flex items-center gap-3">
                                    <button type="button" class="ds-link font-semibold text-xs cursor-pointer p-0 bg-transparent border-0" @click="$dispatch('open-modal', 'modal-ask-question')">Ask a question</button>
                                    <a href="{{ route('forum.index') }}" class="ds-link text-xs font-semibold">Open forum</a>
                                </div>
                            </div>
                            @forelse($latestThreads as $thread)
                                <div class="ds-attempt-row py-2">
                                    <div>
                                        <span class="type-tag mb-1 inline-block text-3xs px-1.5 py-0.5">{{ $thread->category }}</span>
                                        <strong><a href="{{ route('forum.show', $thread) }}" class="text-xs text-slate-800 font-semibold hover:text-indigo-700 no-underline block my-0.5 transition-colors">{{ $thread->title }}</a></strong>
                                        <span class="text-2xs text-slate-500">by {{ $thread->user->name }} &bull; {{ $thread->replies_count }} {{ \Illuminate\Support\Str::plural('reply', $thread->replies_count) }}</span>
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
