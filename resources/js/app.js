import './bootstrap';

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
    hiddenClass = 'd-none',
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

window.initDropdownToggle = initDropdownToggle;
window.initRadioToggleSection = initRadioToggleSection;
window.initAjaxLogout = initAjaxLogout;
window.initHomeDashboardPage = initHomeDashboardPage;
window.initPracticeDashboardPage = initPracticeDashboardPage;
window.smartRenderMath = smartRenderMath;

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



