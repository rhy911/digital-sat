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

        window.Alpine?.destroyTree?.(current);
        current.replaceWith(next);
        window.Alpine?.initTree?.(next);

        const restoreUiState = () => {
            if (!next.isConnected) return;
            next.scrollTop = scrollState.monitor;
            if (dialog) dialog.scrollTop = scrollState.dialog;
            window.scrollTo(scrollState.windowX, scrollState.windowY);
            if (focusedAttemptId) {
                next.querySelector(`[data-attempt-id="${CSS.escape(focusedAttemptId)}"]`)
                    ?.focus({ preventScroll: true });
            }
        };
        restoreUiState();
        window.requestAnimationFrame(restoreUiState);
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
