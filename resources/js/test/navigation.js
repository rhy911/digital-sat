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
        rightPanel.style.maxWidth = 'none'; // Full width for panel
        rightPanel.style.margin = '0';

        // Apply width limit to question content, not the panel itself
        state.questionElements.forEach(q => {
            q.style.maxWidth = '800px';
            q.style.margin = '0 auto';
        });
    }
  } else {
    // Standard 2-column layout
    if (leftPanel) {
        leftPanel.classList.remove('d-none');
        leftPanel.style.flex = state.panelStates[index] ? `0 0 ${state.panelStates[index].left}%` : '0 0 50%';
    }
    if (resizer) resizer.classList.remove('d-none');
    if (rightPanel) {
        rightPanel.style.flex = state.panelStates[index] ? `0 0 ${state.panelStates[index].right}%` : '0 0 49%';
        rightPanel.style.maxWidth = 'none';
        rightPanel.style.margin = '0';
        rightPanel.style.paddingTop = '40px';

        // Reset question content width for 2-column mode
        state.questionElements.forEach(q => {
            q.style.maxWidth = 'none';
            q.style.margin = '0';
        });
    }
  }

  if (state.questionNumberSpan) state.questionNumberSpan.textContent = index + 1;

  const popoverBtn = document.querySelector(".popover-btn");
  if (popoverBtn) popoverBtn.classList.remove("d-none");

  updateNavigationButtons();
  updateQuestionButtonStates();
  
  // Handle [Media:filename] placeholders
  const contentAreas = document.querySelectorAll('.stem-text, .passage-container, .answer-option label');
  contentAreas.forEach(area => {
    area.innerHTML = area.innerHTML.replace(/(?<!\!)\[Media:([^\]]+)\]/gi, (match, filename) => {
        return `<img src="/storage/media/${filename}" alt="${filename}" class="question-media img-fluid">`;
    });
  });

  if (window.renderMathInElement) {
    window.renderMathInElement(document.body, {
      delimiters: [
          {left: "$$", right: "$$", display: true},
          {left: "$", right: "$", display: false},
          {left: "\\(", right: "\\)", display: false},
          {left: "\\[", right: "\\]", display: true}
      ],
      throwOnError : false
    });
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
    submitModule();
    return;
  }
  if (state.currentQuestionIndex < state.totalQuestions - 1) {
    state.currentQuestionIndex++;
    showQuestion(state.currentQuestionIndex);
  } else {
    showReviewSection();
  }
}

async function submitModule() {
  const answers = {};
  
  state.questionElements.forEach(questionEl => {
    const qId = questionEl.dataset.questionId;
    const qType = questionEl.dataset.questionType;
    
    if (qType === 'multiple_choice') {
      const selected = questionEl.querySelector('input[type="radio"]:checked');
      if (selected) answers[qId] = selected.value;
    } else if (qType === 'student_produced_response') {
      const input = questionEl.querySelector('.spr-input');
      if (input) answers[qId] = input.value;
    }
  });

  const confirmNext = confirm("You are about to proceed to the next module/section.\n\nAre you ready to continue?");
  if (!confirmNext) return;

  try {
    const response = await fetch('/test/submit-module', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        user_test_id: window.userTestId,
        module_id: window.currentModuleId,
        answers: answers
      })
    });

    const data = await response.json();

    if (data.test_completed) {
      alert("Test completed! Redirecting to results...");
      window.location.href = data.redirect_url;
    } else if (data.next_module_id) {
      window.location.href = `/take-test/${data.next_module_id}`;
    } else {
      console.error("Submission failed", data);
      alert("Error submitting test. Please try again.");
    }
  } catch (error) {
    console.error("Error submitting module:", error);
    alert("Network error. Please try again.");
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
