import { TEST_DASHBOARD_TAB_KEY, SECTIONS_STORE_URL, MODULES_STORE_URL } from './core/config.js';
import './core/examples.js';
import { showAlert, showCustomConfirm } from './utils/custom-alert.js';
import { captureTomSelectPreservation, initTomSelectOn } from './utils/tomselect.js';
import { loadHeavyDependencies } from './utils/script-loader.js';
import { removeMediaFromEditModal } from './ui/editors.js';
import { initModulesSearch } from './components/modules.js';
import { initRemoteQuestionPicker } from './components/questions.js';
import {
    addBuilderBlock, clearBuilderWorkspace, fetchModuleQuestions, submitBuilderQuestions,
    handleClearBuilder, restoreBuilderDraft, clearUnchangedQuestions
} from './components/builder.js';
import { initQuickAuthorWizard } from './components/wizard.js';
import * as BulkImport from './components/bulk-import.js';
import './ui/sidebar.js';
import { rememberTestDashboardTab, renderActiveTab, refreshQuestionsTableOnly, refreshTestDashboardData } from './dashboard-data.js';
import { initTestDashboardDelegatedActions } from './dashboard-crud-actions.js';
import { initRecycleBin } from './components/recycle-bin.js';
import { initEditQuestionModal } from './edit-question-modal.js';
import { initBladeGlobalHelpers } from './blade-globals.js';

document.addEventListener('DOMContentLoaded', async function () {
    await loadHeavyDependencies();

    // Expose to window for global access
    window.refreshTestDashboardData = async function (preserve = null) {
        rememberTestDashboardTab();
        return refreshTestDashboardData(preserve || captureTomSelectPreservation(null));
    };
    window.removeMediaFromEditModal = removeMediaFromEditModal;
    window.addBuilderBlock = addBuilderBlock;
    window.showCustomConfirm = showCustomConfirm;

    initBladeGlobalHelpers();
    initEditQuestionModal();

    // Generic form setup
    function setupForm(formId, url) {
        const form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (response.ok) {
                    showAlert('success', result.message || 'Created successfully!');
                    const preserve = captureTomSelectPreservation(this);
                    this.reset();
                    await refreshTestDashboardData(preserve);

                    // Auto-close offcanvas if form is inside one
                    const offcanvas = this.closest('[id$="Offcanvas"]');
                    if (offcanvas) {
                        window.dispatchEvent(new CustomEvent('close-offcanvas', { detail: offcanvas.id }));
                    }
                } else {
                    let msg = result.message || 'Validation failed';
                    if (result.errors) msg = Object.values(result.errors).flat().join(' ');
                    showAlert('danger', msg);
                }
            } catch (error) { showAlert('danger', 'Network error: ' + error.message); }
        });
    }

    setupForm('sectionForm', SECTIONS_STORE_URL);
    setupForm('moduleForm', MODULES_STORE_URL);

    // Initializations
    initTestDashboardDelegatedActions();
    initRecycleBin();
    initModulesSearch();

    document.querySelectorAll('#dashboardTabs .sidebar-link, #dashboardTabs .sidebar-link-builder').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const target = btn.getAttribute('data-bs-target');
            if (target) {
                // Immediately hide tables to prevent any visual snap or data jumping
                const testsTable = document.getElementById('testsTabulatorTable');
                if (testsTable) {
                    testsTable.classList.remove('opacity-100');
                    testsTable.classList.add('opacity-0');
                }
                const sectionsTable = document.getElementById('sectionsTabulatorTable');
                if (sectionsTable) {
                    sectionsTable.classList.remove('opacity-100');
                    sectionsTable.classList.add('opacity-0');
                }

                sessionStorage.setItem(TEST_DASHBOARD_TAB_KEY, target);
                requestAnimationFrame(() => {
                    setTimeout(() => {
                        renderActiveTab(target);
                    }, 50);
                });
            }
        });
    });

    const tomSelectEls = Array.from(document.querySelectorAll('.tom-select')).filter(el => !el.classList.contains('tom-select-remote-question'));
    function initTomSelectBatch(index = 0) {
        if (index >= tomSelectEls.length) return;
        const batchSize = 5;
        for (let i = 0; i < batchSize && index + i < tomSelectEls.length; i++) {
            initTomSelectOn(tomSelectEls[index + i]);
        }
        setTimeout(() => initTomSelectBatch(index + batchSize), 0);
    }
    initTomSelectBatch();

    initRemoteQuestionPicker('answerQuestionId', '');
    initRemoteQuestionPicker('explanationQuestionId', '');

    document.getElementById('addBuilderBlockBtn')?.addEventListener('click', addBuilderBlock);

    // Clear All button
    document.getElementById('clearBuilderBtn')?.addEventListener('click', handleClearBuilder);

    // Clear Unchanged button
    document.getElementById('clearUnchangedBtn')?.addEventListener('click', clearUnchangedQuestions);

    // Save All button
    document.getElementById('submitBuilderBtn')?.addEventListener('click', submitBuilderQuestions);

    document.getElementById('builderPreviewToggle')?.addEventListener('click', event => {
        const preview = document.querySelector('#builderMainGrid .live-preview-drawer');
        if (!preview) return;
        const open = preview.classList.toggle('is-open');
        event.currentTarget.setAttribute('aria-expanded', String(open));
        event.currentTarget.querySelector('span').textContent = open ? 'Hide preview' : 'Show preview';
        if (open) {
            const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
            requestAnimationFrame(() => preview.scrollIntoView({ behavior, block: 'nearest' }));
        }
    });

    // Dismiss tips banner
    document.getElementById('builderDismissInstructionsBtn')?.addEventListener('click', function () {
        localStorage.setItem('test_builder_instructions_dismissed', 'true');
        const banner = this.closest('.bg-brand-soft');
        if (banner) {
            banner.remove();
        }
    });

    // Module ID select change handler
    document.getElementById('builderModuleId')?.addEventListener('change', async (e) => {
        const moduleId = e.target.value;
        clearBuilderWorkspace();
        if (moduleId) {
            await fetchModuleQuestions(moduleId);
        }
    });

    initQuickAuthorWizard({
        onCreated: async test => {
            const sections = [...(test.sections || [])].sort((a, b) => (a.order || 0) - (b.order || 0));
            const firstModule = sections
                .flatMap(section => [...(section.modules || [])].sort((a, b) => (a.order || 0) - (b.order || 0)))
                .find(Boolean);

            if (!firstModule) throw new Error('Draft created, but no module was returned. Open it from Practice Tests.');

            await refreshTestDashboardData({ builderModuleId: String(firstModule.id) });
            const builderTab = document.getElementById('builder-tab');
            builderTab?.click();
            sessionStorage.setItem(TEST_DASHBOARD_TAB_KEY, '#builder');

            const moduleSelect = document.getElementById('builderModuleId');
            if (moduleSelect?.tomselect) {
                moduleSelect.tomselect.setValue(String(firstModule.id), true);
            } else if (moduleSelect) {
                moduleSelect.value = String(firstModule.id);
            }

            clearBuilderWorkspace();
            await fetchModuleQuestions(firstModule.id);
            return document.getElementById('addBuilderBlockBtn');
        },
    });
    BulkImport.initBulkImport();

    // Question Bank Filters Event Listeners
    const applyFilterBtn = document.getElementById('questionsTableFilterBtn');
    const clearFilterBtn = document.getElementById('questionsTableFilterClearBtn');

    if (applyFilterBtn) {
        applyFilterBtn.addEventListener('click', async function () {
            window.__tdQuestionsQuery = document.getElementById('questionsTableFilter')?.value || '';
            window.__tdQuestionsSection = document.getElementById('questionsTableSectionFilter')?.value || '';
            window.__tdQuestionsStatus = document.getElementById('questionsTableStatusFilter')?.value || '';

            const moduleFilterEl = document.getElementById('questionsTableModuleFilter');
            if (moduleFilterEl) {
                window.__tdQuestionsModule = moduleFilterEl.tomselect ? moduleFilterEl.tomselect.getValue() : moduleFilterEl.value;
            } else {
                window.__tdQuestionsModule = '';
            }

            window.__tdQuestionsPage = 1;
            try {
                await refreshQuestionsTableOnly();
            } catch (err) {
                showAlert('danger', 'Filter failed: ' + err.message);
            }
        });
    }

    if (clearFilterBtn) {
        clearFilterBtn.addEventListener('click', async function () {
            const filterInput = document.getElementById('questionsTableFilter');
            const secFilter = document.getElementById('questionsTableSectionFilter');
            const statusFilter = document.getElementById('questionsTableStatusFilter');
            const modFilter = document.getElementById('questionsTableModuleFilter');

            if (filterInput) filterInput.value = '';
            if (secFilter) secFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            if (modFilter) {
                if (modFilter.tomselect) modFilter.tomselect.setValue('', true);
                else modFilter.value = '';
            }

            window.__tdQuestionsQuery = '';
            window.__tdQuestionsSection = '';
            window.__tdQuestionsStatus = '';
            window.__tdQuestionsModule = '';
            window.__tdQuestionsPage = 1;

            try {
                await refreshQuestionsTableOnly();
            } catch (err) {
                showAlert('danger', 'Reset failed: ' + err.message);
            }
        });
    }

    document.getElementById('questionsShowSharedToggle')?.addEventListener('change', async function () {
        window.__tdQuestionsPage = 1;
        try {
            await refreshQuestionsTableOnly();
        } catch (err) {
            showAlert('danger', 'Filter failed: ' + err.message);
        }
    });

    // Global click handler to manage actions-dropdown menus
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-dropdown-trigger]');
        const openMenus = document.querySelectorAll('.actions-dropdown .dropdown-menu:not(.hidden)');

        if (trigger) {
            e.preventDefault();
            e.stopPropagation();
            const dropdown = trigger.closest('.actions-dropdown');
            const menu = dropdown?.querySelector('.dropdown-menu');

            openMenus.forEach(m => {
                if (m !== menu) m.classList.add('hidden');
            });

            if (menu) {
                const isOpening = menu.classList.contains('hidden');
                menu.classList.toggle('hidden');
                const isOpen = !menu.classList.contains('hidden');
                trigger.setAttribute('aria-expanded', String(isOpen));

                if (isOpen) {
                    const rect = trigger.getBoundingClientRect();
                    const menuHeight = menu.offsetHeight || 80;
                    const menuWidth = menu.offsetWidth || 140;

                    menu.style.position = 'fixed';
                    menu.style.right = 'auto';
                    menu.style.left = (rect.right - menuWidth) + 'px';

                    if (rect.bottom + menuHeight + 8 > window.innerHeight) {
                        menu.style.top = (rect.top - menuHeight - 4) + 'px';
                    } else {
                        menu.style.top = (rect.bottom + 4) + 'px';
                    }
                }
            }
        } else {
            openMenus.forEach(m => {
                m.classList.add('hidden');
                const trig = m.closest('.actions-dropdown')?.querySelector('[data-dropdown-trigger]');
                if (trig) trig.setAttribute('aria-expanded', 'false');
            });
        }
    });

    // Close actions-dropdown menus on scroll to keep fixed positioning aligned
    document.addEventListener('scroll', function () {
        const openMenus = document.querySelectorAll('.actions-dropdown .dropdown-menu:not(.hidden)');
        openMenus.forEach(menu => {
            menu.classList.add('hidden');
            const trigger = menu.closest('.actions-dropdown')?.querySelector('[data-dropdown-trigger]');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.actions-dropdown .dropdown-menu:not(.hidden)').forEach(menu => {
            menu.classList.add('hidden');
            const trigger = menu.closest('.actions-dropdown')?.querySelector('[data-dropdown-trigger]');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    });

    // Start data fetch
    refreshTestDashboardData(captureTomSelectPreservation(null))
        .then(() => {
            restoreBuilderDraft();

            // Check if builderModuleId has a value and load questions if empty
            const modSelect = document.getElementById('builderModuleId');
            if (modSelect && modSelect.tomselect) {
                const modId = modSelect.tomselect.getValue();
                if (modId && (!window.__builderExistingQuestions || window.__builderExistingQuestions.length === 0)) {
                    fetchModuleQuestions(modId);
                }
            }
        })
        .catch(err => console.error('Initial dashboard load failed:', err));
});
