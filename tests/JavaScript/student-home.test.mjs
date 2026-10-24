import { test, afterEach, mock } from 'node:test';
import assert from 'node:assert/strict';
import { studentHome } from '../../resources/js/student/home.js';

afterEach(() => mock.restoreAll());

function editor(goal = 1400) {
    const state = studentHome({ endpoint: '/goal', csrf: 'test-token', goal, latestScore: 1300 });
    state.$refs = { goalInput: { focus() {} }, goalEdit: { focus() {} } };
    state.$nextTick = callback => callback();
    return state;
}

test('save uses the normalized server value for the score gap and chart', async () => {
    const state = editor();
    state.target = '1456';
    const fetch = mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ success: true, target_score: 1460 }) }));
    await state.saveGoal();
    assert.deepEqual(JSON.parse(fetch.mock.calls[0].arguments[1].body), { target_score: 1456 });
    assert.equal(state.savedGoal, 1460);
    assert.equal(state.target, '1460');
    assert.equal(state.goalMessage, '160 points above your latest full-test score of 1300.');
    assert.equal(state.goalY, 15.5);
    assert.equal(state.goalStatus, 'Goal saved: 1460.');
});

test('HTTP and network failures keep the old goal and the edited input', async () => {
    for (const status of [422, 419, 500, 'network']) {
        const state = editor();
        state.target = '1500';
        const fetch = mock.method(globalThis, 'fetch', async () => {
            if (status === 'network') throw new TypeError('Failed to fetch');
            return { ok: false, status };
        });
        await state.saveGoal();
        assert.equal(state.savedGoal, 1400);
        assert.equal(state.target, '1500');
        assert.equal(state.saving, false);
        assert.equal(state.goalStatus, '');
        assert.match(state.goalError, /save|saving/);
        fetch.mock.restore();
    }
});

test('set later makes no request and clearing sends an explicit null', async () => {
    const state = editor(null);
    const fetch = mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ success: true, target_score: null }) }));
    state.setGoalLater();
    assert.equal(state.editingGoal, false);
    assert.equal(fetch.mock.callCount(), 0);
    state.savedGoal = 1400;
    await state.saveGoal(true);
    assert.deepEqual(JSON.parse(fetch.mock.calls[0].arguments[1].body), { target_score: null });
    assert.equal(state.savedGoal, null);
    assert.equal(state.target, '');
});

test('invalid input never sends a request or overwrites the saved value', async () => {
    const state = editor();
    const fetch = mock.method(globalThis, 'fetch');
    for (const value of ['', '399', '1601', '1450.5']) {
        state.target = value;
        await state.saveGoal();
        assert.equal(state.savedGoal, 1400);
        assert.match(state.goalError, /whole-number/);
    }
    assert.equal(fetch.mock.callCount(), 0);
});

test('an unexpected success body never announces a save', async () => {
    const state = editor();
    state.target = '1500';
    mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => { throw new SyntaxError('invalid JSON'); } }));
    await state.saveGoal();
    assert.equal(state.savedGoal, 1400);
    assert.equal(state.target, '1500');
    assert.equal(state.goalStatus, '');
    assert.equal(state.goalError, 'Unable to confirm your goal. Try saving again.');
});

test('guide preference changes only after confirmed persistence and can be retried', async () => {
    const state = editor();
    mock.method(globalThis, 'fetch', async () => ({ ok: false, status: 500 }));
    await state.setGuide(false);
    assert.equal(state.guideExpanded, true);
    assert.equal(state.guideRequested, false);
    assert.match(state.guideError, /Try again/);
    globalThis.fetch.mock.restore();
    mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ preference: 'hidden' }) }));
    await state.setGuide(state.guideRequested);
    assert.equal(state.guideExpanded, false);
    assert.equal(state.guideError, '');
});

test('chart range contains every score and target with at least a 300 point span', () => {
    const state = editor();
    for (const scores of [[400, 410], [1590, 1600], [1300, 1310], [400, 1600]]) {
        state.scores = scores;
        for (const goal of [null, 400, 1460, 1600]) {
            state.savedGoal = goal;
            const { min, max } = state.chartRange;
            assert.ok(min >= 400 && max <= 1600 && max - min >= 300);
            for (const score of [...scores, ...(goal === null ? [] : [goal])]) assert.ok(score >= min && score <= max);
            assert.equal(state.scoreY(min), 95);
            assert.equal(state.scoreY(max), 5);
        }
    }
});

test('guide errors retain visibility and give an actionable recovery', async () => {
    for (const kind of ['expired', 'network', 'malformed']) {
        const state = editor();
        const stub = mock.method(globalThis, 'fetch', async () => {
            if (kind === 'network') throw new TypeError('Failed to fetch');
            if (kind === 'expired') return { ok: false, status: 419 };
            return { ok: true, json: async () => { throw new SyntaxError('Unexpected token'); } };
        });
        await state.setGuide(false);
        assert.equal(state.guideExpanded, true);
        assert.equal(state.guideSaving, false);
        assert.match(state.guideError, /try again|retry/i);
        stub.mock.restore();
    }
});
