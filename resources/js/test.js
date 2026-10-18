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
  prevQuestion,
  preprocessQuestionMedia
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
import { initializeScreenSizeGuard } from './test/screen-guard.js';

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

document.addEventListener("DOMContentLoaded", async () => {
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
  initializeScreenSizeGuard();

  // Initialize Timer
  const duration = window.durationMinutes ?? 32;
  startTimer(duration);

  // Pre-process all question media placeholders upfront
  preprocessQuestionMedia();

  // Pre-render KaTeX across all questions and passages while loading screen is active
  if (window.smartRenderMath) {
    await window.smartRenderMath(document.body);
  }

  // Preload and await KaTeX font readiness to prevent FOUT layout shift
  if (document.fonts) {
    try {
      document.fonts.load('1em KaTeX_Main');
      document.fonts.load('1em KaTeX_Math');
      document.fonts.load('1em KaTeX_Size1');
      await document.fonts.ready;
    } catch (_) {}
  }

  // Show initial question (already pre-rendered at final layout)
  showQuestion(state.currentQuestionIndex);

  // Event Delegation
  document.addEventListener("click", handleQuestionButtonClick);
  document.addEventListener("click", handleReviewButtonClick);

  // Hide initial loading screen once fully initialized and fonts are settled
  hideLoadingScreen();

  // Prevent browser visual search and operations on exam images
  preventImageSearchSettings();
});

function preventImageSearchSettings() {
  document.querySelectorAll('.passage-container img, .question-body img, .stem-text img, .answer-row img').forEach(img => {
    img.setAttribute('disablepictureinpicture', '');
    img.setAttribute('msallowcapturethroughlens', 'false');
    img.setAttribute('draggable', 'false');
    img.addEventListener('contextmenu', e => e.preventDefault());
    img.addEventListener('dragstart', e => e.preventDefault());
  });
}
