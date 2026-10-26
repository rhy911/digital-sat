import { state } from './state.js';

let latestTimingRequest = 0;
let timingRetry = null;

/**
 * Initialize and start the countdown timer
 */
export function startTimer(durationMinutes) {
  stopTimer();
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
  state.timerPausedMs = 0;
  state.timerPausedStartedAtMs = null;
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
  state.timerPausedMs = 0;
  state.timerPausedStartedAtMs = null;
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
      cache: 'no-store'
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
  state.isPaused = true;
  state.timerPausedStartedAtMs = performance.now();
  syncTimer();
  document.dispatchEvent(new CustomEvent('test-timer-paused'));
}

export function resumeTimer() {
  if (window.isAssignmentAttempt || state.isSubmitting) return;
  if (!state.isPaused) return;
  if (state.timerPausedStartedAtMs !== null) {
    state.timerPausedMs += Math.max(0, performance.now() - state.timerPausedStartedAtMs);
  }
  state.timerPausedStartedAtMs = null;
  state.isPaused = false;
  syncTimer();
  document.dispatchEvent(new CustomEvent('test-timer-resumed'));
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

  let pausedMs = state.timerPausedMs || 0;
  if (state.isPaused && state.timerPausedStartedAtMs !== null) {
    pausedMs += Math.max(0, now - state.timerPausedStartedAtMs);
  }

  return Math.max(0, now - state.timerStartedAtMs - pausedMs);
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
