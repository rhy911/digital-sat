import { pauseTimer, resumeTimer } from './timer.js';

let breakOverlay = null;
let isOnBreak = false;
let previousFooterPointerEvents = '';
let previousFooterOpacity = '';

export function initializeBreakControls() {
  const breakBtn = document.getElementById('takeBreakBtn');
  if (!breakBtn) return;

  if (!window.canTakeBreak) {
    breakBtn.classList.add('hidden');
    return;
  }

  breakBtn.classList.remove('hidden');
  if (breakBtn.dataset.breakInitialized === 'true') return;
  breakBtn.dataset.breakInitialized = 'true';
  breakBtn.addEventListener('click', startBreak);
}

function startBreak() {
  if (isOnBreak) return;
  isOnBreak = true;
  if (!window.isAssignmentAttempt) pauseTimer();

  document.querySelector('main')?.classList.add('hidden');
  const footer = document.querySelector('footer');
  if (footer) {
    previousFooterPointerEvents = footer.style.pointerEvents;
    previousFooterOpacity = footer.style.opacity;
    footer.style.pointerEvents = 'none';
    footer.style.opacity = '0.4';
  }
  const overlay = getBreakOverlay();
  overlay.classList.remove('hidden');
  overlay.style.display = 'flex';
}

function endBreak() {
  isOnBreak = false;
  document.querySelector('main')?.classList.remove('hidden');
  const footer = document.querySelector('footer');
  if (footer) {
    footer.style.pointerEvents = previousFooterPointerEvents;
    footer.style.opacity = previousFooterOpacity;
  }
  const overlay = getBreakOverlay();
  overlay.classList.add('hidden');
  overlay.style.display = 'none';
  if (!window.isAssignmentAttempt) resumeTimer();
}

function getBreakOverlay() {
  if (breakOverlay) return breakOverlay;

  breakOverlay = document.createElement('div');
  breakOverlay.id = 'takeBreakOverlay';
  breakOverlay.className = 'hidden';
  breakOverlay.style.display = 'none';
  breakOverlay.style.position = 'fixed';
  breakOverlay.style.left = '0';
  breakOverlay.style.right = '0';
  breakOverlay.style.top = '96px';
  breakOverlay.style.bottom = '88px';
  breakOverlay.style.zIndex = '9999';
  breakOverlay.style.background = '#ffffff';
  breakOverlay.style.alignItems = 'center';
  breakOverlay.style.justifyContent = 'center';
  const assignmentCopy = window.isAssignmentAttempt
    ? '<h2 class="text-2xl font-bold text-slate-900 mb-3">Break started</h2><p class="text-slate-600 mb-6">Your assignment module timer continues while you are on this break.</p>'
    : '<h2 class="text-2xl font-bold text-slate-900 mb-3">Break paused</h2><p class="text-slate-600 mb-6">Your timer is paused. Continue when you are ready.</p>';

  breakOverlay.innerHTML = `
    <div class="text-center px-6 max-w-md">
      ${assignmentCopy}
      <button type="button" id="continueTestBtn" class="bg-[#fedb00] text-[#1e1e1e] py-3 px-8 rounded-full font-semibold text-sm shadow-[inset_0_0_0_1px_#1e1e1e] hover:shadow-[inset_0_0_0_2px_#1e1e1e]">
        Continue Test
      </button>
    </div>
  `;
  breakOverlay.querySelector('#continueTestBtn').addEventListener('click', endBreak);
  document.body.appendChild(breakOverlay);

  return breakOverlay;
}
