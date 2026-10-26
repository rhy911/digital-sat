import Alpine from 'alpinejs';
import morph from '@alpinejs/morph';
import { initTruncatedTooltips } from './ui/truncated-tooltip.js';
import { initDialogManager } from './ui/dialog-manager.js';
import { initAttemptMonitorPolling } from './teacher/attempt-monitor.js';
import { initNotificationBell } from './notifications.js';
import { initMobileClassroomNav } from './classroom-nav.js';
import { studentHome } from './student/home.js';

(window.Alpine || Alpine).data('studentHome', studentHome);

if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.plugin(morph);
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.Livewire && typeof Alpine.start === 'function') {
            Alpine.start();
        }
    });
}

initTruncatedTooltips();
initDialogManager();
initAttemptMonitorPolling();
initAutoDismissAlerts();
initNotificationBell();
initMobileClassroomNav();

export function clearDatePicker(id) {
    const input = typeof id === 'string' ? document.getElementById(id) : id;
    if (!input) return;
    input.value = '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}
window.clearDatePicker = clearDatePicker;

export function initDatePickers() {
    document.querySelectorAll('.datetime-picker[data-window-role]').forEach((input) => {
        input.removeEventListener('change', handleWindowChange);
        input.addEventListener('change', handleWindowChange);
    });
    syncAssignmentWindows();
}
window.initDatePickers = initDatePickers;

function handleWindowChange(e) {
    syncAssignmentWindow(e.target);
}

function syncAssignmentWindows() {
    document.querySelectorAll('.datetime-picker[data-window-role]').forEach((input) => {
        syncAssignmentWindow(input);
    });
}

function syncAssignmentWindow(input) {
    const pairId = input.dataset.windowPair;
    if (!pairId) return;

    const pairInput = document.getElementById(pairId);
    if (!pairInput) return;

    const startInput = input.dataset.windowRole === 'start' ? input : pairInput;
    const endInput = input.dataset.windowRole === 'end' ? input : pairInput;

    const startVal = startInput.value;
    const endVal = endInput.value;
    if (endInput.dataset.windowBaseMin === undefined) {
        endInput.dataset.windowBaseMin = endInput.getAttribute('min') || '';
    }

    const minimum = [endInput.dataset.windowBaseMin, endInput.dataset.allowPast === 'true' ? '' : startVal]
        .filter(Boolean)
        .sort()
        .at(-1);
    if (minimum) endInput.min = minimum;
    else endInput.removeAttribute('min');

    const hasInvalidWindow = Boolean(startVal && endVal && endVal <= startVal);
    setWindowError(endInput, hasInvalidWindow);
}

function setWindowError(input, hasError) {
    input.closest('.time-field')?.classList.toggle('has-window-error', hasError);

    if (hasError) {
        input.setAttribute('aria-invalid', 'true');
        input.classList.add('!border-rose-500', 'focus:!border-rose-500', 'focus:!ring-rose-500');
    } else {
        input.removeAttribute('aria-invalid');
        input.classList.remove('!border-rose-500', 'focus:!border-rose-500', 'focus:!ring-rose-500');
    }
}

// Auto-initialize on load and Livewire navigation
document.addEventListener('DOMContentLoaded', () => {
    initDatePickers();
    initAutoDismissAlerts();
});
document.addEventListener('livewire:navigated', () => {
    initDatePickers();
    initAutoDismissAlerts();
    initNotificationBell();
});

export function initAutoDismissAlerts() {
    document.querySelectorAll('.class-alert--success').forEach((alert) => {
        if (alert.dataset.dismissInitialized === 'true') {
            return;
        }

        alert.dataset.dismissInitialized = 'true';
        const parsedDelay = Number.parseInt(alert.dataset.dismissTimeout || '4500', 10);
        const delay = Number.isFinite(parsedDelay) ? parsedDelay : 4500;

        window.setTimeout(() => {
            alert.classList.add('is-dismissing');
            window.setTimeout(() => alert.remove(), 260);
        }, delay);
    });
}

export function initAjaxLogout({
    formEl,
    redirectTo = '/signin',
    tokenStorageKey = 'api_token',
} = {}) {
    if (!formEl) return;
    if (formEl.dataset.ajaxLogoutInitialized === 'true') return;
    formEl.dataset.ajaxLogoutInitialized = 'true';

    formEl.addEventListener('submit', async (e) => {
        e.preventDefault();

        const message = 'Are you sure you want to log out?';
        let confirmed = false;
        if (typeof window.showCustomConfirm === 'function') {
            confirmed = await window.showCustomConfirm(message, 'warning', 'Confirm Logout');
        } else if (typeof showCustomConfirm === 'function') {
            // eslint-disable-next-line no-undef
            confirmed = await showCustomConfirm(message, 'warning', 'Confirm Logout');
        } else {
            confirmed = window.confirm(message);
        }

        if (!confirmed) return;

        try {
            const response = await fetch(formEl.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': formEl.querySelector('input[name="_token"]')?.value,
                    Accept: 'application/json',
                },
            });

            if (response.ok) {
                localStorage.removeItem(tokenStorageKey);
                window.location.href = redirectTo;
            }
        } catch (error) {
            console.error('Logout error:', error);
        }
    });
}

export function smartRenderMath(element, options = {}) {
    if (typeof window.smartRenderMath === 'function' && window.smartRenderMath !== smartRenderMath) {
        return window.smartRenderMath(element, options);
    }
    return Promise.resolve();
}

export function getOrCreateAlertModal() {
    let modal = document.getElementById('customAlertModal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'customAlertModal';
    modal.className = 'custom-alert-modal hidden';
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
                will-change: opacity;
            }
            .custom-alert-modal.hidden { display: none !important; opacity: 0; }
            .custom-alert-backdrop {
                position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(8, 12, 21, 0.7);
            }
            .custom-alert-box {
                position: relative; background: #111827; border-radius: 16px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                width: 90%; max-width: 440px; padding: 28px; border: 1px solid rgba(255, 255, 255, 0.08);
                display: flex; flex-direction: column; align-items: center; text-align: center;
                transform: scale(1); transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1); z-index: 1;
                will-change: transform;
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
            .custom-alert-title { font-size: 1.2rem; font-weight: 700; color: #f8fafc; margin-bottom: 10px; font-family: system-ui, -apple-system, sans-serif; }
            .custom-alert-message { font-size: 0.95rem; color: #94a3b8; line-height: 1.6; margin: 0; font-family: system-ui, -apple-system, sans-serif; }
            .custom-alert-input {
                width: 100%; margin-top: 16px; padding: 10px 14px; background: #1e293b; color: #ffffff;
                border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 8px; font-size: 0.95rem; outline: none;
                transition: all 0.2s ease; font-family: system-ui, -apple-system, sans-serif;
            }
            .custom-alert-input:focus {
                border-color: var(--color-brand); box-shadow: 0 0 0 2px rgba(50, 77, 199, 0.25); background: #1e293b;
            }
            .custom-alert-actions { display: flex; gap: 12px; width: 100%; justify-content: center; }
            .custom-alert-btn { flex: 1; max-width: 160px; padding: 10px 18px; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.15s ease; border: none; outline: none; display: inline-flex; align-items: center; justify-content: center; }
            .custom-alert-btn.btn-primary { background: linear-gradient(135deg, var(--color-brand) 0%, var(--color-brand-hover) 100%); color: #ffffff; box-shadow: 0 4px 10px rgba(50, 77, 199, 0.3); }
            .custom-alert-btn.btn-primary:hover { background: linear-gradient(135deg, var(--color-brand-hover) 0%, var(--color-brand) 100%); transform: translateY(-1px); box-shadow: 0 6px 12px rgba(50, 77, 199, 0.4); }
            .custom-alert-btn.btn-secondary { background-color: #1e293b; color: #e2e8f0; border: 1px solid rgba(255, 255, 255, 0.08); }
            .custom-alert-btn.btn-secondary:hover { background-color: #334155; color: #ffffff; transform: translateY(-1px); }
        `;
        document.head.appendChild(style);
    }

    document.body.appendChild(modal);
    return modal;
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
        msgEl.textContent = message;

        cancelBtn.classList.remove('hidden');
        cancelBtn.textContent = 'Cancel';
        confirmBtn.className = 'custom-alert-btn btn-primary';
        confirmBtn.textContent = 'Confirm';

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

        modal.classList.remove('hidden');

        const handleConfirm = () => {
            cleanup();
            resolve(true);
        };

        const handleCancel = () => {
            cleanup();
            resolve(false);
        };

        const cleanup = () => {
            modal.classList.add('hidden');
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', handleCancel);
        };

        confirmBtn.addEventListener('click', handleConfirm);
        cancelBtn.addEventListener('click', handleCancel);
    });
}

export function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text).catch(() => fallbackCopy(text));
    }
    return Promise.resolve(fallbackCopy(text));

    function fallbackCopy(val) {
        const scratch = document.createElement('textarea');
        scratch.value = val;
        scratch.setAttribute('readonly', '');
        scratch.style.position = 'fixed';
        scratch.style.top = '-9999px';
        scratch.style.left = '-9999px';
        document.body.appendChild(scratch);
        scratch.select();
        let ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (e) {
            ok = false;
        }
        scratch.remove();
        return ok;
    }
}

window.initAjaxLogout = initAjaxLogout;
if (!window.smartRenderMath) {
    window.smartRenderMath = smartRenderMath;
}
window.showCustomConfirm = showCustomConfirm;
window.copyToClipboard = copyToClipboard;

export function initScoreDetailsPage() {
    // ── Elements & Data ─────────────────────────────────────────
    const statsData = JSON.parse(
        document.getElementById('sd-stats-data')?.textContent ?? '{}'
    );
    const questionDataMap = JSON.parse(
        document.getElementById('score-question-data')?.textContent ?? '{}'
    );

    const rootEl = document.getElementById('scores-report-root');
    const modalEl = document.getElementById('reviewModal');
    const isTeacher = Boolean(
        rootEl?.dataset?.isTeacher === 'true' ||
        rootEl?.getAttribute('data-is-teacher') === 'true' ||
        modalEl?.dataset?.isTeacher === 'true' ||
        modalEl?.getAttribute('data-is-teacher') === 'true' ||
        document.querySelector('[data-is-teacher="true"]') !== null ||
        document.body?.dataset?.isTeacher === 'true'
    );

    const updateTeacherOverrideDisplay = (itemData) => {
        const teacherOverrideEl = document.getElementById('modalTeacherOverride');
        const teacherOverrideVal = document.getElementById('modalTeacherOverrideVal');
        const labelEl = document.getElementById('modalTeacherOverrideLabel') || teacherOverrideEl?.querySelector('span:first-child');
        const errorTypeSelect = document.getElementById('modalErrorType');

        if (!teacherOverrideEl) return;

        const counterpartType = isTeacher ? itemData.student_error_type : itemData.teacher_error_type;
        const hasCounterpart = counterpartType && counterpartType !== 'unclassified';

        if (hasCounterpart) {
            teacherOverrideEl.hidden = false;
            if (labelEl) {
                labelEl.textContent = isTeacher ? 'Student classification:' : 'Teacher override:';
            }
            if (teacherOverrideVal) {
                const opt = errorTypeSelect?.querySelector(`option[value="${counterpartType}"]`);
                teacherOverrideVal.textContent = opt ? opt.textContent : counterpartType;
            }
        } else {
            teacherOverrideEl.hidden = true;
            if (teacherOverrideVal) {
                teacherOverrideVal.textContent = '';
            }
        }
    };

    // ── URL & State Resolution ──────────────────────────────────
    const urlParams = new URLSearchParams(window.location.search);
    let activeSection = ['all', 'rw', 'math'].includes(urlParams.get('subject'))
        ? urlParams.get('subject')
        : 'all';
    let activeStatusFilter = ['all', 'correct', 'wrong', 'omitted'].includes(urlParams.get('result'))
        ? urlParams.get('result')
        : 'all';
    let currentPage = Math.max(1, parseInt(urlParams.get('page'), 10) || 1);
    const pageSize = 30;

    const syncUrlState = () => {
        const url = new URL(window.location.href);
        if (activeSection !== 'all') url.searchParams.set('subject', activeSection);
        else url.searchParams.delete('subject');

        if (activeStatusFilter !== 'all') url.searchParams.set('result', activeStatusFilter);
        else url.searchParams.delete('result');

        if (currentPage > 1) url.searchParams.set('page', String(currentPage));
        else url.searchParams.delete('page');

        window.history.replaceState(null, '', url.toString());
    };

    const getQuestionRows = () => Array.from(
        document.querySelectorAll('#table-main tbody tr[data-section][data-status]')
    );

    const getMatchingRows = () => getQuestionRows().filter(row => {
        const secOk = activeSection === 'all' || row.dataset.section === activeSection;
        const statusOk = activeStatusFilter === 'all' || row.dataset.status === activeStatusFilter;
        return secOk && statusOk;
    });

    const getFilteredAnswerIds = () => {
        return getMatchingRows().map(r => r.dataset.answerId).filter(Boolean);
    };

    function renderTablePagination(matchingRows) {
        const pagination = document.querySelector('[data-table-pagination="table-main"]');
        if (!pagination) return;

        const infoEl = pagination.querySelector('[data-page-info]');
        const pageNumbersEl = pagination.querySelector('[data-page-numbers]');
        const prevBtn = pagination.querySelector('[data-page-action="prev"]');
        const nextBtn = pagination.querySelector('[data-page-action="next"]');
        const totalRows = matchingRows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / pageSize));
        currentPage = Math.min(Math.max(1, currentPage), totalPages);
        const start = totalRows === 0 ? 0 : ((currentPage - 1) * pageSize) + 1;
        const end = Math.min(currentPage * pageSize, totalRows);

        if (infoEl) {
            infoEl.textContent = totalRows === 0
                ? 'No questions to show'
                : `Showing ${start}-${end} of ${totalRows}`;
        }

        if (prevBtn) prevBtn.disabled = currentPage <= 1;
        if (nextBtn) nextBtn.disabled = currentPage >= totalPages;

        if (!pageNumbersEl) return;
        pageNumbersEl.innerHTML = '';

        const visiblePages = new Set([1, totalPages, currentPage - 1, currentPage, currentPage + 1]);
        let previousPage = 0;

        Array.from(visiblePages)
            .filter(page => page >= 1 && page <= totalPages)
            .sort((a, b) => a - b)
            .forEach(page => {
                if (previousPage && page - previousPage > 1) {
                    const gap = document.createElement('span');
                    gap.className = 'sd-page-gap';
                    gap.textContent = '...';
                    pageNumbersEl.appendChild(gap);
                }

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'sd-page-number';
                btn.dataset.page = String(page);
                btn.textContent = String(page);
                btn.classList.toggle('active', page === currentPage);
                pageNumbersEl.appendChild(btn);
                previousPage = page;
            });
    }

    function applyFilters({ resetPage = false } = {}) {
        if (resetPage) currentPage = 1;

        // 1. Sync subject filter UI chips & aria-pressed state
        document.querySelectorAll('.sd-tab[data-subject]').forEach(tab => {
            const isActive = tab.getAttribute('data-subject') === activeSection;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        // 2. Sync result filter buttons & aria-pressed state
        document.querySelectorAll('.sd-view-btn[data-filter]').forEach(b => {
            const isActive = b.dataset.filter === activeStatusFilter;
            b.classList.toggle('active', isActive);
            b.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        // 3. Domain groups scoped to subject
        document.querySelectorAll('.sd-domain-group').forEach(g => {
            g.style.display = (activeSection === 'all' || g.dataset.section === activeSection) ? '' : 'none';
        });

        // 4. Difficulty breakdowns scoped to subject
        document.querySelectorAll('.sd-difficulty-group').forEach(dg => {
            dg.style.display = (dg.dataset.difficultySection === activeSection) ? '' : 'none';
        });

        // 5. Timeline sections scoped to subject
        document.querySelectorAll('.sd-timeline-section-row').forEach(tr => {
            tr.style.display = (activeSection === 'all' || tr.dataset.timelineSection === activeSection) ? '' : 'none';
        });

        // 6. Timeline nodes dim/highlight by result filter
        document.querySelectorAll('.sd-timeline-node-wrapper').forEach(node => {
            const secOk = activeSection === 'all' || node.dataset.section === activeSection;
            const statusOk = activeStatusFilter === 'all' || node.dataset.status === activeStatusFilter;
            const isVisible = secOk && statusOk;
            node.style.opacity = isVisible ? '1' : '0.25';
            node.style.pointerEvents = isVisible ? '' : 'none';
            node.inert = !isVisible;
            const btn = node.querySelector('button');
            if (btn) {
                btn.disabled = !isVisible;
                btn.tabIndex = isVisible ? 0 : -1;
            }
            if (isVisible) {
                node.removeAttribute('aria-hidden');
            } else {
                node.setAttribute('aria-hidden', 'true');
            }
        });

        // 7. Table rows
        const rows = getQuestionRows();
        const matchingRows = getMatchingRows();
        const totalPages = Math.max(1, Math.ceil(matchingRows.length / pageSize));
        currentPage = Math.min(currentPage, totalPages);
        const startIndex = (currentPage - 1) * pageSize;
        const visibleRows = new Set(matchingRows.slice(startIndex, startIndex + pageSize));

        rows.forEach(row => {
            row.style.display = visibleRows.has(row) ? '' : 'none';
        });

        document.querySelectorAll('.sd-no-results-row').forEach(row => {
            row.hidden = matchingRows.length > 0;
        });

        renderTablePagination(matchingRows);

        // 8. Update stat counters for current subject scope
        const s = statsData[activeSection] ?? statsData['all'] ?? {};
        if (document.getElementById('stat-total')) document.getElementById('stat-total').textContent = s.total ?? '0';
        if (document.getElementById('stat-correct')) document.getElementById('stat-correct').textContent = s.correct ?? '0';
        if (document.getElementById('stat-wrong')) document.getElementById('stat-wrong').textContent = s.wrong ?? '0';
        if (document.getElementById('stat-omitted')) document.getElementById('stat-omitted').textContent = s.omitted ?? '0';

        // 9. Update result filter count badges
        const allSecRows = rows.filter(r => activeSection === 'all' || r.dataset.section === activeSection);
        const cntAll = allSecRows.length;
        const cntWrong = allSecRows.filter(r => r.dataset.status === 'wrong').length;
        const cntOmitted = allSecRows.filter(r => r.dataset.status === 'omitted').length;
        const cntCorrect = allSecRows.filter(r => r.dataset.status === 'correct').length;

        const setCnt = (sel, val) => {
            const el = document.querySelector(sel);
            if (el) el.textContent = String(val);
        };
        setCnt('[data-count-filter="all"]', cntAll);
        setCnt('[data-count-filter="wrong"]', cntWrong);
        setCnt('[data-count-filter="omitted"]', cntOmitted);
        setCnt('[data-count-filter="correct"]', cntCorrect);

        syncUrlState();
    }

    // ── Subject Tab switching ───────────────────────────────────
    document.querySelectorAll('.sd-tab[data-subject]').forEach(btn => {
        btn.addEventListener('click', () => {
            activeSection = btn.getAttribute('data-subject');
            applyFilters({ resetPage: true });
        });
    });

    // ── Result filter buttons ───────────────────────────────────
    document.querySelectorAll('.sd-view-btn[data-filter]').forEach(btn => {
        btn.addEventListener('click', function () {
            activeStatusFilter = this.getAttribute('data-filter');
            applyFilters({ resetPage: true });
        });
    });

    // ── Clear filters button ────────────────────────────────────
    document.querySelectorAll('.sd-clear-filters-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            activeStatusFilter = 'all';
            applyFilters({ resetPage: true });
        });
    });

    // ── Table Pagination buttons ────────────────────────────────
    document.querySelectorAll('[data-table-pagination]').forEach(pagination => {
        pagination.addEventListener('click', e => {
            const button = e.target.closest('button');
            if (!button) return;

            if (button.dataset.pageAction === 'prev') {
                currentPage = Math.max(1, currentPage - 1);
            } else if (button.dataset.pageAction === 'next') {
                currentPage += 1;
            } else if (button.dataset.page) {
                currentPage = Number.parseInt(button.dataset.page, 10) || 1;
            } else {
                return;
            }

            applyFilters();
        });
    });

    // ── Show correct answers toggle ────────────────────────────
    document.querySelectorAll('.sd-correct-toggle').forEach(toggle => {
        toggle.addEventListener('change', function () {
            document.querySelectorAll('.correct-col').forEach(el => {
                el.style.display = this.checked ? '' : 'none';
            });
        });
    });

    // ── Anchor Navigation smooth scroll ────────────────────────
    const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;

    document.querySelectorAll('.sd-nav-link[data-anchor]').forEach(link => {
        link.addEventListener('click', e => {
            const anchor = link.getAttribute('data-anchor');
            const targetSection = document.getElementById(anchor);
            if (targetSection) {
                e.preventDefault();
                targetSection.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
                window.location.hash = anchor;
                document.querySelectorAll('.sd-nav-link').forEach(l => l.classList.toggle('active', l === link));
            }
        });
    });

    // Initial hash scroll
    if (window.location.hash) {
        const hashId = window.location.hash.replace(/^#/, '');
        const hashTarget = document.getElementById(hashId);
        if (hashTarget) {
            requestAnimationFrame(() => {
                hashTarget.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
            });
        }
    }

    // ── Score Explainer Popover Dismissal ──────────────────────
    const explainerDetails = document.querySelector('.sd-score-explainer-popover');
    if (explainerDetails) {
        document.addEventListener('click', (e) => {
            if (explainerDetails.hasAttribute('open') && !explainerDetails.contains(e.target)) {
                explainerDetails.removeAttribute('open');
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && explainerDetails.hasAttribute('open')) {
                explainerDetails.removeAttribute('open');
                explainerDetails.querySelector('summary')?.focus();
            }
        });
    }

    // ── Review Questions Action Button Navigation ──────────────
    const reviewBtn = document.getElementById('btn-goto-review');
    if (reviewBtn) {
        reviewBtn.addEventListener('click', (e) => {
            const target = document.getElementById('review');
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
                window.location.hash = 'review';
                document.querySelectorAll('.sd-nav-link').forEach(l => l.classList.toggle('active', l.getAttribute('data-anchor') === 'review'));
            }
        });
    }

    // ── Review Modal State & Lifecycle ─────────────────────────
    let currentModalAnswerId = null;
    const reviewModalEl = document.getElementById('reviewModal');

    const closeModal = () => {
        if (!reviewModalEl) return;
        window.dispatchEvent(new CustomEvent('close-modal', { detail: 'reviewModal' }));
    };

    window.addEventListener('close-modal', (e) => {
        if (e.detail !== 'reviewModal' || !reviewModalEl) return;
        reviewModalEl.classList.remove('is-open');
        setTimeout(() => {
            if (!reviewModalEl.classList.contains('is-open')) {
                reviewModalEl.classList.add('hidden');
            }
        }, 180);
    });

    const openReviewModal = (answerId) => {
        const data = questionDataMap[answerId];
        if (!data || !reviewModalEl) return;

        currentModalAnswerId = String(answerId);

        // Title and badges
        const titleEl = document.getElementById('modalQuestionTitle');
        if (titleEl) {
            titleEl.textContent = `Question ${data.idx}`;
        }
        const sectionBadge = document.getElementById('modalQuestionSectionBadge');
        if (sectionBadge) {
            sectionBadge.textContent = `${data.section_name}${data.module_number ? ' M' + data.module_number : ''}`;
            sectionBadge.className = `sd-section-pill ${data.section_type}`;
            sectionBadge.style.display = '';
        }
        const unscoredBadge = document.getElementById('modalUnscoredBadge');
        if (unscoredBadge) {
            unscoredBadge.style.display = data.is_pretest ? '' : 'none';
        }

        // Passage (if present)
        const passageBox = document.getElementById('modalPassageContainer');
        const passageContent = document.getElementById('modalPassageContent');
        if (passageBox && passageContent) {
            if (data.passage) {
                passageContent.innerHTML = data.passage;
                passageBox.style.display = '';
            } else {
                passageContent.innerHTML = '';
                passageBox.style.display = 'none';
            }
        }

        // Question stem
        const stemEl = document.getElementById('modalQuestionStem');
        if (stemEl) stemEl.innerHTML = data.stem ?? '';

        // Submitted vs Correct answer
        // SECURITY: Student answer rendered strictly as TEXT!
        const answerEl = document.getElementById('modalYourAnswer');
        const answerBox = document.getElementById('modalYourAnswerBox');
        if (answerEl) answerEl.textContent = data.your_answer ?? 'Omitted';
        if (answerBox) {
            answerBox.className = 'sd-modal-answer-box your-answer';
            if (data.status === 'correct') answerBox.classList.add('is-correct');
            else if (data.status === 'wrong') answerBox.classList.add('is-wrong');
            else answerBox.classList.add('is-omitted');
        }

        const correctEl = document.getElementById('modalCorrectAnswer');
        if (correctEl) correctEl.textContent = data.correct_answer ?? 'N/A';

        // Pacing text
        const pacingEl = document.getElementById('modalPacingText');
        if (pacingEl) {
            if (data.pacing) {
                const estText = data.is_fallback_expected ? ' (estimate)' : '';
                const timingStr = data.time_spent ? `${data.time_spent}s` : 'No timing data';
                pacingEl.innerHTML = `<strong>Pacing:</strong> ${timingStr}` +
                    (data.expected_time ? ` &middot; Expected ~${data.expected_time}s${estText}` : '') +
                    ` &middot; <span style="font-weight:700;">${data.pacing.label}</span>`;
            } else {
                pacingEl.textContent = 'No timing data recorded for this question.';
            }
        }

        // MCQ Choices with explicit accessible labels
        const mcLabel = document.querySelector('.js-mc-label');
        const mcList = document.querySelector('.js-mc-list');
        if (mcLabel) mcLabel.style.display = 'none';
        if (mcList) {
            mcList.style.display = 'none';
            mcList.innerHTML = '';
        }

        if (data.question_type === 'multiple_choice' && Array.isArray(data.choices) && data.choices.length > 0) {
            if (mcLabel) mcLabel.style.display = '';
            if (mcList) {
                mcList.style.display = 'flex';
                const fragment = document.createDocumentFragment();

                data.choices.forEach(choice => {
                    const isCorrectChoice = Boolean(choice.is_correct);
                    const isUserChoice = (data.your_answer === choice.label);

                    const choiceItem = document.createElement('div');
                    choiceItem.className = 'sd-modal-choice-item';

                    let badgesHtml = '';
                    if (isUserChoice && isCorrectChoice) {
                        choiceItem.classList.add('is-correct-choice');
                        badgesHtml = '<span class="sd-choice-text-badge correct">Your answer &middot; Correct</span>';
                    } else if (isUserChoice && !isCorrectChoice) {
                        choiceItem.classList.add('is-wrong-choice');
                        badgesHtml = '<span class="sd-choice-text-badge wrong">Your answer</span>';
                    } else if (isCorrectChoice) {
                        choiceItem.classList.add('is-correct-choice');
                        badgesHtml = '<span class="sd-choice-text-badge correct">Correct answer</span>';
                    }

                    choiceItem.innerHTML = `
                        <div style="display: flex; align-items: flex-start; gap: 0.6rem; width: 100%;">
                            <span class="sd-choice-letter" aria-hidden="true">${choice.label}</span>
                            <div style="flex: 1;">
                                <div class="sd-choice-text">${choice.content}</div>
                                ${badgesHtml ? `<div style="margin-top: 0.35rem;">${badgesHtml}</div>` : ''}
                            </div>
                        </div>
                    `;
                    fragment.appendChild(choiceItem);
                });
                mcList.appendChild(fragment);
            }
        }

        // Explanation (honest unavailable message if missing)
        const explEl = document.getElementById('modalExplanation');
        if (explEl) {
            if (data.has_explanation && data.explanation) {
                explEl.innerHTML = data.explanation;
                explEl.style.color = '';
                explEl.style.fontStyle = '';
            } else {
                explEl.textContent = 'No explanation is available for this practice question.';
                explEl.style.color = 'var(--ds-text-tertiary, #64748b)';
                explEl.style.fontStyle = 'italic';
            }
        }

        // Mistake reflection panel
        const errorPanel = document.getElementById('modalErrorReviewPanel');
        const errorType = document.getElementById('modalErrorType');
        const errorStatus = document.getElementById('modalErrorReviewStatus');
        const retryBtn = document.getElementById('modalRetrySaveBtn');

        if (errorPanel && errorType) {
            const reviewable = Boolean(data.reviewable && data.review_url);
            errorPanel.hidden = !reviewable;
            if (reviewable) {
                updateTeacherOverrideDisplay(data);

                const activeSaved = (isTeacher ? data.teacher_error_type : data.student_error_type) ?? '';
                const displayVal = (data.pendingErrorType !== undefined && data.pendingErrorType !== null)
                    ? data.pendingErrorType
                    : (activeSaved === 'unclassified' ? '' : activeSaved);

                errorType.value = displayVal;
                errorType.disabled = Boolean(data.isSavingClassification);

                if (data.isSavingClassification) {
                    if (retryBtn) retryBtn.style.display = 'none';
                    if (errorStatus) {
                        errorStatus.textContent = 'Saving…';
                        errorStatus.style.color = 'var(--ds-text-tertiary, #64748b)';
                    }
                } else if (data.classificationError) {
                    if (retryBtn) retryBtn.style.display = '';
                    if (errorStatus) {
                        errorStatus.textContent = data.classificationError;
                        errorStatus.style.color = 'var(--ds-danger-text, #b91c1c)';
                    }
                } else if (data.classificationSuccess) {
                    if (retryBtn) retryBtn.style.display = 'none';
                    if (errorStatus) {
                        errorStatus.textContent = data.classificationSuccess;
                        errorStatus.style.color = 'var(--ds-progress-strong, #15803d)';
                    }
                } else {
                    if (retryBtn) retryBtn.style.display = 'none';
                    if (errorStatus) {
                        errorStatus.textContent = '';
                        errorStatus.style.color = 'var(--ds-text-tertiary, #64748b)';
                    }
                }
            }
        }

        // Traversal buttons (Previous / Next in filtered set)
        const filteredIds = getFilteredAnswerIds();
        const currIdx = filteredIds.indexOf(String(answerId));
        const prevBtn = document.getElementById('modalPrevBtn');
        const nextBtn = document.getElementById('modalNextBtn');
        const counterEl = document.getElementById('modalTraversalCounter');

        if (prevBtn) prevBtn.disabled = currIdx <= 0;
        if (nextBtn) nextBtn.disabled = currIdx < 0 || currIdx >= filteredIds.length - 1;
        if (counterEl) {
            counterEl.textContent = currIdx >= 0
                ? `${currIdx + 1} of ${filteredIds.length}`
                : '';
        }

        // Open dialog using shared dialogManager
        if (!reviewModalEl.classList.contains('is-open')) {
            reviewModalEl.classList.remove('hidden');
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'reviewModal' }));
            requestAnimationFrame(() => {
                reviewModalEl.classList.add('is-open');
            });
        }

        // Math KaTeX rendering: render immediately without artificial delay
        if (window.smartRenderMath && reviewModalEl) {
            window.smartRenderMath(reviewModalEl);
        }
    };

    // ── Save Classification with response isolation & retry ───
    async function saveClassification(answerId, errorTypeValue) {
        const data = questionDataMap[answerId];
        if (!data || !data.review_url) return;

        data.pendingErrorType = errorTypeValue;
        data.isSavingClassification = true;
        data.classificationError = null;
        data.classificationSuccess = null;

        const errorTypeSelect = document.getElementById('modalErrorType');
        const status = document.getElementById('modalErrorReviewStatus');
        const retryBtn = document.getElementById('modalRetrySaveBtn');

        if (currentModalAnswerId === String(answerId)) {
            if (errorTypeSelect) {
                errorTypeSelect.value = errorTypeValue || '';
                errorTypeSelect.disabled = true;
            }
            if (retryBtn) retryBtn.style.display = 'none';
            if (status) {
                status.textContent = 'Saving…';
                status.style.color = 'var(--ds-text-tertiary, #64748b)';
            }
        }

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(data.review_url, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
                body: JSON.stringify({ error_type: errorTypeValue || null }),
            });

            if (!response.ok) {
                const errJson = await response.json().catch(() => null);
                throw new Error(errJson?.message || 'Could not save review classification.');
            }

            const saved = await response.json();
            // Bind strictly to originating answerId
            data.isSavingClassification = false;
            data.pendingErrorType = null;
            data.classificationError = null;
            data.classificationSuccess = 'Classification saved.';
            data.effective_error_type = saved.effective_error_type;
            data.student_error_type = saved.student_error_type;
            data.teacher_error_type = saved.teacher_error_type;

            if (currentModalAnswerId === String(answerId)) {
                if (errorTypeSelect) {
                    const val = (isTeacher ? data.teacher_error_type : data.student_error_type) ?? '';
                    errorTypeSelect.value = val === 'unclassified' ? '' : val;
                    errorTypeSelect.disabled = false;
                }
                updateTeacherOverrideDisplay(data);
                if (retryBtn) retryBtn.style.display = 'none';
                if (status) {
                    status.textContent = data.classificationSuccess;
                    status.style.color = 'var(--ds-progress-strong, #15803d)';
                }
            }
        } catch (error) {
            data.isSavingClassification = false;
            data.classificationError = error.message || 'Could not save review classification.';
            if (currentModalAnswerId === String(answerId)) {
                if (errorTypeSelect) errorTypeSelect.disabled = false;
                if (status) {
                    status.textContent = data.classificationError;
                    status.style.color = 'var(--ds-danger-text, #b91c1c)';
                }
                if (retryBtn) {
                    retryBtn.style.display = '';
                }
            }
        }
    }

    // ── Review button event delegation ──────────────────────────
    if (!window._scoreReviewModalBound) {
        window._scoreReviewModalBound = true;
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.js-review-btn');
            if (btn) {
                if (btn.disabled) return;
                const answerId = btn.getAttribute('data-answer-id');
                if (answerId) {
                    openReviewModal(answerId);
                }
            }
        });
    }

    // Modal Traversal click listeners
    document.getElementById('modalPrevBtn')?.addEventListener('click', () => {
        const filteredIds = getFilteredAnswerIds();
        const currIdx = filteredIds.indexOf(String(currentModalAnswerId));
        if (currIdx > 0) {
            openReviewModal(filteredIds[currIdx - 1]);
        }
    });

    document.getElementById('modalNextBtn')?.addEventListener('click', () => {
        const filteredIds = getFilteredAnswerIds();
        const currIdx = filteredIds.indexOf(String(currentModalAnswerId));
        if (currIdx >= 0 && currIdx < filteredIds.length - 1) {
            openReviewModal(filteredIds[currIdx + 1]);
        }
    });

    document.getElementById('modalRetrySaveBtn')?.addEventListener('click', () => {
        if (!currentModalAnswerId) return;
        const data = questionDataMap[currentModalAnswerId];
        const val = data?.pendingErrorType ?? document.getElementById('modalErrorType')?.value ?? '';
        saveClassification(currentModalAnswerId, val);
    });

    document.getElementById('modalErrorType')?.addEventListener('change', function () {
        if (!currentModalAnswerId) return;
        saveClassification(currentModalAnswerId, this.value);
    });

    // Close listeners
    document.getElementById('reviewModalCloseBtn')?.addEventListener('click', closeModal);
    document.getElementById('reviewModalCloseBtn2')?.addEventListener('click', closeModal);
    reviewModalEl?.addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });

    applyFilters({ resetPage: false });
}
window.initScoreDetailsPage = initScoreDetailsPage;

export function initLandingPage() {
    const revealEls = document.querySelectorAll('main section, footer');

    revealEls.forEach(el => {
        el.classList.add('landing-reveal');
    });

    if (!('IntersectionObserver' in window)) {
        revealEls.forEach(el => el.classList.add('is-visible'));
        return;
    }

    const observerOptions = {
        root: null,
        threshold: 0.1,
        rootMargin: '0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    revealEls.forEach(el => {
        observer.observe(el);
    });
}
window.initLandingPage = initLandingPage;

// Global form submit confirmation interceptor using custom confirm modals
document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (form.hasAttribute('data-confirm')) {
        if (form.dataset.confirmed === 'true') {
            return;
        }

        e.preventDefault();

        const message = form.getAttribute('data-confirm');
        let confirmed = false;

        if (typeof window.showCustomConfirm === 'function') {
            confirmed = await window.showCustomConfirm(message, 'warning', 'Confirm Action');
        } else {
            confirmed = window.confirm(message);
        }

        if (confirmed) {
            form.dataset.confirmed = 'true';
            form.submit();
        }
    }
});
