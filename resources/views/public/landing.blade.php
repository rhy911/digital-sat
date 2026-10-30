<x-layouts.landing title="DFET SAT — Digital SAT Practice & Progress Tracking">
    <section class="public-hero">
        <div class="public-wrap public-hero__grid">
            <div class="public-hero__copy">
                <h1>Practice with focus.<br><span>Know what to do next.</span></h1>
                <p class="public-lede">Take Digital SAT practice in a calm, test-like workspace. See your result right away, then make a plan from the questions you missed.</p>
                <div class="public-actions">
                    <x-ui.button href="{{ route('public-trial.index') }}" class="min-h-[3.15rem] rounded-xl px-5 text-base">Try DFET SAT</x-ui.button>
                    <x-ui.button variant="secondary" href="#score-reports" class="min-h-[3.15rem] rounded-xl px-5 text-base">See a sample report</x-ui.button>
                </div>
                <p class="public-note">Start without an account &middot; Timed practice requires 900 &times; 500 screen &middot; Demo, module, or full test</p>
            </div>
            <figure class="public-device-wrap">
                <div class="public-device" aria-label="Sample Reading and Writing test interface">
                    <div class="public-device__bar"><span>Reading and Writing</span><span>Module 1 <i>31:48</i></span></div>
                    <div class="public-device__body">
                        <div class="public-device__passage" aria-hidden="true">
                            <span class="public-tool">Highlights &amp; Notes</span>
                            <h2>Migration Patterns in Urban Transit</h2>
                            <p>Researchers compared weekday rail usage against revised municipal zoning data. <mark>Commute density shifted most sharply near mixed-use commercial corridors where residential development expanded alongside rapid transit.</mark> This finding suggests that integrating housing with transit infrastructure directly reduces single-occupancy vehicle travel in metropolitan centers.</p>
                        </div>
                        <div class="public-device__question" aria-hidden="true">
                            <div class="public-device__meta"><span>Question 14</span><span>Mark for Review</span></div>
                            <p>Which choice best describes the function of the highlighted sentence in the overall structure of the text?</p>
                            <div class="public-answer is-selected"><b>A</b><span>It introduces the empirical finding that the rest of the text contextualizes.</span></div>
                            <div class="public-answer"><b>B</b><span>It questions the reliability of the municipal zoning records examined.</span></div>
                            <div class="public-answer"><b>C</b><span>It defines a transit metric introduced in the opening sentence.</span></div>
                            <div class="public-answer"><b>D</b><span>It provides an exception to the general trend observed by the researchers.</span></div>
                        </div>
                    </div>
                    <div class="public-device__footer"><span>Question review</span><span>Highlights &amp; Notes</span><span aria-hidden="true">Next question</span></div>
                </div>
                <figcaption>Sample interface. Shown for illustration.</figcaption>
            </figure>
        </div>
    </section>

    <section id="students" class="public-section public-section--white">
        <div class="public-wrap public-split">
            <div>
                <h2>Make practice feel clear from the first question.</h2>
                <p class="public-copy">Move through passages and questions, flag items to revisit, and use the tools available in each section. Your answers and timing stay together through the test.</p>
                <a href="{{ route('public-trial.index') }}" class="public-text-link">Explore the test experience <span aria-hidden="true">→</span></a>
            </div>
            <div class="public-tool-col">
                <div class="public-marking-card" aria-label="Preview of question marking and navigator tools">
                    <div class="public-marking-card__header">
                        <div class="public-marking-card__status">
                            <span class="public-marking-ribbon" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="#F6B93B" stroke="#10234A" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                            </span>
                            <strong>Question 14</strong>
                            <span class="public-marking-badge">Marked for Review</span>
                        </div>
                        <div class="public-marking-btn" aria-hidden="true">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="#F6B93B" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                            <span>Mark for Review</span>
                        </div>
                    </div>
                    <div class="public-marking-card__body">
                        <p class="public-marking-sub">Question Navigator &middot; Section 1</p>
                        <div class="public-marking-grid" aria-hidden="true">
                            <span class="is-answered">11</span>
                            <span class="is-answered">12</span>
                            <span class="is-answered">13</span>
                            <span class="is-current is-flagged">14<i>★</i></span>
                            <span class="is-unanswered">15</span>
                            <span class="is-answered">16</span>
                        </div>
                    </div>
                </div>
                <div class="public-tool-list" aria-label="Practice tools">
                    <div><p><strong>Stay oriented</strong><span>See your section, module, timer, and question position.</span></p></div>
                    <div><p><strong>Work your way</strong><span>Mark questions, navigate back, and review before you submit.</span></p></div>
                    <div><p><strong>Use section tools</strong><span>Annotate Reading and Writing passages and open the calculator in Math.</span></p></div>
                </div>
            </div>
        </div>
    </section>

    <section id="score-reports" class="public-section public-section--canvas">
        <div class="public-wrap public-report-grid">
            <div>
                <h2>A score is a starting point. The next step matters more.</h2>
                <p class="public-copy">Get a quick view of your practice performance, then open the full answer review and skill analysis with an account. Parents and tutors can use the report to talk through what to practice next.</p>
                <p class="public-note">Any scores shown in this sample are illustrative, not a student result.</p>
                <a href="{{ route('public-trial.index') }}" class="public-text-link">Try a test and see your result <span aria-hidden="true">→</span></a>
            </div>
            <figure class="public-report">
                <figcaption><span>Sample report</span><span>Illustrative data</span></figcaption>
                <div class="public-report__score"><span>Estimated total score</span><strong>1280</strong><small>Practice estimate &middot; 400&ndash;1600 scale &middot; Not official</small></div>
                <div class="public-report__subjects"><div><span>Reading and Writing</span><strong>610</strong></div><div><span>Math</span><strong>670</strong></div></div>
                <div class="public-report__sample-question">
                    <div class="public-report__sample-head">
                        <span class="public-report__sample-tag">Missed question review</span>
                        <span class="public-report__sample-domain">Craft and Structure</span>
                    </div>
                    <p class="public-report__sample-prompt">Which choice best describes the function of the highlighted sentence in the overall structure of the text?</p>
                    <div class="public-report__sample-answers">
                        <div class="answer-chip is-student">Your answer: <strong>C</strong> (Defines a transit metric)</div>
                        <div class="answer-chip is-correct">Correct answer: <strong>A</strong> (Introduces the empirical finding)</div>
                    </div>
                    <p class="public-report__sample-expl">The sentence reports the primary empirical observation regarding commute shifts; subsequent sentences discuss its implications.</p>
                </div>
                <div class="public-report__skills">
                    <span class="public-report__skills-title">Skill mastery snapshot</span>
                    <div class="public-skill-bar">
                        <div class="public-skill-bar__meta"><span>Craft and Structure</span><strong>6 / 8 (75%)</strong></div>
                        <div class="public-skill-bar__track"><div class="public-skill-bar__fill" style="width: 75%;"></div></div>
                    </div>
                    <div class="public-skill-bar">
                        <div class="public-skill-bar__meta"><span>Standard English Conventions</span><strong>8 / 10 (80%)</strong></div>
                        <div class="public-skill-bar__track"><div class="public-skill-bar__fill" style="width: 80%;"></div></div>
                    </div>
                    <div class="public-skill-bar">
                        <div class="public-skill-bar__meta"><span>Advanced Math</span><strong>9 / 12 (75%)</strong></div>
                        <div class="public-skill-bar__track"><div class="public-skill-bar__fill" style="width: 75%;"></div></div>
                    </div>
                </div>
                <div class="public-report__locked"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/></svg><p><strong>See what to work on next with an account</strong><span>Unlock all 98 question reviews, complete explanations, and mistake classification.</span></p></div>
            </figure>
        </div>
    </section>

    <section id="tutors" class="public-section public-section--navy">
        <div class="public-wrap public-tutor-grid">
            <div>
                <h2>Bring practice and student progress into one place.</h2>
            </div>
            <div>
                <p class="public-copy">Assign practice, follow student results, and review performance with tools built for tutoring. Teacher access is approved before classroom features become available.</p>
                <x-ui.button href="{{ route('signup', ['role' => 'teacher']) }}" class="mt-6 rounded-xl px-5">Apply for teacher access</x-ui.button>
            </div>
        </div>
    </section>

    <section id="faq" class="public-section public-section--white">
        <div class="public-wrap public-faq">
            <div><h2>Good to know.</h2></div>
            <div class="public-faq__items">
                <details><summary>Can I try DFET SAT without creating an account?</summary><p>Yes. Explore the interface demo, complete one module, or take a full adaptive practice test. You can see a partial result as soon as you finish.</p></details>
                <details><summary>What does an account add?</summary><p>An account saves your result and opens full answer review, skill analysis, and mistake patterns.</p></details>
                <details><summary>Is the estimated score official?</summary><p>No. DFET SAT practice estimates are for practice guidance and are not official College Board scores.</p></details>
                <details><summary>What device do I need for a practice test?</summary><p>The test engine workspace requires a laptop, desktop, or landscape tablet with a screen at least 900 by 500 pixels. Landing pages and score guides are mobile-friendly.</p></details>
                <details><summary>How do tutors get access?</summary><p>Apply as a teacher. An administrator reviews teacher accounts before classroom tools are available.</p></details>
            </div>
        </div>
    </section>

    <section class="public-final-cta"><div class="public-wrap"><h2>See how practice feels before you decide.</h2><p>Start with the interface demo or choose a complete practice experience.</p><x-ui.button href="{{ route('public-trial.index') }}" class="mt-6 rounded-xl px-5">Try DFET SAT</x-ui.button></div></section>
</x-layouts.landing>
