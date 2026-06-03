/**
 * Main Entry Point for Test Module
 */
import { state } from './test/state.js';
import {
  toggleTimer,
  generateQuestionButtons,
  showLoadingScreen,
  hideLoadingScreen
} from './test/ui.js';
import {
  showQuestion,
  showReviewSection,
  initializeAutosave,
  nextQuestion,
  prevQuestion
} from './test/navigation.js';
import {
  initializeHighlightFeature,
  initializeQuestionTracking,
  initializeResizablePanels,
  preventNormalCursorBehavior,
  initializeSprInputValidation,
  initializeDesmosCalculator,
  initializeSimpleFullscreen
} from './test/features.js';
import { startTimer } from './test/timer.js';
import { initializeBreakControls } from './test/break.js';

// Expose to global scope for inline onclick handlers and backwards compatibility
window.toggleTimer = toggleTimer;
window.nextQuestion = nextQuestion;
window.prevQuestion = prevQuestion;
window.showQuestion = showQuestion;
window.showReviewSection = showReviewSection;
window.showLoadingScreen = showLoadingScreen;
window.hideLoadingScreen = hideLoadingScreen;

// ============================================================================
// INITIALIZATION
// ============================================================================

function initializeDOMElements() {
  state.backButton = document.getElementById("backButton");
  state.nextButton = document.getElementById("nextButton");

  state.questionElements = Array.from(document.querySelectorAll('.resizable-panel.right-panel .question'));
  state.passageElements = Array.from(document.querySelectorAll('.resizable-panel.left-panel .passage-container'));

  state.questionNumberSpan = document.querySelector(".popover-btn span:first-child");
  state.totalQuestionsSpan = document.querySelector(".popover-btn #total");

  state.totalQuestions = state.questionElements.length;
  state.panelStates = new Array(state.totalQuestions).fill(null);

  if (state.totalQuestionsSpan) state.totalQuestionsSpan.textContent = state.totalQuestions;
  if (state.questionNumberSpan) state.questionNumberSpan.textContent = state.currentQuestionIndex + 1;

  // Initialize UserTestId from blade global variable
  state.userTestId = window.userTestId || null;
}

function handleQuestionButtonClick(e) {
  const button = e.target.closest(".question-btn");
  if (!button) return;
  e.preventDefault();
  const questionNumber = parseInt(button.getAttribute("data-question"));
  if (questionNumber) {
    state.currentQuestionIndex = questionNumber - 1;
    showQuestion(state.currentQuestionIndex);
  }
}

function handleReviewButtonClick(e) {
  const button = e.target.closest(".go-review-btn button");
  if (!button) return;
  e.preventDefault();
  showReviewSection();
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

document.addEventListener("DOMContentLoaded", () => {
  console.log('=== TEST INITIALIZATION ===');

  initializeDOMElements();
  generateQuestionButtons();
  initializeQuestionTracking();
  initializeHighlightFeature();
  initializeResizablePanels();
  preventNormalCursorBehavior();
  initializeSprInputValidation();
  initializeDesmosCalculator();
  initializeSimpleFullscreen();
  initializeBreakControls();
  initializeAutosave();

  // Initialize Timer
  const duration = window.durationMinutes ?? 32;
  startTimer(duration);

  // Show initial question
  showQuestion(state.currentQuestionIndex);

  // Event Delegation
  document.addEventListener("click", handleQuestionButtonClick);
  document.addEventListener("click", handleReviewButtonClick);

  // Hide initial loading screen once fully initialized
  hideLoadingScreen();
});
