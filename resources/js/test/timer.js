import { state } from './state.js';

/**
 * Initialize and start the countdown timer
 */
export function startTimer(durationMinutes) {
  stopTimer();
  state.isPaused = false;
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
  state.timerStartedAtMs = Date.now();
  state.timerPausedMs = 0;
  state.timerPausedStartedAtMs = null;
  installTimerWakeHandlers();
  updateTimerDisplay();

  state.timerInterval = setInterval(() => {
    syncTimer();
  }, 1000);
}

export function stopTimer() {
  if (state.timerInterval) {
    clearInterval(state.timerInterval);
  }
  state.timerInterval = null;
}

export function pauseTimer() {
  if (window.isAssignmentAttempt || state.isUntimed || state.isSubmitting) return;
  if (state.isPaused) return;
  state.isPaused = true;
  state.timerPausedStartedAtMs = Date.now();
  syncTimer();
}

export function resumeTimer() {
  if (window.isAssignmentAttempt || state.isSubmitting) return;
  if (!state.isPaused) return;
  if (state.timerPausedStartedAtMs) {
    state.timerPausedMs += Math.max(0, Date.now() - state.timerPausedStartedAtMs);
  }
  state.timerPausedStartedAtMs = null;
  state.isPaused = false;
  syncTimer();
}

/**
 * Update the timer string in the UI
 */
function updateTimerDisplay() {
  const timerDisplay = document.getElementById("timerDisplay");
  if (!timerDisplay) return;

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
  if (state.isSubmitting || state.isUntimed || !state.timerStartedAtMs) return;

  state.timeLeft = getTimerRemainingSeconds();

  if (state.timeLeft <= 0) {
    stopTimer();
    state.timeLeft = 0;
    updateTimerDisplay();
    handleTimeUp();
    return;
  }

  updateTimerDisplay();
}

export function getTimerElapsedSeconds() {
  if (!state.timerStartedAtMs || state.isUntimed) return 0;

  return Math.min(
    state.timerInitialSeconds,
    Math.floor(getEffectiveElapsedMs() / 1000)
  );
}

function getTimerRemainingSeconds() {
  return Math.max(0, state.timerInitialSeconds - getTimerElapsedSeconds());
}

function getEffectiveElapsedMs(now = Date.now()) {
  if (!state.timerStartedAtMs) return 0;

  let pausedMs = state.timerPausedMs || 0;
  if (state.isPaused && state.timerPausedStartedAtMs) {
    pausedMs += Math.max(0, now - state.timerPausedStartedAtMs);
  }

  return Math.max(0, now - state.timerStartedAtMs - pausedMs);
}

function installTimerWakeHandlers() {
  if (state.timerWakeHandlersInitialized) return;
  state.timerWakeHandlersInitialized = true;

  document.addEventListener('visibilitychange', syncTimer);
  window.addEventListener('focus', syncTimer);
  window.addEventListener('pageshow', syncTimer);
}

/**
 * Action when time runs out
 */
async function handleTimeUp() {
  if (state.isSubmitting) return;
  document.dispatchEvent(new CustomEvent('test-timer-expired'));
}
