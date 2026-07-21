import { BASE_URL } from './core/config.js';
import { showAlert, showCustomConfirm } from './utils/custom-alert.js';
import { captureTomSelectPreservation } from './utils/tomselect.js';
import { showTableLoader, hideTableLoader } from './utils/table-loader.js';
import { getLocalTestById, updateTestStatus } from './components/tests.js';
import { openEditQuestionModal } from './components/questions.js';
import { refreshTestDashboardData } from './dashboard-data.js';

/**
 * Delegated click handler for test/section/module/question CRUD actions
 * (clone, delete, publish/archive, convert type) rendered across dashboard tables.
 */
export function initTestDashboardDelegatedActions() {
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

        let deleteChildren = false;
        let forceDeleteAttempts = false;
        let url;

        if (btn.classList.contains('delete-test-btn')) {
            url = `${BASE_URL}/tests/${id}`;
            const test = getLocalTestById(id);
            if (test && test.user_tests_count > 0) {
                if (window.__currentUserRole === 'admin') {
                    const confirmHtml = `<div>
                        <p class="text-sm text-slate-600 mb-3"><strong>Warning:</strong> This test has <strong>${test.user_tests_count} student attempts</strong>. Hard-deleting will permanently remove all student answers and test history.</p>
                        <label class="flex items-center gap-2 cursor-pointer mt-3 p-2.5 bg-slate-50 border border-slate-200 rounded-lg select-none">
                            <input type="checkbox" id="forceDeleteIrreversibleCheckbox" class="w-4 h-4 text-rose-600 border-slate-300 rounded focus:ring-rose-500 cursor-pointer">
                            <span class="text-xs font-bold text-rose-700">I understand this action is irreversible — delete all student data</span>
                        </label>
                    </div>`;
                    if (!await showCustomConfirm(confirmHtml, 'warning', 'Force Delete Cascade')) {
                        return;
                    }
                    forceDeleteAttempts = true;
                } else {
                    showAlert('danger', `Cannot delete: this test has ${test.user_tests_count} student attempts.`);
                    return;
                }
            } else {
                if (!await showCustomConfirm('Permanently delete this test?', 'warning', 'Delete Test')) return;
            }
            if (await showCustomConfirm('Also delete all sections, modules, and questions inside this test?', 'warning', 'Delete Child Elements')) deleteChildren = true;
        } else {
            if (!await showCustomConfirm('Permanently delete this item?', 'warning', 'Permanently Delete')) return;

            if (btn.classList.contains('delete-section-btn')) {
                url = `${BASE_URL}/sections/${id}`;
                if (await showCustomConfirm('Also delete all modules and questions inside this section?', 'warning', 'Delete Child Elements')) deleteChildren = true;
            } else if (btn.classList.contains('delete-module-btn')) {
                url = `${BASE_URL}/modules/${id}`;
                if (await showCustomConfirm('Also delete all questions linked to this module?', 'warning', 'Delete Child Elements')) deleteChildren = true;
            } else if (btn.classList.contains('delete-question-btn')) {
                url = `${BASE_URL}/questions/${id}`;
            } else return;
        }

        const preserve = captureTomSelectPreservation(null);
        try {
            let requestUrl = url;
            const params = [];
            if (deleteChildren) params.push('delete_children=1');
            if (forceDeleteAttempts) params.push('force_delete_attempts=1');
            if (params.length > 0) {
                requestUrl += '?' + params.join('&');
            }
            const response = await fetch(requestUrl, {
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
