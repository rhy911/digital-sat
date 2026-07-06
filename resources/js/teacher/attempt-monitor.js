const monitors = new Map();

function stopPolling(modalId) {
    const state = monitors.get(modalId);
    if (!state) return;

    window.clearInterval(state.intervalId);
    state.controller?.abort();
    monitors.delete(modalId);
}

function activeAttemptId(monitor) {
    return monitor.querySelector('[data-attempt-id].is-active')?.dataset.attemptId
        || monitor.dataset.activeAttempt;
}

async function refreshMonitor(modalId, state) {
    if (state.fetching || document.hidden) return;

    const current = document.querySelector(`#${CSS.escape(modalId)} [data-attempt-monitor]`);
    if (!current) {
        stopPolling(modalId);
        return;
    }

    // Attach scroll listener to current if not already attached
    if (!current.dataset.scrollListenerAttached) {
        current.addEventListener('scroll', () => {
            state.lastScrollTime = Date.now();
        }, { passive: true, capture: true });
        current.dataset.scrollListenerAttached = 'true';
    }

    // Skip polling if the user scrolled in the last 2 seconds
    const isScrolling = (Date.now() - (state.lastScrollTime || 0)) < 2000;
    if (isScrolling) {
        return;
    }

    state.fetching = true;
    state.controller = new AbortController();
    current.classList.add('is-updating');
    const dialog = current.closest('[data-modal-dialog]');
    const scrollState = {
        monitor: current.scrollTop,
        dialog: dialog?.scrollTop || 0,
        windowX: window.scrollX,
        windowY: window.scrollY,
    };
    const focusedAttemptId = current.contains(document.activeElement)
        ? document.activeElement.closest('[data-attempt-id]')?.dataset.attemptId
        : null;

    try {
        const url = new URL(current.dataset.pollUrl, window.location.origin);
        const selectedAttempt = activeAttemptId(current);
        if (selectedAttempt) url.searchParams.set('active_attempt', selectedAttempt);

        const response = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: state.controller.signal,
        });
        if (!response.ok) throw new Error(`Attempt monitor refresh failed with status ${response.status}`);

        const payload = await response.json();

        const template = document.createElement('template');
        template.innerHTML = payload.html.trim();
        const next = template.content.firstElementChild;
        if (!next) throw new Error('Attempt monitor refresh returned empty HTML');

        // Check if the user is scrolling or selecting text right before DOM swap
        const isScrollingNow = (Date.now() - (state.lastScrollTime || 0)) < 1500;
        const selection = window.getSelection();
        const hasSelection = selection && selection.toString().length > 0 && selection.anchorNode && current.contains(selection.anchorNode);
        
        if (isScrollingNow || hasSelection) {
            state.fetching = false;
            current.classList.remove('is-updating');
            return;
        }

        if (window.Alpine && typeof window.Alpine.morph === 'function') {
            window.Alpine.morph(current, next, {
                updating(el, toEl, childrenOnly, skip) {
                    if (el.hasAttribute && el.hasAttribute('data-morph-skip')) {
                        skip();
                    }
                    // Preserve active classes and styles for Alpine-toggled elements
                    if (el.hasAttribute && (el.hasAttribute(':class') || el.hasAttribute('x-show') || el.hasAttribute('x-bind:class'))) {
                        toEl.setAttribute('class', el.getAttribute('class') || '');
                    }
                    if (el.hasAttribute && (el.hasAttribute(':style') || el.hasAttribute('x-show') || el.hasAttribute('x-bind:style'))) {
                        if (el.hasAttribute('style')) {
                            toEl.setAttribute('style', el.getAttribute('style') || '');
                        }
                    }
                }
            });
        } else {
            current.replaceWith(next);
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            current.classList.remove('is-updating');
            current.classList.add('has-update-error');
            const status = current.querySelector('[data-monitor-update-status]');
            if (status) status.textContent = 'Updates paused';
            console.warn(error);
        }
    } finally {
        state.fetching = false;
        state.controller = null;
    }
}

function startPolling(modalId) {
    stopPolling(modalId);
    const monitor = document.querySelector(`#${CSS.escape(modalId)} [data-attempt-monitor]`);
    if (!monitor) return;

    const state = { fetching: false, controller: null, intervalId: null };
    monitors.set(modalId, state);
    refreshMonitor(modalId, state);
    state.intervalId = window.setInterval(() => refreshMonitor(modalId, state), 5000);
}

export function initAttemptMonitorPolling() {
    if (window.attemptMonitorPollingInitialized) return;
    window.attemptMonitorPollingInitialized = true;

    window.addEventListener('open-modal', event => {
        if (typeof event.detail === 'string' && event.detail.startsWith('attempts-')) {
            window.setTimeout(() => startPolling(event.detail), 0);
        }
    });
    window.addEventListener('close-modal', event => {
        if (typeof event.detail === 'string') stopPolling(event.detail);
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            [...monitors.keys()].forEach(stopPolling);
            return;
        }

        document.querySelectorAll('[data-modal-dialog^="attempts-"][aria-hidden="false"]').forEach(dialog => {
            startPolling(dialog.id);
        });
    });
    window.addEventListener('beforeunload', () => [...monitors.keys()].forEach(stopPolling));
}
