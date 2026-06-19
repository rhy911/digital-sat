const WIDTH_KEY = 'testDashboardSidebarWidth';
const COMPACT_KEY = 'testDashboardSidebarCompact';
const MIN_WIDTH = 208;
const MAX_WIDTH = 384;
const DEFAULT_WIDTH = 256;
const KEYBOARD_STEP = 16;

function clampWidth(value) {
    return Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, value));
}

function savedWidth() {
    const value = Number.parseInt(localStorage.getItem(WIDTH_KEY) || '', 10);
    return Number.isFinite(value) ? clampWidth(value) : DEFAULT_WIDTH;
}

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('testDashboardSidebar');
    const toggle = sidebar?.querySelector('[data-sidebar-toggle]');
    const resizer = sidebar?.querySelector('[data-sidebar-resizer]');
    const desktop = window.matchMedia('(min-width: 1024px)');

    if (!sidebar || !toggle || !resizer) return;

    let width = savedWidth();
    let compact = localStorage.getItem(COMPACT_KEY) === 'true';

    function updateControls() {
        const action = compact ? 'Expand sidebar' : 'Compact sidebar';
        toggle.setAttribute('aria-label', action);
        toggle.setAttribute('title', action);
        toggle.setAttribute('aria-expanded', String(!compact));
        resizer.setAttribute('aria-valuenow', String(width));
        resizer.setAttribute('aria-disabled', String(compact));
        resizer.tabIndex = compact ? -1 : 0;
    }

    function render() {
        sidebar.style.setProperty('--test-builder-sidebar-width', `${width}px`);
        sidebar.classList.toggle('is-compact', compact);
        updateControls();
    }

    function setWidth(nextWidth, persist = true) {
        width = clampWidth(nextWidth);
        sidebar.style.setProperty('--test-builder-sidebar-width', `${width}px`);
        resizer.setAttribute('aria-valuenow', String(width));
        if (persist) localStorage.setItem(WIDTH_KEY, String(width));
    }

    toggle.addEventListener('click', () => {
        if (!desktop.matches) return;
        compact = !compact;
        localStorage.setItem(COMPACT_KEY, String(compact));
        render();
    });

    resizer.addEventListener('pointerdown', (event) => {
        if (!desktop.matches || compact || event.button !== 0) return;

        event.preventDefault();
        resizer.setPointerCapture(event.pointerId);
        sidebar.classList.add('is-resizing');
    });

    resizer.addEventListener('pointermove', (event) => {
        if (!resizer.hasPointerCapture(event.pointerId)) return;
        setWidth(event.clientX, false);
    });

    function finishResize(event) {
        if (!resizer.hasPointerCapture(event.pointerId)) return;
        resizer.releasePointerCapture(event.pointerId);
        sidebar.classList.remove('is-resizing');
        localStorage.setItem(WIDTH_KEY, String(width));
    }

    resizer.addEventListener('pointerup', finishResize);
    resizer.addEventListener('pointercancel', finishResize);

    resizer.addEventListener('dblclick', () => setWidth(DEFAULT_WIDTH));
    resizer.addEventListener('keydown', (event) => {
        const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
        if (!desktop.matches || compact || !keys.includes(event.key)) return;

        event.preventDefault();
        if (event.key === 'Home') setWidth(MIN_WIDTH);
        if (event.key === 'End') setWidth(MAX_WIDTH);
        if (event.key === 'ArrowLeft') setWidth(width - KEYBOARD_STEP);
        if (event.key === 'ArrowRight') setWidth(width + KEYBOARD_STEP);
    });

    render();
});
