export function studentHome({ endpoint, guideEndpoint, csrf, goal, latestScore, scores = [], guideExpanded = true }) {
    return {
        savedGoal: goal,
        target: goal === null ? '' : String(goal),
        latestScore,
        editingGoal: goal === null,
        customGoal: goal !== null && ![1200, 1300, 1400, 1500, 1600].includes(goal),
        guideExpanded,
        guideRequested: guideExpanded,
        guideSaving: false,
        guideError: '',
        guideStatus: '',
        scores,
        saving: false,
        goalError: '',
        goalInvalid: false,
        goalStatus: '',
        get chartRange() {
            if (!this.scores.length) return { min: 400, max: 1600 };
            const values = this.savedGoal === null ? this.scores : [...this.scores, this.savedGoal];
            let min = Math.max(400, Math.floor((Math.min(...values) - 50) / 100) * 100);
            let max = Math.min(1600, Math.ceil((Math.max(...values) + 50) / 100) * 100);
            if (max - min < 300) {
                min = Math.max(400, Math.min(min, max - 300));
                max = Math.min(1600, Math.max(max, min + 300));
            }
            return { min, max };
        },
        scoreY(score) {
            return 95 - ((score - this.chartRange.min) / (this.chartRange.max - this.chartRange.min)) * 90;
        },
        get goalY() { return this.scoreY(this.savedGoal); },
        async setGuide(expanded) {
            if (this.guideSaving) return;
            this.guideRequested = expanded;
            this.guideSaving = true;
            this.guideError = '';
            this.guideStatus = 'Saving guide preference…';
            try {
                const response = await fetch(guideEndpoint, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ preference: expanded ? 'expanded' : 'hidden' }),
                });
                if (!response.ok) throw new Error(response.status === 419 || response.status === 401
                    ? 'Your session expired. Reload this page, then try again.' : 'Unable to save your guide preference. Try again.');
                const data = await response.json().catch(() => {
                    throw new Error('Unable to confirm your guide preference. Try again.');
                });
                if (data.preference !== (expanded ? 'expanded' : 'hidden')) throw new Error('Unable to confirm your guide preference. Try again.');
                this.guideExpanded = expanded;
                this.guideStatus = expanded ? 'Getting started guide shown.' : 'Getting started guide hidden.';
            } catch (error) {
                this.guideStatus = '';
                this.guideError = error instanceof TypeError ? 'Check your connection and retry saving your guide preference.' : error.message;
            } finally { this.guideSaving = false; }
        },
        get goalMessage() {
            if (this.savedGoal === null) return 'Set a target to track your score gap. You can set it later.';
            if (this.latestScore === null) return 'Complete a full practice test to see your score gap.';
            if (this.latestScore >= this.savedGoal) return 'Your latest score meets this target.';
            return `${this.savedGoal - this.latestScore} points above your latest full-test score of ${this.latestScore}.`;
        },
        editGoal() {
            this.editingGoal = true;
        },
        showCustomGoal() {
            this.customGoal = true;
            this.$nextTick(() => this.$refs.goalInput.focus());
        },
        setGoalLater() {
            this.editingGoal = false;
            this.goalError = '';
            this.goalInvalid = false;
            this.$nextTick(() => this.$refs.goalEdit.focus());
        },
        async saveGoal(clear = false) {
            if (this.saving) return;
            const value = clear ? null : Number(this.target);
            this.goalError = '';
            this.goalInvalid = false;
            this.goalStatus = '';
            if (!clear && (this.target === '' || !Number.isInteger(value) || value < 400 || value > 1600)) {
                this.goalError = 'Enter a whole-number score between 400 and 1600, then save again.';
                this.goalInvalid = true;
                this.showCustomGoal();
                return;
            }
            this.saving = true;
            this.goalStatus = clear ? 'Clearing goal…' : 'Saving goal…';
            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ target_score: value }),
                });
                if (!response.ok) {
                    if (response.status === 419 || response.status === 401) {
                        throw new Error('Your session expired. Reload this page, then try saving again.');
                    }
                    if (response.status === 422) {
                        this.goalInvalid = true;
                        this.showCustomGoal();
                        throw new Error('Enter a whole-number score between 400 and 1600, then save again.');
                    }
                    throw new Error('Unable to save your goal. Try saving again.');
                }
                const data = await response.json().catch(() => {
                    throw new Error('Unable to confirm your goal. Try saving again.');
                });
                if (data.success !== true || (data.target_score !== null &&
                    (!Number.isInteger(data.target_score) || data.target_score < 400 || data.target_score > 1600))) {
                    throw new Error('Unable to confirm your goal. Try saving again.');
                }
                this.savedGoal = data.target_score;
                this.target = data.target_score === null ? '' : String(data.target_score);
                this.goalStatus = data.target_score === null ? 'Goal cleared.' : `Goal saved: ${data.target_score}.`;
            } catch (error) {
                this.goalStatus = '';
                this.goalError = error instanceof TypeError
                    ? 'Unable to save your goal. Check your connection and try again.'
                    : error.message;
            } finally {
                this.saving = false;
            }
        },
    };
}
