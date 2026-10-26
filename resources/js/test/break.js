import { pauseTimer, resumeTimer } from './timer.js';
import { showCustomConfirm } from './ui.js';
import { state } from './state.js';

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

let interSectionOverlay = null;
let interSectionTimer = null;
let isFinishingBreak = false;
let breakSyncRetry = null;

function showBreakTransitionState() {
  if (!interSectionOverlay) return;

  interSectionOverlay.innerHTML = `
    <div class="max-w-lg text-center font-sans">
      <div class="w-12 h-12 mx-auto mb-6 rounded-full border-4 border-slate-200 border-t-slate-800 animate-spin"></div>
      <h2 class="text-2xl font-bold text-slate-900 mb-2">Starting Section 2</h2>
      <p class="text-slate-600 text-base">Loading your next module. Please keep this page open.</p>
    </div>
  `;
}

export function showInterSectionBreak(durationSeconds = 600, onComplete, options = {}) {
  state.isOnSectionBreak = true;
  if (interSectionOverlay) {
    interSectionOverlay.remove();
    interSectionOverlay = null;
  }
  if (interSectionTimer) {
    clearInterval(interSectionTimer);
    interSectionTimer = null;
  }
  isFinishingBreak = false;

  let startPerfMs = performance.now();
  let initialRemainingSeconds = Number.isFinite(options?.remainingSeconds) ? options.remainingSeconds : null;
  const nextModuleId = options?.nextModuleId ?? window.nextModuleId;

  const getRemainingSeconds = () => {
    if (initialRemainingSeconds === null) return null;
    const elapsed = (performance.now() - startPerfMs) / 1000;
    return Math.max(0, Math.ceil(initialRemainingSeconds - elapsed));
  };

  interSectionOverlay = document.createElement('div');
  interSectionOverlay.id = 'interSectionBreakOverlay';
  interSectionOverlay.style.position = 'fixed';
  interSectionOverlay.style.inset = '0';
  interSectionOverlay.style.zIndex = '99999';
  interSectionOverlay.style.background = '#ffffff';
  interSectionOverlay.style.display = 'flex';
  interSectionOverlay.style.flexDirection = 'column';
  interSectionOverlay.style.alignItems = 'center';
  interSectionOverlay.style.justifyContent = 'center';
  interSectionOverlay.style.padding = '2rem';

  const formatTime = (secs) => {
    if (secs === null) return 'SYNC';
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  };

  const breakMinutes = Math.max(0, Math.round(durationSeconds / 60));
  const breakCopy = breakMinutes > 0
    ? `Section 1 is complete! You have a ${breakMinutes}-minute break before Section 2 begins. You may step away from your device or start the next section immediately.`
    : `Section 1 is complete! Section 2 is ready to begin.`;

  const renderBreakCard = () => {
    if (!interSectionOverlay) return;
    interSectionOverlay.innerHTML = `
      <div class="max-w-lg text-center font-sans">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 text-slate-800 mb-6">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
        </div>
        <h2 class="text-3xl font-bold text-slate-900 mb-3">Take a Break</h2>
        <p class="text-slate-600 text-base mb-6 leading-relaxed">
          ${breakCopy}
        </p>
        <div class="my-6 py-4 px-8 bg-slate-50 border border-slate-200 rounded-2xl inline-block">
          <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Break Time Remaining</div>
          <div id="breakTimerDisplay" class="text-5xl font-mono font-bold text-slate-900">${formatTime(getRemainingSeconds())}</div>
        </div>
        <div class="mt-6">
          <button type="button" id="resumeTestBtn" class="bg-[#fedb00] text-[#1e1e1e] py-3.5 px-10 rounded-full font-bold text-base transition-shadow duration-300 shadow-[inset_0_0_0_1px_#1e1e1e] hover:shadow-[inset_0_0_0_2px_#1e1e1e] cursor-pointer">
            Resume Test / Start Section 2
          </button>
        </div>
      </div>
    `;

    interSectionOverlay.querySelector('#resumeTestBtn')?.addEventListener('click', async () => {
      if (isFinishingBreak) return;
      const confirmed = await showCustomConfirm(
        'Are you sure you want to end your break and start Section 2 now?',
        'info',
        'Resume Test',
        'Resume Test',
        'Stay on Break'
      );
      if (confirmed) {
        finishBreak(true);
      }
    });
  };

  renderBreakCard();
  document.body.appendChild(interSectionOverlay);

  const removeOverlay = () => {
    window.removeEventListener('focus', syncBreakWithServer);
    window.removeEventListener('pageshow', syncBreakWithServer);
    document.removeEventListener('visibilitychange', syncBreakWithServer);
    if (breakSyncRetry) clearTimeout(breakSyncRetry);
    breakSyncRetry = null;
    if (interSectionOverlay) {
      interSectionOverlay.remove();
      interSectionOverlay = null;
    }
  };

  const finishBreak = async (isEarlyResume = false) => {
    if (isFinishingBreak) return;
    isFinishingBreak = true;

    if (interSectionTimer) {
      clearInterval(interSectionTimer);
      interSectionTimer = null;
    }

    if (isEarlyResume) {
      try {
        const response = await fetch('/engine/test/resume-break', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          },
          body: JSON.stringify({
            user_test_id: window.userTestId,
            module_id: nextModuleId
          })
        });

        if (!response.ok) {
          throw new Error('Failed to resume break on server');
        }
      } catch (err) {
        console.error('Early resume error:', err);
        isFinishingBreak = false;
        renderBreakCard();
        showCustomAlert('Could not resume test. Please check your connection and try again.', 'error', 'Resume Failed');
        startCountdown();
        return;
      }
    }

    state.isOnSectionBreak = false;
    showBreakTransitionState();

    let outcome;
    if (typeof onComplete === 'function') {
      try {
        outcome = await onComplete();
      } catch (error) {
        console.error('Failed to start the next section:', error);
      }
    }

    if (outcome !== 'redirecting') {
      removeOverlay();
    }
  };

  const startCountdown = () => {
    if (interSectionTimer) clearInterval(interSectionTimer);
    interSectionTimer = setInterval(() => {
      const remaining = getRemainingSeconds();
      const timerDisplay = document.getElementById('breakTimerDisplay');
      if (timerDisplay) {
        timerDisplay.textContent = formatTime(remaining);
      }
      if (remaining <= 0) {
        clearInterval(interSectionTimer);
        interSectionTimer = null;
        syncBreakWithServer();
      }
    }, 250);
  };

  let syncingBreak = false;
  async function syncBreakWithServer() {
    if (!interSectionOverlay || isFinishingBreak || syncingBreak || document.visibilityState === 'hidden') return;
    if (breakSyncRetry) clearTimeout(breakSyncRetry);
    breakSyncRetry = null;
    syncingBreak = true;
    const startedAt = performance.now();
    try {
      const params = new URLSearchParams({
        user_test_id: String(window.userTestId),
        module_id: String(nextModuleId),
      });
      const response = await fetch(`${window.timingUrl || '/engine/test/timing'}?${params}`, {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      });
      if (!response.ok) throw new Error(`Break timing request failed (${response.status})`);
      const timing = await response.json();
      if (!interSectionOverlay) return;

      const destination = timing.status === 'completed'
        ? timing.redirect_url
        : (timing.status === 'advanced' ? timing.navigation_url : null);
      if (destination) {
        state.isOnSectionBreak = false;
        if (interSectionTimer) clearInterval(interSectionTimer);
        interSectionTimer = null;
        removeOverlay();
        window.isNavigatingLegitimately = true;
        window.location.href = destination;
        return;
      }

      if (timing.status !== 'break') {
        await finishBreak(false);
        return;
      }

      initialRemainingSeconds = Math.max(0, Math.ceil(
        Number(timing.remaining_break_seconds) - (performance.now() - startedAt) / 2000
      ));
      startPerfMs = performance.now();
      const timerDisplay = document.getElementById('breakTimerDisplay');
      if (timerDisplay) timerDisplay.textContent = formatTime(getRemainingSeconds());
      if (!interSectionTimer) startCountdown();
    } catch (error) {
      console.error('Could not verify section break timing:', error);
      if (interSectionOverlay) {
        const timerDisplay = document.getElementById('breakTimerDisplay');
        if (timerDisplay && getRemainingSeconds() === null) timerDisplay.textContent = 'SYNC';
        breakSyncRetry = setTimeout(() => {
          breakSyncRetry = null;
          syncBreakWithServer();
        }, 2000);
      }
    } finally {
      syncingBreak = false;
    }
  }

  window.addEventListener('focus', syncBreakWithServer);
  window.addEventListener('pageshow', syncBreakWithServer);
  document.addEventListener('visibilitychange', syncBreakWithServer);
  if (initialRemainingSeconds !== null) startCountdown();
  syncBreakWithServer();
}

export function initializeBreakRecoveryView() {
  showInterSectionBreak(
    window.breakDurationSeconds ?? 600,
    () => {
      window.isNavigatingLegitimately = true;
      window.location.href = window.nextModuleUrl;
      return 'redirecting';
    },
    {
      nextModuleId: window.nextModuleId,
      remainingSeconds: window.remainingBreakSeconds ?? 600,
    }
  );
}
