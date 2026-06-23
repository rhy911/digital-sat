import {
    SKILL_DOMAINS, TEST_DASHBOARD_TAB_KEY, SNAPSHOT_URL, SECTIONS_STORE_URL,
    MODULES_STORE_URL, BASE_URL, BULK_STORE_URL,
    CSV_BULK_URL, MEDIA_UPLOAD_URL
} from './core/config.js';
import './core/examples.js';
import {
    showAlert, showCustomConfirm, getTomSelectValue, captureTomSelectPreservation,
    rebuildSectionTestTomSelect, rebuildModuleSectionTomSelect, rebuildQuestionModuleTomSelect,
    rebuildQuestionPassageTomSelect, initTomSelectOn,
    destroyTomSelectIfAny, optionExistsInSelect, humanizeUnderscores, capitalizeFirstLetter,
    loadHeavyDependencies, showTableLoader, hideTableLoader
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
    debouncedUpdateLivePreview, getBuilderEditors, resetBuilderBlockCount,
    clearBuilderWorkspace, fetchModuleQuestions, submitBuilderQuestions, handleClearBuilder,
    restoreBuilderDraft, clearUnchangedQuestions
} from './components/builder.js';
import { initQuickAuthorWizard } from './components/wizard.js';
import * as BulkImport from './components/bulk-import.js';
import './ui/sidebar.js';

document.addEventListener('DOMContentLoaded', async function () {
    await loadHeavyDependencies();

    function rememberTestDashboardTab() {
        const activeBtn = document.querySelector('#dashboardTabs .sidebar-link.active, #dashboardTabs .sidebar-link-builder.active, #dashboardTabs .nav-link.active');
        if (!activeBtn) return;
        const target = activeBtn.getAttribute('data-bs-target');
        if (target) sessionStorage.setItem(TEST_DASHBOARD_TAB_KEY, target);
    }

    window.__tdLatestTests = null;
    window.__tdLatestPayload = null;
    window.__tdLatestListJson = null;

    async function renderActiveTab(targetId = null) {
        if (!targetId) {
            targetId = sessionStorage.getItem(TEST_DASHBOARD_TAB_KEY);
        }
        if (!targetId) {
            const activeBtn = document.querySelector('#dashboardTabs .sidebar-link.active, #dashboardTabs .sidebar-link-builder.active, #dashboardTabs .nav-link.active');
            if (activeBtn) targetId = activeBtn.getAttribute('data-bs-target');
        }
        if (!targetId) targetId = '#tests';

        const activeTitle = document.getElementById('dashboard-active-title');
        const activeDescription = document.getElementById('dashboard-active-description');
        if (activeTitle) {
            const titleMap = {
                '#tests': 'Practice Tests',
                '#sections': 'Sections',
                '#modules': 'Modules',
                '#questions': 'Question Bank',
                '#builder': 'Easy Builder',
            };
            activeTitle.textContent = titleMap[targetId] || 'Test Dashboard';
        }
        if (activeDescription) {
            const descriptionMap = {
                '#tests': 'Create and manage SAT practice tests.',
                '#builder': 'Write questions with a live Bluebook-style preview.',
                '#sections': 'Organize Reading & Writing and Math sections.',
                '#modules': 'Manage module timing, difficulty, and capacity.',
                '#questions': 'Search, review, and reuse assessment content.',
            };
            activeDescription.textContent = descriptionMap[targetId] || 'Manage SAT assessment content.';
        }

        await loadHeavyDependencies();

        if (targetId === '#tests' && window.__tdLatestTests) {
            renderTestsTable(window.__tdLatestTests);
        } else if (targetId === '#sections' && window.__tdLatestTests) {
            renderSectionsTable(window.__tdLatestTests);
        } else if (targetId === '#modules' && window.__tdLatestPayload?.allModules) {
            const mods = window.__tdLatestPayload.allModules.data || window.__tdLatestPayload.allModules;
            renderModulesTable(mods);
        } else if (targetId === '#questions' && window.__tdLatestListJson) {
            const listJson = window.__tdLatestListJson;
            renderQuestionsTable(listJson.data || []);
            renderQuestionsPagination(listJson, refreshQuestionsTableOnly);
            const qBadge = document.getElementById('questionsPoolCountBadge');
            if (qBadge && listJson.total != null) qBadge.textContent = listJson.total + ' Total';
        }
    }

    async function refreshQuestionsTableOnly() {
        showTableLoader('questionsTableContainer');
        const startTime = Date.now();

        try {
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
            await renderActiveTab('#questions');
        } finally {
            const elapsed = Date.now() - startTime;
            const remaining = Math.max(0, 400 - elapsed);
            setTimeout(() => {
                hideTableLoader('questionsTableContainer');
            }, remaining);
        }
    }

    function rebuildAllTomSelects(payload, preserve) {
        const p = preserve || {};
        const tests = payload.tests ? (payload.tests.data || payload.tests) : [];
        const passages = payload.passages ? (payload.passages.data || payload.passages) : [];
        const allModules = payload.allModules ? (payload.allModules.data || payload.allModules) : [];
        rebuildSectionTestTomSelect(tests, p.sectionTest, 'sectionTest');
        rebuildSectionTestTomSelect(tests, p.linkTest, 'linkTest');
        rebuildModuleSectionTomSelect(tests, p.moduleSection, 'moduleSection');
        rebuildModuleSectionTomSelect(tests, p.linkSection, 'linkSection');
        rebuildQuestionModuleTomSelect(tests, p.questionModule, 'questionModule');
        rebuildQuestionModuleTomSelect(tests, p.bulkQuestionModule, 'bulkQuestionModule');
        rebuildQuestionModuleTomSelect(tests, p.builderModuleId, 'builderModuleId');
        rebuildQuestionModuleTomSelect(tests, p.questionsTableModuleFilter, 'questionsTableModuleFilter');
        rebuildQuestionPassageTomSelect(passages, p.questionPassage);

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

        window.__tdLatestTests = payload.tests ? (payload.tests.data || payload.tests) : [];
        window.__tdLatestPayload = payload;

        let listJson = { data: [], total: 0, current_page: 1, last_page: 1 };
        if (listRes.ok) { try { listJson = await listRes.json(); } catch (e) { } }

        const last = listJson.last_page || 1;
        if ((listJson.current_page || 1) > last) {
            window.__tdQuestionsPage = last;
            await refreshQuestionsTableOnly();
        } else {
            window.__tdLatestListJson = listJson;
            await renderActiveTab();
        }
        rebuildAllTomSelects(payload, preserveTomSelects);
    }

    // Expose to window for global access
    window.refreshTestDashboardData = async function (preserve = null) {
        rememberTestDashboardTab();
        return refreshTestDashboardData(preserve || captureTomSelectPreservation(null));
    };
    window.removeMediaFromEditModal = removeMediaFromEditModal;
    window.addBuilderBlock = addBuilderBlock;
    window.showCustomConfirm = showCustomConfirm;

    function initTestDashboardDelegatedActions() {
        const root = document.getElementById('dashboardTabContent');
        if (!root || root.dataset.delegatedActionsBound === '1') return;
        root.dataset.delegatedActionsBound = '1';

        root.addEventListener('click', async function (e) {
            const btn = e.target.closest('.delete-test-btn, .delete-section-btn, .delete-module-btn, .delete-question-btn, .edit-question-btn, .clone-test-btn, .clone-module-btn, .change-test-status-btn, .reuse-section-btn, .reuse-module-btn, .convert-to-normal-btn');
            if (!btn) return;

            const id = btn.getAttribute('data-id');
            if (btn.classList.contains('reuse-section-btn') || btn.classList.contains('reuse-module-btn')) {
                window.dispatchEvent(new CustomEvent('open-content-reuse', {
                    detail: {
                        kind: btn.classList.contains('reuse-module-btn') ? 'module' : 'section',
                        id,
                        sectionId: btn.getAttribute('data-section-id') || id,
                    }
                }));
                return;
            }
            if (btn.classList.contains('change-test-status-btn')) {
                const status = btn.getAttribute('data-status');
                const action = status === 'active' ? 'Publish this test for students?' : status === 'archived' ? 'Archive this test?' : 'Return this test to draft?';
                if (!await showCustomConfirm(action, status === 'active' ? 'info' : 'warning', status === 'active' ? 'Publish Test' : 'Change Test Status')) return;
                await updateTestStatus(id, status, () => refreshTestDashboardData(captureTomSelectPreservation(null)));
                return;
            }
            if (btn.classList.contains('convert-to-normal-btn')) {
                if (!await showCustomConfirm('Convert this incomplete Adaptive Full draft to a fixed Normal Full test?', 'info', 'Convert Test Type')) return;
                const response = await fetch(`${BASE_URL}/tests/${id}/convert-to-normal`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                const result = await response.json();
                if (!response.ok) {
                    showAlert('danger', result.message || Object.values(result.errors || {}).flat()[0] || 'Conversion failed');
                    return;
                }
                showAlert('success', result.message);
                await refreshTestDashboardData(captureTomSelectPreservation(null));
                return;
            }
            if (btn.classList.contains('edit-question-btn')) {
                openEditQuestionModal(id);
                return;
            }

            if (btn.classList.contains('clone-test-btn')) {
                const preserve = captureTomSelectPreservation(null);
                if (!await showCustomConfirm('Clone this test?', 'info', 'Clone Test')) return;
                showTableLoader('testsTableContainer');
                try {
                    const response = await fetch(`${BASE_URL}/tests/${id}/clone`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });
                    const result = await response.json();
                    if (response.ok) {
                        showAlert('success', 'Test cloned successfully!');
                        await refreshTestDashboardData(preserve);
                    } else {
                        showAlert('danger', result.message || 'Clone failed');
                    }
                } catch (error) {
                    showAlert('danger', 'Error: ' + error.message);
                } finally {
                    hideTableLoader('testsTableContainer');
                }
                return;
            }

            if (btn.classList.contains('clone-module-btn')) {
                const preserve = captureTomSelectPreservation(null);
                if (!await showCustomConfirm('Clone this module?', 'info', 'Clone Module')) return;
                showTableLoader('modulesTableContainer');
                try {
                    const response = await fetch(`${BASE_URL}/modules/${id}/clone`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });
                    const result = await response.json();
                    if (response.ok) {
                        showAlert('success', 'Module cloned successfully!');
                        await refreshTestDashboardData(preserve);
                    } else {
                        showAlert('danger', result.message || 'Clone failed');
                    }
                } catch (error) {
                    showAlert('danger', 'Error: ' + error.message);
                } finally {
                    hideTableLoader('modulesTableContainer');
                }
                return;
            }

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
                    try { const j = await response.json(); msg = j.message || msg; } catch (err) { }
                    showAlert('danger', msg);
                }
            } catch (error) { showAlert('danger', 'Error: ' + error.message); }
        });
    }

    // Modal listeners
    window.addEventListener('open-modal', function (e) {
        if (e.detail !== 'editQuestionModal') return;
        const loader = document.getElementById('editQuestionModalLoader');
        if (loader) {
            loader.style.opacity = '1';
            loader.style.pointerEvents = 'auto';
            loader.style.display = 'flex';
        }

        const question = window.__editingQuestion;
        if (!question) return;

        // Delay heavy initialization to ensure 60fps modal entry transition
        setTimeout(() => {
            let stemEditor, passageEditor, explanationEditor;
            try {
                initEditModalEditors();

                stemEditor = getEditStemEditor();
                passageEditor = getEditPassageEditor();
                explanationEditor = getEditExplanationEditor();

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

                // Disablement & Mode Toggling based on ownership
                const isOwner = question.created_by === window.__currentUserId || window.__currentUserRole === 'admin';
                const titleText = document.getElementById('editQuestionModalTitleText');
                const titleIcon = document.getElementById('editQuestionModalIcon');
                const submitBtn = document.querySelector('#editQuestionForm button[type="submit"]');

                if (titleText) titleText.textContent = isOwner ? 'Edit Question' : 'View Question';
                if (titleIcon) {
                    titleIcon.className = isOwner ? 'bi bi-pencil-square text-indigo-400' : 'bi bi-eye text-slate-400';
                }
                if (submitBtn) {
                    if (isOwner) submitBtn.classList.remove('hidden');
                    else submitBtn.classList.add('hidden');
                }

                // Disable form fields
                const formElements = document.querySelectorAll('#editQuestionForm input, #editQuestionForm select, #editQuestionForm textarea:not(.easy-mde-textarea)');
                formElements.forEach(el => {
                    if (el.id === 'editQuestionId' || el.name === '_token') return;
                    el.disabled = !isOwner;
                });

                // Set EasyMDE read-only mode
                if (stemEditor) stemEditor.codemirror.setOption('readOnly', isOwner ? false : 'nocursor');
                if (passageEditor) passageEditor.codemirror.setOption('readOnly', isOwner ? false : 'nocursor');
                if (explanationEditor) explanationEditor.codemirror.setOption('readOnly', isOwner ? false : 'nocursor');

                // Toggle toolbar styling for readonly state
                document.querySelectorAll('#editQuestionModal .editor-toolbar').forEach(tb => {
                    tb.style.pointerEvents = isOwner ? 'auto' : 'none';
                    tb.style.opacity = isOwner ? '1' : '0.5';
                });

                setTimeout(() => {
                    if (stemEditor) stemEditor.codemirror.refresh();
                    if (passageEditor) passageEditor.codemirror.refresh();
                    if (explanationEditor) explanationEditor.codemirror.refresh();
                }, 100);
            } catch (err) {
                console.error("Failed to initialize edit question editors:", err);
            } finally {
                // ALWAYS hide the loader overlay, even if initialization failed!
                setTimeout(() => {
                    if (loader) {
                        loader.style.opacity = '0';
                        loader.style.pointerEvents = 'none'; // prevent blocking any clicks
                        setTimeout(() => {
                            loader.style.display = 'none';
                        }, 300);
                    }
                }, 150);
            }
        }, 250); // Exact time for Alpine modal enter transition to complete
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
                window.dispatchEvent(new CustomEvent('close-modal', { detail: 'editQuestionModal' }));
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
            if (sprHintContainer) sprHintContainer.classList.add('hidden');
        } else {
            if (sprOption) sprOption.style.display = 'block';
            if (sprHintContainer) sprHintContainer.classList.remove('hidden');
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
        const banner = this.closest('.bg-indigo-50');
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
