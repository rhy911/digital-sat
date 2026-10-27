import test from 'node:test';
import assert from 'node:assert/strict';
import { state } from '../../resources/js/test/state.js';

globalThis.window = {};
const { showInterSectionBreak } = await import('../../resources/js/test/break.js');

function installBreakPage(fetchTiming) {
  let now = 10_000;
  const listeners = new Map();
  const intervalCallbacks = new Map();
  const timeoutCallbacks = new Map();
  let intervalId = 0;
  let timeoutId = 0;
  const display = { textContent: '' };
  const overlay = {
    style: {},
    innerHTML: '',
    removed: false,
    querySelector: () => ({ addEventListener() {} }),
    remove() { this.removed = true; },
  };

  globalThis.performance = { now: () => now };
  globalThis.window = {
    userTestId: 7,
    timingUrl: '/engine/test/timing',
    location: { href: '' },
    addEventListener: (name, callback) => listeners.set(name, callback),
    removeEventListener: name => listeners.delete(name),
  };
  globalThis.document = {
    visibilityState: 'visible',
    body: { appendChild() {} },
    createElement: () => overlay,
    getElementById: id => id === 'breakTimerDisplay' ? display : null,
    addEventListener: (name, callback) => listeners.set(name, callback),
    removeEventListener: name => listeners.delete(name),
  };
  globalThis.setInterval = callback => {
    const id = ++intervalId;
    intervalCallbacks.set(id, callback);
    return id;
  };
  globalThis.clearInterval = id => intervalCallbacks.delete(id);
  globalThis.setTimeout = callback => {
    const id = ++timeoutId;
    timeoutCallbacks.set(id, callback);
    return id;
  };
  globalThis.clearTimeout = id => timeoutCallbacks.delete(id);
  globalThis.fetch = async (...args) => fetchTiming(...args);

  return {
    display,
    intervalCallbacks,
    timeoutCallbacks,
    listeners,
    advance: ms => { now += ms; },
  };
}

const timingResponse = timing => ({ ok: true, json: async () => timing });

test('section break uses server remaining time after a delayed submit response', async () => {
  const page = installBreakPage(async url => {
    assert.match(url, /module_id=9/);
    return timingResponse({ status: 'break', remaining_break_seconds: 300 });
  });
  state.isOnSectionBreak = false;

  showInterSectionBreak(600, () => 'redirecting', { nextModuleId: 9 });
  await new Promise(resolve => setImmediate(resolve));

  assert.equal(page.display.textContent, '05:00');
  page.advance(1_000);
  page.intervalCallbacks.values().next().value();
  assert.equal(page.display.textContent, '04:59');
});

test('section break leaves for the next module when another tab resumed it', async () => {
  let requests = 0;
  let navigations = 0;
  const page = installBreakPage(async () => timingResponse(
    ++requests === 1
      ? { status: 'break', remaining_break_seconds: 300 }
      : { status: 'active', module_id: 9 }
  ));
  state.isOnSectionBreak = false;

  showInterSectionBreak(600, () => {
    navigations++;
    return 'redirecting';
  }, { nextModuleId: 9 });
  await new Promise(resolve => setImmediate(resolve));
  page.listeners.get('focus')();
  await new Promise(resolve => setImmediate(resolve));

  assert.equal(requests, 2);
  assert.equal(navigations, 1);
  assert.equal(state.isOnSectionBreak, false);
  assert.equal(page.intervalCallbacks.size, 0);
});

test('failed initial timing sync shows SYNC and retries until the server confirms the break', async () => {
  let requests = 0;
  const page = installBreakPage(async () => {
    if (++requests === 1) throw new Error('temporary network failure');
    return timingResponse({ status: 'break', remaining_break_seconds: 300 });
  });
  const savedError = console.error;
  console.error = () => {};
  state.isOnSectionBreak = false;

  showInterSectionBreak(600, () => 'redirecting', { nextModuleId: 9 });
  await new Promise(resolve => setImmediate(resolve));

  assert.equal(page.display.textContent, 'SYNC');
  assert.equal(page.intervalCallbacks.size, 0);
  assert.equal(page.timeoutCallbacks.size, 1);
  page.timeoutCallbacks.values().next().value();
  await new Promise(resolve => setImmediate(resolve));

  assert.equal(requests, 2);
  assert.equal(page.display.textContent, '05:00');
  assert.equal(page.intervalCallbacks.size, 1);
  console.error = savedError;
});

test('section break follows server navigation when another tab advanced or completed the attempt', async () => {
  for (const timing of [
    { status: 'advanced', navigation_url: '/engine/session/math-2' },
    { status: 'completed', redirect_url: '/student/scores/7' },
  ]) {
    const page = installBreakPage(async () => timingResponse(timing));
    state.isOnSectionBreak = false;
    let staleNavigation = false;

    showInterSectionBreak(600, () => {
      staleNavigation = true;
      return 'redirecting';
    }, { nextModuleId: 9 });
    await new Promise(resolve => setImmediate(resolve));

    assert.equal(window.location.href, timing.navigation_url || timing.redirect_url);
    assert.equal(window.isNavigatingLegitimately, true);
    assert.equal(staleNavigation, false);
    assert.equal(page.intervalCallbacks.size, 0);
  }
});

test('practice break expiry waits for explicit continuation instead of starting the next module', async () => {
  let now = 10_000;
  let countdown;
  let requests = 0;
  const timerDisplay = { textContent: '' };
  const resumeButton = { addEventListener() {} };
  const overlay = {
    style: {},
    set id(value) { this._id = value; },
    set innerHTML(value) { this._html = value; },
    get innerHTML() { return this._html; },
    querySelector: () => resumeButton,
    remove() {},
  };
  globalThis.performance = { now: () => now };
  globalThis.window = { isAssignmentAttempt: false, nextModuleId: 9, addEventListener() {}, removeEventListener() {} };
  globalThis.document = {
    visibilityState: 'visible',
    body: { appendChild() {} },
    createElement: () => overlay,
    getElementById: id => id === 'breakTimerDisplay' ? timerDisplay : null,
    addEventListener() {},
    removeEventListener() {},
  };
  globalThis.setInterval = callback => { countdown = callback; return 1; };
  globalThis.clearInterval = () => {};
  globalThis.setTimeout = () => 1;
  globalThis.clearTimeout = () => {};
  globalThis.fetch = async () => { requests++; throw new Error('practice must wait for explicit continue'); };
  state.isOnSectionBreak = false;

  const { showInterSectionBreak } = await import('../../resources/js/test/break.js');
  showInterSectionBreak(60, () => {}, { nextModuleId: 9, waitForExplicitContinue: true });
  now += 61_000;
  countdown();

  assert.equal(timerDisplay.textContent, '00:00');
  assert.equal(requests, 0);
  assert.equal(state.isOnSectionBreak, true);
});
