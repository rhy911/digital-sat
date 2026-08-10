import { showLoadingScreen } from './ui.js';

/**
 * Scoring status polling.
 *
 * A slow queue is not a failure. The 70-student mock exam failed because the
 * client treated "not finished yet" as "broken": it gave up after 30 attempts,
 * showed an error, and released the submit guard — which let a second POST reach
 * a still-held lock and produce `module_progression_conflict`. Everything here
 * exists to keep waiting honestly instead.
 *
 * Rules this module enforces:
 *   - A wall-clock budget, not an attempt count.
 *   - Transient failures (network blips, HTTP 429) mean "keep waiting".
 *   - 409 `submission_in_progress` means "keep waiting" — never re-POST.
 *   - The student sees escalating progress copy, never a raw error code.
 */

// Must stay below config('scoring.client_budget'); the blade exposes the server
// value so the two cannot drift.
const DEFAULT_BUDGET_MS = 300 * 1000;
const MIN_DELAY_MS = 1500;
const MAX_DELAY_MS = 10000;
const MAX_TRANSPORT_FAILURES = 8;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function budgetMs() {
  const seconds = Number(window.scoringBudgetSeconds);
  return Number.isFinite(seconds) && seconds > 0 ? seconds * 1000 : DEFAULT_BUDGET_MS;
}

/**
 * Exponential backoff with jitter. The jitter matters more than the backoff at
 * 70 concurrent clients: without it they re-sync into a single burst every cycle.
 */
function nextDelay(current, aggressive = false) {
  const jitter = Math.floor(Math.random() * 300) + 200;
  const grown = Math.min(MAX_DELAY_MS, Math.floor(current * (aggressive ? 2 : 1.3)));
  return grown + jitter;
}

function progressCopy(elapsedMs) {
  const seconds = Math.floor(elapsedMs / 1000);
  if (seconds < 20) return 'Saving responses & scoring current module...';
  if (seconds < 60) return 'Scoring your module. Please keep this page open.';
  if (seconds < 120) {
    return `Still scoring — many students finished at the same time, so there is a queue. Your answers are saved. (${seconds}s)`;
  }
  return `Still scoring. Please do not close or refresh this page — your answers are saved. (${seconds}s)`;
}

/**
 * Poll /engine/submit-status until a terminal result arrives or the wall-clock
 * budget runs out.
 *
 * Resolves with the server payload, or with a NON-FATAL {error} code that the
 * caller renders as a state rather than a dead end.
 *
 * @param {string} userTestUlid
 * @param {number|string} moduleId
 * @returns {Promise<object>}
 */
export async function pollForScoringResult(userTestUlid, moduleId) {
  // The route binds {userTest:ulid} and UserTest::getRouteKeyName() returns
  // 'ulid', so falling back to the numeric id (as the old loop did) is a
  // guaranteed 404 that surfaced to students as "Polling error".
  if (!userTestUlid) {
    return { error: 'scoring_unavailable' };
  }

  const startedAt = Date.now();
  const budget = budgetMs();
  let delay = MIN_DELAY_MS;
  let transportFailures = 0;

  const statusUrl = `/engine/submit-status/${encodeURIComponent(userTestUlid)}`
    + `?module_id=${encodeURIComponent(moduleId ?? '')}`;

  while (Date.now() - startedAt < budget) {
    showLoadingScreen(progressCopy(Date.now() - startedAt));
    await sleep(delay);

    let response;
    try {
      response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
    } catch (e) {
      if (++transportFailures >= MAX_TRANSPORT_FAILURES) return { error: 'network_lost' };
      delay = nextDelay(delay, true);
      continue;
    }

    // Back-pressure, not failure. Back off and keep waiting.
    if (response.status === 429) {
      const retryAfter = parseInt(response.headers.get('Retry-After') || '0', 10);
      delay = Math.max(delay, (retryAfter || 5) * 1000);
      continue;
    }

    // Only a dead session justifies leaving the page.
    if (response.status === 401 || response.status === 419) {
      return { error: 'session_expired' };
    }

    if (!response.ok) {
      if (++transportFailures >= MAX_TRANSPORT_FAILURES) return { error: 'network_lost' };
      delay = nextDelay(delay, true);
      continue;
    }

    transportFailures = 0;

    const data = await response.json().catch(() => null);
    if (data && data.status !== 'scoring') {
      return data; // terminal: success OR a server-reported error
    }

    delay = nextDelay(delay);
  }

  // Budget exhausted. This is a STATE, not a crash — the caller offers
  // "keep waiting" before anything else.
  return { error: 'scoring_timeout' };
}

/**
 * Server error codes are a contract (ModuleProgressionSecurityTest asserts on
 * them), so they stay machine-readable and the wording lives here — the one
 * place a human reads it. `recovery` tells the caller what to do next.
 */
const MESSAGES = {
  scoring_timeout: {
    title: 'Still Scoring',
    type: 'warning',
    recovery: 'wait-or-retry',
    message: 'Your answers are saved. Scoring is taking longer than usual because many students finished at the same moment.\n\nYou can keep waiting, or ask us to try again.',
  },
  submission_in_progress: {
    title: 'Still Scoring',
    type: 'warning',
    recovery: 'wait',
    message: 'Your module is already being scored. Your answers are saved — please keep this page open.',
  },
  module_progression_conflict: {
    title: 'Already Submitted',
    type: 'warning',
    recovery: 'resync',
    message: 'This module has already been submitted and your answers are saved.\n\nWe will take you to the correct place in your test.',
  },
  module_expired: {
    title: 'Time Is Up',
    type: 'warning',
    recovery: 'none',
    message: 'The time for this module has ended. Your saved answers were submitted.',
  },
  'Unauthorized submission.': {
    title: 'Attempt Unavailable',
    type: 'error',
    recovery: 'home',
    message: 'This test attempt is no longer available on this device. Please return home and reopen the test.',
  },
  'Server error during submission.': {
    title: 'Scoring Problem',
    type: 'error',
    recovery: 'retry',
    message: 'We could not finish scoring this module. Your answers are saved — please try again.',
  },
  network_lost: {
    title: 'Connection Lost',
    type: 'error',
    recovery: 'retry',
    message: 'We lost the connection while scoring. Your answers are saved. Check your internet and try again.',
  },
  session_expired: {
    title: 'Session Ended',
    type: 'error',
    recovery: 'home',
    message: 'You were signed out. Your answers are saved. Please sign in again to continue.',
  },
  scoring_unavailable: {
    title: 'Scoring Problem',
    type: 'error',
    recovery: 'retry',
    message: 'We could not check your scoring status. Your answers are saved — please try again.',
  },
};

/**
 * Map a server payload to text a student can act on. Never returns a raw code:
 * `data.error` used to be rendered verbatim, which is how students saw the
 * literal string "module_progression_conflict".
 */
export function describeSubmitError(data) {
  return MESSAGES[data && data.error] || {
    title: 'Submission Problem',
    type: 'error',
    recovery: 'retry',
    message: 'We could not finish submitting this module. Your answers are saved. Please try again.',
  };
}
