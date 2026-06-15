<x-layouts.landing>
    <section class="landing-hero px-5 sm:px-6">
        <div class="mx-auto grid max-w-7xl items-center gap-12 lg:grid-cols-[0.92fr_1.08fr]">
            <div class="max-w-2xl">
                <p class="mb-5 inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-900">
                    <span class="h-2 w-2 rounded-full bg-brand"></span>
                    High-fidelity Digital SAT practice
                </p>
                <h1 class="max-w-2xl text-4xl font-bold leading-[1.04] tracking-tight text-slate-950 sm:text-6xl lg:text-7xl">
                    Test day should feel familiar.
                </h1>
                <p class="mt-7 max-w-xl text-lg leading-8 text-slate-700">
                    DigiSAT gives students a Bluebook-like testing environment and gives educators the tools to assign,
                    monitor, and score practice with confidence.
                </p>
                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <a href="/signup"
                        class="inline-flex min-h-12 items-center justify-center rounded-xl bg-brand px-7 py-3.5 text-base font-bold text-white transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand active:scale-[0.98]">
                        Start a practice test
                    </a>
                    <a href="#features"
                        class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-7 py-3.5 text-base font-bold text-slate-900 transition hover:border-slate-400 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand active:scale-[0.98]">
                        See platform tools
                    </a>
                </div>
            </div>

            <div class="landing-device" aria-label="Digital SAT practice interface preview">
                <div class="landing-device__topbar">
                    <div>
                        <span class="block text-xs font-bold text-slate-500">Reading and Writing</span>
                        <span class="text-sm font-semibold text-slate-950">Module 1, Question 14</span>
                    </div>
                    <div class="landing-timer">31:48</div>
                </div>
                <div class="landing-device__body">
                    <aside class="landing-passage" aria-hidden="true">
                        <span class="landing-tool-pill">Notes</span>
                        <h2>Migration Patterns in Urban Transit</h2>
                        <p>
                            Researchers compared weekday rail usage against revised zoning data and found that commute
                            density shifted most sharply near mixed-use corridors.
                        </p>
                        <div class="landing-highlight">commute density shifted most sharply</div>
                    </aside>
                    <div class="landing-question" aria-hidden="true">
                        <div class="landing-question__meta">
                            <span>14</span>
                            <span>Mark for Review</span>
                        </div>
                        <p class="landing-question__stem">
                            Which choice best describes the function of the highlighted sentence?
                        </p>
                        <div class="landing-choice is-selected">It introduces the finding the passage explains.</div>
                        <div class="landing-choice">It challenges the study's central assumption.</div>
                        <div class="landing-choice">It defines a term used later in the passage.</div>
                        <div class="landing-choice">It compares two unrelated transit models.</div>
                    </div>
                </div>
                <div class="landing-device__footer">
                    <span>Calculator</span>
                    <span>Annotation</span>
                    <span>Question Review</span>
                    <button type="button">Next</button>
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white px-5 py-10 sm:px-6">
        <div class="mx-auto grid max-w-7xl gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="landing-stat">
                <strong>Bluebook-like</strong>
                <span>Testing flow, tools, review screen, and timing behavior.</span>
            </div>
            <div class="landing-stat">
                <strong>Adaptive</strong>
                <span>Module routing supports easier and harder second modules.</span>
            </div>
            <div class="landing-stat">
                <strong>IRT scoring</strong>
                <span>3PL parameters and theta estimation for realistic score bands.</span>
            </div>
            <div class="landing-stat">
                <strong>Teacher-ready</strong>
                <span>Item banks, assigned tests, progress views, and import tools.</span>
            </div>
        </div>
    </section>

    <section id="features" class="bg-slate-50 px-5 py-24 sm:px-6 lg:py-28">
        <div class="mx-auto max-w-7xl">
            <div class="max-w-3xl">
                <h2 class="text-4xl font-bold tracking-tight text-slate-950 sm:text-5xl">
                    Built around real practice, not extra noise.
                </h2>
                <p class="mt-5 text-lg leading-8 text-slate-700">
                    The landing page now mirrors the product promise: faithful student testing, practical educator
                    control, and defensible scoring.
                </p>
            </div>

            <div class="mt-12 grid gap-5 lg:grid-cols-12">
                <article id="fidelity" class="landing-feature landing-feature--primary lg:col-span-7">
                    <div>
                        <span class="landing-feature__label">Student experience</span>
                        <h3>Every tool sits where students expect it.</h3>
                        <p>
                            The test surface keeps timing, review, annotations, Desmos access, and passage/question
                            layout close to the official exam so practice transfers cleanly to test day.
                        </p>
                    </div>
                    <div class="landing-mini-browser" aria-hidden="true">
                        <div class="landing-mini-browser__bar">
                            <span></span><span></span><span></span>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border border-blue-200 bg-white p-4">
                                <div class="mb-3 h-2 w-24 rounded bg-blue-200"></div>
                                <div class="space-y-2">
                                    <div class="h-2 rounded bg-slate-200"></div>
                                    <div class="h-2 w-5/6 rounded bg-slate-200"></div>
                                    <div class="h-2 w-3/4 rounded bg-slate-200"></div>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="rounded-lg border-2 border-brand bg-blue-50 p-3 text-sm font-semibold text-blue-950">A</div>
                                <div class="rounded-lg border border-slate-200 bg-white p-3 text-sm font-semibold text-slate-600">B</div>
                                <div class="rounded-lg border border-slate-200 bg-white p-3 text-sm font-semibold text-slate-600">C</div>
                            </div>
                        </div>
                    </div>
                </article>

                <article id="teachers" class="landing-feature lg:col-span-5">
                    <span class="landing-feature__label">Educator workflow</span>
                    <h3>Assign, import, monitor, and adjust.</h3>
                    <p>
                        Teachers can build tests from item banks, import questions in bulk, send private links, and
                        keep classroom progress visible without turning the product into a game layer.
                    </p>
                    <ul class="landing-checklist">
                        <li>Bulk question import with validation</li>
                        <li>Private and public test assignments</li>
                        <li>Progress tracking for active practice</li>
                    </ul>
                </article>

                <article id="scoring" class="landing-feature lg:col-span-5">
                    <span class="landing-feature__label">Scoring model</span>
                    <h3>IRT signals stay visible.</h3>
                    <p>
                        Scoring views can explain ability estimates, domain performance, and score bands so teachers
                        know what changed and students know what to practice next.
                    </p>
                    <div class="landing-score-card" aria-hidden="true">
                        <div class="flex items-center justify-between">
                            <span>Theta estimate</span>
                            <strong>0.64</strong>
                        </div>
                        <div class="landing-score-bar"><span style="width: 68%"></span></div>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <span>RW 610</span>
                            <span>Math 670</span>
                            <span>Total 1280</span>
                        </div>
                    </div>
                </article>

                <article class="landing-feature landing-feature--dark lg:col-span-7">
                    <span class="landing-feature__label">Reliability</span>
                    <h3>Practice stays calm under exam pressure.</h3>
                    <p>
                        The interface favors predictable navigation, legible states, keyboard focus, and restrained
                        motion. Students can concentrate on the question instead of decoding the product.
                    </p>
                    <div class="mt-8 grid gap-3 sm:grid-cols-3">
                        <div>Stable layouts</div>
                        <div>Clear focus states</div>
                        <div>Reduced motion</div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="bg-white px-5 py-24 sm:px-6">
        <div class="mx-auto grid max-w-7xl items-center gap-10 rounded-2xl border border-slate-200 bg-slate-950 p-8 text-white sm:p-10 lg:grid-cols-[1fr_auto] lg:p-14">
            <div>
                <h2 class="max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl">
                    Give students a practice room that behaves like the real one.
                </h2>
                <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-300">
                    Start with a practice test, then use teacher tools and score details when your classroom needs
                    deeper control.
                </p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                <a href="/signup"
                    class="inline-flex min-h-12 items-center justify-center rounded-xl bg-white px-7 py-3.5 text-base font-bold text-slate-950 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white active:scale-[0.98]">
                    Launch free test
                </a>
                <a href="/signin"
                    class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-700 px-7 py-3.5 text-base font-bold text-white transition hover:border-slate-500 hover:bg-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white active:scale-[0.98]">
                    Sign in
                </a>
            </div>
        </div>
    </section>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof window.initLandingPage === 'function') {
                window.initLandingPage();
            }
        });
    </script>
    @endpush
</x-layouts.landing>
