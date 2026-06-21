import { state } from './state.js';
import { updateQuestionButtonStates } from './ui.js';

// ============================================================================
// HIGHLIGHT FUNCTIONALITY
// ============================================================================

export function toggleHighlightMode() {
  state.highlightMode = !state.highlightMode;
  const highlightBtn = document.getElementById('highlightNotesBtn');
  const contentAreas = document.querySelectorAll('.resizable-panel');

  if (state.highlightMode) {
    if (highlightBtn) highlightBtn.classList.add('highlight-mode-active');
    contentAreas.forEach(area => area.classList.add('highlight-mode'));
  } else {
    if (highlightBtn) highlightBtn.classList.remove('highlight-mode-active');
    contentAreas.forEach(area => area.classList.remove('highlight-mode'));
  }
}

export function highlightSelection() {
  const highlightBtn = document.getElementById('highlightNotesBtn');
  if (!highlightBtn || !state.highlightMode) return;
  const selection = window.getSelection();
  if (!selection.rangeCount || selection.isCollapsed) return;
  const range = selection.getRangeAt(0);

  const contentAreas = document.querySelectorAll('.resizable-panel');
  const isValidArea = Array.from(contentAreas).some(area => area.contains(range.commonAncestorContainer));
  if (!isValidArea) return;

  const parentElement = range.commonAncestorContainer.parentElement;
  if (parentElement && parentElement.classList.contains('highlighted-text')) return;

  try {
    const highlightSpan = document.createElement('span');
    highlightSpan.className = 'highlighted-text';
    highlightSpan.appendChild(range.extractContents());
    range.insertNode(highlightSpan);
    selection.removeAllRanges();
  } catch (e) {
    console.error('Error highlighting text:', e);
  }
}

let highlightInitialized = false;

export function initializeHighlightFeature() {
  const highlightBtn = document.getElementById('highlightNotesBtn');
  if (highlightBtn) {
    highlightBtn.removeEventListener('click', toggleHighlightMode);
    highlightBtn.addEventListener('click', toggleHighlightMode);
  }

  if (highlightInitialized) return;
  highlightInitialized = true;

  document.addEventListener('mouseup', () => {
    if (state.highlightMode) setTimeout(highlightSelection, 10);
  });

  document.addEventListener('dblclick', (e) => {
    if (e.target.classList.contains('highlighted-text')) {
      const parent = e.target.parentNode;
      while (e.target.firstChild) parent.insertBefore(e.target.firstChild, e.target);
      parent.removeChild(e.target);
    }
  });
}

// ============================================================================
// SPR INPUT VALIDATION
// ============================================================================

export function initializeSprInputValidation() {
  const sprInputs = document.querySelectorAll('.spr-input');
  
  sprInputs.forEach(input => {
    // Keydown listener to block invalid keys
    input.addEventListener('keydown', (e) => {
      // Allow control keys (backspace, delete, arrows, enter, etc.)
      const allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'];
      if (allowedKeys.includes(e.key)) return;

      // SAT SPR allowed characters: 0-9, decimal point, fraction slash, negative sign
      const validCharsRegex = /[0-9\.\/\-]/;
      if (!validCharsRegex.test(e.key)) {
        e.preventDefault();
        return false;
      }
    });

    // Input listener to clean up any invalid characters (from paste, etc.)
    input.addEventListener('input', (e) => {
      const start = input.selectionStart;
      const end = input.selectionEnd;
      let value = input.value;
      
      // Clean up invalid characters
      const cleanedValue = value.replace(/[^0-9\.\/\-]/g, '');
      
      // Dynamic MaxLength: 6 if starts with '-', else 5
      const dynamicMax = cleanedValue.startsWith('-') ? 6 : 5;
      input.maxLength = dynamicMax;
      
      // Trim if exceeds dynamic max (can happen when removing '-')
      const finalValue = cleanedValue.slice(0, dynamicMax);
      
      if (value !== finalValue) {
        input.value = finalValue;
        // Restore cursor position
        input.setSelectionRange(start, end);
      }
      
      // Update Preview
      const container = input.closest('.answer-input-container');
      if (container) {
        const previewSpan = container.querySelector('.preview-value');
        if (previewSpan) previewSpan.textContent = input.value;
      }
      
      updateQuestionButtonStates();
    });

    // Explicitly handle paste events
    input.addEventListener('paste', (e) => {
      const pasteData = (e.clipboardData || window.clipboardData).getData('text');
      if (/[^0-9\.\/\-]/.test(pasteData)) {
        e.preventDefault();
        const cleanedPaste = pasteData.replace(/[^0-9\.\/\-]/g, '').slice(0, input.maxLength);
        
        // Manual insertion of cleaned text
        const start = input.selectionStart;
        const end = input.selectionEnd;
        input.value = input.value.substring(0, start) + cleanedPaste + input.value.substring(end);
        
        // Set cursor position after inserted text
        input.setSelectionRange(start + cleanedPaste.length, start + cleanedPaste.length);
        updateQuestionButtonStates();
      }
    });
  });
}

// ============================================================================
// BOOKMARK & TRACKING
// ============================================================================

export function initializeQuestionTracking() {
  const questions = document.querySelectorAll('[id^="question"]');
  questions.forEach((question) => {
    const bookmark = question.querySelector(".bookmark");
    if (bookmark) {
      bookmark.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.toggle("marked");
        updateQuestionButtonStates();
      });
    }

    const crossOutToggleBtn = question.querySelector('.cross-out-toggle-btn');
    if (crossOutToggleBtn) {
      crossOutToggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.toggle('active');
        question.classList.toggle('show-strike');
      });
    }

    const radioButtons = question.querySelectorAll('input[type="radio"]');
    radioButtons.forEach((radio) => {
      radio.addEventListener("change", () => {
        const option = radio.closest('.answer-option');
        if (option && option.classList.contains('struck')) {
          option.classList.remove('struck');
          const row = option.closest('.answer-row');
          const strikeBtn = row ? row.querySelector('.strike-btn') : null;
          if (strikeBtn) strikeBtn.classList.remove('active');
        }
        updateQuestionButtonStates();
      });
    });

    const strikeBtns = question.querySelectorAll('.strike-btn');
    strikeBtns.forEach((btn) => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const row = this.closest('.answer-row');
        const option = row ? row.querySelector('.answer-option') : null;
        if (option) {
          const isStruck = option.classList.toggle('struck');
          this.classList.toggle('active');
          
          if (isStruck) {
            const radio = option.querySelector('input[type="radio"]');
            if (radio && radio.checked) {
              radio.checked = false;
              updateQuestionButtonStates();
            }
          }
        }
      });
    });
  });
}

// ============================================================================
// RESIZABLE PANELS
// ============================================================================

export function initializeResizablePanels() {
  const resizers = document.querySelectorAll('.resizer');
  resizers.forEach((resizer) => {
    let isResizing = false;
    let startX = 0;
    let startLeftWidth = 0;
    let startRightWidth = 0;
    let container = null;
    let leftPanel = null;
    let rightPanel = null;

    resizer.addEventListener('mousedown', (e) => {
      isResizing = true;
      startX = e.clientX;
      container = resizer.parentElement;
      leftPanel = resizer.previousElementSibling;
      rightPanel = resizer.nextElementSibling;

      if (leftPanel && rightPanel) {
        startLeftWidth = leftPanel.offsetWidth;
        startRightWidth = rightPanel.offsetWidth;
      }

      document.body.style.cursor = 'col-resize';
      document.body.style.userSelect = 'none';
      e.preventDefault();
      e.stopPropagation();
    });

    resizer.addEventListener('dblclick', () => {
      const left = resizer.previousElementSibling;
      const right = resizer.nextElementSibling;
      if (left && right) {
        left.style.flex = '0 0 50%';
        right.style.flex = '0 0 49%';
        if (state.currentQuestionIndex < state.totalQuestions) state.panelStates[state.currentQuestionIndex] = null;
      }
    });

    document.addEventListener('mousemove', (e) => {
      if (!isResizing || !leftPanel || !rightPanel || !container) return;
      const deltaX = e.clientX - startX;
      const containerWidth = container.offsetWidth;
      const newLeftWidth = startLeftWidth + deltaX;
      const newRightWidth = startRightWidth - deltaX;
      const minWidth = containerWidth * 0.2;

      if (newLeftWidth >= minWidth && newRightWidth >= minWidth) {
        const leftPercentage = (newLeftWidth / containerWidth) * 100;
        const rightPercentage = (newRightWidth / containerWidth) * 100;
        leftPanel.style.flex = `0 0 ${leftPercentage}%`;
        rightPanel.style.flex = `0 0 ${rightPercentage}%`;
        if (state.currentQuestionIndex < state.totalQuestions) {
          state.panelStates[state.currentQuestionIndex] = { left: leftPercentage, right: rightPercentage };
        }
      }
    });

    document.addEventListener('mouseup', () => {
      if (isResizing) {
        isResizing = false;
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
      }
    });
  });
}

// ============================================================================
// SECURITY
// ============================================================================

let cursorBehaviorPrevented = false;

export function preventNormalCursorBehavior() {
  if (cursorBehaviorPrevented) return;
  cursorBehaviorPrevented = true;

  document.addEventListener('contextmenu', (e) => e.preventDefault());
  document.addEventListener('keydown', (e) => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return true;
    if ((e.ctrlKey || e.metaKey) && ['c', 'v', 'x', 'a'].includes(e.key.toLowerCase())) {
      e.preventDefault();
      return false;
    }
  });
  history.pushState(null, null, location.href);
  window.addEventListener('popstate', () => history.pushState(null, null, location.href));
}

// ============================================================================
// DESMOS TABBED CALCULATOR
// ============================================================================

let graphingInstance = null;
let scientificInstance = null;

let desmosModalSetupDone = false;

export function initializeDesmosCalculator() {
  const calcBtn = document.getElementById('calculatorBtn');
  const modal = document.getElementById('calculatorModal');
  if (!calcBtn || !modal) return;

  const tabs = modal.querySelectorAll('.calc-tab');
  const closeBtn = modal.querySelector('.closeCalculatorBtn');
  const graphingDiv = document.getElementById('graphingCalc');
  const scientificDiv = document.getElementById('scientificCalc');

  // Toggle Modal
  calcBtn.onclick = () => {
    modal.classList.toggle('hidden');
    
    // Update button active state
    if (modal.classList.contains('hidden')) {
      calcBtn.classList.remove('highlight-mode-active');
      document.body.classList.remove('calculator-active');
    } else {
      calcBtn.classList.add('highlight-mode-active');
      document.body.classList.add('calculator-active');
      const activeTab = modal.querySelector('.calc-tab.active').getAttribute('data-tab');
      initCalculator(activeTab);
    }
  };

  // Close Modal
  if (closeBtn) {
    closeBtn.onclick = () => {
      modal.classList.add('hidden');
      const currentCalcBtn = document.getElementById('calculatorBtn');
      if (currentCalcBtn) currentCalcBtn.classList.remove('highlight-mode-active');
      document.body.classList.remove('calculator-active');
    };
  }

  if (desmosModalSetupDone) return;
  desmosModalSetupDone = true;

  // Tab Switching
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.getAttribute('data-tab');
      
      // Update UI
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      
      if (target === 'graphing') {
        graphingDiv.classList.remove('hidden');
        scientificDiv.classList.add('hidden');
        initCalculator('graphing');
      } else {
        graphingDiv.classList.add('hidden');
        scientificDiv.classList.remove('hidden');
        initCalculator('scientific');
      }
    });
  });

  // Dragging logic
  const header = modal.querySelector('.calculator-modal-header');
  if (header) {
    let isDragging = false;
    let offset = { x: 0, y: 0 };

    header.addEventListener('mousedown', (e) => {
      if (e.target.classList.contains('calc-tab')) return;
      isDragging = true;
      offset.x = e.clientX - modal.offsetLeft;
      offset.y = e.clientY - modal.offsetTop;
      header.style.cursor = 'grabbing';
    });

    document.addEventListener('mousemove', (e) => {
      if (!isDragging) return;
      modal.style.left = (e.clientX - offset.x) + 'px';
      modal.style.top = (e.clientY - offset.y) + 'px';
      modal.style.transform = 'none';
    });

    document.addEventListener('mouseup', () => {
      isDragging = false;
      header.style.cursor = 'move';
    });
  }

  // Resizing logic
  const resizer = modal.querySelector('.calculator-resize-handle');
  if (resizer) {
    let isResizing = false;

    resizer.addEventListener('mousedown', (e) => {
      isResizing = true;
      e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
      if (!isResizing) return;
      const width = e.clientX - modal.offsetLeft;
      const height = e.clientY - modal.offsetTop;
      
      if (width > 400) modal.style.width = width + 'px';
      if (height > 300) modal.style.height = height + 'px';
      
      if (graphingInstance) graphingInstance.resize();
    });

    document.addEventListener('mouseup', () => {
      isResizing = false;
    });
  }
}

function initCalculator(type) {
  const graphingDiv = document.getElementById('graphingCalc');
  const scientificDiv = document.getElementById('scientificCalc');
  if (type === 'graphing' && !graphingInstance && typeof Desmos !== 'undefined') {
    graphingInstance = Desmos.GraphingCalculator(graphingDiv, {
      keypad: true,
      keypadActivated: true,
      expressions: true,
      settingsMenu: true,
      smartRenderer: true
    });
    // Explicitly focus to open keypad
    graphingInstance.focusFirstExpression();
  } else if (type === 'scientific' && !scientificInstance && typeof Desmos !== 'undefined') {
    scientificInstance = Desmos.ScientificCalculator(scientificDiv, {
      keypad: true
    });
  } else if (type === 'graphing' && graphingInstance) {
    graphingInstance.resize();
  }
}

// ============================================================================
// SIMPLE FULLSCREEN
// ============================================================================

let fullscreenListener = null;

export function initializeSimpleFullscreen() {
  if (fullscreenListener) {
    document.removeEventListener('click', fullscreenListener);
  }

  fullscreenListener = () => {
    // Check if not already in fullscreen
    const isFullscreen = document.fullscreenElement || 
                         document.webkitFullscreenElement || 
                         document.mozFullScreenElement || 
                         document.msFullscreenElement;
    if (isFullscreen) return;

    const docEl = document.documentElement;
    if (docEl.requestFullscreen) {
      docEl.requestFullscreen().catch(() => {});
    } else if (docEl.mozRequestFullScreen) {
      docEl.mozRequestFullScreen();
    } else if (docEl.webkitRequestFullscreen) {
      docEl.webkitRequestFullscreen();
    } else if (docEl.msRequestFullscreen) {
      docEl.msRequestFullscreen();
    }
    
    // Remove listener after first interaction attempt
    document.removeEventListener('click', fullscreenListener);
    fullscreenListener = null;
  };

  document.addEventListener('click', fullscreenListener);
}

