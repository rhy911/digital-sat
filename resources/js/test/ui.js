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
    const rect = trigger.getBoundingClientRect();
    const popoverRect = state.persistentPopover.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2 - popoverRect.width / 2;
    const topY = rect.top - popoverRect.height - 10;
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
    moreMenu.classList.toggle("hidden");
  });

  document.addEventListener("click", (event) => {
    if (!moreBtn.contains(event.target) && !moreMenu.contains(event.target)) {
      moreMenu.classList.add("hidden");
    }
  });

  if (takeBreakBtn) {
    takeBreakBtn.addEventListener("click", () => {
      moreMenu.classList.add("hidden");
      alert("Taking a break... (Functionality to be implemented)");
    });
  }

  if (exitExamBtn) {
    exitExamBtn.addEventListener("click", () => {
      moreMenu.classList.add("hidden");
      if (confirm("Are you sure you want to exit the exam? Your progress will be saved.")) {
        window.location.href = "/dashboard";
      }
    });
  }
}
