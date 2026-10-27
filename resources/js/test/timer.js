import { state } from './state.js';

let latestTimingRequest = 0;
let timingRetry = null;
let controlLifecycleInstalled = false;

async function controlRequest(action, token = state.attemptControlToken, keepalive = false) {
  const response = await fetch('/engine/test/control', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        ...(token ? { 'X-Attempt-Control': token } : {})
      },
      body: JSON.stringify({ user_test_id: window.userTestId, module_id: window.currentModuleId, action }),
      signal: AbortSignal.timeout(5000),
      keepalive,
      cache: 'no-store'
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const error = new Error(data.error || `Attempt control failed (${response.status})`);
      error.status = response.status;
      throw error;
    }
  return data;
}

export async function initializeAttemptControl() {
  if (window.isPreview || !window.userTestId || !window.currentModuleId) return true;
  installAttemptControlLifecycle();
  try {
    const timing = await controlRequest('claim', null);
    acceptControllerState(timing);
    if (timing.status === 'paused') {
      state.isPaused = true;
      showAttemptControlOverlay('paused');
    }
    return true;
  } catch (error) {
    state.isPaused = true;
    stopTimer();
    if (error.message === 'attempt_in_use') {
      showAttemptControlOverlay('in_use');
      return false;
    }
    console.error('Could not claim the test attempt:', error);
    showAttemptControlOverlay('offline');
    return false;
  }
}

function acceptControllerState(data) {
  if (data.token) state.attemptControlToken = data.token;
  if (!state.attemptControlHeartbeat) {
    state.attemptControlHeartbeat = setInterval(async () => {
      if (!state.attemptControlToken || document.visibilityState === 'hidden' || !navigator.onLine) return;
      const token = state.attemptControlToken;
      try {
        const timing = await controlRequest('heartbeat', token);
        if (state.attemptControlToken !== token) return;
        acceptControllerState(timing);
        if (timing.status === 'paused') {
          state.isPaused = true;
          stopTimer();
          showAttemptControlOverlay('paused');
        }
      } catch (error) {
        if (state.attemptControlToken !== token) return;
        if (error.message === 'controller_lost' || error.message === 'practice_paused') {
          state.attemptControlToken = null;
          if (!window.isAssignmentAttempt) {
            state.isPaused = true;
            stopTimer();
          }
          showAttemptControlOverlay('lost');
        }
      }
    }, 15000);
  }
}

function installAttemptControlLifecycle() {
  if (controlLifecycleInstalled) return;
  controlLifecycleInstalled = true;
  window.addEventListener('offline', () => {
    if (!window.isAssignmentAttempt) {
      pausePracticeAttempt();
      showAttemptControlOverlay('offline');
    }
  });
  window.addEventListener('online', () => {
    if (window.isAssignmentAttempt) return;
    if (state.attemptControlToken) {
      state.isPaused = true;
      showAttemptControlOverlay('paused');
      return;
    }
    initializeAttemptControl().then(claimed => {
      if (!claimed) return;
      if (state.isPaused) showAttemptControlOverlay('paused');
      else {
        dismissAttemptControlOverlay();
        startTimer(window.durationMinutes ?? 32);
      }
    });
  });
  window.addEventListener('pagehide', () => {
    if (!state.attemptControlToken) return;
    controlRequest('release', state.attemptControlToken, true).catch(() => {});
  });
}

export async function pausePracticeAttempt() {
  if (window.isAssignmentAttempt || !state.attemptControlToken || state.isPaused || state.isSubmitting) return;
  state.isPaused = true;
  document.dispatchEvent(new CustomEvent('test-timer-paused'));
  stopTimer();
  showAttemptControlOverlay(navigator.onLine ? 'pausing' : 'offline');
  try {
    const timing = await controlRequest('pause');
    acceptControllerState(timing);
    showAttemptControlOverlay('paused');
  } catch (error) {
    if (error.message === 'controller_lost') state.attemptControlToken = null;
    showAttemptControlOverlay('offline');
  }
}

export function showAttemptControlOverlay(mode) {
  if (!state.attemptControlOverlay) {
    const overlay = document.createElement('div');
    overlay.id = 'attemptControlOverlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.tabIndex = -1;
    Object.assign(overlay.style, {
      position: 'fixed', inset: '0', zIndex: '100000', display: 'grid', placeItems: 'center',
      background: 'rgba(15, 23, 42, .96)', color: 'white', padding: '24px', textAlign: 'center'
    });
    state.attemptControlOverlay = overlay;
    document.body.appendChild(overlay);
  }
  document.querySelector('main')?.setAttribute('inert', '');
  const copy = {
    paused: ['Practice paused', 'Reconnect and choose Resume to continue. Your remaining time is saved.'],
    pausing: ['Pausing practice', 'Saving the timer state…'],
    offline: ['Connection lost', 'Practice is paused. Reconnect, then resume to continue.'],
    in_use: ['Attempt open elsewhere', 'Only one tab or device can control this attempt.'],
    lost: ['Attempt control lost', 'This attempt is controlled elsewhere. Take it over to continue.']
  }[mode] || ['Test unavailable', 'Reconnect to continue.'];
  const mayResume = mode === 'paused' && navigator.onLine;
  const mayTakeOver = ['in_use', 'lost', 'offline'].includes(mode) && navigator.onLine;
  state.attemptControlOverlay.innerHTML = `<div><h1 style="font-size:1.6rem;margin:0 0 12px">${copy[0]}</h1><p style="max-width:34rem;margin:0 auto 20px">${copy[1]}</p>${mayResume ? '<button type="button" data-attempt-action="resume">Resume</button>' : ''}${mayTakeOver ? '<button type="button" data-attempt-action="takeover">Take over</button>' : ''}</div>`;
  state.attemptControlOverlay.querySelectorAll('button').forEach(button => {
    Object.assign(button.style, { padding: '12px 24px', borderRadius: '8px', background: '#f6b93b', color: '#10234a', fontWeight: '700', margin: '4px' });
    button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        const timing = await controlRequest(button.dataset.attemptAction, button.dataset.attemptAction === 'takeover' ? null : state.attemptControlToken);
        acceptControllerState(timing);
        if (timing.status === 'paused') {
          state.isPaused = true;
          showAttemptControlOverlay('paused');
        } else {
          dismissAttemptControlOverlay();
          state.isPaused = false;
          if (!window.isBreakRecoveryView) {
            syncTimerFromServer(timing.remaining_seconds, performance.now());
            document.dispatchEvent(new CustomEvent('test-timer-resumed'));
          }
        }
      } catch (error) {
        button.disabled = false;
        showAttemptControlOverlay(error.message === 'attempt_in_use' ? 'in_use' : 'offline');
      }
    });
  });
  (state.attemptControlOverlay.querySelector('button') || state.attemptControlOverlay).focus();
}

function dismissAttemptControlOverlay() {
  state.attemptControlOverlay?.remove();
  state.attemptControlOverlay = null;
  document.querySelector('main')?.removeAttribute('inert');
}

/**
 * Initialize and start the countdown timer
 */
export function startTimer(durationMinutes) {
  stopTimer();
  if (!window.isAssignmentAttempt && state.isPaused) return;
  state.isPaused = false;
  if (window.isAssignmentAttempt && !window.assignmentTimerSynced) {
    state.isUntimed = false;
    state.timerStartedAtMs = null;
    state.timerInitialSeconds = 0;
    state.timeLeft = 0;
    updateTimerDisplay();
    installTimerWakeHandlers();
    refreshAssignmentTimer(durationMinutes);
    return;
  }
  const isAssignmentTimer = Boolean(window.isAssignmentAttempt);
  const assignmentRemaining = Number(window.serverRemainingSeconds);
  const initialSeconds = isAssignmentTimer && Number.isFinite(assignmentRemaining)
    ? Math.max(0, Math.floor(assignmentRemaining))
    : Math.round(durationMinutes * 60);

  // Infinite/Untimed logic or time limit hit
  if (initialSeconds <= 0) {
    if (window.isPreview) {
      state.isUntimed = true;
      state.timeLeft = 0;
      updateTimerDisplay(); // Will render 00:00
      return; // Bypass setInterval and auto-submit
    } else {
      state.isUntimed = false;
      state.timeLeft = 0;
      updateTimerDisplay();
      handleTimeUp();
      return;
    }
  }

  state.isUntimed = false;
  state.timeLeft = initialSeconds;
  state.timerInitialSeconds = initialSeconds;
  state.timerStartedAtMs = performance.now();
  installTimerWakeHandlers();
  updateTimerDisplay();

  state.timerInterval = setInterval(() => {
    syncTimer();
  }, 1000);
}

export function syncTimerFromServer(remainingSeconds, requestStartedAtMs, responseReceivedAtMs = performance.now()) {
  const roundTripMs = Math.max(0, responseReceivedAtMs - requestStartedAtMs);
  state.timerInitialSeconds = Math.max(0, Math.floor(Number(remainingSeconds) - roundTripMs / 2000));
  state.timerStartedAtMs = performance.now();
  state.timeLeft = getTimerRemainingSeconds();
  updateTimerDisplay();
  if (state.timeLeft <= 0) {
    stopTimer();
    handleTimeUp();
  } else if (!state.timerInterval && !state.isSubmitting) {
    state.timerInterval = setInterval(syncTimer, 1000);
  }
}

export async function refreshAssignmentTimer(durationMinutes = 32) {
  if (timingRetry) clearTimeout(timingRetry);
  timingRetry = null;
  stopTimer();
  const requestId = ++latestTimingRequest;
  const requestedModuleId = window.currentModuleId;
  window.assignmentTimerSynced = false;
  state.timerStartedAtMs = null;
  state.timeLeft = 0;
  updateTimerDisplay();
  const startedAt = performance.now();
  try {
    const params = new URLSearchParams({
      user_test_id: String(window.userTestId),
      module_id: String(window.currentModuleId)
    });
    const response = await fetch(`${window.timingUrl || '/engine/test/timing'}?${params}`, {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
      signal: AbortSignal.timeout(5000)
    });
    if (!response.ok) {
      const error = new Error(`Timing request failed (${response.status})`);
      error.permanent = [401, 403, 404, 409, 422].includes(response.status);
      throw error;
    }
    const timing = await response.json();
    if (requestId !== latestTimingRequest || window.currentModuleId !== requestedModuleId) return;

    if (timing.status === 'advanced' && timing.navigation_url) {
      window.assignmentTimerSynced = true;
      window.assignmentModuleRecovery = true;
      document.dispatchEvent(new CustomEvent('assignment-module-advanced', {
        detail: { url: timing.navigation_url }
      }));
      return;
    }
    if (timing.status === 'completed' && timing.redirect_url) {
      window.assignmentTimerSynced = true;
      window.assignmentModuleRecovery = true;
      window.isNavigatingLegitimately = true;
      window.location.href = timing.redirect_url;
      return;
    }
    if (timing.status === 'break') {
      state.isOnSectionBreak = true;
      stopTimer();
      return;
    }
    if (timing.status !== 'active') {
      const error = new Error('Timing response has no active attempt state');
      error.permanent = true;
      throw error;
    }
    if (Number(timing.module_id) !== Number(window.currentModuleId)) {
      const error = new Error('Timing response is for another module');
      error.permanent = true;
      throw error;
    }
    window.assignmentModuleRecovery = false;
    state.isPaused = false;
    window.assignmentTimerSynced = true;
    syncTimerFromServer(timing.remaining_seconds, startedAt);
  } catch (error) {
    if (requestId !== latestTimingRequest || window.currentModuleId !== requestedModuleId) return;
    console.error('Could not verify assignment timer with server:', error);
    state.timerStartedAtMs = null;
    state.timerInterval = null;
    state.timeLeft = 0;
    updateTimerDisplay();
    if (error.permanent) {
      window.assignmentModuleRecovery = true;
      document.dispatchEvent(new CustomEvent('assignment-timer-unavailable'));
      return;
    }
    scheduleTimingRetry(durationMinutes, requestId, requestedModuleId);
  }
}

function scheduleTimingRetry(durationMinutes, requestId, moduleId) {
  if (timingRetry) clearTimeout(timingRetry);
  timingRetry = setTimeout(() => {
    timingRetry = null;
    if (requestId === latestTimingRequest && window.currentModuleId === moduleId) {
      return refreshAssignmentTimer(durationMinutes);
    }
    return undefined;
  }, 2000);
}

export function stopTimer() {
  latestTimingRequest++;
  if (timingRetry) clearTimeout(timingRetry);
  timingRetry = null;
  if (state.timerInterval) {
    clearInterval(state.timerInterval);
  }
  state.timerInterval = null;
}

export function pauseTimer() {
  if (window.isAssignmentAttempt || state.isUntimed || state.isSubmitting) return;
  if (state.isPaused) return;
  pausePracticeAttempt();
}

export function resumeTimer() {
  if (window.isAssignmentAttempt || state.isSubmitting) return;
  if (!state.isPaused) return;
  controlRequest('resume').then(timing => {
    acceptControllerState(timing);
    state.isPaused = false;
    dismissAttemptControlOverlay();
    syncTimerFromServer(timing.remaining_seconds, performance.now());
    document.dispatchEvent(new CustomEvent('test-timer-resumed'));
  }).catch(() => showAttemptControlOverlay('offline'));
}

/**
 * Update the timer string in the UI
 */
function updateTimerDisplay() {
  const timerDisplay = document.getElementById("timerDisplay");
  if (!timerDisplay) return;

  if (window.isTeacherPreview) {
    timerDisplay.textContent = "PREVIEW";
    timerDisplay.classList.remove('timer-warning');
    return;
  }

  if (window.isAssignmentAttempt && state.timerStartedAtMs === null) {
    timerDisplay.textContent = 'SYNC';
    timerDisplay.classList.add('timer-warning');
    return;
  }

  const minutes = Math.floor(state.timeLeft / 60);
  const seconds = state.timeLeft % 60;

  timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
  
  // Visual warning if less than 5 minutes and not untimed
  if (state.timeLeft < 300 && !state.isUntimed) {
    timerDisplay.classList.add('timer-warning');
  } else {
    timerDisplay.classList.remove('timer-warning');
  }
}

export function syncTimer() {
  if (state.isSubmitting || state.isUntimed || state.timerStartedAtMs === null) return;

  state.timeLeft = getTimerRemainingSeconds();

  if (state.timeLeft <= 0) {
    stopTimer();
    state.timeLeft = 0;
    updateTimerDisplay();
    if (window.isAssignmentAttempt) {
      refreshAssignmentTimer(window.durationMinutes ?? 32);
      return;
    }
    handleTimeUp();
    return;
  }

  updateTimerDisplay();
}

export function getTimerElapsedSeconds() {
  if (state.timerStartedAtMs === null || state.isUntimed) return 0;

  return Math.min(
    state.timerInitialSeconds,
    Math.floor(getEffectiveElapsedMs() / 1000)
  );
}

function getTimerRemainingSeconds() {
  return Math.max(0, state.timerInitialSeconds - getTimerElapsedSeconds());
}

function getEffectiveElapsedMs(now = performance.now()) {
  if (state.timerStartedAtMs === null) return 0;
  return Math.max(0, now - state.timerStartedAtMs);
}

function installTimerWakeHandlers() {
  if (state.timerWakeHandlersInitialized) return;
  state.timerWakeHandlersInitialized = true;

  const refreshOnWake = () => {
    if (state.isOnSectionBreak) {
      return;
    }
    if (window.isAssignmentAttempt) {
      refreshAssignmentTimer(window.durationMinutes ?? 32);
    } else {
      syncTimer();
    }
  };
  document.addEventListener('visibilitychange', refreshOnWake);
  window.addEventListener('focus', refreshOnWake);
  window.addEventListener('pageshow', refreshOnWake);
}

/**
 * Action when time runs out
 */
async function handleTimeUp() {
  if (state.isSubmitting) return;
  document.dispatchEvent(new CustomEvent('test-timer-expired'));
}
