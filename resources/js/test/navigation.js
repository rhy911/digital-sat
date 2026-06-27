import { state } from './state.js';
import { 
  updateQuestionButtonStates,
  showLoadingScreen,
  hideLoadingScreen,
  showCustomConfirm,
  showCustomAlert
} from './ui.js';

let autosaveInitialized = false;
let autosaveTimer = null;
let lastAutosavePayload = '';

export function initSecurity() {
  // Block DevTools shortcuts
  document.addEventListener('keydown', function(e) {
    if (e.key === 'F12' || 
        (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J')) || 
        (e.ctrlKey && e.key === 'U')) {
      e.preventDefault();
      return false;
    }
  });

  // Block right-click
  document.addEventListener('contextmenu', e => e.preventDefault());

  // Warn on tab close / F5
  window.addEventListener('beforeunload', function(e) {
    if (window.isNavigatingLegitimately) return;
    // If the user hasn't finished the test, show the warning
    if (document.getElementById('timerDisplay')) {
      e.preventDefault();
      e.returnValue = ''; // Standard behavior
    }
  });

  // Block back button
  history.pushState(null, null, location.href);
  window.addEventListener('popstate', function() {
    history.pushState(null, null, location.href);
    showCustomAlert('You cannot use the Back button during a test.');
  });
}

// Ensure initSecurity is called when app starts
document.addEventListener('DOMContentLoaded', () => {
  if(document.getElementById('timerDisplay')) {
    initSecurity();
  }
});

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

  if (reviewSection) reviewSection.classList.add("hidden");
  if (resizableContainer) resizableContainer.classList.remove("hidden");

  state.currentQuestionIndex = index;

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
      const safeFilename = filename.trim();
      if (!/^[A-Za-z0-9]{20}\.(jpe?g|png|gif|webp|svg)$/i.test(safeFilename)) {
        return match;
      }

      return `<img src="/media/${safeFilename}" alt="${safeFilename}" class="question-media img-fluid">`;
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

export function collectAnswers() {
  const answers = {};

  state.questionElements.forEach(questionEl => {
    const qId = questionEl.dataset.questionId;
    const qType = questionEl.dataset.questionType;
    if (!qId) return;

    if (qType === 'multiple_choice') {
      const selected = questionEl.querySelector('input[type="radio"]:checked');
      answers[qId] = selected ? selected.value : null;
    } else if (qType === 'student_produced_response') {
      const input = questionEl.querySelector('.spr-input');
      answers[qId] = input && input.value.trim() !== '' ? input.value.trim() : null;
    }
  });

  return answers;
}

export function initializeAutosave() {
  if (autosaveInitialized) return;
  autosaveInitialized = true;

  document.addEventListener('change', (event) => {
    if (event.target.matches('.question input[type="radio"]')) {
      scheduleAutosave();
    }
  });

  document.addEventListener('input', (event) => {
    if (event.target.matches('.question .spr-input')) {
      scheduleAutosave();
    }
  });

  // Periodically autosave elapsed time every 15 seconds
  setInterval(() => {
    if (!state.isSubmitting && !window.isPreview && window.userTestId && window.currentModuleId) {
      autosaveAnswers();
    }
  }, 15000);
}

function scheduleAutosave() {
  if (window.isPreview || !window.userTestId || !window.currentModuleId) return;

  clearTimeout(autosaveTimer);
  autosaveTimer = setTimeout(() => {
    autosaveAnswers();
  }, window.isAssignmentAttempt ? 0 : 700);
}

async function autosaveAnswers() {
  if (window.isPreview || !window.userTestId || !window.currentModuleId) return;

  const elapsed = (window.initialElapsedSeconds || 0) + Math.round((window.durationMinutes || 0) * 60 - state.timeLeft);
  const elapsed_seconds = Math.max(0, elapsed);

  const answers = collectAnswers();
  const payload = JSON.stringify({
    user_test_id: window.userTestId,
    module_id: window.currentModuleId,
    answers: answers,
    elapsed_seconds: elapsed_seconds
  });

  // Local state backup
  localStorage.setItem(`sat_state_${window.userTestId}_${window.currentModuleId}`, JSON.stringify(answers));

  if (payload === lastAutosavePayload) return;

  try {
    const response = await fetch('/engine/test/autosave-module', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: payload,
      keepalive: true
    });

    if (response.status === 409) {
      const data = await response.json();
      if (data.error === 'module_expired') {
        await submitModule({ skipConfirm: true, timedOut: true });
        return;
      }
    }

    if (!response.ok) {
      throw new Error(`Autosave failed with status ${response.status}`);
    }

    lastAutosavePayload = payload;
  } catch (error) {
    console.warn("Autosave failed:", error);
  }
}

export async function submitModule(options = {}) {
  if (state.isSubmitting) return;

  const answers = collectAnswers();
  const skipConfirm = options.skipConfirm || false;
  state.isSubmitting = true;

  if (!skipConfirm) {
    const confirmNext = await showCustomConfirm("You are about to proceed to the next module/section.\n\nAre you ready to continue?", "warning", "Proceed to Next Section");
    if (!confirmNext) {
      state.isSubmitting = false;
      return;
    }
  }

  if (state.timerInterval) {
    clearInterval(state.timerInterval);
    state.timerInterval = null;
  }

  if (window.isPreview) {
    if (window.nextModuleId) {
      navigateModule(`/engine/session/${window.nextModuleId}`);
    } else {
      hideLoadingScreen();
      await showCustomAlert("Test Preview completed! Redirecting home...", "success", "Test Completed");
      showLoadingScreen("Completing test preview...");
      window.isNavigatingLegitimately = true;
      window.location.href = window.homeUrl || '/student/progress';
    }
    return;
  }

  clearTimeout(autosaveTimer);
  showLoadingScreen("Saving responses & scoring current module...");
  try {
    const response = await fetch('/engine/test/submit-module', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        user_test_id: window.userTestId,
        module_id: window.currentModuleId,
        answers: answers
      })
    });

    let data = await response.json();

    if (data.status === 'scoring') {
        data = await new Promise((resolve) => {
            let pollAttempts = 0;
            const maxPollAttempts = 40;
            const poll = setInterval(async () => {
                try {
                    pollAttempts++;
                    const statusRes = await fetch(`/engine/submit-status/${window.userTestUlid || window.userTestId}`);
                    const statusData = await statusRes.json();
                    if (statusData.status !== 'scoring') {
                        clearInterval(poll);
                        resolve(statusData);
                    } else if (pollAttempts >= maxPollAttempts) {
                        clearInterval(poll);
                        resolve({
                          error: "Scoring timeout",
                          message: "Scoring is taking longer than expected. Please try again in a minute."
                        });
                    }
                } catch (e) {
                    clearInterval(poll);
                    resolve({
                      error: "Polling error",
                      message: "Unable to check scoring status. Please try again."
                    });
                }
            }, 1500);
        });
    }

    const timedOut = Boolean(options.timedOut || data.timed_out);

    if (data.test_completed) {
      hideLoadingScreen();
      let secondsLeft = 5;
      let completionTimer;
      let hasNavigated = false;
      const homeUrl = data.redirect_url || window.homeUrl || '/student/progress';
      const doNavigateHome = () => {
        if (hasNavigated) return;
        hasNavigated = true;
        if (completionTimer) clearInterval(completionTimer);
        window.isNavigatingLegitimately = true;
        window.location.href = homeUrl;
      };

      showCustomAlert(
        `Test completed. You will be redirected home in <strong id="completion-countdown">5</strong> seconds.`,
        "success",
        "Test Completed",
        true,
        "Back to Home"
      ).then(doNavigateHome);

      completionTimer = setInterval(() => {
        secondsLeft--;
        const counterEl = document.getElementById('completion-countdown');
        if (counterEl) counterEl.textContent = secondsLeft;
        if (secondsLeft <= 0) doNavigateHome();
      }, 1000);
    } else if (data.fallback_module_id) {
      hideLoadingScreen();
      let secondsLeft = 5;
      const message = `The ${data.path} module for this section is currently unavailable. \n\nYou will be automatically re-routed to an alternative module in <span id="fallback-countdown">5</span> seconds.`;
      
      let timer;
      let hasNavigated = false;

      const doNavigate = () => {
        if (hasNavigated) return;
        hasNavigated = true;
        if (timer) clearInterval(timer);

        const confirmBtn = document.getElementById('customAlertConfirmBtn');
        if (confirmBtn) confirmBtn.click();

        navigateModule(`/engine/session/${data.fallback_module_id}`);
      };
      
      showCustomAlert(message, "warning", "Module Unavailable", true, "Re-route Now").then(() => {
        doNavigate();
      });
      
      timer = setInterval(() => {
        secondsLeft--;
        const counterEl = document.getElementById('fallback-countdown');
        if (counterEl) counterEl.textContent = secondsLeft;
        
        if (secondsLeft <= 0) {
          doNavigate();
        }
      }, 1000);
    } else if (data.next_module_id) {
      if (timedOut) {
        hideLoadingScreen();
        let secondsLeft = 5;
        let continueTimer;
        let hasNavigated = false;
        const doNavigate = () => {
          if (hasNavigated) return;
          hasNavigated = true;
          if (continueTimer) clearInterval(continueTimer);
          navigateModule(`/engine/session/${data.next_module_id}`);
        };

        showCustomAlert(
          `Time is up. Your saved answers were submitted. Continuing to the next module in <strong id="module-continue-countdown">5</strong> seconds.`,
          "warning",
          "Module Complete",
          true,
          "Continue Now"
        ).then(doNavigate);

        continueTimer = setInterval(() => {
          secondsLeft--;
          const counterEl = document.getElementById('module-continue-countdown');
          if (counterEl) counterEl.textContent = secondsLeft;
          if (secondsLeft <= 0) doNavigate();
        }, 1000);
      } else {
        navigateModule(`/engine/session/${data.next_module_id}`);
      }
    } else {
      hideLoadingScreen();
      console.error("Submission failed", data);
      const msg = data.error || data.message || "Error submitting test. Please try again.";
      await showCustomAlert(msg, "error", "Submission Error");
      state.isSubmitting = false;
    }
  } catch (error) {
    hideLoadingScreen();
    console.error("Error submitting module:", error);
    await showCustomAlert("Network error: " + error.message, "error", "Network Error");
    state.isSubmitting = false;
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
  // Append attempt query param if exists and not already present
  if (window.userTestUlid && !url.includes('attempt=')) {
    const separator = url.includes('?') ? '&' : '?';
    url = `${url}${separator}attempt=${window.userTestUlid}`;
  }

  const isFullscreen = document.fullscreenElement || 
                       document.webkitFullscreenElement || 
                       document.mozFullScreenElement || 
                       document.msFullscreenElement;

  // Clear any existing alert modals safely
  const confirmBtn = document.getElementById('customAlertConfirmBtn');
  if (confirmBtn) {
    confirmBtn.click();
  }

  if (!isFullscreen) {
    window.isNavigatingLegitimately = true;
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
    const currentButtonsContainer = document.getElementById('testToolBar');
    const newButtonsContainer = newDoc.getElementById('testToolBar');
    if (currentButtonsContainer && newButtonsContainer) {
      const newCalc = newButtonsContainer.querySelector('#calculatorBtn');
      const newHighlight = newButtonsContainer.querySelector('#highlightNotesBtn');
      
      currentButtonsContainer.querySelectorAll('#calculatorBtn, #highlightNotesBtn').forEach(button => button.remove());
      
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
    const { initializeBreakControls } = await import('./break.js');

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
    initializeBreakControls();
    initializeAutosave();

    // Start new timer
    const duration = window.durationMinutes ?? 32;
    state.isSubmitting = false;
    startTimer(duration);

    // Show initial question
    showQuestion(state.currentQuestionIndex);

    // Done! Hide loading
    hideLoadingScreen();

  } catch (err) {
    console.error("Dynamic transition failed, falling back to standard redirect:", err);
    window.isNavigatingLegitimately = true;
    window.location.href = url;
  }
}
