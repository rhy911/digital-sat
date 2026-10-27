import test from 'node:test';
import assert from 'node:assert/strict';

// Set up minimal globals required by navigation.js on import
globalThis.window = {
  location: { href: '' },
  addEventListener() {},
  removeEventListener() {},
};
globalThis.document = {
  addEventListener() {},
  removeEventListener() {},
  getElementById() { return null; },
  querySelectorAll() { return []; },
};

const { confirmExitTest } = await import('../../resources/js/test/navigation.js');

test('confirmExitTest prompts custom confirm modal and navigates on confirmation for practice trial', async () => {
  let modalCall = null;
  globalThis.window = {
    homeUrl: '/try',
    isPublicTrial: true,
    isPublicTrialDemo: false,
    isGuestPublicTrial: true,
    isNavigatingLegitimately: false,
    location: { href: '' },
    showCustomConfirm: async (message, type, title, confirmText, cancelText) => {
      modalCall = { message, type, title, confirmText, cancelText };
      return true;
    },
  };

  const result = await confirmExitTest('/try');

  assert.equal(result, true);
  assert.equal(globalThis.window.isNavigatingLegitimately, true);
  assert.equal(globalThis.window.location.href, '/try');
  assert.equal(modalCall.title, 'Exit Practice Test');
  assert.match(modalCall.message, /progress will be saved/);
  assert.equal(modalCall.confirmText, 'Exit');
  assert.equal(modalCall.cancelText, 'Stay');
});

test('confirmExitTest prompts demo-specific warning and stays on cancellation', async () => {
  let modalCall = null;
  globalThis.window = {
    homeUrl: '/try',
    isPublicTrial: false,
    isPublicTrialDemo: true,
    isGuestPublicTrial: true,
    isNavigatingLegitimately: false,
    location: { href: '' },
    showCustomConfirm: async (message, type, title, confirmText, cancelText) => {
      modalCall = { message, type, title, confirmText, cancelText };
      return false;
    },
  };

  const result = await confirmExitTest();

  assert.equal(result, false);
  assert.equal(globalThis.window.isNavigatingLegitimately, false);
  assert.equal(globalThis.window.location.href, '');
  assert.equal(modalCall.title, 'Exit Interface Demo');
  assert.match(modalCall.message, /answers will not be saved/);
  assert.equal(modalCall.confirmText, 'Exit Demo');
  assert.equal(modalCall.cancelText, 'Stay');
});

test('confirmExitTest navigates immediately when custom confirm modal is not available', async () => {
  globalThis.window = {
    homeUrl: '/try',
    isPublicTrial: true,
    isPublicTrialDemo: false,
    isGuestPublicTrial: true,
    isNavigatingLegitimately: false,
    location: { href: '' },
  };

  const result = await confirmExitTest('/custom-destination');

  assert.equal(result, true);
  assert.equal(globalThis.window.isNavigatingLegitimately, true);
  assert.equal(globalThis.window.location.href, '/custom-destination');
});
