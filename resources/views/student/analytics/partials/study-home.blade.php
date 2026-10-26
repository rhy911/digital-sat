<div class="study-home" x-data="studentHome(@js(['endpoint' => route('student.progress.goal'), 'guideEndpoint' => route('student.home.guide'), 'csrf' => csrf_token(), 'goal' => $user->target_score, 'latestScore' => $latestComparableAttempt ? (int) $latestComparableAttempt->total_score : null, 'scores' => array_column($homeScoreTrend['attempts'], 'score'), 'guideExpanded' => $canUseTeacherWorkspace ? true : $homeGuide['expanded']]))">
    <header class="study-home__welcome">
        <div>
            <p class="study-home__eyebrow">{{ $primaryClassroom?->name ?? $todayLabel }}</p>
            <h1>{{ $homeGuide['practiceCompleted'] ? 'Good ' . $greeting . ', ' . $displayName : 'Welcome, ' . $displayName . '. Let’s get you started.' }}</h1>
            <p>{{ \App\Support\HomeFocus::forStudent(open: $openAssignments, overdue: $overdueAssignments, dueSoon: $dueSoonAssignments, hasAnyAssignment: $hasAnyAssignment, hasAttemptInProgress: (bool) $inProgressAttempt, completedCount: $completedCount, bestScore: $bestScore) }}</p>
        </div>
        <div class="study-home__actions">
            @if($canUseTeacherWorkspace)
                @include('student.analytics.partials.view-switcher')
            @endif
            <x-ui.button href="{{ route('exam-join.entry') }}" variant="secondary" size="sm">Join an exam</x-ui.button>
            @if($user->role === 'student')
                <x-ui.button href="{{ route('student.classes.index') }}" variant="secondary" size="sm">Join a class</x-ui.button>
            @endif
        </div>
    </header>

    <div class="study-home__columns">
        <div class="study-home__main">
            <section class="study-next" aria-labelledby="next-step-title" data-home-next-step>
                <p class="study-next__eyebrow"><span aria-hidden="true">●</span> {{ $inProgressAttempt ? 'Pick up where you left off' : 'Your next step' }}</p>
                @if($inProgressAttempt)
                    <h2 id="next-step-title">{{ $formatTitle($inProgressAttempt->test->title) }}</h2>
                    <p>Your answers are saved. Check you’re ready, then continue your practice.</p>
                    <p class="study-next__meta">Last active {{ $inProgressAttempt->updated_at->diffForHumans() }} · {{ $resumeModule?->section?->name ?? 'Practice' }}</p>
                    <x-ui.button type="button" variant="primary" @click="$dispatch('open-ready-modal', {{ \Illuminate\Support\Js::from($resumeModalPayload) }})">Resume practice</x-ui.button>
                @elseif($promotedAssignment)
                    <h2 id="next-step-title">{{ $promotedAssignment->title ?? 'Your next assignment' }}</h2>
                    <p>From {{ $promotedAssignment->classroom->name }}. Open it for instructions and available attempts.</p>
                    <p class="study-next__meta">{{ $promotedAssignment->due_at ? 'Due ' . $promotedAssignment->due_at->diffForHumans() : 'No due date — work at your own pace.' }}</p>
                    <x-ui.button href="{{ route('student.assignments.show', $promotedAssignment->ulid) }}" variant="primary">View assignment</x-ui.button>
                @elseif($lastCompletedAttempt && !$homeGuide['reportOpened'])
                    <h2 id="next-step-title">See what you can learn from your practice</h2>
                    <p>Your score report has your results and answer explanations. Take a look before choosing what to practise next.</p>
                    <x-ui.button href="{{ route('student.scores.show', $lastCompletedAttempt) }}" variant="primary">Open your score report</x-ui.button>
                @elseif(!$homeGuide['practiceCompleted'] && !$homeGuide['previewOpened'])
                    <h2 id="next-step-title">Get started with Test Preview</h2>
                    <p>Meet the questions and try the tools. There’s no timer or recorded score, so you can explore at your own pace.</p>
                    <x-ui.button href="{{ route('test.preview') }}" variant="primary">Try Test Preview</x-ui.button>
                @else
                    <h2 id="next-step-title">{{ $homeGuide['practiceCompleted'] ? 'Choose your next practice' : 'Ready for your first practice?' }}</h2>
                    <p>Choose a full adaptive test or start with one section. Afterwards, review your answers to find your next focus.</p>
                    <x-ui.button href="{{ route('home.practice') }}" variant="primary">{{ $homeGuide['practiceCompleted'] ? 'Browse practice tests' : 'Choose first practice' }}</x-ui.button>
                @endif
            </section>

            @if(!$isTeacher && ($remainingAssignments->isNotEmpty() || $openAssignments > 0))
                <section class="study-panel" aria-labelledby="assignments-title">
                    <div class="study-panel__heading"><div><h2 id="assignments-title">From your teacher</h2><p>{{ $openAssignments }} open{{ $promotedAssignment ? ' · Next assignment featured above' : '' }}</p></div></div>
                    <ul class="study-list">
                        @foreach($remainingAssignments as $assignment)
                            <li class="study-work"><div><h3>{{ $assignment->title ?? 'Class assignment' }}</h3><p>{{ $assignment->classroom->name }} · {{ $assignment->due_at ? 'Due ' . $assignment->due_at->diffForHumans() : 'No due date' }}</p><span @class(['study-state', 'study-state--overdue' => $assignment->resolved['state'] === 'Overdue'])>{{ $assignment->resolved['state'] }}</span></div><x-ui.button href="{{ route('student.assignments.show', $assignment->ulid) }}" variant="secondary" size="sm">View assignment</x-ui.button></li>
                        @endforeach
                    </ul>
                    <a class="study-link" href="{{ route('student.assignments.index') }}">View all assignments →</a>
                </section>
            @endif

            <section class="study-panel study-guide" aria-labelledby="getting-started-title">
                <div class="study-panel__heading"><div><h2 id="getting-started-title">Getting started</h2><p>{{ (int) $homeGuide['practiceCompleted'] + (int) $homeGuide['reportOpened'] }} of 2 essential steps complete</p></div>
                    @if($user->role === 'student')
                        <button type="button" class="study-link" x-ref="guideToggle" :aria-expanded="guideExpanded" aria-controls="home-guide-steps" :aria-disabled="guideSaving" @click="setGuide(!guideExpanded)" x-text="guideExpanded ? 'Hide guide' : 'Show guide'">{{ $homeGuide['expanded'] ? 'Hide guide' : 'Show guide' }}</button>
                    @endif
                </div>
                <div id="home-guide-steps" x-show="guideExpanded" @if(!$homeGuide['expanded']) x-cloak @endif>
                    <progress max="2" value="{{ (int) $homeGuide['practiceCompleted'] + (int) $homeGuide['reportOpened'] }}" aria-label="Essential getting started steps"></progress>
                    <ol class="study-guide__steps">
                        <li><span class="study-guide__check" aria-hidden="true">@if($homeGuide['previewOpened'])<x-ui.icon name="check-circle" />@else<span class="study-guide__empty"></span>@endif</span><div><a href="{{ route('test.preview') }}">Explore Test Preview <span class="study-optional">— optional</span></a><p>{{ $homeGuide['previewOpened'] ? 'Opened' : 'Explore the questions and tools, without a score.' }}</p></div></li>
                        <li><span class="study-guide__check" aria-hidden="true">@if($homeGuide['practiceCompleted'])<x-ui.icon name="check-circle" />@else<span class="study-guide__empty"></span>@endif</span><div><a href="{{ route('home.practice') }}">Complete practice</a><p>{{ $homeGuide['practiceCompleted'] ? 'Completed' : 'Choose a full test or one section to see where you stand.' }}</p></div></li>
                        <li><span class="study-guide__check" aria-hidden="true">@if($homeGuide['reportOpened'])<x-ui.icon name="check-circle" />@else<span class="study-guide__empty"></span>@endif</span><div><a href="{{ $lastCompletedAttempt ? route('student.scores.show', $lastCompletedAttempt) : route('student.scores.index') }}">Open your score report</a><p>{{ $homeGuide['reportOpened'] ? 'Opened' : 'After practice, explore your results and answer explanations.' }}</p></div></li>
                    </ol>
                </div>
                @if($user->role === 'student')
                    <p role="status" x-text="guideStatus"></p><p class="study-error" role="alert" x-text="guideError"></p>
                    <button class="study-link" type="button" x-show="guideError" @click="setGuide(guideRequested)" x-cloak>Retry saving guide preference</button>
                @endif
            </section>

            @if($comparableCount === 1)
                <section class="study-panel study-baseline" aria-labelledby="baseline-title">
                    <div class="study-panel__heading"><div><h2 id="baseline-title">Your starting score</h2><p>{{ $latestComparableAttempt->completed_at?->format('M j, Y') ?? 'Completion date unavailable' }}</p></div><span class="study-state">First full test</span></div>
                    <p class="study-baseline__score">{{ $latestComparableAttempt->total_score }} <span>/ 1600</span></p>
                    <div class="study-baseline__split"><span>RW: <strong>{{ $latestComparableAttempt->score_reading_writing }}</strong> / 800</span><span>Math: <strong>{{ $latestComparableAttempt->score_math }}</strong> / 800</span></div>
                    <p>A starting point to build on. Review your answers, then choose your next practice.</p>
                    <a class="study-link" href="{{ route('student.scores.show', $latestComparableAttempt) }}">Review your answers →</a>
                </section>
            @elseif($comparableCount >= 2)
                <section aria-label="Progress at a glance" class="study-metrics">
                    <div class="study-panel"><h2>Latest full test</h2><strong>{{ $latestComparableAttempt->total_score }}</strong><p>RW: {{ $latestComparableAttempt->score_reading_writing }} · Math: {{ $latestComparableAttempt->score_math }}</p></div>
                    <div class="study-panel"><h2>Personal best</h2><strong>{{ $bestScore }}</strong><p>Out of 1600</p></div>
                    <div class="study-panel"><h2>Practice completed</h2><strong>{{ $completedCount }}</strong><p>Full tests and sections</p></div>
                </section>
                <section class="study-panel" aria-labelledby="history-title"><div class="study-panel__heading"><div><h2 id="history-title">Your scores over time</h2><p>Last {{ count($homeScoreTrend['attempts']) }} comparable full tests</p></div></div>@include('student.analytics.partials.score-history')<a class="study-link" href="{{ route('student.progress') }}">View full progress →</a></section>
            @else
                <section class="study-panel" aria-labelledby="baseline-title"><h2 id="baseline-title">Find your starting point</h2><p>A full practice test gives you a starting score in Reading &amp; Writing and Math. You can also begin with one section.</p><a class="study-link" href="{{ route('home.practice') }}">Explore practice tests →</a></section>
            @endif

            <section class="study-panel study-focus" aria-labelledby="tip-title"><h2 id="tip-title">Suggested focus</h2>
                @if($suggestion)
                    <h3>{{ $suggestion['skill'] }}</h3><p>{{ $suggestion['domain'] }} · Correct on {{ $suggestion['correct'] }} of {{ $suggestion['total'] }} recent {{ \Illuminate\Support\Str::plural('question', $suggestion['total']) }}.</p>
                    <p>{{ $suggestion['total'] < 5 ? 'This is limited evidence. Try more questions to understand whether this is a consistent gap.' : 'Revisit this topic in your next practice, then review the explanations.' }}</p>
                @else
                    <p>Complete practice and review your answers to build evidence for a suggested focus.</p>
                @endif
                <a class="study-link" href="{{ route('home.practice') }}">Browse practice tests →</a>
            </section>

            @if($recentWorkAttempts->isNotEmpty())
                <section class="study-panel" aria-labelledby="recent-title"><div class="study-panel__heading"><h2 id="recent-title">Recent work</h2><a class="study-link" href="{{ route('student.scores.index') }}">All score reports →</a></div><ul class="study-list">
                    @foreach($recentWorkAttempts as $attempt)
                        @php
                            $sectionOnly = $attempt->attempt_type === 'section';
                            $result = $sectionOnly ? ($attempt->section_type === 'reading_writing' ? $attempt->score_reading_writing : $attempt->score_math) : $attempt->total_score;
                        @endphp
                        <li class="study-work"><div><h3>{{ $formatTitle($attempt->test->title) }}</h3><p>{{ $sectionOnly ? ($attempt->section_type === 'reading_writing' ? 'Reading & Writing' : 'Math') : 'Full test' }} · {{ $attempt->completed_at?->format('M j, Y') ?? 'Undated' }}</p><p class="study-result">{{ $result ?? 'Pending' }} / {{ $sectionOnly ? 800 : 1600 }}</p></div><x-ui.button href="{{ route('student.scores.show', $attempt) }}" variant="secondary" size="sm">Review</x-ui.button></li>
                    @endforeach
                </ul></section>
            @endif
        </div>

        <aside class="study-home__support" aria-label="Your study tools">
            @include('student.analytics.partials.home-goal')
            <section class="study-panel" aria-labelledby="weekly-title"><h2 id="weekly-title">This week</h2>
                <ol class="study-week">
                    @foreach($weeklyActivity['days'] as $day)
                        <li @class(['study-week__day', 'is-today' => $day['today'], 'is-completed' => $day['completed']]) aria-label="{{ $day['label'] }}, {{ $day['date'] }}{{ $day['today'] ? ', today' : '' }}, {{ $day['completed'] ? 'practice completed' : 'no completed practice' }}"><span aria-hidden="true">{{ $day['completed'] ? '✓' : ($day['today'] ? '•' : '') }}</span><small aria-hidden="true">{{ $day['label'] }}</small></li>
                    @endforeach
                </ol>
                <p><strong>{{ $weeklyActivity['practiceCount'] }}</strong> {{ \Illuminate\Support\Str::plural('test', $weeklyActivity['practiceCount']) }} completed · <strong>{{ $weeklyActivity['questionsCount'] }}</strong> questions answered</p>
                <p class="study-caption">Monday–Sunday, Vietnam time. A little practice is a good place to start.</p>
            </section>
            <div class="study-help">
                <details><summary>What is Test Preview?</summary><p>Try sample questions and tools, including Desmos and the reference sheet. It’s untimed and does not record a score.</p><a class="study-link" href="{{ route('test.preview') }}">Open Test Preview →</a></details>
                <details><summary>How does adaptive practice work?</summary><p>Your first module’s answers determine the second module’s difficulty. Your score considers performance across both modules; a harder module does not guarantee a higher score.</p></details>
            </div>
        </aside>
    </div>

    @include('student.analytics.partials.home-discovery')
</div>
