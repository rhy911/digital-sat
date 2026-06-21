const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const stack = [];
const managedInertElements = new Set();
let initialized = false;

function visibleFocusableElements(dialog) {
    return Array.from(dialog.querySelectorAll(FOCUSABLE_SELECTOR)).filter(element => {
        const style = window.getComputedStyle(element);
        return element.getClientRects().length > 0 && style.display !== 'none' && style.visibility !== 'hidden';
    });
}

function syncPageState() {
    const activeDialog = stack.at(-1)?.dialog ?? null;

    managedInertElements.forEach(element => {
        element.inert = false;
        managedInertElements.delete(element);
    });

    if (!activeDialog) {
        document.body.classList.remove('overflow-hidden');
        return;
    }

    document.body.classList.add('overflow-hidden');
    Array.from(document.body.children).forEach(element => {
        if (element === activeDialog || element.tagName === 'SCRIPT' || element.tagName === 'STYLE') return;
        element.inert = true;
        managedInertElements.add(element);
    });
}

function openDialog(id) {
    const dialog = document.getElementById(id);
    if (!dialog) return;

    const existingIndex = stack.findIndex(entry => entry.dialog === dialog);
    if (existingIndex >= 0) stack.splice(existingIndex, 1);

    stack.push({
        dialog,
        opener: document.activeElement instanceof HTMLElement ? document.activeElement : null,
    });
    syncPageState();

    requestAnimationFrame(() => window.setTimeout(() => {
        const initialFocus = dialog.querySelector('[data-dialog-initial-focus]');
        const focusTarget = initialFocus || visibleFocusableElements(dialog)[0] || dialog;
        if (!dialog.hasAttribute('tabindex')) dialog.setAttribute('tabindex', '-1');
        focusTarget?.focus({ preventScroll: true });
    }, 50));
}

function closeDialog(id) {
    const index = stack.findIndex(entry => entry.dialog.id === id);
    if (index < 0) return;

    const wasTopmost = index === stack.length - 1;
    const [{ opener }] = stack.splice(index, 1);
    syncPageState();

    requestAnimationFrame(() => {
        if (wasTopmost && opener?.isConnected) opener.focus({ preventScroll: true });
    });
}

function trapFocus(event) {
    if (event.key !== 'Tab' || stack.length === 0) return;

    const dialog = stack.at(-1).dialog;
    const focusable = visibleFocusableElements(dialog);
    if (focusable.length === 0) {
        event.preventDefault();
        dialog.focus();
        return;
    }

    const first = focusable[0];
    const last = focusable.at(-1);
    if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

function closeTopmostDialog(event) {
    if (event.key !== 'Escape' || stack.length === 0) return;

    event.preventDefault();
    const dialog = stack.at(-1).dialog;
    const eventName = dialog.hasAttribute('data-offcanvas-dialog') ? 'close-offcanvas' : 'close-modal';
    window.dispatchEvent(new CustomEvent(eventName, { detail: dialog.id }));
}

export function initDialogManager() {
    if (initialized) return;
    initialized = true;

    window.addEventListener('open-modal', event => openDialog(event.detail));
    window.addEventListener('close-modal', event => closeDialog(event.detail));
    window.addEventListener('open-offcanvas', event => openDialog(event.detail));
    window.addEventListener('close-offcanvas', event => closeDialog(event.detail));
    document.addEventListener('keydown', trapFocus);
    document.addEventListener('keydown', closeTopmostDialog);
}
