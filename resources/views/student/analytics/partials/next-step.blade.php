<div class="home-guidance">
    <section class="home-next" aria-labelledby="next-step-title" data-home-next-step>
        @if($inProgressAttempt)
            <h2 id="next-step-title">Pick up your practice</h2>
            <p class="home-next__title">{{ $formatTitle($inProgressAttempt->test->title, 'Adaptive Full Practice Test 1') }}</p>
            <p>Your responses are saved. Review the readiness check before your timer resumes.</p>
            <div class="home-next__meta">
                <span><x-ui.icon name="clock" class="w-4 h-4" /> Last active {{ $inProgressAttempt->updated_at->diffForHumans() }}</span>
                <span>{{ $resumeModule?->section?->name ?? 'Practice' }}{{ $resumeModule ? ' · Module ' . $resumeModule->module_number : '' }}</span>
            </div>
            <div class="home-next__actions">
                <x-ui.button variant="secondary" @click="$dispatch('open-ready-modal', {{ \Illuminate\Support\Js::from($resumeModalPayload) }})">Resume practice</x-ui.button>
            </div>
        @elseif($promotedAssignment)
            <h2 id="next-step-title">Your next assignment</h2>
            <p class="home-next__title">{{ $promotedAssignment->title ?? 'Class assignment' }}</p>
            <p>{{ $promotedAssignment->classroom->name }} · {{ $promotedAssignment->due_at ? 'Due ' . $promotedAssignment->due_at->diffForHumans() : 'No due date' }}</p>
            <p>Open the assignment to check its instructions and available attempts.</p>
            <div class="home-next__actions">
                <x-ui.button variant="secondary" :href="route('student.assignments.show', ['assignment' => $promotedAssignment->ulid])">View assignment</x-ui.button>
            </div>
        @elseif($completedCount === 0)
            <h2 id="next-step-title">Get started with Test Preview</h2>
            <p>Explore sample questions, Desmos, the reference sheet, and answer tools before taking a scored test.</p>
            <div class="home-next__meta"><span>Untimed</span><span>No recorded score</span></div>
            <div class="home-next__actions">
                <x-ui.button variant="secondary" :href="route('test.preview')">Try Test Preview</x-ui.button>
                <a class="home-next__link" href="{{ route('home.practice') }}">Browse practice tests <x-ui.icon name="arrow-right" class="w-4 h-4" /></a>
            </div>
        @else
            <h2 id="next-step-title">Choose your next practice</h2>
            <p>Build on your recent work with a full adaptive test or a focused section from the library.</p>
            <div class="home-next__actions">
                <x-ui.button variant="secondary" :href="route('home.practice')">Browse practice tests</x-ui.button>
            </div>
        @endif
    </section>

    <section class="home-goal" aria-labelledby="home-goal-title">
        <div class="home-goal__heading">
            <h2 id="home-goal-title">Target score</h2>
            <span class="home-goal__value home-num" x-text="savedGoal === null ? 'Not set' : savedGoal">{{ $user->target_score ?? 'Not set' }}</span>
        </div>
        <p class="home-goal__description" x-text="goalMessage">Set a target to track your score gap. You can set it later.</p>
        <button type="button" class="home-link" x-ref="goalEdit" x-show="!editingGoal" @click="editGoal" x-cloak x-text="savedGoal === null ? 'Set target goal' : 'Edit target goal'">Edit target goal</button>
        <form class="home-goal__form" x-show="editingGoal" :aria-busy="saving" @submit.prevent="saveGoal()" novalidate x-cloak>
            <div class="home-goal__presets" role="group" aria-label="Target score presets">
                @foreach([1200, 1300, 1400, 1500, 1600] as $preset)
                    <button type="button" class="home-goal__preset home-num" :aria-pressed="Number(target) === {{ $preset }}" @click="target = '{{ $preset }}'; goalError = ''; goalInvalid = false; goalStatus = ''" :disabled="saving">{{ $preset }}</button>
                @endforeach
            </div>
            <label for="home-goal-input">Custom target score</label>
            <input id="home-goal-input" name="target_score" type="number" min="400" max="1600" step="1" inputmode="numeric" x-ref="goalInput" x-model="target" :readonly="saving" :aria-invalid="goalInvalid ? 'true' : 'false'" aria-describedby="home-goal-hint home-goal-error" value="{{ $user->target_score }}">
            <p id="home-goal-hint" class="home-goal__hint">400–1600, rounded to the nearest 10.</p>
            <div class="home-goal__actions">
                <x-ui.button variant="secondary" type="submit" ::aria-disabled="saving"><span x-text="saving ? 'Saving…' : 'Save goal'">Save goal</span></x-ui.button>
                <button type="button" class="home-link" x-show="savedGoal === null" @click="setGoalLater" :disabled="saving">Set later</button>
                <button type="button" class="home-link" x-show="savedGoal !== null" @click="saveGoal(true)" :aria-disabled="saving" x-cloak>Clear goal</button>
            </div>
        </form>
        <p id="home-goal-error" class="home-goal__error" x-text="goalError" role="alert"></p>
        <p class="home-goal__status" x-text="goalStatus" role="status" aria-atomic="true"></p>
    </section>
</div>

@if($remainingAssignments->isNotEmpty() || $promotedAssignment || ($inProgressAttempt && $openAssignments > 0))
    <x-ui.card aria-labelledby="assignments-title">
        <x-slot:header>
            <div class="home-panel__headline">
                <h2 id="assignments-title" class="home-panel__title">Assigned by your teacher</h2>
                <p class="home-panel__sub">{{ $openAssignments }} open{{ $promotedAssignment ? ' · Next assignment featured above' : '' }}</p>
            </div>
            <x-ui.status-badge status="brand">Class work</x-ui.status-badge>
        </x-slot:header>
        @if($remainingAssignments->isNotEmpty())
            <ul class="home-list">
                @foreach($remainingAssignments as $assignment)
                    <li>
                        <div class="home-row home-row--actionable">
                            <span class="home-row__main">
                                <span class="home-row__title">{{ $assignment->title ?? 'Class assignment' }}</span>
                                <span class="home-row__meta">{{ $assignment->classroom->name }} · {{ $assignment->due_at ? 'Due ' . $assignment->due_at->diffForHumans() : 'No due date' }}</span>
                            </span>
                            <span class="home-row__aside">
                                <x-ui.status-badge :status="$assignment->resolved['variant']">{{ $assignment->resolved['state'] }}</x-ui.status-badge>
                                <x-ui.button variant="secondary" size="sm" :href="route('student.assignments.show', ['assignment' => $assignment->ulid])">View assignment</x-ui.button>
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="home-note">{{ $promotedAssignment ? 'Your next assignment is featured above.' : 'Your active assignment is featured above.' }} View all assignments for classroom instructions and results.</p>
        @endif
        <x-slot:footer>
            <div class="home-panel__foot">
                <span>All classroom tasks and scores</span>
                <a href="{{ route('student.assignments.index') }}" class="home-link">View all assignments <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" /></a>
            </div>
        </x-slot:footer>
    </x-ui.card>
@endif
