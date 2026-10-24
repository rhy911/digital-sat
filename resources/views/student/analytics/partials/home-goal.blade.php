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
            <button type="button" class="study-link" :aria-expanded="customGoal" aria-controls="home-custom-goal" @click="showCustomGoal">Custom score</button>
            <div id="home-custom-goal" x-show="customGoal" x-cloak>
                <label for="home-goal-input">Custom target score</label>
                <input id="home-goal-input" name="target_score" type="number" min="400" max="1600" step="1" inputmode="numeric" x-ref="goalInput" x-model="target" :readonly="saving" :aria-invalid="goalInvalid ? 'true' : 'false'" aria-describedby="home-goal-hint home-goal-error" value="{{ $user->target_score }}">
                <p id="home-goal-hint" class="home-goal__hint">400–1600, rounded to the nearest 10.</p>
            </div>
            <div class="home-goal__actions">
                <x-ui.button variant="secondary" type="submit" ::aria-disabled="saving"><span x-text="saving ? 'Saving…' : 'Save goal'">Save goal</span></x-ui.button>
                <button type="button" class="home-link" x-show="savedGoal === null" @click="setGoalLater" :disabled="saving">Set later</button>
                <button type="button" class="home-link" x-show="savedGoal !== null" @click="saveGoal(true)" :aria-disabled="saving" x-cloak>Clear goal</button>
            </div>
        </form>
        <p id="home-goal-error" class="home-goal__error" x-text="goalError" role="alert"></p>
        <p class="home-goal__status" x-text="goalStatus" role="status" aria-atomic="true"></p>
    </section>