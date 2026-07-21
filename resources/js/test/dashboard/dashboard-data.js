import { TEST_DASHBOARD_TAB_KEY, SNAPSHOT_URL } from './core/config.js';
import { loadHeavyDependencies } from './utils/script-loader.js';
import { showTableLoader, hideTableLoader } from './utils/table-loader.js';
import {
    rebuildSectionTestTomSelect, rebuildModuleSectionTomSelect,
    rebuildQuestionModuleTomSelect, rebuildQuestionPassageTomSelect,
} from './utils/tomselect.js';
import { renderTestsTable } from './components/tests.js';
import { renderSectionsTable } from './components/sections.js';
import { renderModulesTable } from './components/modules.js';
import {
    renderQuestionsTable, renderQuestionsPagination, questionsListFetchUrl,
    initRemoteQuestionPicker,
} from './components/questions.js';

// Core data-refresh / tab-render cluster for the test dashboard. These functions call
// each other (refreshTestDashboardData -> renderActiveTab -> refreshQuestionsTableOnly
// -> renderActiveTab), so they stay together rather than split further.

window.__tdLatestTests = null;
window.__tdLatestPayload = null;
window.__tdLatestListJson = null;

export function rememberTestDashboardTab() {
    const activeBtn = document.querySelector('#dashboardTabs .sidebar-link.active, #dashboardTabs .sidebar-link-builder.active, #dashboardTabs .nav-link.active');
    if (!activeBtn) return;
    const target = activeBtn.getAttribute('data-bs-target');
    if (target) sessionStorage.setItem(TEST_DASHBOARD_TAB_KEY, target);
}

export async function renderActiveTab(targetId = null) {
    if (!targetId) {
        const hashTab = window.location.hash;
        if (hashTab && ['#tests', '#builder', '#sections', '#modules', '#questions'].includes(hashTab)) {
            targetId = hashTab;
        }
    }
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

export async function refreshQuestionsTableOnly() {
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

export function rebuildAllTomSelects(payload, preserve) {
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

export async function refreshTestDashboardData(preserveTomSelects) {
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
