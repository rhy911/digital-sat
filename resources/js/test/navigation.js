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
    if (window.nextModuleId) {
      navigateModule(`/take-test/${window.nextModuleId}`);
    } else {
      hideLoadingScreen();
      await showCustomAlert("Test Preview completed! Redirecting home...", "success", "Test Completed");
      showLoadingScreen("Completing test preview...");
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
      hideLoadingScreen();
      await showCustomAlert("Test completed! Redirecting to results...", "success", "Test Completed");
      showLoadingScreen("Scoring exam & loading results...");
      window.location.href = data.redirect_url;
    } else if (data.fallback_module_id) {
      hideLoadingScreen();
      let secondsLeft = 10;
      const message = `The ${data.path} module for this section is currently unavailable. \n\nYou will be automatically re-routed to an alternative module in <span id="fallback-countdown">10</span> seconds.`;
      
      let timer;
      
      showCustomAlert(message, "warning", "Module Unavailable", false).then(() => {
        // If user clicks OK, redirect immediately
        if (timer) clearInterval(timer);
        navigateModule(`/take-test/${data.fallback_module_id}`);
      });
      
      timer = setInterval(() => {
        secondsLeft--;
        const counterEl = document.getElementById('fallback-countdown');
        if (counterEl) counterEl.textContent = secondsLeft;
        
        if (secondsLeft <= 0) {
          clearInterval(timer);
          navigateModule(`/take-test/${data.fallback_module_id}`);
        }
      }, 1000);
    } else if (data.next_module_id) {
      navigateModule(`/take-test/${data.next_module_id}`);
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

export async function navigateModule(url) {
  const isFullscreen = document.fullscreenElement || 
                       document.webkitFullscreenElement || 
                       document.mozFullScreenElement || 
                       document.msFullscreenElement;

  if (!isFullscreen) {
    window.location.href = url;
    return;
  }

  showLoadingScreen("Saving responses and loading next section...");
  try {
    const response = await fetch(url);
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    const htmlText = await response.text();
    const newDoc = new DOMParser().parseFromString(htmlText, 'text/html');

    // 1. Update Title
    document.title = newDoc.title;

    // 2. Update Main Content
    const currentMain = document.querySelector('main');
    const newMain = newDoc.querySelector('main');
    if (currentMain && newMain) {
      currentMain.innerHTML = newMain.innerHTML;
    }

    // 3. Update Header title & directions surgically
    const currentH1 = document.querySelector('header h1');
    const newH1 = newDoc.querySelector('header h1');
    if (currentH1 && newH1) {
      currentH1.innerHTML = newH1.innerHTML;
    }

    const currentDirections = document.querySelector('header .overflow-y-auto');
    const newDirections = newDoc.querySelector('header .overflow-y-auto');
    if (currentDirections && newDirections) {
      currentDirections.innerHTML = newDirections.innerHTML;
    }

    // 4. Update Header buttons surgically (highlight / calculator)
    const currentButtonsContainer = document.querySelector('header .flex.justify-end');
    const newButtonsContainer = newDoc.querySelector('header .flex.justify-end');
    if (currentButtonsContainer && newButtonsContainer) {
      const currentCalc = currentButtonsContainer.querySelector('#calculatorBtn');
      const currentHighlight = currentButtonsContainer.querySelector('#highlightNotesBtn');
      const newCalc = newButtonsContainer.querySelector('#calculatorBtn');
      const newHighlight = newButtonsContainer.querySelector('#highlightNotesBtn');
      
      if (currentCalc) currentCalc.remove();
      if (currentHighlight) currentHighlight.remove();
      
      if (newCalc) {
        currentButtonsContainer.insertBefore(newCalc.cloneNode(true), currentButtonsContainer.firstChild);
      } else if (newHighlight) {
        currentButtonsContainer.insertBefore(newHighlight.cloneNode(true), currentButtonsContainer.firstChild);
      }
    }

    // 5. Update footer popover counts and clear buttons
    const currentPopoverBtn = document.querySelector('footer .popover-btn');
    const newPopoverBtn = newDoc.querySelector('footer .popover-btn');
    if (currentPopoverBtn && newPopoverBtn) {
      currentPopoverBtn.innerHTML = newPopoverBtn.innerHTML;
    }

    const popoverTemplate = document.getElementById("popover-content");
    if (popoverTemplate) {
      const questionButtonsContainer = popoverTemplate.querySelector(".flex.flex-wrap.gap-3");
      if (questionButtonsContainer) {
        questionButtonsContainer.innerHTML = "";
      }
    }

    // 6. Extract and evaluate window variables from script inside new main/footer
    const scriptTag = Array.from(newDoc.querySelectorAll('script')).find(s => s.textContent.includes('window.nextModuleId'));
    if (scriptTag) {
      try {
        (0, eval)(scriptTag.textContent);
      } catch (err) {
        console.error("Error evaluating next module scripts:", err);
      }
    }

    // 7. Update browser history/URL without reloading
    history.pushState(null, '', url);

    // 8. Re-initialize elements and state for the new module
    const { startTimer } = await import('./timer.js');
    const {
      initializeHighlightFeature,
      initializeQuestionTracking,
      initializeResizablePanels,
      preventNormalCursorBehavior,
      initializeSprInputValidation,
      initializeDesmosCalculator,
      initializeSimpleFullscreen
    } = await import('./features.js');
    const { generateQuestionButtons } = await import('./ui.js');

    // Reset state variables
    state.currentQuestionIndex = 0;
    state.totalQuestions = 0;
    state.highlightMode = false;
    state.panelStates = [];

    // Re-query DOM Elements
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

    // Run initializers
    generateQuestionButtons();
    initializeQuestionTracking();
    initializeHighlightFeature();
    initializeResizablePanels();
    preventNormalCursorBehavior();
    initializeSprInputValidation();
    initializeDesmosCalculator();
    initializeSimpleFullscreen();

    // Start new timer
    const duration = window.durationMinutes || 32;
    startTimer(duration);

    // Show initial question
    showQuestion(state.currentQuestionIndex);

    // Done! Hide loading
    hideLoadingScreen();

  } catch (err) {
    console.error("Dynamic transition failed, falling back to standard redirect:", err);
    window.location.href = url;
  }
}

