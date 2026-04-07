// Import Bootstrap
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// ============================================================================
// CONFIGURATION & STATE
// ============================================================================

// DOM Elements - Navigation (initialized in DOMContentLoaded)
let backButton;
let nextButton;

// DOM Elements - Questions (initialized in DOMContentLoaded)
let questionElements = [];
let questionNumberSpan;
let totalQuestionsSpan;

// State Variables
let currentQuestionIndex = 0;
let totalQuestions = 0;
let highlightMode = false;
let persistentPopover = null;

// ============================================================================
// QUESTION BUTTON GENERATION
// ============================================================================

/**
 * Creates a question button element
 */
function createQuestionButton(questionNumber) {
  const button = document.createElement("button");
  button.className = "btn question-btn";
  button.textContent = questionNumber;
  button.setAttribute("data-question", questionNumber);
  return button;
}

/**
 * Generates question buttons in the popover template
 */
function generateQuestionButtons() {
  const popoverTemplate = document.getElementById("popover-content");
  if (!popoverTemplate) {
    console.error("Popover template not found!");
    return;
  }

  const questionButtonsContainer = popoverTemplate.querySelector(".d-flex.flex-wrap.gap-3");
  if (!questionButtonsContainer) {
    console.error("Question button container not found in popover template!");
    return;
  }

  // Only generate buttons if they don't already exist
  if (questionButtonsContainer.children.length > 0) {
    console.log("Question buttons already exist in template, skipping generation");
    return;
  }

  console.log(`Generating ${totalQuestions} question buttons in popover template...`);

  for (let i = 1; i <= totalQuestions; i++) {
    questionButtonsContainer.appendChild(createQuestionButton(i));
  }

  console.log(`Successfully generated ${questionButtonsContainer.children.length} question buttons`);

  // Also generate buttons in the navigation box
  generateNavigationBoxButtons();
}

/**
 * Generates question buttons in the navigation box
 */
function generateNavigationBoxButtons() {
  const navBoxContainer = document.querySelector(".question-navigation-box .d-flex.flex-wrap");
  if (!navBoxContainer) {
    console.log("Navigation box container not found!");
    return;
  }

  navBoxContainer.innerHTML = "";
  console.log(`Generating ${totalQuestions} question buttons in navigation box...`);

  for (let i = 1; i <= totalQuestions; i++) {
    navBoxContainer.appendChild(createQuestionButton(i));
  }

  console.log(`Successfully generated ${navBoxContainer.children.length} question buttons in navigation box`);
}

// ============================================================================
// QUESTION BUTTON STATE MANAGEMENT
// ============================================================================

/**
 * Checks if a question is answered
 */
function isQuestionAnswered(questionElement) {
  if (!questionElement) return false;
  
  const selectedAnswer = questionElement.querySelector('input[type="radio"]:checked');
  const textInput = questionElement.querySelector('input.answer-input');
  
  return selectedAnswer || (textInput && textInput.value.trim() !== '');
}

/**
 * Checks if a question is marked for review
 */
function isQuestionMarkedForReview(questionElement) {
  if (!questionElement) return false;
  return !!questionElement.querySelector(".bookmark.marked");
}

/**
 * Updates the state classes for a single button
 */
function updateButtonState(button, includeCurrent = true) {
  const buttonIndex = parseInt(button.getAttribute("data-question")) - 1;
  button.classList.remove("current", "answered", "marked-for-review");

  // Mark current question
  if (includeCurrent && buttonIndex === currentQuestionIndex) {
    button.classList.add("current");
  }

  // Check if question is answered
  const questionElement = questionElements[buttonIndex];
  if (isQuestionAnswered(questionElement)) {
    button.classList.add("answered");
  }

  // Check if question is marked for review
  if (isQuestionMarkedForReview(questionElement)) {
    button.classList.add("marked-for-review");
  }
}

/**
 * Updates button states in the popover template
 */
function updateQuestionButtonStatesInTemplate() {
  const popoverTemplate = document.getElementById("popover-content");
  if (!popoverTemplate) return;

  const templateButtons = popoverTemplate.querySelectorAll(".question-btn");
  templateButtons.forEach(button => updateButtonState(button));

  console.log(`Updated ${templateButtons.length} template question buttons`);
}

/**
 * Updates all question button states
 */
function updateQuestionButtonStates() {
  updateQuestionButtonStatesInTemplate();

  // Update popover buttons
  const popoverButtons = document.querySelectorAll(".popover .question-btn");
  popoverButtons.forEach(button => updateButtonState(button));

  console.log(`Updated ${popoverButtons.length} question buttons in popovers`);

  // Update navigation box buttons
  updateNavigationBoxButtons();
}

/**
 * Updates question buttons in the navigation box (review section)
 */
function updateNavigationBoxButtons() {
  const navBoxButtons = document.querySelectorAll(".question-navigation-box .question-btn");
  navBoxButtons.forEach(button => updateButtonState(button, false)); // Don't mark current in review

  console.log(`Updated ${navBoxButtons.length} question buttons in navigation box`);
}

// ============================================================================
// NAVIGATION & QUESTION DISPLAY
// ============================================================================

/**
 * Checks if review section is currently visible
 */
function isReviewSectionVisible() {
  const reviewSection = document.getElementById("review-section");
  return reviewSection && !reviewSection.classList.contains("d-none");
}

/**
 * Updates navigation button visibility
 */
function updateNavigationButtons() {
  if (backButton) {
    const isReviewVisible = isReviewSectionVisible();
    backButton.style.display = currentQuestionIndex === 0 && !isReviewVisible ? "none" : "block";
  }
}

/**
 * Displays a specific question by index
 */
function showQuestion(index) {
  const reviewSection = document.getElementById("review-section");
  const wasReviewVisible = isReviewSectionVisible();

  // Hide review section
  if (reviewSection) {
    reviewSection.classList.add("d-none");
  }

  // If coming back from review section, go to last question
  if (wasReviewVisible) {
    currentQuestionIndex = totalQuestions - 1;
    index = currentQuestionIndex;
  } else {
    currentQuestionIndex = index;
  }

  // Show/hide questions
  questionElements.forEach((el, i) => {
    el.classList.toggle("d-none", i !== index);
  });

  // Update question number display
  if (questionNumberSpan) {
    questionNumberSpan.textContent = index + 1;
  }

  // Show popover button when returning to questions
  const popoverBtn = document.querySelector(".popover-btn");
  if (popoverBtn) {
    popoverBtn.classList.remove("d-none");
  }

  updateNavigationButtons();
  updateQuestionButtonStatesInTemplate();
  updateQuestionButtonStates();
}

/**
 * Displays the review section
 */
function showReviewSection() {
  // Hide all questions
  questionElements.forEach(el => el.classList.add("d-none"));

  // Set current index beyond all questions
  currentQuestionIndex = totalQuestions;

  // Show review section
  const reviewSection = document.getElementById("review-section");
  if (reviewSection) {
    reviewSection.classList.remove("d-none");
  }

  // Hide popover button in footer
  const popoverBtn = document.querySelector(".popover-btn");
  if (popoverBtn) {
    popoverBtn.classList.add("d-none");
  }

  updateNavigationButtons();
  updateQuestionButtonStatesInTemplate();
  updateQuestionButtonStates();
}

/**
 * Navigate to next question or review section
 */
function nextQuestion() {
  // If on review section, confirm before proceeding to next section
  if (isReviewSectionVisible()) {
    const confirmNext = confirm("You are about to proceed to the next section.\n\nSection 2: Math\n\nAre you ready to continue?");
    if (confirmNext) {
      window.location.href = "test_page_math.php";
    }
    return;
  }
  
  // Navigate to next question or show review
  if (currentQuestionIndex < totalQuestions - 1) {
    currentQuestionIndex++;
    showQuestion(currentQuestionIndex);
  } else {
    showReviewSection();
  }
}

/**
 * Navigate to previous question
 */
function prevQuestion() {
  if (isReviewSectionVisible()) {
    currentQuestionIndex = totalQuestions - 1;
    showQuestion(currentQuestionIndex);
  } else if (currentQuestionIndex > 0) {
    currentQuestionIndex--;
    showQuestion(currentQuestionIndex);
  }
}

// ============================================================================
// POPOVER MANAGEMENT
// ============================================================================

/**
 * Creates and initializes the persistent popover
 */
function initializePopover() {
  const popoverTrigger = document.querySelector('[data-bs-toggle="popover"]');
  if (!popoverTrigger || !popoverTrigger.hasAttribute("data-bs-content-id")) return;

  // Create persistent popover element
  const popoverContent = document.getElementById(popoverTrigger.getAttribute("data-bs-content-id")).innerHTML;

  persistentPopover = document.createElement("div");
  persistentPopover.className = "popover custom-popover";
  persistentPopover.style.position = "absolute";
  persistentPopover.style.display = "none";
  persistentPopover.innerHTML = `
    <div class="popover-arrow"></div>
    <div class="popover-body">${popoverContent}</div>
  `;

  document.body.appendChild(persistentPopover);
  updateQuestionButtonStates();

  // Handle popover toggle
  popoverTrigger.addEventListener("click", function (e) {
    e.preventDefault();
    togglePopover(popoverTrigger);
  });

  // Close popover when clicking outside
  document.addEventListener("click", function (event) {
    if (persistentPopover && persistentPopover.style.display === "block") {
      if (!popoverTrigger.contains(event.target) && !persistentPopover.contains(event.target)) {
        hidePopover(popoverTrigger);
      }
    }
  });
}

/**
 * Toggles the popover visibility
 */
function togglePopover(trigger) {
  if (!persistentPopover) return;

  if (persistentPopover.style.display === "none") {
    // Show popover
    persistentPopover.style.visibility = "hidden";
    persistentPopover.style.display = "block";

    // Calculate position
    const rect = trigger.getBoundingClientRect();
    const popoverRect = persistentPopover.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2 - popoverRect.width / 2;
    const topY = rect.top - popoverRect.height - 10;

    persistentPopover.style.left = centerX + "px";
    persistentPopover.style.top = topY + "px";
    persistentPopover.style.visibility = "visible";
    trigger.classList.add("popover-open");
  } else {
    hidePopover(trigger);
  }
}

/**
 * Hides the popover
 */
function hidePopover(trigger) {
  if (persistentPopover) {
    persistentPopover.style.display = "none";
    trigger.classList.remove("popover-open");
  }
}

// ============================================================================
// DROPDOWN MANAGEMENT
// ============================================================================

/**
 * Initializes the dropdown menu and overlay
 */
function initializeDropdown() {
  const dropdownButton = document.getElementById("dropdownMenuButton");
  const dropdownOverlay = document.getElementById("dropdownOverlay");
  const dropdownMenu = document.getElementById("dropdownMenu");

  if (!dropdownButton) return;

  // Close button handler
  if (dropdownMenu) {
    const closeButton = dropdownMenu.querySelector('.btn-secondary');
    if (closeButton) {
      closeButton.addEventListener("click", function (e) {
        e.preventDefault();
        const dropdown = bootstrap.Dropdown.getInstance(dropdownButton);
        if (dropdown) dropdown.hide();
      });
    }
  }

  // Listen for dropdown shown event
  dropdownButton.addEventListener("shown.bs.dropdown", function () {
    if (dropdownOverlay) {
      dropdownOverlay.style.display = "block";
    }
  });

  // Listen for dropdown hidden event
  dropdownButton.addEventListener("hidden.bs.dropdown", function () {
    if (dropdownOverlay) {
      dropdownOverlay.style.display = "none";
    }
  });

  // Show dropdown initially
  const dropdown = new bootstrap.Dropdown(dropdownButton);
  dropdown.show();

  if (dropdownOverlay) {
    dropdownOverlay.style.display = "block";
  }
}

// ============================================================================
// BOOKMARK & ANSWER TRACKING
// ============================================================================

/**
 * Initializes bookmark and answer tracking for all questions
 */
function initializeQuestionTracking() {
  const questions = document.querySelectorAll('[id^="question"]');
  
  if (questions.length === 0) {
    console.warn('No question elements found in DOM');
    return;
  }

  console.log(`Initializing tracking for ${questions.length} question(s)`);

  questions.forEach((question) => {
    // Bookmark toggle handler
    const bookmark = question.querySelector(".bookmark");
    if (bookmark) {
      bookmark.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.toggle("marked");
        console.log('Bookmark toggled:', this.classList.contains('marked') ? 'marked' : 'unmarked');
        updateQuestionButtonStatesInTemplate();
        updateQuestionButtonStates();
      });
      console.log('Bookmark handler attached');
    } else {
      console.warn('No bookmark element found in question');
    }

    // Answer selection handler
    const radioButtons = question.querySelectorAll('input[type="radio"]');
    radioButtons.forEach((radio) => {
      radio.addEventListener("change", function () {
        console.log('Answer changed for radio:', this.name);
        updateQuestionButtonStatesInTemplate();
        updateQuestionButtonStates();
      });
    });
  });
}

// ============================================================================
// TIMER FUNCTIONALITY
// ============================================================================

/**
 * Toggles the timer display
 */
function toggleTimer() {
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

// Expose to global scope for inline onclick handlers
window.toggleTimer = toggleTimer;
window.nextQuestion = nextQuestion;
window.prevQuestion = prevQuestion;
window.showQuestion = showQuestion;
window.showReviewSection = showReviewSection;

// ============================================================================
// HIGHLIGHT FUNCTIONALITY
// ============================================================================

/**
 * Toggles highlight mode on/off
 */
function toggleHighlightMode() {
  highlightMode = !highlightMode;

  const highlightBtn = document.getElementById('highlightNotesBtn');
  const contentAreas = document.querySelectorAll('.resizable-panel');

  console.log('Highlight mode toggled:', highlightMode ? 'ON' : 'OFF');
  console.log('Content areas found:', contentAreas.length);

  if (highlightMode) {
    if (highlightBtn) {
      highlightBtn.classList.add('highlight-mode-active');
      console.log('Highlight button activated');
    } else {
      console.warn('Highlight button not found');
    }
    contentAreas.forEach(area => area.classList.add('highlight-mode'));
    console.log('Highlight mode added to', contentAreas.length, 'panels');
  } else {
    if (highlightBtn) {
      highlightBtn.classList.remove('highlight-mode-active');
      console.log('Highlight button deactivated');
    }
    contentAreas.forEach(area => area.classList.remove('highlight-mode'));
    console.log('Highlight mode removed from', contentAreas.length, 'panels');
  }
}

/**
 * Applies highlight to selected text
 */
function highlightSelection() {
  if (!highlightMode) return;

  const selection = window.getSelection();
  if (!selection.rangeCount || selection.isCollapsed) return;

  const range = selection.getRangeAt(0);

  // Validate selection is within content area
  const contentAreas = document.querySelectorAll('.resizable-panel');
  const isValidArea = Array.from(contentAreas).some(area =>
    area.contains(range.commonAncestorContainer)
  );

  if (!isValidArea) {
    console.log('Selection not in valid content area');
    return;
  }

  // Don't highlight if already highlighted
  const parentElement = range.commonAncestorContainer.parentElement;
  if (parentElement && parentElement.classList.contains('highlighted-text')) return;

  try {
    const highlightSpan = document.createElement('span');
    highlightSpan.className = 'highlighted-text';
    highlightSpan.appendChild(range.extractContents());
    range.insertNode(highlightSpan);
    selection.removeAllRanges();
    console.log('Text highlighted successfully');
  } catch (e) {
    console.error('Error highlighting text:', e);
  }
}

/**
 * Initializes highlight functionality
 */
function initializeHighlightFeature() {
  const highlightBtn = document.getElementById('highlightNotesBtn');

  if (highlightBtn) {
    highlightBtn.addEventListener('click', toggleHighlightMode);
    console.log('Highlight button click handler attached');
  } else {
    console.warn('Highlight button not found!');
  }

  // Apply highlights on mouseup
  document.addEventListener('mouseup', function() {
    if (highlightMode) {
      setTimeout(highlightSelection, 10);
    }
  });

  // Remove highlight on double-click
  document.addEventListener('dblclick', function(e) {
    if (e.target.classList.contains('highlighted-text')) {
      const parent = e.target.parentNode;
      while (e.target.firstChild) {
        parent.insertBefore(e.target.firstChild, e.target);
      }
      parent.removeChild(e.target);
      console.log('Highlight removed on double-click');
    }
  });
  console.log("Highlight feature initialized");
}

// ============================================================================
// SECURITY & INTERACTION RESTRICTIONS
// ============================================================================

/**
 * Prevents right-click context menu, copy/paste hotkeys, and browser back navigation
 */
function preventNormalCursorBehavior() {
  // Disable right-click context menu
  document.addEventListener('contextmenu', function(e) {
    e.preventDefault();
    return false;
  });
  
  // Disable copy, paste, cut hotkeys
  document.addEventListener('keydown', function(e) {
    // Allow in input fields and textareas
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
      return true;
    }
    
    // Prevent Ctrl+C (Copy), Ctrl+V (Paste), Ctrl+X (Cut), Ctrl+A (Select All)
    if (e.ctrlKey || e.metaKey) {
      if (e.key === 'c' || e.key === 'C' || 
          e.key === 'v' || e.key === 'V' || 
          e.key === 'x' || e.key === 'X' || 
          e.key === 'a' || e.key === 'A') {
        e.preventDefault();
        return false;
      }
    }
  });
  
  // Prevent browser back navigation
  history.pushState(null, null, location.href);
  window.addEventListener('popstate', function() {
    history.pushState(null, null, location.href);
  });
}

// ============================================================================
// EVENT DELEGATION & INITIALIZATION
// ============================================================================

/**
 * Handles question button clicks via event delegation
 */
function handleQuestionButtonClick(e) {
  const button = e.target.closest(".question-btn");
  if (!button) return;

  e.preventDefault();
  const questionNumber = parseInt(button.getAttribute("data-question"));
  if (questionNumber) {
    console.log(`Jumping to question ${questionNumber}`);
    currentQuestionIndex = questionNumber - 1;
    showQuestion(currentQuestionIndex);

    const popoverTrigger = document.querySelector('[data-bs-toggle="popover"]');
    if (popoverTrigger) hidePopover(popoverTrigger);
  }
}

/**
 * Handles review button clicks
 */
function handleReviewButtonClick(e) {
  const button = e.target.closest(".go-review-btn button, .btn-outline-primary");
  if (!button) return;

  e.preventDefault();
  showReviewSection();

  const popoverTrigger = document.querySelector('[data-bs-toggle="popover"]');
  if (popoverTrigger) hidePopover(popoverTrigger);
}

// ============================================================================
// RESIZABLE PANELS
// ============================================================================

/**
 * Initialize resizable panels functionality
 */
function initializeResizablePanels() {
  const resizers = document.querySelectorAll('.resizer');

  if (resizers.length === 0) {
    console.warn('No resizer elements found in DOM');
    return;
  }

  console.log(`Initializing ${resizers.length} resizer(s)`);

  resizers.forEach((resizer, index) => {
    let isResizing = false;
    let startX = 0;
    let startLeftWidth = 0;
    let startRightWidth = 0;
    let container = null;
    let leftPanel = null;
    let rightPanel = null;

    console.log(`Setting up resizer ${index + 1}`);

    resizer.addEventListener('mousedown', function(e) {
      console.log('Resizer mousedown triggered');
      isResizing = true;
      startX = e.clientX;

      container = resizer.parentElement;
      leftPanel = resizer.previousElementSibling;
      rightPanel = resizer.nextElementSibling;

      if (leftPanel && rightPanel) {
        startLeftWidth = leftPanel.offsetWidth;
        startRightWidth = rightPanel.offsetWidth;
        console.log(`Panel widths - Left: ${startLeftWidth}px, Right: ${startRightWidth}px`);
      } else {
        console.error('Could not find left or right panel for resizer');
        return;
      }

      // Add class to body to prevent text selection during drag
      document.body.style.cursor = 'col-resize';
      document.body.style.userSelect = 'none';

      e.preventDefault();
      e.stopPropagation();
    });

    document.addEventListener('mousemove', function(e) {
      if (!isResizing || !leftPanel || !rightPanel || !container) return;

      const deltaX = e.clientX - startX;
      const containerWidth = container.offsetWidth;

      // Calculate new widths
      const newLeftWidth = startLeftWidth + deltaX;
      const newRightWidth = startRightWidth - deltaX;

      // Set minimum widths (20% of container)
      const minWidth = containerWidth * 0.2;

      if (newLeftWidth >= minWidth && newRightWidth >= minWidth) {
        const leftPercentage = (newLeftWidth / containerWidth) * 100;
        const rightPercentage = (newRightWidth / containerWidth) * 100;

        leftPanel.style.flex = `0 0 ${leftPercentage}%`;
        rightPanel.style.flex = `0 0 ${rightPercentage}%`;
      }
    });

    document.addEventListener('mouseup', function() {
      if (isResizing) {
        console.log('Resizer mouseup - resize complete');
        isResizing = false;
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
      }
    });
  });
  console.log("Resizable panels initialized");
}

/**
 * Initializes DOM elements and state
 */
function initializeDOMElements() {
  // DOM Elements - Navigation
  backButton = document.getElementById("backButton");
  nextButton = document.getElementById("nextButton");

  // DOM Elements - Questions
  questionElements = Array.from(document.querySelectorAll('[id^="question"]'));
  questionNumberSpan = document.querySelector(".popover-btn span:first-child");
  totalQuestionsSpan = document.querySelector(".popover-btn #total");

  // Update state
  totalQuestions = questionElements.length;

  // Initialize display
  if (totalQuestionsSpan) {
    totalQuestionsSpan.textContent = totalQuestions;
  }
  if (questionNumberSpan) {
    questionNumberSpan.textContent = currentQuestionIndex + 1;
  }
  
  console.log(`Initialized with ${totalQuestions} questions`);
}

/**
 * Main initialization function
 */
document.addEventListener("DOMContentLoaded", function () {
  console.log('=== DOM CONTENT LOADED ===');
  console.log('Document ready state:', document.readyState);

  // Debug: Check if key elements exist
  console.log('Resizers found:', document.querySelectorAll('.resizer').length);
  console.log('Bookmark elements found:', document.querySelectorAll('.bookmark').length);
  console.log('Highlight button found:', document.getElementById('highlightNotesBtn') ? 'YES' : 'NO');
  console.log('Resizable panels found:', document.querySelectorAll('.resizable-panel').length);
  console.log('Questions found:', document.querySelectorAll('[id^="question"]').length);

  // Initialize DOM elements first
  initializeDOMElements();

  // Initialize all components
  generateQuestionButtons();
  initializePopover();
  initializeDropdown();
  initializeQuestionTracking();
  initializeHighlightFeature();
  initializeResizablePanels();

  // Enable security restrictions
  preventNormalCursorBehavior();

  // Show first question
  showQuestion(currentQuestionIndex);

  // Setup global event delegation
  document.addEventListener("click", handleQuestionButtonClick);
  document.addEventListener("click", handleReviewButtonClick);
});
