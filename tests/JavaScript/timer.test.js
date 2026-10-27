import test from 'node:test';
import assert from 'node:assert/strict';
import { state } from '../../resources/js/test/state.js';
import { getTimerElapsedSeconds, initializeAttemptControl, pausePracticeAttempt, refreshAssignmentTimer, startTimer, stopTimer, syncTimer, syncTimerFromServer } from '../../resources/js/test/timer.js';

test('countdown uses a monotonic clock and cannot expire from a wall-clock jump', () => {
  let now = 10_000;
  globalThis.performance = { now: () => now };
  globalThis.window = { isAssignmentAttempt: false, addEventListener() {} };
  globalThis.document = {
    getElementById: () => ({ textContent: '', classList: { add() {}, remove() {} } }),
    addEventListener() {}
  };
  globalThis.setInterval = () => 1;
  globalThis.clearInterval = () => {};
  const originalDateNow = Date.now;
  Date.now = () => 99_000_000;

  try {
    startTimer(1);
    now += 3_000;
    assert.equal(getTimerElapsedSeconds(), 3);
    stopTimer();
  } finally {
    Date.now = originalDateNow;
  }
});

test('server timing synchronization preserves positive time and applies half the request latency', () => {
  let now = 50_000;
  globalThis.performance = { now: () => now };
  globalThis.window = { isAssignmentAttempt: true };
  globalThis.document = {
    getElementById: () => ({ textContent: '', classList: { add() {}, remove() {} } }),
    addEventListener() {}
  };
  globalThis.setInterval = () => 1;
  globalThis.clearInterval = () => {};
  state.isSubmitting = false;
  state.timerInterval = null;

  syncTimerFromServer(120, 40_000, 50_000);

  assert.equal(state.timeLeft, 115);
  stopTimer();
});

test('failed server synchronization stays visibly unverified and retries instead of trusting stale time', async () => {
  let now = 10_000;
  let fetchCount = 0;
  let retry;
  const display = { textContent: '', classList: { add() {}, remove() {} } };
  globalThis.performance = { now: () => now };
  globalThis.window = {
    isAssignmentAttempt: true,
    assignmentTimerSynced: false,
    serverRemainingSeconds: 600,
    currentModuleId: 4,
    userTestId: 7,
    addEventListener() {}
  };
  globalThis.document = { getElementById: () => display, addEventListener() {} };
  globalThis.setInterval = () => 1;
  globalThis.clearInterval = () => {};
  globalThis.setTimeout = callback => { retry = callback; return 1; };
  globalThis.fetch = async () => {
    fetchCount++;
    if (fetchCount === 1 || fetchCount === 3) throw new Error('offline');
    return { ok: true, json: async () => ({ status: 'active', module_id: 4, remaining_seconds: 120 }) };
  };
  const originalConsoleError = console.error;
  console.error = () => {};
  state.timerWakeHandlersInitialized = false;
  state.timerInterval = null;
  startTimer(10);
  await new Promise(resolve => setImmediate(resolve));

  assert.equal(display.textContent, 'SYNC');
  assert.equal(window.assignmentTimerSynced, false);
  assert.equal(typeof retry, 'function');
  await retry();

  assert.equal(fetchCount, 2);
  assert.equal(window.assignmentTimerSynced, true);
  assert.equal(state.timeLeft, 120);

  await refreshAssignmentTimer();
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(display.textContent, 'SYNC');
  assert.equal(window.assignmentTimerSynced, false);
  await retry();
  assert.equal(fetchCount, 4);
  assert.equal(window.assignmentTimerSynced, true);
  assert.equal(state.timeLeft, 120);
  stopTimer();
  console.error = originalConsoleError;
});

test('break timing response stops module timer and enters section break state', async () => {
  let now = 10_000;
  const display = { textContent: '', classList: { add() {}, remove() {} } };
  globalThis.performance = { now: () => now };
  globalThis.window = { isAssignmentAttempt: true, currentModuleId: 8, userTestId: 11, addEventListener() {} };
  globalThis.document = { getElementById: () => display, addEventListener() {}, dispatchEvent() {} };
  globalThis.setInterval = () => 1;
  globalThis.clearInterval = () => {};
  globalThis.setTimeout = () => 1;
  globalThis.clearTimeout = () => {};
  globalThis.fetch = async () => ({
    ok: true,
    json: async () => ({
      status: 'break',
      module_id: 8,
      remaining_break_seconds: 300,
      break_ends_at: new Date(Date.now() + 300000).toISOString(),
      duration_seconds: 600,
    })
  });
  state.timerWakeHandlersInitialized = false;
  state.timerInterval = 123;
  state.isOnSectionBreak = false;

  await refreshAssignmentTimer();

  assert.equal(state.isOnSectionBreak, true);
  assert.equal(state.timerInterval, null);
  stopTimer();
});

test('advanced timing state navigates once without retrying the stale module', async () => {
  const events = [];
  globalThis.performance = { now: () => 10_000 };
  globalThis.window = { isAssignmentAttempt: true, currentModuleId: 8, userTestId: 11, addEventListener() {} };
  globalThis.document = {
    getElementById: () => ({ textContent: '', classList: { add() {}, remove() {} } }),
    addEventListener() {},
    dispatchEvent: event => events.push(event)
  };
  globalThis.setInterval = () => 1;
  globalThis.clearInterval = () => {};
  globalThis.clearTimeout = () => {};
  globalThis.fetch = async () => ({ ok: true, json: async () => ({ status: 'advanced', navigation_url: '/engine/session/next' }) });
  state.timerWakeHandlersInitialized = false;
  state.timerInterval = null;

  await refreshAssignmentTimer();

  assert.equal(events.length, 1);
  assert.equal(events[0].type, 'assignment-module-advanced');
  assert.equal(events[0].detail.url, '/engine/session/next');
  stopTimer();
});

test('permanent timing authorization failures stop retrying and request recovery UI', async () => {
  const events = [];
  globalThis.performance = { now: () => 10_000 };
  globalThis.window = { isAssignmentAttempt: true, currentModuleId: 8, userTestId: 11, addEventListener() {} };
  globalThis.document = {
    getElementById: () => ({ textContent: '', classList: { add() {}, remove() {} } }),
    addEventListener() {},
    dispatchEvent: event => events.push(event)
  };
  globalThis.setInterval = () => 1;
  globalThis.clearInterval = () => {};
  globalThis.setTimeout = () => { throw new Error('permanent errors must not retry'); };
  globalThis.fetch = async () => ({ ok: false, status: 403 });
  state.timerWakeHandlersInitialized = false;
  state.timerInterval = null;
  const originalConsoleError = console.error;
  console.error = () => {};

  await refreshAssignmentTimer();

  assert.equal(events.length, 1);
  assert.equal(events[0].type, 'assignment-timer-unavailable');
  assert.equal(window.assignmentModuleRecovery, true);
  stopTimer();
  console.error = originalConsoleError;
});

test('an older timing response cannot replace a newer active module response', async () => {
  const events = [];
  let resolveFirst;
  let fetchCount = 0;
  globalThis.performance = { now: () => 10_000 };
  globalThis.window = { isAssignmentAttempt: true, currentModuleId: 8, userTestId: 11, addEventListener() {} };
  globalThis.document = {
    getElementById: () => ({ textContent: '', classList: { add() {}, remove() {} } }),
    addEventListener() {},
    dispatchEvent: event => events.push(event)
  };
  globalThis.setInterval = () => 1;
  globalThis.clearInterval = () => {};
  globalThis.clearTimeout = () => {};
  globalThis.fetch = () => {
    fetchCount++;
    if (fetchCount === 1) return new Promise(resolve => { resolveFirst = resolve; });
    return Promise.resolve({ ok: true, json: async () => ({ status: 'active', module_id: 8, remaining_seconds: 80 }) });
  };
  state.timerWakeHandlersInitialized = false;
  state.timerInterval = null;

  const olderRequest = refreshAssignmentTimer();
  await refreshAssignmentTimer();
  resolveFirst({ ok: true, json: async () => ({ status: 'advanced', navigation_url: '/engine/session/old-response' }) });
  await olderRequest;

  assert.equal(window.assignmentTimerSynced, true);
  assert.equal(state.timeLeft, 80);
  assert.equal(events.length, 0);
  stopTimer();
});

test('a local countdown reaching zero verifies the server again before submitting', async () => {
  let now = 10_000;
  let fetchCount = 0;
  const events = [];
  globalThis.performance = { now: () => now };
  globalThis.window = { isAssignmentAttempt: true, currentModuleId: 8, userTestId: 11, addEventListener() {} };
  globalThis.document = {
    getElementById: () => ({ textContent: '', classList: { add() {}, remove() {} } }),
    addEventListener() {},
    dispatchEvent: event => events.push(event)
  };
  globalThis.setInterval = () => 1;
  globalThis.clearInterval = () => {};
  globalThis.clearTimeout = () => {};
  globalThis.fetch = async () => {
    fetchCount++;
    return { ok: true, json: async () => ({ status: 'active', module_id: 8, remaining_seconds: fetchCount === 1 ? 1 : 30 }) };
  };
  state.timerWakeHandlersInitialized = false;
  state.timerInterval = null;

  startTimer(1);
  await new Promise(resolve => setImmediate(resolve));
  now += 2_000;
  syncTimer();
  await new Promise(resolve => setImmediate(resolve));

  assert.equal(fetchCount, 2);
  assert.equal(state.timeLeft, 30);
  assert.equal(events.some(event => event.type === 'test-timer-expired'), false);
  stopTimer();
});

test('a paused practice attempt never starts a local countdown', () => {
  let intervalStarts = 0;
  globalThis.window = { isAssignmentAttempt: false };
  globalThis.document = {
    getElementById: () => ({ textContent: '', classList: { add() {}, remove() {} } }),
    addEventListener() {}
  };
  globalThis.setInterval = () => { intervalStarts++; return 1; };
  globalThis.clearInterval = () => {};
  state.isPaused = true;
  state.timerStartedAtMs = null;

  startTimer(10);

  assert.equal(intervalStarts, 0);
  assert.equal(state.timerStartedAtMs, null);
  state.isPaused = false;
  stopTimer();
});

test('a stale heartbeat failure cannot clear the token installed by a newer pause', async () => {
  let heartbeat;
  let resolveHeartbeat;
  globalThis.performance = { now: () => 10_000 };
  globalThis.window = {
    userTestId: 4,
    currentModuleId: 8,
    isAssignmentAttempt: false,
    addEventListener() {},
  };
  Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { onLine: true } });
  globalThis.document = {
    visibilityState: 'visible',
    querySelector: () => null,
    querySelectorAll: () => [],
    createElement: () => ({
      style: {},
      setAttribute() {},
      querySelectorAll: () => [],
      querySelector: () => null,
      focus() {},
      remove() {},
    }),
    body: { appendChild() {} },
    querySelectorMain: null,
    querySelector(selector) {
      return selector === 'main' ? { setAttribute() {}, removeAttribute() {} } : null;
    },
    dispatchEvent() {},
  };
  globalThis.CustomEvent = class { constructor(type) { this.type = type; } };
  globalThis.setInterval = callback => { heartbeat = callback; return 71; };
  globalThis.clearInterval = () => {};
  globalThis.fetch = async (_url, options) => {
    const action = JSON.parse(options.body).action;
    if (action === 'claim') {
      return { ok: true, json: async () => ({ token: 'old-token', status: 'active', remaining_seconds: 300 }) };
    }
    if (action === 'heartbeat') {
      return new Promise(resolve => { resolveHeartbeat = resolve; });
    }
    if (action === 'pause') {
      return { ok: true, json: async () => ({ token: 'new-token', status: 'paused', remaining_seconds: 300 }) };
    }
    throw new Error(`Unexpected action ${action}`);
  };
  state.attemptControlToken = null;
  state.attemptControlHeartbeat = null;
  state.attemptControlOverlay = null;
  state.isPaused = false;
  state.isSubmitting = false;
  state.timerInterval = null;

  assert.equal(await initializeAttemptControl(), true);
  const oldHeartbeat = heartbeat();
  await pausePracticeAttempt();
  assert.equal(state.attemptControlToken, 'new-token');

  resolveHeartbeat({ ok: false, status: 409, json: async () => ({ error: 'controller_lost' }) });
  await oldHeartbeat;

  assert.equal(state.attemptControlToken, 'new-token');
  assert.match(state.attemptControlOverlay.innerHTML, /Practice paused/);
  state.attemptControlHeartbeat = null;
});
