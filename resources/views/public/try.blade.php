<x-layouts.landing title="Try DFET SAT — Practice without an account">
@php
    $rwTestUlid = (string) config('public_trial.tests.reading_writing');
    $mathTestUlid = (string) config('public_trial.tests.math');
    $fullTestUlid = (string) config('public_trial.tests.full');

    $inProgressRw = $attempts->first(fn ($a) => $a->status === 'in_progress' && $a->test?->ulid === $rwTestUlid);
    $inProgressMath = $attempts->first(fn ($a) => $a->status === 'in_progress' && $a->test?->ulid === $mathTestUlid);
    $inProgressFull = $attempts->first(fn ($a) => $a->status === 'in_progress' && $a->test?->ulid === $fullTestUlid);

    $rwResumeUrl = $inProgressRw ? route('engine.session', [
        'ulid' => $inProgressRw->currentModule?->ulid ?? $inProgressRw->test?->sections?->first()?->modules?->first()?->ulid,
        'attempt' => $inProgressRw->ulid,
    ]) : null;

    $mathResumeUrl = $inProgressMath ? route('engine.session', [
        'ulid' => $inProgressMath->currentModule?->ulid ?? $inProgressMath->test?->sections?->first()?->modules?->first()?->ulid,
        'attempt' => $inProgressMath->ulid,
    ]) : null;

    $fullResumeUrl = $inProgressFull ? route('engine.session', [
        'ulid' => $inProgressFull->currentModule?->ulid ?? $inProgressFull->test?->sections?->first()?->modules?->first()?->ulid,
        'attempt' => $inProgressFull->ulid,
    ]) : null;

    $activeAttemptsConfig = [
        'reading_writing' => $inProgressRw && $rwResumeUrl ? [
            'label' => 'Reading & Writing',
            'url' => $rwResumeUrl,
        ] : null,
        'math' => $inProgressMath && $mathResumeUrl ? [
            'label' => 'Math',
            'url' => $mathResumeUrl,
        ] : null,
        'full' => $inProgressFull && $fullResumeUrl ? [
            'label' => 'Full Practice Test',
            'url' => $fullResumeUrl,
        ] : null,
    ];
@endphp
    <div x-data="trialLauncher({ activeAttempts: {{ Js::from($activeAttemptsConfig) }} })">
    <section class="bg-[#F6F8FC] px-5 py-16 sm:px-6 lg:py-24">
        <div class="mx-auto max-w-6xl">
            <a href="/" class="text-sm font-semibold text-blue-700 hover:underline">← Back to DFET SAT</a>
            <div class="mt-8 max-w-3xl">
                <p class="font-bold uppercase tracking-[.16em] text-blue-700">Try before you sign up</p>
                <h1 class="mt-3 text-4xl font-extrabold tracking-tight text-[#10234A] sm:text-5xl">Choose how much you want to try.</h1>
                <p class="mt-5 text-lg leading-8 text-slate-600">Explore the interface, practice one complete module, or take an adaptive full test. You can start without an account.</p>
            </div>

            @if (session('error'))
                <div role="alert" class="mt-8 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950">{{ session('error') }}</div>
            @endif

            @if ($attempts->contains(fn ($attempt) => $attempt->status === 'in_progress'))
                <div class="mt-8 rounded-2xl border-2 border-blue-600 bg-blue-50 p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <span class="text-2xs font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-md bg-blue-200 text-blue-900">In Progress</span>
                            <h2 class="mt-2 text-xl font-bold text-[#10234A]">You have an in-progress practice attempt</h2>
                            <p class="mt-1 text-sm text-slate-600">Your test session is still active. Continue where you left off without losing your progress.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 shrink-0">
                            @if ($inProgressRw && $rwResumeUrl)
                                <a href="{{ $rwResumeUrl }}"
                                   @click.prevent="openResume('Reading & Writing', '{{ $rwResumeUrl }}')"
                                   class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-blue-700 px-5 text-sm font-bold text-white shadow-sm hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 cursor-pointer">
                                    <span>Continue Reading &amp; Writing</span> &rarr;
                                </a>
                            @endif
                            @if ($inProgressMath && $mathResumeUrl)
                                <a href="{{ $mathResumeUrl }}"
                                   @click.prevent="openResume('Math', '{{ $mathResumeUrl }}')"
                                   class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-blue-700 px-5 text-sm font-bold text-white shadow-sm hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 cursor-pointer">
                                    <span>Continue Math</span> &rarr;
                                </a>
                            @endif
                            @if ($inProgressFull && $fullResumeUrl)
                                <a href="{{ $fullResumeUrl }}"
                                   @click.prevent="openResume('Full Practice Test', '{{ $fullResumeUrl }}')"
                                   class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-blue-700 px-5 text-sm font-bold text-white shadow-sm hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 cursor-pointer">
                                    <span>Continue Full Practice Test</span> &rarr;
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-12 grid gap-5 lg:grid-cols-3">
                <article class="flex flex-col rounded-2xl border-2 border-[#F6B93B] bg-white p-6 shadow-sm sm:p-8">
                    <span class="text-sm font-bold uppercase tracking-wide text-blue-700">Start here</span>
                    <h2 class="mt-3 text-2xl font-bold text-[#10234A]">Explore the interface</h2>
                    <p class="mt-3 flex-1 leading-7 text-slate-600">Try 6 Reading and Writing questions and 6 Math questions. This untimed demo shows the answer formats and test tools. It does not save answers or produce a score.</p>
                    <x-ui.button href="{{ route('public-trial.demo', 'reading_writing') }}" @click.prevent="openDemo('{{ route('public-trial.demo', 'reading_writing') }}')" class="mt-6 w-full">Explore the interface</x-ui.button>
                    <p class="mt-3 text-xs text-slate-500">No account or guest record is created.</p>
                </article>

                <article class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
                    <span class="text-sm font-bold uppercase tracking-wide text-blue-700">One subject</span>
                    <h2 class="mt-3 text-2xl font-bold text-[#10234A]">Try a module</h2>
                    <p class="mt-3 leading-7 text-slate-600">Choose one full section module. Your result shows practice performance and one sample review.</p>

                    @if ($inProgressRw || $inProgressMath)
                        <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50/80 p-3.5 space-y-2 text-xs">
                            <div class="font-bold uppercase tracking-wider text-2xs text-blue-800">In-progress practice attempt</div>
                            @if ($inProgressRw && $rwResumeUrl)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-slate-800">Reading &amp; Writing</span>
                                    <a href="{{ $rwResumeUrl }}"
                                       @click.prevent="openResume('Reading & Writing', '{{ $rwResumeUrl }}')"
                                       class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-blue-700 hover:bg-blue-800 text-white font-bold text-xs shadow-sm transition-colors cursor-pointer">
                                        <span>Continue</span> &rarr;
                                    </a>
                                </div>
                            @endif
                            @if ($inProgressMath && $mathResumeUrl)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-slate-800">Math</span>
                                    <a href="{{ $mathResumeUrl }}"
                                       @click.prevent="openResume('Math', '{{ $mathResumeUrl }}')"
                                       class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-blue-700 hover:bg-blue-800 text-white font-bold text-xs shadow-sm transition-colors cursor-pointer">
                                        <span>Continue</span> &rarr;
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif

                    <form method="POST" action="{{ route('public-trial.start') }}" class="mt-auto pt-6" @submit.prevent="openModule($el)">
                        @csrf
                        <input type="hidden" name="experience" value="module">
                        <label for="trial-section" class="mb-2 block text-sm font-semibold text-slate-800">Section</label>
                        <select id="trial-section" name="section" x-model="moduleSection" required class="min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3 text-base focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                            <option value="" selected disabled>Choose a section</option>
                            <option value="reading_writing" @disabled(! $options['reading_writing']['available'])>Reading and Writing — 27 questions, 32 minutes{{ $inProgressRw ? ' (In progress)' : '' }}</option>
                            <option value="math" @disabled(! $options['math']['available'])>Math — 22 questions, 35 minutes{{ $inProgressMath ? ' (In progress)' : '' }}</option>
                        </select>
                        <x-ui.button type="submit" class="mt-4 w-full cursor-pointer" :disabled="true" x-bind:disabled="!moduleSection || {{ ! $options['reading_writing']['available'] && ! $options['math']['available'] ? 'true' : 'false' }}">
                            <span x-text="moduleButtonText">Start a module</span>
                        </x-ui.button>
                    </form>
                    @if (! $options['reading_writing']['available'] && ! $options['math']['available'])<p class="mt-3 text-sm text-amber-800">Modules are temporarily unavailable. Try the interface demo.</p>@endif
                </article>

                <article class="flex flex-col rounded-2xl border border-slate-200 bg-[#10234A] p-6 text-white sm:p-8">
                    <span class="text-sm font-bold uppercase tracking-wide text-[#F6B93B]">Full experience</span>
                    <h2 class="mt-3 text-2xl font-bold">Take a full practice test</h2>
                    <p class="mt-3 flex-1 leading-7 text-blue-100">Take one adaptive SAT practice test: 98 questions, 134 minutes of test time, plus the scheduled break. Your result includes an estimated score range when available.</p>

                    @if ($inProgressFull && $fullResumeUrl)
                        <div class="mt-4 rounded-xl border border-blue-400/40 bg-blue-900/60 p-3.5 space-y-2 text-xs text-blue-100">
                            <div class="font-bold uppercase tracking-wider text-2xs text-[#F6B93B]">In-progress practice attempt</div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-white">Full Practice Test</span>
                                <a href="{{ $fullResumeUrl }}"
                                   @click.prevent="openResume('Full Practice Test', '{{ $fullResumeUrl }}')"
                                   class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-[#F6B93B] hover:bg-[#e5aa32] text-[#10234A] font-bold text-xs shadow-sm transition-colors cursor-pointer">
                                    <span>Continue</span> &rarr;
                                </a>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('public-trial.start') }}" class="mt-6" @submit.prevent="openFull($el)">
                        @csrf
                        <input type="hidden" name="experience" value="full">
                        <x-ui.button type="submit" class="w-full cursor-pointer" :disabled="! $options['full']['available']">
                            <span x-text="fullButtonText">Start full practice test</span>
                        </x-ui.button>
                    </form>
                    @if (! $options['full']['available'])<p class="mt-3 text-sm text-blue-100">This practice test is temporarily unavailable. Try the interface demo.</p>@endif
                </article>
            </div>

            <aside class="mt-10 rounded-xl border border-slate-200 bg-white p-5 text-sm leading-6 text-slate-600">
                <h2 class="font-bold text-[#10234A]">Before you start</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Module and full-test results are available as soon as you finish. An account unlocks complete answer review, skill analysis, and saved history.</li>
                    <li>The estimate is for practice and is not an official College Board score.</li>
                    <li>The test engine requires a screen at least 900 × 500 pixels. Open it on a larger screen to start a timed test.</li>
                    <li>Without an account, return to this result while your browser session remains active.</li>
                    <li>Guest attempts expire after 30 minutes without activity and no later than 24 hours after starting. Closing your browser does not guarantee immediate server deletion.</li>
                </ul>
            </aside>
        </div>
    </section>

    <!-- Guest Engine Entry Confirmation Modal -->
    <template x-teleport="body">
        <div id="guest-engine-confirm-modal"
             data-modal-dialog="guest-engine-confirm-modal"
             x-show="showModal"
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             role="dialog"
             aria-modal="true"
             aria-labelledby="guest-confirm-modal-title"
             :aria-hidden="showModal ? 'false' : 'true'">
            <!-- Backdrop -->
            <div x-show="showModal"
                 x-transition:enter="transition-opacity ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm"
                 @click="closeModal()"
                 aria-hidden="true"></div>

            <!-- Modal Panel -->
            <div class="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4 text-center">
                <div x-show="showModal"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-0 sm:scale-95"
                     class="relative flex flex-col w-full transform rounded-t-2xl sm:rounded-2xl bg-white text-left shadow-2xl sm:max-w-lg border border-slate-200 overflow-hidden">
                    <div class="sm:hidden w-12 h-1 bg-slate-300 rounded-full mx-auto my-2.5 shrink-0" aria-hidden="true"></div>

                    <!-- Header -->
                    <div class="bg-slate-50/95 px-5 py-4 sm:px-6 border-b border-slate-200/80 flex justify-between items-center shrink-0">
                        <div class="flex items-center gap-2">
                            <span class="text-2xs font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-md bg-blue-100 text-blue-800" x-text="badge"></span>
                            <h3 class="text-base sm:text-lg font-bold text-[#10234A] tracking-tight m-0" id="guest-confirm-modal-title" x-text="title"></h3>
                        </div>
                        <button type="button"
                                @click="closeModal()"
                                class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 rounded-lg p-1.5 transition-colors cursor-pointer"
                                aria-label="Close modal">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-5 py-5 sm:px-6 space-y-4 text-slate-700">
                        <p class="text-sm leading-relaxed text-slate-600" x-text="description"></p>

                        <!-- Checklist -->
                        <div class="rounded-xl bg-slate-50 border border-slate-200/90 p-4 space-y-2.5 text-xs text-slate-700">
                            <div class="font-bold uppercase tracking-wider text-2xs text-slate-500">Before entering the test engine</div>
                            <div class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="font-medium" x-text="questions"></span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="font-medium" x-text="duration === 'Untimed' ? 'Untimed practice — work at your own pace' : (isResuming ? 'Continues active test clock from where you left off' : 'Timer starts immediately upon entering the test engine')"></span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                <span>Desktop / laptop display verified (&ge; 900 &times; 500 px)</span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <span>Built-in Desmos calculator &amp; reference sheet active</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="bg-slate-50/95 px-5 py-3.5 sm:px-6 border-t border-slate-200/80 sm:flex sm:flex-row-reverse gap-3 shrink-0">
                        <button type="button"
                                @click="proceed()"
                                class="w-full sm:w-auto min-h-11 px-6 rounded-xl bg-[#10234A] hover:bg-[#1a346e] text-white text-sm font-bold shadow-sm transition-colors cursor-pointer flex items-center justify-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2">
                            <span x-text="confirmBtnText"></span>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </button>
                        <button type="button"
                                @click="closeModal()"
                                class="w-full sm:w-auto min-h-11 px-5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold transition-colors cursor-pointer mt-2 sm:mt-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
    </div>
    <script>
        function trialLauncher(config = {}) {
            return {
                showModal: false,
                title: '',
                badge: '',
                duration: '',
                questions: '',
                description: '',
                isResuming: false,
                confirmBtnText: 'Enter Test Engine',
                actionType: '',
                targetForm: null,
                targetUrl: '',
                activeAttempts: (config && config.activeAttempts) || {},
                moduleSection: '',

                get moduleButtonText() {
                    if (this.moduleSection && this.activeAttempts[this.moduleSection]) {
                        const label = this.moduleSection === 'reading_writing' ? 'Reading & Writing' : 'Math';
                        return 'Continue ' + label + ' Module';
                    }
                    return 'Start a module';
                },

                get fullButtonText() {
                    if (this.activeAttempts.full) {
                        return 'Continue Full Practice Test';
                    }
                    return 'Start full practice test';
                },

                openDemo(url) {
                    if (!this.checkScreen()) return;
                    this.title = 'Explore the Interface';
                    this.badge = 'Untimed Demo';
                    this.duration = 'Untimed';
                    this.questions = '12 sample questions (6 Reading & Writing, 6 Math)';
                    this.description = 'Try the official digital SAT tools, passage layouts, and question types without a timer, score, or saved record.';
                    this.isResuming = false;
                    this.confirmBtnText = 'Launch Demo Engine';
                    this.actionType = 'link';
                    this.targetUrl = url;
                    this.showModal = true;
                },

                openModule(form) {
                    const sectionSelect = form.querySelector('select[name="section"]');
                    if (!sectionSelect || !sectionSelect.value) {
                        sectionSelect?.reportValidity();
                        sectionSelect?.focus();
                        return;
                    }
                    if (!this.checkScreen()) return;

                    const section = sectionSelect.value;
                    const isRw = section === 'reading_writing';
                    const activeForSection = this.activeAttempts[section];

                    if (activeForSection) {
                        this.title = isRw ? 'Continue Reading & Writing Module' : 'Continue Math Module';
                        this.badge = 'In Progress';
                        this.duration = 'Continues active module clock';
                        this.questions = isRw ? '27 questions' : '22 questions';
                        this.description = isRw
                            ? 'You will return directly to your active Reading & Writing module test session in the engine.'
                            : 'You will return directly to your active Math module test session in the engine.';
                        this.confirmBtnText = 'Continue Module';
                        this.isResuming = true;
                        this.actionType = 'link';
                        this.targetUrl = activeForSection.url;
                    } else {
                        this.title = isRw ? 'Start Reading and Writing Module' : 'Start Math Module';
                        this.badge = isRw ? '32 Minutes' : '35 Minutes';
                        this.duration = isRw ? '32 minutes' : '35 minutes';
                        this.questions = isRw ? '27 questions' : '22 questions';
                        this.description = isRw
                            ? 'You will take one full Reading and Writing module. When finished, you will receive your practice score and one sample review.'
                            : 'You will take one full Math module with built-in Desmos graphing calculator. When finished, you will receive your practice score and one sample review.';
                        this.confirmBtnText = 'Start Module';
                        this.isResuming = false;
                        this.actionType = 'submit';
                        this.targetForm = form;
                    }

                    this.showModal = true;
                },

                openFull(form) {
                    if (!this.checkScreen()) return;
                    const existing = this.activeAttempts['full'];

                    if (existing) {
                        this.title = 'Continue Full Practice Test';
                        this.badge = 'In Progress';
                        this.duration = 'Continues active test clock';
                        this.questions = '98 questions (2 sections)';
                        this.description = 'You will return directly to your active adaptive Digital SAT test session in the engine.';
                        this.confirmBtnText = 'Continue Full Test';
                        this.isResuming = true;
                        this.actionType = 'link';
                        this.targetUrl = existing.url;
                    } else {
                        this.title = 'Start Full Practice Test';
                        this.badge = '134 Minutes';
                        this.duration = '134 minutes (+ 10-minute break)';
                        this.questions = '98 questions (2 sections)';
                        this.description = 'You will take a complete adaptive Digital SAT: Reading and Writing (54 questions), 10-minute break, then Math (44 questions). Question difficulty in Module 2 adapts to your performance.';
                        this.confirmBtnText = 'Start Full Test';
                        this.isResuming = false;
                        this.actionType = 'submit';
                        this.targetForm = form;
                    }

                    this.showModal = true;
                },

                openResume(title, url) {
                    if (!this.checkScreen()) return;
                    this.title = 'Resume ' + title;
                    this.badge = 'In Progress';
                    this.duration = 'Continues active test clock';
                    this.questions = title;
                    this.description = 'You will return directly to your active test session in the engine.';
                    this.isResuming = true;
                    this.confirmBtnText = 'Resume Test';
                    this.actionType = 'link';
                    this.targetUrl = url;
                    this.showModal = true;
                },

                checkScreen() {
                    if (window.innerWidth < 900 || window.innerHeight < 500) {
                        const message = document.getElementById('trial-screen-message');
                        if (message) {
                            message.hidden = false;
                            message.scrollIntoView({ behavior: 'smooth' });
                        }
                        return false;
                    }
                    return true;
                },

                closeModal() {
                    this.showModal = false;
                },

                proceed() {
                    if (this.actionType === 'submit' && this.targetForm) {
                        const formToSubmit = this.targetForm;
                        this.showModal = false;
                        HTMLFormElement.prototype.submit.call(formToSubmit);
                    } else if (this.actionType === 'link' && this.targetUrl) {
                        const urlToNavigate = this.targetUrl;
                        this.showModal = false;
                        window.location.href = urlToNavigate;
                    }
                }
            };
        }
    </script>
    <p id="trial-screen-message" hidden role="alert" class="mx-auto max-w-6xl px-5 py-4 font-semibold text-amber-900">Open DFET SAT on a screen at least 900 × 500 pixels to start a practice test or demo.</p>
</x-layouts.landing>
