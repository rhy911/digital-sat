export function getOrCreateAlertModal() {
    let modal = document.getElementById('customAlertModal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'customAlertModal';
    modal.className = 'custom-alert-modal hidden';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'customAlertTitle');
    modal.setAttribute('aria-describedby', 'customAlertMessage');
    modal.innerHTML = `
        <div class="custom-alert-backdrop"></div>
        <div class="custom-alert-box">
            <div class="custom-alert-icon" id="customAlertIcon"></div>
            <div class="custom-alert-content">
                <h5 class="custom-alert-title" id="customAlertTitle">Notification</h5>
                <p id="customAlertMessage" class="custom-alert-message"></p>
                <input type="text" id="customAlertInput" class="custom-alert-input hidden" placeholder="Enter value...">
            </div>
            <div class="custom-alert-actions">
                <button id="customAlertCancelBtn" class="custom-alert-btn btn-secondary hidden">Cancel</button>
                <button id="customAlertConfirmBtn" class="custom-alert-btn btn-primary">OK</button>
            </div>
        </div>
    `;

    if (!document.querySelector('style[data-custom-alerts]')) {
        const style = document.createElement('style');
        style.setAttribute('data-custom-alerts', 'true');
        style.textContent = `
            .custom-alert-modal {
                position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 10000;
                display: flex; align-items: center; justify-content: center; opacity: 1; transition: opacity 0.2s ease;
                will-change: opacity; pointer-events: auto;
            }
            .custom-alert-modal.hidden { display: none !important; opacity: 0; }
            .custom-alert-backdrop {
                position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(8, 12, 21, 0.7); pointer-events: auto;
            }
            .custom-alert-box {
                position: relative; background: #ffffff; border-radius: 12px;
                box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
                width: 90%; max-width: 440px; padding: 28px;
                display: flex; flex-direction: column; align-items: center; text-align: center;
                transform: scale(1); transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1); z-index: 1;
                will-change: transform; pointer-events: auto;
                transform-gpu: translate3d(0,0,0);
            }
            .custom-alert-modal.hidden .custom-alert-box { transform: scale(0.95); }
            .custom-alert-icon {
                display: flex; align-items: center; justify-content: center; width: 56px; height: 56px;
                border-radius: 50%; background-color: rgba(99, 102, 241, 0.1); color: #818cf8; margin-bottom: 18px;
            }
            .custom-alert-icon.warning { background-color: rgba(245, 158, 11, 0.1); color: #fbbf24; }
            .custom-alert-icon.error { background-color: rgba(239, 68, 68, 0.1); color: #f87171; }
            .custom-alert-icon.success { background-color: rgba(16, 185, 129, 0.1); color: #34d399; }
            .custom-alert-content { margin-bottom: 24px; width: 100%; }
            .custom-alert-title { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-bottom: 10px; font-family: system-ui, -apple-system, sans-serif; }
            /* pre-line so multi-line import errors list one problem per line;
               the text is still set via textContent, never innerHTML. */
            .custom-alert-message { font-size: 0.9rem; color: #475569; line-height: 1.6; margin: 0; font-family: system-ui, -apple-system, sans-serif; white-space: pre-line; text-align: left; max-height: 45vh; overflow-y: auto; }
            .custom-alert-input {
                width: 100%; margin-top: 16px; padding: 10px 14px; background: #ffffff; color: #0f172a;
                border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; outline: none;
                transition: all 0.2s ease; font-family: system-ui, -apple-system, sans-serif;
            }
            .custom-alert-input:focus {
                border-color: var(--color-brand); box-shadow: 0 0 0 3px rgba(50, 77, 199, 0.16); background: #ffffff;
            }
            .custom-alert-actions { display: flex; gap: 12px; width: 100%; justify-content: center; }
            .custom-alert-btn { flex: 1; max-width: 160px; padding: 10px 18px; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.15s ease; border: none; outline: none; display: inline-flex; align-items: center; justify-content: center; }
            .custom-alert-btn.btn-primary { background: var(--color-brand); color: #ffffff; }
            .custom-alert-btn.btn-primary:hover { background: var(--color-brand-hover); }
            .custom-alert-btn.btn-secondary { background-color: #ffffff; color: #334155; border: 1px solid #cbd5e1; }
            .custom-alert-btn.btn-secondary:hover { background-color: #f8fafc; color: #0f172a; }
            .custom-alert-btn:focus-visible { outline: 2px solid var(--color-brand); outline-offset: 2px; }
        `;
        document.head.appendChild(style);
    }

    document.body.appendChild(modal);
    return modal;
}

function prepareAlertModal(modal) {
    modal.inert = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.style.pointerEvents = 'auto';
}

function hideAlertModal(modal) {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
}

export function showCustomAlert(message, type = 'info', title = 'Notification') {
    return new Promise((resolve) => {
        const modal = getOrCreateAlertModal();
        const titleEl = modal.querySelector('#customAlertTitle');
        const msgEl = modal.querySelector('#customAlertMessage');
        const iconEl = modal.querySelector('#customAlertIcon');
        const confirmBtn = modal.querySelector('#customAlertConfirmBtn');
        const cancelBtn = modal.querySelector('#customAlertCancelBtn');

        titleEl.textContent = title;
        msgEl.textContent = message;

        cancelBtn.classList.add('hidden');
        confirmBtn.className = 'custom-alert-btn btn-primary';
        confirmBtn.textContent = 'OK';

        iconEl.className = 'custom-alert-icon ' + type;
        if (type === 'warning') {
            iconEl.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            `;
        } else if (type === 'error') {
            iconEl.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
            `;
        } else if (type === 'success') {
            iconEl.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            `;
        } else {
            iconEl.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            `;
        }

        const opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        prepareAlertModal(modal);
        modal.classList.remove('hidden');
        requestAnimationFrame(() => confirmBtn.focus({ preventScroll: true }));

        const handleConfirm = () => {
            cleanup();
            resolve(true);
        };

        const handleKeydown = event => {
            if (event.key === 'Escape' || event.key === 'Enter') {
                event.preventDefault();
                event.stopPropagation();
                handleConfirm();
            }
        };

        const cleanup = () => {
            hideAlertModal(modal);
            confirmBtn.removeEventListener('click', handleConfirm);
            modal.removeEventListener('keydown', handleKeydown);
            if (opener?.isConnected) opener.focus({ preventScroll: true });
        };

        confirmBtn.addEventListener('click', handleConfirm);
        modal.addEventListener('keydown', handleKeydown);
    });
}

export function showCustomConfirm(message, type = 'warning', title = 'Confirm Action') {
    return new Promise((resolve) => {
        const modal = getOrCreateAlertModal();
        const titleEl = modal.querySelector('#customAlertTitle');
        const msgEl = modal.querySelector('#customAlertMessage');
        const iconEl = modal.querySelector('#customAlertIcon');
        const confirmBtn = modal.querySelector('#customAlertConfirmBtn');
        const cancelBtn = modal.querySelector('#customAlertCancelBtn');

        titleEl.textContent = title;
        if (typeof message === 'string' && message.trim().startsWith('<')) {
            msgEl.innerHTML = message;
        } else {
            msgEl.textContent = message;
        }

        cancelBtn.classList.remove('hidden');
        cancelBtn.textContent = 'Cancel';
        confirmBtn.className = 'custom-alert-btn btn-primary';
        confirmBtn.textContent = 'Confirm';
        confirmBtn.disabled = false;
        confirmBtn.classList.remove('opacity-40', 'cursor-not-allowed');

        const forceCheckbox = msgEl.querySelector('#forceDeleteIrreversibleCheckbox');
        if (forceCheckbox) {
            confirmBtn.disabled = true;
            confirmBtn.classList.add('opacity-40', 'cursor-not-allowed');
            forceCheckbox.addEventListener('change', () => {
                confirmBtn.disabled = !forceCheckbox.checked;
                if (forceCheckbox.checked) {
                    confirmBtn.classList.remove('opacity-40', 'cursor-not-allowed');
                } else {
                    confirmBtn.classList.add('opacity-40', 'cursor-not-allowed');
                }
            });
        }

        iconEl.className = 'custom-alert-icon ' + type;
        if (type === 'warning') {
            iconEl.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            `;
        } else {
            iconEl.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            `;
        }

        const opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        const appShell = document.querySelector('[data-test-builder-shell]');
        prepareAlertModal(modal);
        modal.classList.remove('hidden');
        if (appShell) appShell.inert = true;
        requestAnimationFrame(() => cancelBtn.focus({ preventScroll: true }));

        const handleConfirm = () => {
            cleanup();
            resolve(true);
        };

        const handleCancel = () => {
            cleanup();
            resolve(false);
        };

        const handleKeydown = event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                handleCancel();
                return;
            }
            if (event.key !== 'Tab') return;
            event.stopPropagation();
            if (event.shiftKey && document.activeElement === cancelBtn) {
                event.preventDefault();
                confirmBtn.focus();
            } else if (!event.shiftKey && document.activeElement === confirmBtn) {
                event.preventDefault();
                cancelBtn.focus();
            }
        };

        const cleanup = () => {
            hideAlertModal(modal);
            if (appShell) appShell.inert = false;
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', handleCancel);
            modal.removeEventListener('keydown', handleKeydown);
            if (opener?.isConnected) opener.focus({ preventScroll: true });
        };

        confirmBtn.addEventListener('click', handleConfirm);
        cancelBtn.addEventListener('click', handleCancel);
        modal.addEventListener('keydown', handleKeydown);
    });
}

export function showCustomPrompt(message, defaultValue = '', title = 'Input Required') {
    return new Promise((resolve) => {
        const modal = getOrCreateAlertModal();
        const titleEl = modal.querySelector('#customAlertTitle');
        const msgEl = modal.querySelector('#customAlertMessage');
        const inputEl = modal.querySelector('#customAlertInput');
        const iconEl = modal.querySelector('#customAlertIcon');
        const confirmBtn = modal.querySelector('#customAlertConfirmBtn');
        const cancelBtn = modal.querySelector('#customAlertCancelBtn');

        titleEl.textContent = title;
        msgEl.textContent = message;

        inputEl.classList.remove('hidden');
        inputEl.value = defaultValue;

        cancelBtn.classList.remove('hidden');
        cancelBtn.textContent = 'Cancel';
        confirmBtn.className = 'custom-alert-btn btn-primary';
        confirmBtn.textContent = 'OK';

        iconEl.className = 'custom-alert-icon info';
        iconEl.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
        `;

        prepareAlertModal(modal);
        modal.classList.remove('hidden');

        setTimeout(() => {
            inputEl.focus();
            inputEl.select();
        }, 50);

        const handleConfirm = () => {
            const val = inputEl.value;
            cleanup();
            resolve(val);
        };

        const handleCancel = () => {
            cleanup();
            resolve(null);
        };

        const handleKeyDown = (e) => {
            if (e.key === 'Enter') {
                handleConfirm();
            }
        };

        const cleanup = () => {
            hideAlertModal(modal);
            inputEl.classList.add('hidden');
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', handleCancel);
            inputEl.removeEventListener('keydown', handleKeyDown);
        };

        confirmBtn.addEventListener('click', handleConfirm);
        cancelBtn.addEventListener('click', handleCancel);
        inputEl.addEventListener('keydown', handleKeyDown);
    });
}

export function showAlert(type, message) {
    let mappedType = type;
    if (type === 'danger') mappedType = 'error';
    showCustomAlert(message, mappedType);
}
