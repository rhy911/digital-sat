import { state } from './state.js';
import { showReviewSection } from './navigation.js';
import { showCustomAlert } from './ui.js';

/**
 * Initialize and start the countdown timer
 */
export function startTimer(durationMinutes) {
  if (state.timerInterval) clearInterval(state.timerInterval);

  state.timeLeft = durationMinutes * 60;
  updateTimerDisplay();

  state.timerInterval = setInterval(() => {
    state.timeLeft--;

    if (state.timeLeft <= 0) {
      clearInterval(state.timerInterval);
      state.timeLeft = 0;
      updateTimerDisplay();
      handleTimeUp();
    } else {
      updateTimerDisplay();
    }
  }, 1000);
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
  
  // Visual warning if less than 5 minutes
  if (state.timeLeft < 300) {
    timerDisplay.classList.add('timer-warning');
  } else {
    timerDisplay.classList.remove('timer-warning');
  }
}

/**
 * Action when time runs out
 */
async function handleTimeUp() {
  await showCustomAlert("Time is up! Moving to review section.", "warning", "Time Up");
  showReviewSection();
}
