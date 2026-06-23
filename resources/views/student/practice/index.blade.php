<x-layouts.student :user="$user" header-type="progress" title="Choose Practice" :cancel-route="route('home')">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/student/practice.css'])
    @endpush

    <div class="ds-home ds-practice-page">
        <section class="ds-workspace-head" aria-labelledby="choose-practice-title">
            <div class="ds-profile-panel">
                <div>
                    <h1 id="choose-practice-title">Choose a full-length practice test</h1>
                    <p class="ds-workspace-copy">
                        Pick an active practice test, then continue an unfinished attempt or start fresh.
                    </p>
                </div>
            </div>

            <div class="ds-workspace-actions">
                <a href="{{ route('test.preview') }}" class="ds-button ds-button--secondary">
                    Preview test format
                </a>
            </div>
        </section>

        <div id="ajaxErrorContainer" class="ds-alert hidden" role="alert" style="margin-bottom: 1.25rem;">
            <div class="ds-alert__content" style="display: flex; align-items: center; gap: 0.75rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                    stroke="currentColor" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <span id="ajaxErrorMessage"></span>
            </div>
        </div>

        @php
            $inProgressAttempts = $tests
                ->flatMap(fn($t) => $t->userTests)
                ->where('status', 'in_progress')
                ->sortByDesc('updated_at');
        @endphp

        @if ($inProgressAttempts->isNotEmpty())
            <section class="ds-resume-section" aria-labelledby="active-work-title">
                <h2 id="active-work-title" class="ds-section-heading">Continue where you left off</h2>
                <div class="ds-resume-grid">
                    @foreach ($inProgressAttempts as $attempt)
                        @php
                            $moduleUlid =
                                $attempt->currentModule?->ulid ??
                                $attempt->test?->sections?->first()?->modules?->first()?->ulid;
                            $currentModule = $attempt->currentModule;
                            $currentSection = $currentModule?->section;
                        @endphp
                        <article class="ds-resume-card">
                            <div class="ds-resume-card__body">
                                <div class="ds-resume-card__info">
                                    <div class="ds-resume-card__icon-wrapper" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="ds-resume-card__title">{{ $attempt->test->title }}</h3>
                                        <p class="ds-resume-card__status">
                                            @if ($currentModule && $currentSection)
                                                Currently on: {{ $currentSection->name }} • Module
                                                {{ $currentModule->module_number }}
                                            @else
                                                Ready to start: Section 1, Module 1
                                            @endif
                                        </p>
                                        <span class="ds-resume-card__meta">
                                            Last active {{ $attempt->updated_at->diffForHumans() }}
                                        </span>
                                    </div>
                                </div>

                                <div class="ds-resume-card__action-wrapper">
                                    @if ($moduleUlid)
                                        <a href="{{ route('engine.session', ['ulid' => $moduleUlid]) }}?attempt={{ $attempt->ulid }}"
                                            class="ds-button ds-button--primary ds-resume-card__button">
                                            Resume Practice
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="2.5" stroke="currentColor" class="w-4 h-4 ml-2"
                                                aria-hidden="true"
                                                style="margin-right: -0.25rem; display: inline-block; width: 1rem; height: 1rem; vertical-align: text-bottom; margin-left: 0.5rem;">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                            </svg>
                                        </a>
                                    @else
                                        <button class="ds-button ds-button--secondary" disabled>
                                            Unavailable
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="ds-dashboard-grid" aria-labelledby="practice-library-title">
            <article class="ds-card ds-practice-library">
                <div class="ds-card__header">
                    <div>
                        <h2 id="practice-library-title" class="ds-card-title">Available tests</h2>
                    </div>
                    @if ($tests->isNotEmpty())
                        <span class="ds-card-note">{{ $tests->count() }}
                            {{ \Illuminate\Support\Str::plural('test', $tests->count()) }}</span>
                    @endif
                </div>

                @if ($tests->isNotEmpty())
                    <div class="ds-test-grid">
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
                            <article class="ds-test-card {{ $inProgressAttempt ? 'ds-test-card--in-progress' : '' }}"
                                aria-labelledby="test-card-{{ $test->id }}-title">
                                <div class="ds-test-card__body">
                                    <div class="ds-test-card__topline">
                                        <span>{{ $test->test_type ? \Illuminate\Support\Str::headline($test->test_type) : 'Full-length' }}</span>
                                        @if ($inProgressAttempt)
                                            <span class="ds-badge ds-badge--in-progress">In Progress</span>
                                        @elseif($latestCompletedAttempt)
                                            <span class="ds-badge ds-badge--completed">Completed
                                                @if($latestCompletedAttempt->total_score !== null)
                                                    &bull; Estimated {{ $latestCompletedAttempt->total_score }}
                                                @endif
                                            </span>
                                        @else
                                            <span class="ds-badge ds-badge--new">New</span>
                                        @endif
                                    </div>
                                    <h3 id="test-card-{{ $test->id }}-title">{{ $test->title }}</h3>
                                    @if ($test->description)
                                        <p>{{ \Illuminate\Support\Str::limit($test->description, 140) }}</p>
                                    @else
                                        <p>Complete a scored digital SAT practice session and review your results
                                            afterward.</p>
                                    @endif
                                </div>

                                <div class="ds-test-card__meta-flat">
                                    <span>{{ $duration ?: '--' }} min</span>
                                    <span class="ds-meta-dot" aria-hidden="true">•</span>
                                    <span>{{ $sections->count() ?: '--' }} sections</span>
                                    <span class="ds-meta-dot" aria-hidden="true">•</span>
                                    <span>{{ $modules->count() ?: '--' }} modules</span>
                                </div>

                                <button type="button" class="ds-button ds-button--primary ds-test-card__action"
                                    data-test-id="{{ $test->id }}">
                                    @if ($inProgressAttempt)
                                        Resume practice
                                    @elseif($latestCompletedAttempt)
                                        Retake Test
                                    @else
                                        Start practice
                                    @endif
                                </button>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="ds-empty ds-empty--compact">
                        <h4>No active practice tests</h4>
                        <p>Ask a teacher to publish a practice test, then return here to begin.</p>
                    </div>
                @endif
            </article>
        </section>
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
                <button id="btnContinueAttempt" type="button" class="ds-button ds-button--primary">
                    Continue in progress
                </button>
                <button id="btnFreshAttempt" type="button" class="ds-button ds-button--secondary">
                    Start fresh
                </button>
                <button id="btnCancelAttempt" type="button" class="ds-button ds-button--secondary"
                    style="border-color: transparent; background: transparent;">
                    Cancel
                </button>
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
                const testActions = document.querySelectorAll('.ds-test-card__action[data-test-id]');

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
