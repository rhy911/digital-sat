                {{-- ================= F. RESOURCES AND COMMUNITY ================= --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-ui.card stretch aria-labelledby="blog-title">
                        <x-slot:header>
                            <h2 id="blog-title" class="home-panel__title">From the blog</h2>
                            <a href="{{ route('blog.index') }}" class="home-link">
                                Browse all
                                <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                            </a>
                        </x-slot:header>

                        @if($featuredPost)
                            <a href="{{ route('blog.show', $featuredPost) }}" class="home-feature-link">
                                <span class="home-feature-link__title">{{ $featuredPost->title }}</span>
                                @if($featuredPost->excerpt)
                                    <span class="home-feature-link__excerpt">{{ \Illuminate\Support\Str::limit($featuredPost->excerpt, 90) }}</span>
                                @endif
                                <span class="home-feature-link__byline">{{ $featuredPost->teacher->name }} &middot; {{ optional($featuredPost->published_at)->diffForHumans() }}</span>
                            </a>

                            @if($moreposts->isNotEmpty())
                                <ul class="home-minilist">
                                    @foreach($moreposts->take(2) as $post)
                                        <li>
                                            <a href="{{ route('blog.show', $post) }}" class="home-minilist__link">
                                                <span class="home-minilist__title">{{ $post->title }}</span>
                                                <span class="home-minilist__meta">{{ $post->teacher->name }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @else
                            <div class="home-feed-empty">
                                <p class="home-feed-empty__text">Study tips written by teachers will show up here.</p>
                                <a href="{{ route('blog.index') }}" class="home-link">
                                    Browse blog archive
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        @endif
                    </x-ui.card>

                    <x-ui.card stretch aria-labelledby="forum-title">
                        <x-slot:header>
                            <h2 id="forum-title" class="home-panel__title">Forum highlights</h2>
                            <div class="home-panel__links">
                                <button type="button" class="home-link" @click="$dispatch('open-modal', 'modal-ask-question')">Ask</button>
                                <a href="{{ route('forum.index') }}" class="home-link">
                                    Open
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </x-slot:header>

                        @if($latestThreads->isNotEmpty())
                            <ul class="home-minilist home-minilist--flush">
                                @foreach($latestThreads->take(4) as $thread)
                                    <li>
                                        <a href="{{ route('forum.show', $thread) }}" class="home-minilist__link">
                                            <span class="home-minilist__title">{{ $thread->title }}</span>
                                            <span class="home-minilist__meta">
                                                {{ $thread->category }}
                                                <span aria-hidden="true">&middot;</span>
                                                {{ $thread->replies_count }} {{ \Illuminate\Support\Str::plural('reply', $thread->replies_count) }}
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="home-feed-empty">
                                <p class="home-feed-empty__text">Ask the first question and a teacher will answer it.</p>
                                <div class="home-feed-empty__actions">
                                    <a href="{{ route('forum.index') }}" class="home-link">
                                        Open forum
                                        <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                                    </a>
                                    <span aria-hidden="true">&middot;</span>
                                    <button type="button" class="home-link" @click="$dispatch('open-modal', 'modal-ask-question')">
                                        Ask a question
                                    </button>
                                </div>
                            </div>
                        @endif
                    </x-ui.card>
                </div>