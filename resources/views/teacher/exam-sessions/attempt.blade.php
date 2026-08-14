@php
    $user = auth()->user();
    $candidateName = $attempt->guest_name ?: $attempt->user->name;
@endphp

<x-layouts.student :user="$user" title="{{ $candidateName }}" header-type="none">
    @push('styles')
        @vite([
            'resources/css/classroom-workspace.css',
            'resources/css/student/analytics.css',
            'resources/css/teacher/exam-sessions.css',
        ])
    @endpush

    <div class="app-shell app-shell--no-list">
        <x-shell.icon-rail :logo-href="route('teacher.progress')" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::teacher('exam-sessions')" />

        <main class="shell-content">
            <x-ui.flash />

            {{-- The Alpine scope wraps the Livewire component rather than living
                 inside it: the modal below holds fetched HTML and open/closed
                 state, and keeping it outside means Livewire's 5s poll can never
                 morph away a question a teacher is mid-way through reading.
                 `finally` clears `loading` on every path — a throw raised before
                 the promise chain existed used to strand the skeleton forever. --}}
            <div class="home" x-data="{
                    selectedAnswerId: null,
                    previewHtml: '',
                    loading: false,
                    previewUrl: @js(route('teacher.exam-sessions.attempts.question-preview', [$examSession, ':answerId'])),
                    async fetchPreview(answerId) {
                        if (this.selectedAnswerId === answerId) return;
                        this.selectedAnswerId = answerId;
                        this.loading = true;
                        this.previewHtml = '';
                        try {
                            const res = await fetch(this.previewUrl.replace(':answerId', answerId), {
                                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                            });
                            if (!res.ok) throw new Error('Request failed');
                            this.previewHtml = await res.text();
                        } catch (error) {
                            console.error(error);
                            this.previewHtml = @js('<p class="text-sm text-rose-700">This question could not be loaded. Close and try again.</p>');
                        } finally {
                            this.loading = false;
                        }
                    },
                    close() { this.selectedAnswerId = null; this.previewHtml = ''; },
                }">
                <header class="home-head">
                    <div class="home-head__lead">
                        <div class="home-head__meta">
                            <a href="{{ route('teacher.exam-sessions.show', $examSession) }}" class="home-link">
                                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" />
                                {{ $examSession->title }}
                            </a>
                            <x-ui.status-badge
                                :status="match ($attempt->status) {
                                    'completed' => 'success',
                                    'in_progress' => 'brand',
                                    default => 'neutral',
                                }">
                                {{ ucfirst(str_replace('_', ' ', $attempt->status)) }}
                            </x-ui.status-badge>
                        </div>
                        <h1 class="home-title">{{ $candidateName }}</h1>
                        <p class="home-subtitle">{{ $examSession->test->title }}</p>
                    </div>

                    <div class="home-head__actions">
                        @if ($attempt->status === 'completed' || $attempt->total_score !== null)
                            <x-ui.button size="sm" variant="secondary" :href="route('student.scores.show', $attempt)">
                                <x-slot:icon><x-ui.icon name="graph-up-arrow" class="w-4 h-4" /></x-slot:icon>
                                Score report
                            </x-ui.button>
                        @endif
                    </div>
                </header>

                <livewire:teacher.exam-attempt-monitor :exam-session="$examSession" :attempt="$attempt" />

                {{-- Question detail dialog. Outside the Livewire component, so a
                     poll never disturbs it.

                     The `:class` flex/hidden toggle is what actually shows and
                     hides this — Tailwind runs in `important` mode (app.css:1),
                     so x-show's inline display cannot win against a utility
                     class. Do not "simplify" it away. --}}
                <div x-show="selectedAnswerId" x-cloak style="display: none;"
                    class="fixed inset-0 z-50 items-center justify-center p-4"
                    :class="selectedAnswerId ? 'flex' : 'hidden'" role="dialog" aria-modal="true"
                    aria-labelledby="questionDetailTitle" @keydown.escape.window="close()">
                    <div class="fixed inset-0 bg-slate-900/50" @click="close()"></div>

                    <div class="relative bg-white rounded-xl shadow-2xl border border-slate-200 w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                            <h2 id="questionDetailTitle" class="text-base font-semibold text-slate-900 m-0">
                                Question detail
                            </h2>
                            <button type="button"
                                class="text-slate-400 hover:text-slate-600 transition-colors focus:outline-none p-1.5 rounded-full hover:bg-slate-200/50"
                                @click="close()" aria-label="Close question detail">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="p-6 overflow-y-auto flex-1 bg-[#FCFBF7]">
                            {{-- x-if, NOT x-show. resources/css/app.css:1 imports
                                 Tailwind in `important` mode, so every utility
                                 lands as `!important` — `.flex{display:flex!important}`
                                 in the built CSS. x-show only sets an inline
                                 `display:none`, which loses to that, so it can
                                 never hide an element carrying a display utility.
                                 x-if removes the node instead, which no CSS can
                                 override. (This is also why Alpine code elsewhere
                                 in the app pairs x-show with a :class toggle —
                                 that pairing is load-bearing, not redundant.) --}}
                            <template x-if="loading">
                                <div class="animate-pulse flex flex-col gap-4">
                                    <div class="h-4 bg-slate-200 rounded w-1/4"></div>
                                    <div class="h-3 bg-slate-200 rounded w-full"></div>
                                    <div class="h-3 bg-slate-200 rounded w-5/6"></div>
                                    <div class="h-10 bg-slate-100 rounded w-full"></div>
                                </div>
                            </template>

                            {{-- Always present; `previewHtml` is cleared while a
                                 request is in flight, so there is nothing to hide. --}}
                            <div x-html="previewHtml"></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</x-layouts.student>
