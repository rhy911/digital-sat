import { 
    SKILL_DOMAINS, TEST_DASHBOARD_TAB_KEY, SNAPSHOT_URL, TESTS_STORE_URL, SECTIONS_STORE_URL, 
    MODULES_STORE_URL, SECTIONS_LINK_MODULE_URL, QUESTIONS_ATTACH_URL, BASE_URL, BULK_STORE_URL,
    CSV_BULK_URL, MEDIA_UPLOAD_URL
} from './core/config.js';
import './core/examples.js';
import { 
    showAlert, showCustomConfirm, getTomSelectValue, captureTomSelectPreservation,
    rebuildSectionTestTomSelect, rebuildModuleSectionTomSelect, rebuildQuestionModuleTomSelect,
    rebuildQuestionPassageTomSelect, rebuildLinkModuleTomSelect, initTomSelectOn,
    destroyTomSelectIfAny, optionExistsInSelect, humanizeUnderscores, capitalizeFirstLetter
} from './utils/helpers.js';
import { 
    initEditModalEditors, refreshEditMediaList, debouncedEditQuestionPreview, 
    updateEditQuestionPreview, removeMediaFromEditModal,
    getEditStemEditor, getEditPassageEditor, getEditExplanationEditor
} from './ui/editors.js';
import { renderTestsTable, updateTestStatus } from './components/tests.js';
import { renderSectionsTable } from './components/sections.js';
import { renderModulesTable, initModulesSearch } from './components/modules.js';
import { 
    renderQuestionsTable, renderQuestionsPagination, questionsListFetchUrl, 
    openEditQuestionModal, initRemoteQuestionPicker 
} from './components/questions.js';
import { 
    addBuilderBlock, syncBuilderBlockDomain, updateSidebarNavigator, 
    debouncedUpdateLivePreview, getBuilderEditors, resetBuilderBlockCount 
} from './components/builder.js';
import { initQuickAuthorWizard } from './components/wizard.js';
import * as BulkImport from './components/bulk-import.js';

document.addEventListener('DOMContentLoaded', function () {

    function rememberTestDashboardTab() {
        const activeBtn = document.querySelector('#dashboardTabs .nav-link.active') || document.querySelector('#dashboardTabs .sidebar-link.active');
        if (!activeBtn) return;
        const target = activeBtn.getAttribute('data-bs-target');
        if (target) sessionStorage.setItem(TEST_DASHBOARD_TAB_KEY, target);
    }

    window.__tdLatestTests = null;
    window.__tdLatestPayload = null;
    window.__tdLatestListJson = null;

    function renderActiveTab(targetId = null) {
        if (!targetId) {
            const activeBtn = document.querySelector('#dashboardTabs .sidebar-link.active') || document.querySelector('#dashboardTabs .nav-link.active');
            if (activeBtn) targetId = activeBtn.getAttribute('data-bs-target');
        }
        if (!targetId) return;

        if (targetId === '#tests' && window.__tdLatestTests) {
            renderTestsTable(window.__tdLatestTests);
        } else if (targetId === '#sections' && window.__tdLatestTests) {
            renderSectionsTable(window.__tdLatestTests);
        } else if (targetId === '#modules' && window.__tdLatestPayload?.allModules) {
            renderModulesTable(window.__tdLatestPayload.allModules);
        } else if (targetId === '#questions' && window.__tdLatestListJson) {
            const listJson = window.__tdLatestListJson;
            renderQuestionsTable(listJson.data || []);
            renderQuestionsPagination(listJson, refreshQuestionsTableOnly);
            const qBadge = document.getElementById('questionsPoolCountBadge');
            if (qBadge && listJson.total != null) qBadge.textContent = listJson.total + ' Total';
        }
    }

    async function refreshQuestionsTableOnly() {
        const response = await fetch(questionsListFetchUrl(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        if (!response.ok) throw new Error('Questions list failed (' + response.status + ')');
        const listJson = await response.json();
        const last = listJson.last_page || 1;
        if ((listJson.current_page || 1) > last) {
            window.__tdQuestionsPage = last;
            return refreshQuestionsTableOnly();
        }
        window.__tdLatestListJson = listJson;
        renderActiveTab('#questions');
    }

    function rebuildAllTomSelects(payload, preserve) {
        const p = preserve || {};
        const tests = payload.tests || [];
        const passages = payload.passages || [];
        const allModules = payload.allModules || [];
        rebuildSectionTestTomSelect(tests, p.sectionTest, 'sectionTest');
        rebuildSectionTestTomSelect(tests, p.linkTest, 'linkTest');
        rebuildModuleSectionTomSelect(tests, p.moduleSection, 'moduleSection');
        rebuildModuleSectionTomSelect(tests, p.linkSection, 'linkSection');
        rebuildQuestionModuleTomSelect(tests, p.questionModule, 'questionModule');
        rebuildQuestionModuleTomSelect(tests, p.bulkQuestionModule, 'bulkQuestionModule');
        rebuildQuestionPassageTomSelect(passages, p.questionPassage);
        rebuildLinkModuleTomSelect(allModules, p.linkModule);
        
        initRemoteQuestionPicker('answerQuestionId', p.answerQuestionId);
        initRemoteQuestionPicker('explanationQuestionId', p.explanationQuestionId);
    }

    async function refreshTestDashboardData(preserveTomSelects) {
        const [snapRes, listRes] = await Promise.all([
            fetch(SNAPSHOT_URL, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }),
            fetch(questionsListFetchUrl(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        ]);
        if (!snapRes.ok) throw new Error('Snapshot request failed (' + snapRes.status + ')');
        
        let payload = { tests: [], passages: [], allModules: [] };
        try { payload = await snapRes.json(); } catch (e) { console.error('Snapshot JSON parse failed'); }

        window.__tdLatestTests = payload.tests || [];
        window.__tdLatestPayload = payload;

        let listJson = { data: [], total: 0, current_page: 1, last_page: 1 };
        if (listRes.ok) { try { listJson = await listRes.json(); } catch (e) {} }
        
        const last = listJson.last_page || 1;
        if ((listJson.current_page || 1) > last) {
            window.__tdQuestionsPage = last;
            await refreshQuestionsTableOnly();
        } else {
            window.__tdLatestListJson = listJson;
            renderActiveTab();
        }
        rebuildAllTomSelects(payload, preserveTomSelects);
    }

    // Expose to window for global access
    window.refreshTestDashboardData = function () {
        rememberTestDashboardTab();
        window.location.reload();
    };
    window.removeMediaFromEditModal = removeMediaFromEditModal;
    window.addBuilderBlock = addBuilderBlock;

    function initTestDashboardDelegatedActions() {
        const root = document.getElementById('dashboardTabContent');
        if (!root || root.dataset.delegatedActionsBound === '1') return;
        root.dataset.delegatedActionsBound = '1';

        root.addEventListener('change', function (e) {
            const sel = e.target.closest('select.status-select[data-test-id]');
            if (sel) updateTestStatus(sel.getAttribute('data-test-id'), sel.value, () => refreshTestDashboardData(captureTomSelectPreservation(null)));
        });

        root.addEventListener('click', async function (e) {
            const btn = e.target.closest('.delete-test-btn, .delete-section-btn, .delete-module-btn, .delete-question-btn, .edit-question-btn, .clone-test-btn, .clone-module-btn');
            if (!btn) return;

            const id = btn.getAttribute('data-id');
            if (btn.classList.contains('edit-question-btn')) {
                openEditQuestionModal(id);
                return;
            }

            // Clone handled separately via another listener but kept for delegation safety
            if (btn.classList.contains('clone-test-btn') || btn.classList.contains('clone-module-btn')) return;

            if (!await showCustomConfirm('Permanently delete this item?', 'warning', 'Permanently Delete')) return;

            let deleteChildren = false;
            let url;
            if (btn.classList.contains('delete-test-btn')) {
                url = `${BASE_URL}/tests/${id}`;
                if (await showCustomConfirm('Also delete all sections, modules, and questions inside this test?', 'warning', 'Delete Child Elements')) deleteChildren = true;
            } else if (btn.classList.contains('delete-section-btn')) {
                url = `${BASE_URL}/sections/${id}`;
                if (await showCustomConfirm('Also delete all modules and questions inside this section?', 'warning', 'Delete Child Elements')) deleteChildren = true;
            } else if (btn.classList.contains('delete-module-btn')) {
                url = `${BASE_URL}/modules/${id}`;
                if (await showCustomConfirm('Also delete all questions linked to this module?', 'warning', 'Delete Child Elements')) deleteChildren = true;
            } else if (btn.classList.contains('delete-question-btn')) {
                url = `${BASE_URL}/questions/${id}`;
            } else return;

            const preserve = captureTomSelectPreservation(null);
            try {
                const response = await fetch(deleteChildren ? `${url}?delete_children=1` : url, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                if (response.ok) {
                    showAlert('success', 'Deleted successfully');
                    await refreshTestDashboardData(preserve);
                } else {
                    let msg = 'Delete failed';
                    try { const j = await response.json(); msg = j.message || msg; } catch (err) {}
                    showAlert('danger', msg);
                }
            } catch (error) { showAlert('danger', 'Error: ' + error.message); }
        });
    }

    // Modal listeners
    document.getElementById('editQuestionModal')?.addEventListener('shown.bs.modal', function () {
        const question = window.__editingQuestion;
        if (!question) return;

        initEditModalEditors();

        const stemEditor = getEditStemEditor();
        const passageEditor = getEditPassageEditor();
        const explanationEditor = getEditExplanationEditor();

        if (stemEditor) { stemEditor.value(question.stem || ''); stemEditor.codemirror.refresh(); }
        if (passageEditor) {
            const pContent = question.passage_content || (question.passage ? (typeof question.passage === 'string' ? question.passage : question.passage.content) : '');
            passageEditor.value(pContent || ''); passageEditor.codemirror.refresh();
        }
        if (explanationEditor) {
            const expContent = question.explanation ? (question.explanation.explanation || '') : '';
            explanationEditor.value(expContent); explanationEditor.codemirror.refresh();
        }

        refreshEditMediaList();
        updateEditQuestionPreview();

        setTimeout(() => {
            if (stemEditor) stemEditor.codemirror.refresh();
            if (passageEditor) passageEditor.codemirror.refresh();
            if (explanationEditor) explanationEditor.codemirror.refresh();
        }, 100);
    });

    document.getElementById('editQuestionForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        try {
            const stemEditor = getEditStemEditor();
            const passageEditor = getEditPassageEditor();
            const explanationEditor = getEditExplanationEditor();

            if (stemEditor) document.getElementById('editQuestionStem').value = stemEditor.value();
            if (passageEditor) document.getElementById('editPassageContent').value = passageEditor.value();
            if (explanationEditor) document.getElementById('editExplanation').value = explanationEditor.value();

            const stemVal = stemEditor ? stemEditor.value() : document.getElementById('editQuestionStem').value;
            if (!stemVal || stemVal.trim() === '') { showAlert('danger', 'Question stem is required.'); return; }

            const id = document.getElementById('editQuestionId').value;
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            if (data.question_type === 'multiple_choice') {
                data.choices = [];
                ['A', 'B', 'C', 'D'].forEach((label, index) => {
                    const contentInput = document.getElementById(`editChoice${label}Content`);
                    const isCorrectRadio = document.getElementById(`editChoice${label}Correct`);
                    if (contentInput) {
                        data.choices.push({ label, content: contentInput.value, is_correct: isCorrectRadio ? isCorrectRadio.checked : false, order: index + 1 });
                    }
                });
            }

            data.is_pretest = document.getElementById('editIsPretest').checked ? 1 : 0;
            data.calculator_allowed = document.getElementById('editCalculatorAllowed').checked ? 1 : 0;

            const response = await fetch(`${BASE_URL}/questions/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify(data)
            });

            if (response.ok) {
                showAlert('success', 'Question updated successfully!');
                bootstrap.Modal.getInstance(document.getElementById('editQuestionModal'))?.hide();
                await refreshQuestionsTableOnly();
            } else {
                const result = await response.json();
                showAlert('danger', result.message || 'Update failed');
            }
        } catch (error) { showAlert('danger', 'Submission error: ' + error.message); }
    });

    // Helper functions for Blade
    window.updateSectionName = function (select) {
        const nameInput = document.getElementById('sectionName');
        if (!nameInput) return;
        nameInput.value = select.value === 'reading_writing' ? 'Reading and Writing' : 'Math';
    };

    window.autoFetchSectionType = function (select) {
        const sectionType = select.options[select.selectedIndex].getAttribute('data-section-type');
        const sectionTypeSelect = document.getElementById('qSectionType');
        if (sectionType && sectionTypeSelect) {
            sectionTypeSelect.value = sectionType;
            window.updateSkillDomains(sectionTypeSelect);
        }
    };

    window.applyModuleDefaults = function (select) {
        const type = select.options[select.selectedIndex].getAttribute('data-type');
        const durationInput = document.getElementById('moduleDuration');
        const questionsInput = document.getElementById('totalQuestions');
        if (durationInput && questionsInput) {
            if (type === 'reading_writing') { durationInput.value = 32; questionsInput.value = 27; }
            else if (type === 'math') { durationInput.value = 35; questionsInput.value = 22; }
        }
    };

    window.updateSkillDomains = function (select) {
        const domainSelect = document.getElementById('skillDomain');
        if (!domainSelect) return;
        const type = select.value;
        const qTypeSelect = document.getElementById('questionType');
        const sprOption = qTypeSelect?.querySelector?.('option[value="student_produced_response"]');
        const sprHintContainer = document.getElementById('sprHintContainer');

        if (type === 'reading_writing') {
            if (qTypeSelect && qTypeSelect.value === 'student_produced_response') qTypeSelect.value = 'multiple_choice';
            if (sprOption) sprOption.style.display = 'none';
            if (sprHintContainer) sprHintContainer.classList.add('d-none');
        } else {
            if (sprOption) sprOption.style.display = 'block';
            if (sprHintContainer) sprHintContainer.classList.remove('d-none');
        }

        domainSelect.innerHTML = '<option value="">Select domain...</option>';
        if (type && SKILL_DOMAINS[type]) {
            SKILL_DOMAINS[type].forEach(domain => {
                const opt = document.createElement('option');
                opt.value = domain.value; opt.textContent = domain.label;
                domainSelect.appendChild(opt);
            });
        }
    };

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
                } else {
                    let msg = result.message || 'Validation failed';
                    if (result.errors) msg = Object.values(result.errors).flat().join(' ');
                    showAlert('danger', msg);
                }
            } catch (error) { showAlert('danger', 'Network error: ' + error.message); }
        });
    }

    setupForm('testForm', TESTS_STORE_URL);
    setupForm('sectionForm', SECTIONS_STORE_URL);
    setupForm('moduleForm', MODULES_STORE_URL);
    setupForm('linkModuleForm', SECTIONS_LINK_MODULE_URL);
    setupForm('attachQuestionForm', QUESTIONS_ATTACH_URL);

    // Initializations
    initTestDashboardDelegatedActions();
    initModulesSearch();
    document.querySelectorAll('#dashboardTabs [data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', e => {
            const target = e.target.getAttribute('data-bs-target');
            sessionStorage.setItem(TEST_DASHBOARD_TAB_KEY, target);
            requestAnimationFrame(() => {
                setTimeout(() => {
                    renderActiveTab(target);
                }, 0);
            });
        });
    });

    const savedTab = sessionStorage.getItem(TEST_DASHBOARD_TAB_KEY);
    if (savedTab) {
        const trigger = document.querySelector('#dashboardTabs [data-bs-target="' + savedTab + '"]');
        if (trigger && typeof bootstrap !== 'undefined' && bootstrap.Tab) bootstrap.Tab.getOrCreateInstance(trigger).show();
    }

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
    document.getElementById('builderModuleId')?.addEventListener('change', (e) => {
        document.querySelectorAll('.builder-block').forEach(syncBuilderBlockDomain);
    });

    initQuickAuthorWizard();

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

    // Start data fetch
    refreshTestDashboardData(captureTomSelectPreservation(null)).catch(err => console.error('Initial dashboard load failed:', err));
});
