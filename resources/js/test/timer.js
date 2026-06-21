import { state } from './state.js';
import { submitModule } from './navigation.js';

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
  state.timerStartedAtMs = performance.now();
  updateTimerDisplay();

  state.timerInterval = setInterval(() => {
    if (state.isSubmitting) return;

    if (isAssignmentTimer) {
      const elapsed = Math.floor((performance.now() - state.timerStartedAtMs) / 1000);
      state.timeLeft = Math.max(0, state.timerInitialSeconds - elapsed);
    } else {
      if (state.isPaused) return;
      state.timeLeft--;
    }

    if (state.timeLeft <= 0) {
      stopTimer();
      state.timeLeft = 0;
      updateTimerDisplay();
      handleTimeUp();
    } else {
      updateTimerDisplay();
    }
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
  state.isPaused = true;
}

export function resumeTimer() {
  if (window.isAssignmentAttempt || state.isSubmitting) return;
  state.isPaused = false;
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

/**
 * Action when time runs out
 */
async function handleTimeUp() {
  if (state.isSubmitting) return;
  await submitModule({ skipConfirm: true, timedOut: true });
}
