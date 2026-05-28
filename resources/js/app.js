import './bootstrap';
import Alpine from 'alpinejs';

if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}



export function initDropdownToggle({
    triggerId = 'userDropdown',
    menuId = 'dropdownMenu',
    openClass = 'show',
} = {}) {
    const triggerEl = document.getElementById(triggerId);
    const menuEl = document.getElementById(menuId);

    if (!triggerEl || !menuEl) return { triggerEl: null, menuEl: null };

    const onTriggerClick = (e) => {
        e.stopPropagation();
        menuEl.classList.toggle(openClass);
    };

    const onDocumentClick = (e) => {
        if (!triggerEl.contains(e.target)) {
            menuEl.classList.remove(openClass);
        }
    };

    triggerEl.addEventListener('click', onTriggerClick);
    document.addEventListener('click', onDocumentClick);

    return {
        triggerEl,
        menuEl,
        destroy() {
            triggerEl.removeEventListener('click', onTriggerClick);
            document.removeEventListener('click', onDocumentClick);
        }
    };
}

export function initRadioToggleSection({
    activeRadioId,
    pastRadioId,
    activeContainerId,
    pastContainerId,
    activeLabelText = 'Active',
    pastLabelText = 'Past',
    checkedPrefix = '✓ ',
    hiddenClass = 'hidden',
} = {}) {
    const activeRadioEl = document.getElementById(activeRadioId);
    const pastRadioEl = document.getElementById(pastRadioId);
    const activeContainerEl = document.getElementById(activeContainerId);
    const pastContainerEl = document.getElementById(pastContainerId);
    const activeLabelEl = document.querySelector(`label[for="${activeRadioId}"]`);
    const pastLabelEl = document.querySelector(`label[for="${pastRadioId}"]`);

    if (!activeRadioEl || !pastRadioEl || !activeContainerEl || !pastContainerEl || !activeLabelEl || !pastLabelEl) {
        return;
    }

    const setActiveState = (isActive) => {
        activeContainerEl.classList.toggle(hiddenClass, !isActive);
        pastContainerEl.classList.toggle(hiddenClass, isActive);
        activeLabelEl.textContent = isActive ? `${checkedPrefix}${activeLabelText}` : activeLabelText;
        pastLabelEl.textContent = isActive ? pastLabelText : `${checkedPrefix}${pastLabelText}`;
    };

    activeRadioEl.addEventListener('change', () => setActiveState(true));
    pastRadioEl.addEventListener('change', () => setActiveState(false));
}

export function initAjaxLogout({
    formEl,
    redirectTo = '/',
    tokenStorageKey = 'api_token',
} = {}) {
    if (!formEl) return;

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

export function initHomeDashboardPage() {
    const { menuEl } = initDropdownToggle({
        triggerId: 'userDropdown',
        menuId: 'dropdownMenu',
        openClass: 'show',
    });

    const logoutForm = menuEl?.querySelector?.('form');
    initAjaxLogout({ formEl: logoutForm, redirectTo: '/', tokenStorageKey: 'api_token' });

    // Teacher-assigned tests toggle
    initRadioToggleSection({
        activeRadioId: 'btnradio1',
        pastRadioId: 'btnradio2',
        activeContainerId: 'active-tests',
        pastContainerId: 'past-tests',
    });

    // Practice tests toggle
    initRadioToggleSection({
        activeRadioId: 'btnradio3',
        pastRadioId: 'btnradio4',
        activeContainerId: 'practice-active',
        pastContainerId: 'practice-past',
    });
}

export function initPracticeDashboardPage() {
    const { menuEl } = initDropdownToggle({
        triggerId: 'userDropdown',
        menuId: 'dropdownMenu',
        openClass: 'show',
    });

    const logoutForm = menuEl?.querySelector?.('form');
    initAjaxLogout({ formEl: logoutForm, redirectTo: '/', tokenStorageKey: 'api_token' });
}

export function smartRenderMath(element, options = {}) {
    if (!window.renderMathInElement) return;

    const defaultOptions = {
        delimiters: [
            { left: '$$', right: '$$', display: false },
            { left: '\\\\[', right: '\\\\]', display: true },
        ],
        ignoredTags: ["script", "noscript", "style", "textarea", "pre", "code", "option"],
        throwOnError: false,
        trust: true,
        preProcess: (math) => math.trim()
    };

    const finalOptions = { ...defaultOptions, ...options };

    window.renderMathInElement(element || document.body, finalOptions);
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
                border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25); background: #1e293b;
            }
            .custom-alert-actions { display: flex; gap: 12px; width: 100%; justify-content: center; }
            .custom-alert-btn { flex: 1; max-width: 160px; padding: 10px 18px; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.15s ease; border: none; outline: none; display: inline-flex; align-items: center; justify-content: center; }
            .custom-alert-btn.btn-primary { background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); color: #ffffff; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
            .custom-alert-btn.btn-primary:hover { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); transform: translateY(-1px); box-shadow: 0 6px 12px rgba(79, 70, 229, 0.4); }
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

window.initDropdownToggle = initDropdownToggle;
window.initRadioToggleSection = initRadioToggleSection;
window.initAjaxLogout = initAjaxLogout;
window.initHomeDashboardPage = initHomeDashboardPage;
window.initPracticeDashboardPage = initPracticeDashboardPage;
window.smartRenderMath = smartRenderMath;
window.showCustomConfirm = showCustomConfirm;

export function initScoreDetailsPage() {
    // ── Sticky tabs bar ─────────────────────────────────────────
    const sentinel = document.getElementById('sd-tabs-sentinel');
    const tabsBar  = document.getElementById('sd-tabs-bar');
    if (sentinel && tabsBar) {
        new IntersectionObserver(
            ([entry]) => tabsBar.classList.toggle('is-sticky', !entry.isIntersecting),
            { threshold: 0 }
        ).observe(sentinel);
    }

    // ── Stats from embedded JSON ────────────────────────────────
    const statsData = JSON.parse(
        document.getElementById('sd-stats-data')?.textContent ?? '{}'
    );

    // ── State ───────────────────────────────────────────────────
    let activeSection      = 'all';
    let activeStatusFilter = 'all';

    function applyFilters() {
        // Show/hide domain groups
        document.querySelectorAll('.sd-domain-group').forEach(g => {
            g.style.display = (activeSection === 'all' || g.dataset.section === activeSection) ? '' : 'none';
        });

        // Filter table rows by both section AND status
        document.querySelectorAll('#table-main tbody tr').forEach(row => {
            const secOk    = activeSection === 'all' || row.dataset.section === activeSection;
            const statusOk = activeStatusFilter === 'all' || row.dataset.status === activeStatusFilter;
            row.style.display = (secOk && statusOk) ? '' : 'none';
        });

        // Show section column only on All tab
        document.querySelectorAll('.section-col').forEach(el => {
            el.style.display = activeSection === 'all' ? '' : 'none';
        });

        // Update stat counters
        const s = statsData[activeSection] ?? statsData['all'] ?? {};
        if (document.getElementById('stat-total'))   document.getElementById('stat-total').textContent   = s.total   ?? '';
        if (document.getElementById('stat-correct')) document.getElementById('stat-correct').textContent = s.correct ?? '';
        if (document.getElementById('stat-wrong'))   document.getElementById('stat-wrong').textContent   = s.wrong   ?? '';
    }

    // ── Tab switching ───────────────────────────────────────────
    document.querySelectorAll('#skillTabs .sd-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#skillTabs .sd-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeSection      = btn.getAttribute('data-target');
            activeStatusFilter = 'all';
            // Reset filter buttons
            document.querySelectorAll('.sd-view-btn[data-filter]').forEach(b => {
                b.classList.toggle('active', b.dataset.filter === 'all');
            });
            applyFilters();
        });
    });

    // ── Result filter buttons ───────────────────────────────────
    document.querySelectorAll('.sd-view-btn[data-filter]').forEach(btn => {
        btn.addEventListener('click', function () {
            const toolbar = this.closest('.sd-table-toolbar');
            toolbar.querySelectorAll('.sd-view-btn[data-filter]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            activeStatusFilter = this.getAttribute('data-filter');
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

    // ── Review modal ───────────────────────────────────────────
    const closeModal = () => {
        document.getElementById('reviewModal').classList.add('hidden');
        document.body.style.overflow = '';
    };

    document.querySelectorAll('.js-review-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            try {
                const data = JSON.parse(this.getAttribute('data-question'));
                document.getElementById('modalQuestionStem').innerHTML  = data.stem ?? '';
                document.getElementById('modalCorrectAnswer').innerHTML = data.correct_answer ?? 'N/A';
                document.getElementById('modalExplanation').innerHTML   = data.explanation ?? '';

                const answerEl  = document.getElementById('modalYourAnswer');
                const answerBox = document.getElementById('modalYourAnswerBox');
                answerEl.innerHTML = data.your_answer ?? 'Omitted';
                answerBox.className = 'sd-modal-answer-box your-answer';
                if (data.status === 'correct')    answerBox.classList.add('is-correct');
                else if (data.status === 'wrong') answerBox.classList.add('is-wrong');
                else                              answerBox.classList.add('is-omitted');

                // Dynamic Choice Review rendering
                const mcLabel = document.querySelector('.js-mc-label');
                const mcList = document.querySelector('.js-mc-list');
                if (mcLabel) mcLabel.style.display = 'none';
                if (mcList) {
                    mcList.style.display = 'none';
                    mcList.innerHTML = '';
                }

                if (data.question_type === 'multiple_choice' && data.choices && data.choices.length > 0) {
                    if (mcLabel) mcLabel.style.display = '';
                    if (mcList) {
                        mcList.style.display = 'flex';
                        data.choices.forEach(choice => {
                            const isCorrectChoice = choice.is_correct;
                            const isUserChoice = (data.your_answer === choice.label);

                            const choiceItem = document.createElement('div');
                            choiceItem.className = 'sd-modal-choice-item';
                            if (isCorrectChoice) {
                                choiceItem.classList.add('is-correct-choice');
                            } else if (isUserChoice && !isCorrectChoice) {
                                choiceItem.classList.add('is-wrong-choice');
                            }

                            choiceItem.innerHTML = `
                                <span class="sd-choice-letter">${choice.label}</span>
                                <span class="sd-choice-text">${choice.content}</span>
                            `;
                            mcList.appendChild(choiceItem);
                        });
                    }
                }

                document.getElementById('reviewModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';

                // Render LaTeX expressions in the modal
                if (window.smartRenderMath) {
                    window.smartRenderMath(document.getElementById('reviewModal'));
                }
            } catch (e) {
                console.error('Review modal error:', e);
            }
        });
    });

    document.getElementById('reviewModalCloseBtn')?.addEventListener('click', closeModal);
    document.getElementById('reviewModalCloseBtn2')?.addEventListener('click', closeModal);
    document.getElementById('reviewModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeModal();
    });
}
window.initScoreDetailsPage = initScoreDetailsPage;



