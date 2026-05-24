import { state } from './state.js';
import { 
  updateQuestionButtonStates,
  showLoadingScreen,
  hideLoadingScreen,
  showCustomConfirm,
  showCustomAlert
} from './ui.js';

export function isReviewSectionVisible() {
  const reviewSection = document.getElementById("review-section");
  return reviewSection && !reviewSection.classList.contains("hidden");
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

  if (reviewSection) reviewSection.classList.add("hidden");
  if (resizableContainer) resizableContainer.classList.remove("hidden");

  if (wasReviewVisible) {
    state.currentQuestionIndex = state.totalQuestions - 1;
    index = state.currentQuestionIndex;
  } else {
    state.currentQuestionIndex = index;
  }

  state.questionElements.forEach((el, i) => el.classList.toggle("hidden", i !== index));
  state.passageElements.forEach((el, i) => el.classList.toggle("hidden", i !== index));

  // Layout logic for Math and SPR
  const currentQuestionEl = state.questionElements[index];
  const sectionType = currentQuestionEl.dataset.sectionType;
  const questionType = currentQuestionEl.dataset.questionType;

  const leftPanel = document.querySelector('.resizable-panel.left-panel');
  const rightPanel = document.querySelector('.resizable-panel.right-panel');
  const resizer = document.querySelector('.resizer');

  if (sectionType === 'math' && questionType !== 'student_produced_response') {
    // 1-column layout for non-SPR Math
    if (leftPanel) leftPanel.classList.add('hidden');
    if (resizer) resizer.classList.add('hidden');
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
      leftPanel.classList.remove('hidden');
      leftPanel.style.flex = state.panelStates[index] ? `0 0 ${state.panelStates[index].left}%` : '0 0 50%';
    }
    if (resizer) resizer.classList.remove('hidden');
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
  if (popoverBtn) popoverBtn.classList.remove("hidden");

  updateNavigationButtons();
  updateQuestionButtonStates();

  // Handle [Media:filename] placeholders - Optimized: only current question + mark as processed
  const currentPassageEl = state.passageElements[index];
  const processElements = [];
  
  if (currentQuestionEl) {
    processElements.push(...currentQuestionEl.querySelectorAll('.stem-text, .answer-option label'));
  }
  if (currentPassageEl) {
    processElements.push(currentPassageEl);
  }

  processElements.forEach(area => {
    if (area.classList.contains('media-processed')) return;
    
    const originalHTML = area.innerHTML;
    const newHTML = originalHTML.replace(/(?<!\!)\[Media:([^\]]+)\]/gi, (match, filename) => {
      return `<img src="/storage/media/${filename}" alt="${filename}" class="question-media img-fluid">`;
    });
    
    if (newHTML !== originalHTML) {
      area.innerHTML = newHTML;
    }
    area.classList.add('media-processed');
  });

  if (window.smartRenderMath) {
    window.smartRenderMath(currentQuestionEl);
    // If there's a passage, render it too
    if (currentPassageEl && !currentPassageEl.classList.contains('hidden')) {
      window.smartRenderMath(currentPassageEl);
    }
  }
}

export function showReviewSection() {
  state.questionElements.forEach(el => el.classList.add("hidden"));
  state.passageElements.forEach(el => el.classList.add("hidden"));
  state.currentQuestionIndex = state.totalQuestions;

  const reviewSection = document.getElementById("review-section");
  const resizableContainer = document.querySelector(".resizable-container");

  if (reviewSection) reviewSection.classList.remove("hidden");
  if (resizableContainer) resizableContainer.classList.add("hidden");

  const popoverBtn = document.querySelector(".popover-btn");
  if (popoverBtn) popoverBtn.classList.add("hidden");

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

  const confirmNext = await showCustomConfirm("You are about to proceed to the next module/section.\n\nAre you ready to continue?", "warning", "Proceed to Next Section");
  if (!confirmNext) return;

  if (window.isPreview) {
    showLoadingScreen("Saving responses and loading next section...");
    if (window.nextModuleId) {
      window.location.href = `/take-test/${window.nextModuleId}`;
    } else {
      showLoadingScreen("Completing test preview...");
      await showCustomAlert("Test Preview completed! Redirecting home...", "success", "Test Completed");
      window.location.href = '/home';
    }
    return;
  }

  showLoadingScreen("Saving responses & scoring current module...");
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
      showLoadingScreen("Scoring exam & loading results...");
      await showCustomAlert("Test completed! Redirecting to results...", "success", "Test Completed");
      window.location.href = data.redirect_url;
    } else if (data.fallback_module_id) {
      hideLoadingScreen();
      let secondsLeft = 10;
      const message = `The ${data.path} module for this section is currently unavailable. \n\nYou will be automatically re-routed to an alternative module in <span id="fallback-countdown">10</span> seconds.`;
      
      let timer;
      
      showCustomAlert(message, "warning", "Module Unavailable", false).then(() => {
        // If user clicks OK, redirect immediately
        if (timer) clearInterval(timer);
        showLoadingScreen("Re-routing to alternative module...");
        window.location.href = `/take-test/${data.fallback_module_id}`;
      });
      
      timer = setInterval(() => {
        secondsLeft--;
        const counterEl = document.getElementById('fallback-countdown');
        if (counterEl) counterEl.textContent = secondsLeft;
        
        if (secondsLeft <= 0) {
          clearInterval(timer);
          showLoadingScreen("Re-routing to alternative module...");
          window.location.href = `/take-test/${data.fallback_module_id}`;
        }
      }, 1000);
    } else if (data.next_module_id) {
      showLoadingScreen("Adaptive routing complete! Loading next module...");
      window.location.href = `/take-test/${data.next_module_id}`;
    } else {
      hideLoadingScreen();
      console.error("Submission failed", data);
      const msg = data.error || data.message || "Error submitting test. Please try again.";
      await showCustomAlert(msg, "error", "Submission Error");
    }
  } catch (error) {
    hideLoadingScreen();
    console.error("Error submitting module:", error);
    await showCustomAlert("Network error: " + error.message, "error", "Network Error");
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
