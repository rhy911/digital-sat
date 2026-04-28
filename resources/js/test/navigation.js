import { state } from './state.js';
import { updateQuestionButtonStates } from './ui.js';

export function isReviewSectionVisible() {
  const reviewSection = document.getElementById("review-section");
  return reviewSection && !reviewSection.classList.contains("d-none");
}

export function updateNavigationButtons() {
  if (state.backButton) {
    const isReviewVisible = isReviewSectionVisible();
    state.backButton.style.display = state.currentQuestionIndex === 0 && !isReviewVisible ? "none" : "block";
  }
}

export function showQuestion(index) {
  const reviewSection = document.getElementById("review-section");
  const resizableContainer = document.querySelector(".resizable-container");
  const wasReviewVisible = isReviewSectionVisible();

  if (reviewSection) reviewSection.classList.add("d-none");
  if (resizableContainer) resizableContainer.classList.remove("d-none");

  if (wasReviewVisible) {
    state.currentQuestionIndex = state.totalQuestions - 1;
    index = state.currentQuestionIndex;
  } else {
    state.currentQuestionIndex = index;
  }

  state.questionElements.forEach((el, i) => el.classList.toggle("d-none", i !== index));
  state.passageElements.forEach((el, i) => el.classList.toggle("d-none", i !== index));

  // Layout logic for Math and SPR
  const currentQuestionEl = state.questionElements[index];
  const sectionType = currentQuestionEl.dataset.sectionType;
  const questionType = currentQuestionEl.dataset.questionType;

  const leftPanel = document.querySelector('.resizable-panel.left-panel');
  const rightPanel = document.querySelector('.resizable-panel.right-panel');
  const resizer = document.querySelector('.resizer');

  if (sectionType === 'math' && questionType !== 'student_produced_response') {
    // 1-column layout for non-SPR Math
    if (leftPanel) leftPanel.classList.add('d-none');
    if (resizer) resizer.classList.add('d-none');
    if (rightPanel) {
        rightPanel.style.flex = '0 0 100%';
        rightPanel.style.maxWidth = '800px';
        rightPanel.style.margin = '0 auto';
    }
  } else {
    // Standard 2-column layout (or SPR Math which needs directions on left)
    if (leftPanel) {
        leftPanel.classList.remove('d-none');
        leftPanel.style.flex = state.panelStates[index] ? `0 0 ${state.panelStates[index].left}%` : '0 0 50%';
    }
    if (resizer) resizer.classList.remove('d-none');
    if (rightPanel) {
        rightPanel.style.flex = state.panelStates[index] ? `0 0 ${state.panelStates[index].right}%` : '0 0 49%';
        rightPanel.style.maxWidth = 'none';
        rightPanel.style.margin = '0';
    }
  }

  if (state.questionNumberSpan) state.questionNumberSpan.textContent = index + 1;

  const popoverBtn = document.querySelector(".popover-btn");
  if (popoverBtn) popoverBtn.classList.remove("d-none");

  updateNavigationButtons();
  updateQuestionButtonStates();
  
  // Re-run KaTeX auto-render if available
  if (window.renderMathInElement) {
    window.renderMathInElement(document.body);
  }
}

export function showReviewSection() {
  state.questionElements.forEach(el => el.classList.add("d-none"));
  state.passageElements.forEach(el => el.classList.add("d-none"));
  state.currentQuestionIndex = state.totalQuestions;

  const reviewSection = document.getElementById("review-section");
  const resizableContainer = document.querySelector(".resizable-container");
  
  if (reviewSection) reviewSection.classList.remove("d-none");
  if (resizableContainer) resizableContainer.classList.add("d-none");

  const popoverBtn = document.querySelector(".popover-btn");
  if (popoverBtn) popoverBtn.classList.add("d-none");

  updateNavigationButtons();
  updateQuestionButtonStates();
}

export function nextQuestion() {
  if (isReviewSectionVisible()) {
    if (window.nextModuleId) {
      const targetName = window.nextModuleName || "the next module";
      const confirmNext = confirm(`You are about to proceed to ${targetName}.\n\nAre you ready to continue?`);
      if (confirmNext) {
        window.location.href = `/take-test/${window.nextModuleId}`;
      }
    } else {
      alert("You have completed the practice test.");
      window.location.href = "/home";
    }
    return;
  }
  if (state.currentQuestionIndex < state.totalQuestions - 1) {
    state.currentQuestionIndex++;
    showQuestion(state.currentQuestionIndex);
  } else {
    showReviewSection();
  }
}

export function prevQuestion() {
  if (isReviewSectionVisible()) {
    state.currentQuestionIndex = state.totalQuestions - 1;
    showQuestion(state.currentQuestionIndex);
  } else if (state.currentQuestionIndex > 0) {
    state.currentQuestionIndex--;
    showQuestion(state.currentQuestionIndex);
  }
}
