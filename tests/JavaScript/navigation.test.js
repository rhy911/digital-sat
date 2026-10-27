import test from 'node:test';
import assert from 'node:assert/strict';
import { state } from '../../resources/js/test/state.js';

test('a local storage failure does not prevent the server autosave', async () => {
  let requests = 0;
  globalThis.window = {
    userTestId: 17,
    currentModuleId: 23,
    isPreview: false,
    isAssignmentAttempt: false,
    isGuestPublicTrial: false,
  };
  globalThis.document = {
    addEventListener() {},
    querySelector: () => ({ getAttribute: () => 'csrf' }),
    getElementById: () => null,
  };
  globalThis.localStorage = { setItem() { throw new Error('storage disabled'); } };
  globalThis.fetch = async () => {
    requests++;
    return { ok: true, status: 200 };
  };
  state.questionElements = [];
  state.questionTimings = {};
  state.questionActiveStartedAtMs = null;
  state.isPaused = false;
  state.isSubmitting = false;
  state.attemptControlToken = 'attempt-control';

  const { autosaveAnswers } = await import('../../resources/js/test/navigation.js');
  const originalWarn = console.warn;
  console.warn = () => {};
  try {
    await autosaveAnswers();
    assert.equal(requests, 1);
  } finally {
    console.warn = originalWarn;
  }
});

test('a timed-out practice module waits for the student to continue', async () => {
  const listeners = new Map();
  const nodes = new Map();
  let intervalStarts = 0;
  const makeNode = () => ({
    textContent: '',
    innerHTML: '',
    className: '',
    classList: { add() {}, remove() {} },
    addEventListener(type, callback) { listeners.set(type, callback); },
    removeEventListener(type) { listeners.delete(type); },
    click() { listeners.get('click')?.(); },
  });
  const modal = {
    id: 'customAlertModal',
    classList: { add() {}, remove() {} },
    querySelector(selector) {
      const id = selector.match(/#([\w-]+)/)?.[1];
      if (!id) return makeNode();
      if (!nodes.has(id)) nodes.set(id, makeNode());
      return nodes.get(id);
    },
  };
  const loading = { classList: { add() {}, remove() {} } };
  globalThis.window = {
    userTestId: 17,
    userTestUlid: 'attempt-ulid',
    currentModuleId: 23,
    isPreview: false,
    isAssignmentAttempt: false,
    isGuestPublicTrial: false,
    location: { href: '' },
  };
  globalThis.document = {
    fullscreenElement: null,
    webkitFullscreenElement: null,
    mozFullScreenElement: null,
    msFullscreenElement: null,
    querySelector: () => ({ getAttribute: () => 'csrf' }),
    querySelectorAll: () => [],
    getElementById(id) {
      if (id === 'loadingScreen') return loading;
      if (id === 'customAlertModal') return modal;
      if (id === 'customAlertConfirmBtn') return nodes.get('customAlertConfirmBtn');
      return null;
    },
    createElement: () => makeNode(),
    head: { appendChild() {} },
    body: { appendChild() {} },
  };
  globalThis.setInterval = () => { intervalStarts++; return 1; };
  globalThis.clearInterval = () => {};
  globalThis.clearTimeout = () => {};
  globalThis.fetch = async () => ({
    ok: true,
    status: 200,
    json: async () => ({ next_module_id: 24 }),
  });
  state.questionElements = [];
  state.questionTimings = {};
  state.isSubmitting = false;
  state.isPaused = false;
  state.timerInterval = null;
  state.autosaveIntervalId = null;

  const { submitModule } = await import('../../resources/js/test/navigation.js');
  await submitModule({ skipConfirm: true, timedOut: true });

  assert.equal(intervalStarts, 0);
  assert.equal(window.location.href, '');
  nodes.get('customAlertConfirmBtn').click();
  await new Promise(resolve => setImmediate(resolve));
  assert.match(window.location.href, /\/engine\/session\/24/);
});
