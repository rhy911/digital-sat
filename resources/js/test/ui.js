import { state } from './state.js';
import * as bootstrap from 'bootstrap';

// ============================================================================
// TIMER FUNCTIONALITY
// ============================================================================

export function toggleTimer() {
  const timerDisplay = document.getElementById("timerDisplay");
  const clockIcon = document.getElementById("clockIcon");
  const timerToggle = document.getElementById("timerToggle");

  if (timerDisplay.classList.contains("d-none")) {
    timerDisplay.classList.remove("d-none");
    clockIcon.classList.add("d-none");
    timerToggle.textContent = "Hide";
  } else {
    timerDisplay.classList.add("d-none");
    clockIcon.classList.remove("d-none");
    timerToggle.textContent = "Show";
  }
  console.log(`Timer toggled: ${timerDisplay.classList.contains("d-none") ? "Hidden" : "Visible"}`);
}

// ============================================================================
// QUESTION BUTTONS
// ============================================================================

export function createQuestionButton(questionNumber) {
  const button = document.createElement("button");
  button.className = "btn question-btn";
  button.textContent = questionNumber;
  button.setAttribute("data-question", questionNumber);
  return button;
}

export function generateQuestionButtons() {
  const popoverTemplate = document.getElementById("popover-content");
  if (!popoverTemplate) return;

  const questionButtonsContainer = popoverTemplate.querySelector(".d-flex.flex-wrap.gap-3");
  if (!questionButtonsContainer || questionButtonsContainer.children.length > 0) return;

  for (let i = 1; i <= state.totalQuestions; i++) {
    questionButtonsContainer.appendChild(createQuestionButton(i));
  }

  generateNavigationBoxButtons();
}

export function generateNavigationBoxButtons() {
  const navBoxContainer = document.querySelector(".question-navigation-box .d-flex.flex-wrap");
  if (!navBoxContainer) return;

  navBoxContainer.innerHTML = "";
  for (let i = 1; i <= state.totalQuestions; i++) {
    navBoxContainer.appendChild(createQuestionButton(i));
  }
}

export function updateButtonState(button, includeCurrent = true) {
  const buttonIndex = parseInt(button.getAttribute("data-question")) - 1;
  button.classList.remove("current", "answered", "marked-for-review");

  if (includeCurrent && buttonIndex === state.currentQuestionIndex) {
    button.classList.add("current");
  }

  const questionElement = state.questionElements[buttonIndex];
  if (isQuestionAnswered(questionElement)) button.classList.add("answered");
  if (isQuestionMarkedForReview(questionElement)) button.classList.add("marked-for-review");
}

function isQuestionAnswered(questionElement) {
  if (!questionElement) return false;
  const selectedAnswer = questionElement.querySelector('input[type="radio"]:checked');
  const textInput = questionElement.querySelector('input.answer-input, input.spr-input');
  return selectedAnswer || (textInput && textInput.value.trim() !== '');
}

function isQuestionMarkedForReview(questionElement) {
  if (!questionElement) return false;
  return !!questionElement.querySelector(".bookmark.marked");
}

export function updateQuestionButtonStates() {
  const popoverTemplate = document.getElementById("popover-content");
  if (popoverTemplate) {
    popoverTemplate.querySelectorAll(".question-btn").forEach(button => updateButtonState(button));
  }
  document.querySelectorAll(".popover .question-btn").forEach(button => updateButtonState(button));
  document.querySelectorAll(".question-navigation-box .question-btn").forEach(button => updateButtonState(button, false));
}

// ============================================================================
// POPOVER & DROPDOWN
// ============================================================================

export function initializePopover() {
  const popoverTrigger = document.querySelector('[data-bs-toggle="popover"]');
  if (!popoverTrigger || !popoverTrigger.hasAttribute("data-bs-content-id")) return;

  const popoverContent = document.getElementById(popoverTrigger.getAttribute("data-bs-content-id")).innerHTML;
  state.persistentPopover = document.createElement("div");
  state.persistentPopover.className = "popover custom-popover";
  state.persistentPopover.style.position = "absolute";
  state.persistentPopover.style.display = "none";
  state.persistentPopover.innerHTML = `
    <div class="popover-arrow"></div>
    <div class="popover-body">${popoverContent}</div>
  `;

  document.body.appendChild(state.persistentPopover);
  updateQuestionButtonStates();

  popoverTrigger.addEventListener("click", (e) => {
    e.preventDefault();
    togglePopover(popoverTrigger);
  });

  document.addEventListener("click", (event) => {
    if (state.persistentPopover && state.persistentPopover.style.display === "block") {
      if (!popoverTrigger.contains(event.target) && !state.persistentPopover.contains(event.target)) {
        hidePopover(popoverTrigger);
      }
    }
  });
}

export function togglePopover(trigger) {
  if (!state.persistentPopover) return;
  if (state.persistentPopover.style.display === "none") {
    state.persistentPopover.style.visibility = "hidden";
    state.persistentPopover.style.display = "block";
    
    // Use offset coordinates which are relative to the zoomed body parent
    const popoverWidth = state.persistentPopover.offsetWidth;
    const popoverHeight = state.persistentPopover.offsetHeight;
    
    const triggerLeft = trigger.offsetLeft;
    const triggerTop = trigger.offsetTop;
    const triggerWidth = trigger.offsetWidth;
    
    const centerX = triggerLeft + triggerWidth / 2 - popoverWidth / 2;
    const topY = triggerTop - popoverHeight - 10;
    
    state.persistentPopover.style.left = centerX + "px";
    state.persistentPopover.style.top = topY + "px";
    state.persistentPopover.style.visibility = "visible";
    trigger.classList.add("popover-open");
  } else {
    hidePopover(trigger);
  }
}

export function hidePopover(trigger) {
  if (state.persistentPopover) {
    state.persistentPopover.style.display = "none";
    trigger.classList.remove("popover-open");
  }
}

export function initializeDropdown() {
  const dropdownButton = document.getElementById("dropdownMenuButton");
  const dropdownOverlay = document.getElementById("dropdownOverlay");
  const dropdownMenu = document.getElementById("dropdownMenu");
  if (!dropdownButton) return;

  if (dropdownMenu) {
    const closeButton = dropdownMenu.querySelector('.btn-secondary');
    if (closeButton) {
      closeButton.addEventListener("click", (e) => {
        e.preventDefault();
        const dropdown = bootstrap.Dropdown.getInstance(dropdownButton);
        if (dropdown) dropdown.hide();
      });
    }
  }

  dropdownButton.addEventListener("shown.bs.dropdown", () => {
    if (dropdownOverlay) dropdownOverlay.style.display = "block";
  });
  dropdownButton.addEventListener("hidden.bs.dropdown", () => {
    if (dropdownOverlay) dropdownOverlay.style.display = "none";
  });

  const dropdown = new bootstrap.Dropdown(dropdownButton);
  dropdown.show();
  if (dropdownOverlay) dropdownOverlay.style.display = "block";
}

export function initializeMoreDropdown() {
  const moreBtn = document.getElementById("moreBtn");
  const moreMenu = document.getElementById("moreMenu");
  const takeBreakBtn = document.getElementById("takeBreakBtn");
  const exitExamBtn = document.getElementById("exitExamBtn");

  if (!moreBtn || !moreMenu) return;

  moreBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    const isHidden = moreMenu.classList.toggle("hidden");
    if (isHidden) {
      moreBtn.classList.remove("highlight-mode-active");
    } else {
      moreBtn.classList.add("highlight-mode-active");
    }
  });

  document.addEventListener("click", (event) => {
    if (!moreBtn.contains(event.target) && !moreMenu.contains(event.target)) {
      moreMenu.classList.add("hidden");
      moreBtn.classList.remove("highlight-mode-active");
    }
  });

  if (takeBreakBtn) {
    takeBreakBtn.addEventListener("click", () => {
      moreMenu.classList.add("hidden");
      moreBtn.classList.remove("highlight-mode-active");
      showCustomAlert("Taking a break... (Functionality to be implemented)", "info", "Take a Break");
    });
  }

  if (exitExamBtn) {
    exitExamBtn.addEventListener("click", async () => {
      moreMenu.classList.add("hidden");
      moreBtn.classList.remove("highlight-mode-active");
      const confirmed = await showCustomConfirm("Are you sure you want to exit the exam? Your progress will be saved.", "warning", "Exit Exam");
      if (confirmed) {
        window.location.href = "/home";
      }
    });
  }
}

// ============================================================================
// SECURE LOADING SCREEN
// ============================================================================

export function showLoadingScreen(message = "Loading...") {
  const overlay = document.getElementById('loadingScreen');
  const textEl = document.getElementById('loadingStatusText');
  if (overlay) {
    if (textEl) textEl.textContent = message;
    overlay.classList.remove('hidden');
  }
}

export function hideLoadingScreen() {
  const overlay = document.getElementById('loadingScreen');
  if (overlay) {
    overlay.classList.add('hidden');
  }
}

// ============================================================================
// PREMIUM CUSTOM POPUP ALERTS
// ============================================================================

function getOrCreateAlertModal() {
  let modal = document.getElementById('customAlertModal');
  if (modal) return modal;

  // Dynamically create the modal element
  modal = document.createElement('div');
  modal.id = 'customAlertModal';
  modal.className = 'custom-alert-modal hidden';
  modal.innerHTML = `
    <div class="custom-alert-backdrop"></div>
    <div class="custom-alert-box">
      <div class="custom-alert-icon" id="customAlertIcon"></div>
      <div class="custom-alert-content">
        <h5 class="custom-alert-title" id="customAlertTitle">Notification</h5>
        <p id="customAlertMessage" class="custom-alert-message"></p>
      </div>
      <div class="custom-alert-actions">
        <button id="customAlertCancelBtn" class="custom-alert-btn btn-secondary hidden">Cancel</button>
        <button id="customAlertConfirmBtn" class="custom-alert-btn btn-primary">OK</button>
      </div>
    </div>
  `;

  // Inject styles if they are not loaded
  if (!document.querySelector('style[data-custom-alerts]')) {
    const style = document.createElement('style');
    style.setAttribute('data-custom-alerts', 'true');
    style.textContent = `
      .custom-alert-modal {
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 10000;
        display: flex; align-items: center; justify-content: center; opacity: 1; transition: opacity 0.25s ease;
      }
      .custom-alert-modal.hidden { display: none !important; opacity: 0; }
      .custom-alert-backdrop {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
      }
      .custom-alert-box {
        position: relative; background: #ffffff; border-radius: 16px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        width: 90%; max-width: 420px; padding: 24px; border: 1px solid rgba(226, 232, 240, 0.8);
        display: flex; flex-direction: column; align-items: center; text-align: center;
        transform: scale(1); transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); z-index: 1;
      }
      .custom-alert-modal.hidden .custom-alert-box { transform: scale(0.9); }
      .custom-alert-icon {
        display: flex; align-items: center; justify-content: center; width: 56px; height: 56px;
        border-radius: 50%; background-color: rgba(30, 41, 59, 0.05); color: #1e293b; margin-bottom: 16px;
      }
      .custom-alert-icon.warning { background-color: rgba(245, 158, 11, 0.1); color: #d97706; }
      .custom-alert-icon.error { background-color: rgba(239, 68, 68, 0.1); color: #dc2626; }
      .custom-alert-icon.success { background-color: rgba(16, 185, 129, 0.1); color: #059669; }
      .custom-alert-content { margin-bottom: 24px; width: 100%; }
      .custom-alert-title { font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 8px; font-family: sans-serif; }
      .custom-alert-message { font-size: 0.95rem; color: #475569; line-height: 1.5; margin: 0; font-family: sans-serif; }
      .custom-alert-actions { display: flex; gap: 12px; width: 100%; justify-content: center; }
      .custom-alert-btn { flex: 1; max-width: 160px; padding: 10px 16px; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; border: none; outline: none; }
      .custom-alert-btn.btn-primary { background-color: #1e293b; color: #ffffff; }
      .custom-alert-btn.btn-primary:hover { background-color: #0f172a; transform: translateY(-1px); }
      .custom-alert-btn.btn-secondary { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
      .custom-alert-btn.btn-secondary:hover { background-color: #e2e8f0; color: #334155; transform: translateY(-1px); }
    `;
    document.head.appendChild(style);
  }

  document.body.appendChild(modal);
  return modal;
}

export function showCustomAlert(message, type = 'info', title = 'Notification') {
  return new Promise((resolve) => {
    const modal = getOrCreateAlertModal();
    const titleEl = modal.querySelector('#customAlertTitle');
    const msgEl = modal.querySelector('#customAlertMessage');
    const iconEl = modal.querySelector('#customAlertIcon');
    const confirmBtn = modal.querySelector('#customAlertConfirmBtn');
    const cancelBtn = modal.querySelector('#customAlertCancelBtn');

    // Set texts
    titleEl.textContent = title;
    msgEl.textContent = message;

    // Reset button states
    cancelBtn.classList.add('hidden');
    confirmBtn.className = 'custom-alert-btn btn-primary';
    confirmBtn.textContent = 'OK';

    // Set icons and colors based on type
    iconEl.className = 'custom-alert-icon ' + type;
    if (type === 'warning') {
      iconEl.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
          <line x1="12" y1="9" x2="12" y2="13"></line>
          <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
      `;
    } else if (type === 'error') {
      iconEl.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="15" y1="9" x2="9" y2="15"></line>
          <line x1="9" y1="9" x2="15" y2="15"></line>
        </svg>
      `;
    } else if (type === 'success') {
      iconEl.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
          <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
      `;
    } else {
      iconEl.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="16" x2="12" y2="12"></line>
          <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
      `;
    }

    modal.classList.remove('hidden');

    const handleConfirm = () => {
      cleanup();
      resolve(true);
    };

    const cleanup = () => {
      modal.classList.add('hidden');
      confirmBtn.removeEventListener('click', handleConfirm);
    };

    confirmBtn.addEventListener('click', handleConfirm);
  });
}

export function showCustomConfirm(message, type = 'warning', title = 'Confirm Action') {
  return new Promise((resolve) => {
    const modal = getOrCreateAlertModal();
    const titleEl = modal.querySelector('#customAlertTitle');
    const msgEl = modal.querySelector('#customAlertMessage');
    const iconEl = modal.querySelector('#customAlertIcon');
    const confirmBtn = modal.querySelector('#customAlertConfirmBtn');
    const cancelBtn = modal.querySelector('#customAlertCancelBtn');

    // Set texts
    titleEl.textContent = title;
    msgEl.textContent = message;

    // Set button visibility & labels
    cancelBtn.classList.remove('hidden');
    cancelBtn.textContent = 'Cancel';
    confirmBtn.className = 'custom-alert-btn btn-primary';
    confirmBtn.textContent = 'Confirm';

    // Set icons and colors based on type
    iconEl.className = 'custom-alert-icon ' + type;
    if (type === 'warning') {
      iconEl.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
          <line x1="12" y1="9" x2="12" y2="13"></line>
          <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
      `;
    } else {
      iconEl.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="16" x2="12" y2="12"></line>
          <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
      `;
    }

    modal.classList.remove('hidden');

    const handleConfirm = () => {
      cleanup();
      resolve(true);
    };

    const handleCancel = () => {
      cleanup();
      resolve(false);
    };

    const cleanup = () => {
      modal.classList.add('hidden');
      confirmBtn.removeEventListener('click', handleConfirm);
      cancelBtn.removeEventListener('click', handleCancel);
    };

    confirmBtn.addEventListener('click', handleConfirm);
    cancelBtn.addEventListener('click', handleCancel);
  });
}

// Expose globally for general usage
window.showCustomAlert = showCustomAlert;
window.showCustomConfirm = showCustomConfirm;

