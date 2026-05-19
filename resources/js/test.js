/**
 * Main Entry Point for Test Module
 */
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import { state } from './test/state.js';
import { 
  toggleTimer, 
  generateQuestionButtons, 
  initializePopover, 
  initializeDropdown, 
  initializeMoreDropdown,
  hidePopover 
} from './test/ui.js';
import { 
  showQuestion, 
  showReviewSection, 
  nextQuestion, 
  prevQuestion 
} from './test/navigation.js';
import { 
  initializeHighlightFeature, 
  initializeQuestionTracking, 
  initializeResizablePanels, 
  preventNormalCursorBehavior,
  initializeSprInputValidation,
  initializeDesmosCalculator
} from './test/features.js';
import { startTimer } from './test/timer.js';

// Expose to global scope for inline onclick handlers and backwards compatibility
window.toggleTimer = toggleTimer;
window.nextQuestion = nextQuestion;
window.prevQuestion = prevQuestion;
window.showQuestion = showQuestion;
window.showReviewSection = showReviewSection;

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
    const popoverTrigger = document.querySelector('[data-bs-toggle="popover"]');
    if (popoverTrigger) hidePopover(popoverTrigger);
  }
}

function handleReviewButtonClick(e) {
  const button = e.target.closest(".go-review-btn button, .btn-outline-primary");
  if (!button) return;
  e.preventDefault();
  showReviewSection();
  const popoverTrigger = document.querySelector('[data-bs-toggle="popover"]');
  if (popoverTrigger) hidePopover(popoverTrigger);
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

document.addEventListener("DOMContentLoaded", () => {
  console.log('=== TEST INITIALIZATION ===');
  
  initializeDOMElements();
  generateQuestionButtons();
  initializePopover();
  initializeDropdown();
  initializeMoreDropdown();
  initializeQuestionTracking();
  initializeHighlightFeature();
  initializeResizablePanels();
  preventNormalCursorBehavior();
  initializeSprInputValidation();
  initializeDesmosCalculator();

  // Initialize Timer
  const duration = window.durationMinutes || 32;
  startTimer(duration);

  // Show initial question
  showQuestion(state.currentQuestionIndex);

  // Event Delegation
  document.addEventListener("click", handleQuestionButtonClick);
  document.addEventListener("click", handleReviewButtonClick);
});
