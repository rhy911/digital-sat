<x-layouts.student :user="$user" title="Test Library" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/practice.css', 'resources/css/student/analytics.css'])
    @endpush

    @php
        $inProgressAttempts = $tests
            ->flatMap(fn($t) => $t->userTests)
            ->where('status', 'in_progress')
            ->sortByDesc('updated_at');

    @endphp

    <div class="app-shell app-shell--no-list">
        <!-- COLUMN 1: ICON RAIL -->
        <x-shell.icon-rail :logo-href="\App\Support\NavRail::logoHrefForUser($user)" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::forUser($user, 'practice')" />

        <!-- COLUMN 2: CONTENT -->
        <div class="shell-content">
            <x-ui.flash />

            <div class="binder-panel">
                <div class="ledger-header">
                    <div class="dh-left">
                        <h2>{{ __('classroom.nav_practice') }} <span class="handwriting status-quote">"Practice makes perfect"</span></h2>
                        <div class="dh-desc">
                            Pick an active practice test, then continue an unfinished attempt or start fresh.
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('test.preview') }}" class="btn-outline">Preview test format</a>
                    </div>
                </div>

                <div id="ajaxErrorContainer" class="toast toast-error hidden" role="alert" style="margin-top: 18px;">
                    <span id="ajaxErrorMessage"></span>
                </div>

                @if ($inProgressAttempts->isNotEmpty())
                    <section class="library-section" aria-labelledby="active-work-title">
                        <div class="library-section-head">
                            <h3 id="active-work-title" class="library-section-heading">Continue where you left off</h3>
                        </div>
                        <div class="library-resume-grid">
                            @foreach ($inProgressAttempts as $attempt)
                                @php
                                    $moduleUlid =
                                        $attempt->currentModule?->ulid ??
                                        $attempt->test?->sections?->first()?->modules?->first()?->ulid;
                                    $currentModule = $attempt->currentModule;
                                    $currentSection = $currentModule?->section;
                                @endphp
                                <article class="library-resume-card">
                                    <div class="library-resume-card__info">
                                        <div class="library-resume-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke-linecap="round" stroke-linejoin="round" />
                                                <path d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </div>
                                        <div>
                                            <span class="library-resume-card__title">{{ $attempt->test->title }}</span>
                                            <span class="library-resume-card__status">
                                                @if ($currentModule && $currentSection)
                                                    Currently on: {{ $currentSection->name }} &bull; Module {{ $currentModule->module_number }}
                                                @else
                                                    Ready to start: Section 1, Module 1
                                                @endif
                                            </span>
                                            <span class="library-resume-card__meta">
                                                Last active {{ $attempt->updated_at->diffForHumans() }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="library-resume-card__action">
                                        @if ($moduleUlid)
                                            <a href="{{ route('engine.session', ['ulid' => $moduleUlid]) }}?attempt={{ $attempt->ulid }}"
                                                class="library-btn-primary">
                                                Resume
                                            </a>
                                        @else
                                            <span class="library-btn-outline" aria-disabled="true">Unavailable</span>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="library-section" aria-labelledby="practice-library-title">
                    <div class="library-section-head">
                        <h3 id="practice-library-title" class="library-section-heading">Available tests</h3>
                        @if ($tests->isNotEmpty())
                            <span class="library-count">{{ $tests->count() }}
                                {{ \Illuminate\Support\Str::plural('test', $tests->count()) }}</span>
                        @endif
                    </div>

                    @if ($tests->isNotEmpty())
                        <div class="tests-grid">
                            @foreach ($tests as $test)
                                @php
                                    $sections = $test->sections;
                                    $modules = $sections->flatMap->modules->unique('id');
                                    $duration =
                                        $test->total_duration_minutes ?:
                                        $sections->sum(
                                            fn($section) => $section->modules
                                                ->unique('module_number')
                                                ->sum('duration_minutes'),
                                        );

                                    $inProgressAttempt = $test->userTests->firstWhere('status', 'in_progress');
                                    $latestCompletedAttempt = $test->userTests->firstWhere('status', 'completed');
                                @endphp
                                <article class="index-card test-index-card {{ $inProgressAttempt ? 'is-in-progress' : '' }}"
                                    aria-labelledby="test-card-{{ $test->id }}-title">
                                    <div class="test-card-top">
                                        <span class="type-tag">{{ $test->test_type ? \Illuminate\Support\Str::headline($test->test_type) : 'Full-length' }}</span>
                                        @if ($inProgressAttempt)
                                            <span class="status-pill pending"><span class="d"></span>In Progress</span>
                                        @elseif($latestCompletedAttempt)
                                            <span class="status-pill ok"><span class="d"></span>Completed</span>
                                        @else
                                            <span class="status-pill new"><span class="d"></span>New</span>
                                        @endif
                                    </div>
                                    <div class="card-title" id="test-card-{{ $test->id }}-title">{{ $test->title }}</div>
                                    @if ($test->description)
                                        <p class="test-card-desc">{{ \Illuminate\Support\Str::limit($test->description, 140) }}</p>
                                    @else
                                        <p class="test-card-desc">Complete a scored digital SAT practice session and review your results afterward.</p>
                                    @endif

                                    <div class="test-card-meta-flat">
                                        <span>{{ $duration ?: '--' }} min</span>
                                        <span class="dot-sep" aria-hidden="true">&bull;</span>
                                        <span>{{ $sections->count() ?: '--' }} sections</span>
                                        <span class="dot-sep" aria-hidden="true">&bull;</span>
                                        <span>{{ $modules->count() ?: '--' }} modules</span>
                                        @if ($latestCompletedAttempt?->total_score !== null)
                                            <span class="dot-sep" aria-hidden="true">&bull;</span>
                                            <span>Best {{ $latestCompletedAttempt->total_score }}</span>
                                        @endif
                                    </div>

                                    <div class="test-card-action">
                                        <button type="button" class="library-btn-primary" data-test-id="{{ $test->id }}">
                                            @if ($inProgressAttempt)
                                                Resume practice
                                            @elseif($latestCompletedAttempt)
                                                Retake test
                                            @else
                                                Start practice
                                            @endif
                                        </button>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-grid-cell">
                            <strong>No active practice tests</strong>
                            <p style="margin-top: 4px;">Ask a teacher to publish a practice test, then return here to begin.</p>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </div>

    <div id="attemptModal" class="ds-modal" aria-modal="true" role="dialog" aria-labelledby="attemptModalTitle">
        <div class="ds-modal__backdrop"></div>

        <div class="ds-modal__content">
            <div class="ds-modal__icon ds-modal__icon--warning">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="h-6 w-6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
            </div>
            <div>
                <h3 id="attemptModalTitle" class="ds-modal__title">Resume or start fresh?</h3>
                <p class="ds-modal__body">
                    You have an unfinished attempt for this practice test. Continue where you left off or start a fresh
                    attempt.
                </p>
            </div>

            <div class="ds-modal__actions">
                <x-ui.button id="btnContinueAttempt" type="button" variant="primary">
                    Continue in progress
                </x-ui.button>
                <x-ui.button id="btnFreshAttempt" type="button" variant="secondary">
                    Start fresh
                </x-ui.button>
                <x-ui.button id="btnCancelAttempt" type="button" variant="secondary"
                    style="border-color: transparent; background: transparent;">
                    Cancel
                </x-ui.button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function triggerLoadingScreen(message = 'Preparing your test...') {
                const loadingScreen = document.getElementById('loadingScreen');
                const loadingStatusText = document.getElementById('loadingStatusText');

                if (loadingStatusText) {
                    loadingStatusText.textContent = message;
                }

                if (loadingScreen) {
                    loadingScreen.classList.remove('hidden');
                    loadingScreen.setAttribute('aria-hidden', 'false');
                }

                document.body.style.cursor = 'wait';
            }

            function hideLoadingScreen() {
                const loadingScreen = document.getElementById('loadingScreen');

                if (loadingScreen) {
                    loadingScreen.classList.add('hidden');
                    loadingScreen.setAttribute('aria-hidden', 'true');
                }

                document.body.style.cursor = '';
            }

            function navigateAfterLoaderPaint(href) {
                triggerLoadingScreen();
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        window.location.href = href;
                    });
                });
            }

            function showAjaxError(message) {
                const errorContainer = document.getElementById('ajaxErrorContainer');
                const errorMessage = document.getElementById('ajaxErrorMessage');
                if (errorContainer && errorMessage) {
                    errorMessage.textContent = message;
                    errorContainer.classList.remove('hidden');
                    errorContainer.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });
                }
            }

            function hideAjaxError() {
                const errorContainer = document.getElementById('ajaxErrorContainer');
                if (errorContainer) {
                    errorContainer.classList.add('hidden');
                }
            }

            let lastFocusedElement = null;
            let activeKeydownListener = null;

            function showAttemptOptionsModal(testId, options) {
                const modal = document.getElementById('attemptModal');
                const backdrop = modal?.querySelector('.ds-modal__backdrop');
                const content = modal?.querySelector('.ds-modal__content');
                const btnContinue = document.getElementById('btnContinueAttempt');
                const btnFresh = document.getElementById('btnFreshAttempt');
                const btnCancel = document.getElementById('btnCancelAttempt');

                if (!modal || !backdrop || !content || !btnContinue || !btnFresh || !btnCancel) {
                    return;
                }

                hideAjaxError();
                lastFocusedElement = document.activeElement;

                btnContinue.onclick = function() {
                    hideAttemptModal();
                    const redirectUrl =
                        `/engine/session/${options.latest_in_progress_current_module_ulid}?attempt=${options.latest_in_progress_ulid}`;
                    navigateAfterLoaderPaint(redirectUrl);
                };

                btnFresh.onclick = function() {
                    hideAttemptModal();
                    startTestFresh(testId, options.first_module_ulid);
                };

                btnCancel.onclick = hideAttemptModal;

                // Escape key and Focus Trap
                activeKeydownListener = function(e) {
                    if (e.key === 'Escape') {
                        hideAttemptModal();
                        e.preventDefault();
                        return;
                    }
                    if (e.key === 'Tab') {
                        const focusables = [btnContinue, btnFresh, btnCancel];
                        const first = focusables[0];
                        const last = focusables[focusables.length - 1];
                        if (e.shiftKey) {
                            if (document.activeElement === first) {
                                last.focus();
                                e.preventDefault();
                            }
                        } else {
                            if (document.activeElement === last) {
                                first.focus();
                                e.preventDefault();
                            }
                        }
                    }
                };

                modal.classList.add('show');
                window.setTimeout(() => {
                    backdrop.classList.add('is-visible');
                    content.classList.add('is-visible');
                    btnContinue.focus();
                }, 10);

                window.addEventListener('keydown', activeKeydownListener);
            }

            function hideAttemptModal() {
                const modal = document.getElementById('attemptModal');
                const backdrop = modal?.querySelector('.ds-modal__backdrop');
                const content = modal?.querySelector('.ds-modal__content');

                if (!modal || !backdrop || !content) {
                    return;
                }

                if (activeKeydownListener) {
                    window.removeEventListener('keydown', activeKeydownListener);
                    activeKeydownListener = null;
                }

                backdrop.classList.remove('is-visible');
                content.classList.remove('is-visible');
                window.setTimeout(() => {
                    modal.classList.remove('show');
                    if (lastFocusedElement) {
                        lastFocusedElement.focus();
                        lastFocusedElement = null;
                    }
                }, 250);
            }

            async function startTestFresh(testId, firstModuleUlid) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                hideAjaxError();
                triggerLoadingScreen('Creating fresh attempt...');

                try {
                    const response = await fetch(`/engine/test/start/${testId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            mode: 'fresh'
                        }),
                    });

                    if (!response.ok) {
                        throw new Error('Failed to start test');
                    }

                    const data = await response.json();

                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                        return;
                    }

                    window.location.href =
                        `/engine/session/${data.first_module_ulid || firstModuleUlid}?attempt=${data.user_test_ulid}`;
                } catch (err) {
                    console.error(err);
                    hideLoadingScreen();
                    showAjaxError('Could not start a new attempt. Please try again.');
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                const testActions = document.querySelectorAll('.test-index-card [data-test-id]');

                testActions.forEach(action => {
                    action.addEventListener('click', async function() {
                        const testId = this.dataset.testId;
                        if (!testId) {
                            return;
                        }

                        hideAjaxError();
                        try {
                            const response = await fetch(`/engine/test/${testId}/attempt-options`, {
                                headers: {
                                    'Accept': 'application/json',
                                },
                            });

                            if (!response.ok) {
                                throw new Error('Failed to load attempt options');
                            }

                            const optionsData = await response.json();

                            if (optionsData.has_in_progress) {
                                showAttemptOptionsModal(testId, optionsData);
                                return;
                            }

                            startTestFresh(testId, optionsData.first_module_ulid);
                        } catch (err) {
                            console.error(err);
                            showAjaxError(
                                'An error occurred while loading attempt options. Please try again.'
                                );
                        }
                    });
                });
            });
        </script>
    @endpush
</x-layouts.student>
