import { BASE_URL, TESTS_STORE_URL } from '../core/config.js';
import { showAlert, escapeHtml, humanizeUnderscores } from '../utils/helpers.js';

let testsTabulator = null;

export function handleTestTitleEdit(cell) {
    const data = cell.getRow().getData();
    fetch(`${BASE_URL}/tests/${data.id}`, {
        method: 'PUT',
        headers: { 
            'Content-Type': 'application/json', 
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 
            'Accept': 'application/json' 
        },
        body: JSON.stringify({ title: data.title })
    }).then(res => res.json()).then(res => {
        if (!res.success) cell.restoreOldValue();
    }).catch(() => cell.restoreOldValue());
}

export function testStatusFormatter(cell) {
    const val = cell.getValue();
    let bgClass = 'bg-slate-800/80 text-slate-300 border-slate-700/60';
    if (val === 'active') bgClass = 'bg-emerald-950/30 text-emerald-400 border-emerald-500/20';
    if (val === 'draft') bgClass = 'bg-amber-950/30 text-amber-400 border-amber-500/20';
    if (val === 'archived') bgClass = 'bg-slate-800/80 text-slate-300 border-slate-700/60';
    return `<span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider border ${bgClass}">${escapeHtml(val)}</span>`;
}

export function testActionsFormatter(cell) {
    const id = cell.getValue();
    const data = cell.getRow().getData();
    return `
        <div class="flex items-center gap-3">
            <select class="px-3 py-1.5 text-xs font-bold rounded-xl border border-slate-800/80 shadow-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none bg-slate-900/60 text-slate-200 status-select cursor-pointer hover:bg-slate-850 hover:text-white" data-test-id="${id}">
                <option value="draft" ${data.status === 'draft' ? 'selected' : ''}>Draft</option>
                <option value="active" ${data.status === 'active' ? 'selected' : ''}>Active</option>
                <option value="archived" ${data.status === 'archived' ? 'selected' : ''}>Archived</option>
            </select>
            <div class="flex items-center gap-1">
                <button type="button" class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-indigo-400 hover:bg-indigo-500/10 rounded-xl clone-test-btn transition-all border border-transparent hover:border-indigo-500/30" data-id="${id}" title="Clone Template (Hierarchy Only)"><i class="bi bi-copy text-base"></i></button>
                <button type="button" class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl delete-test-btn transition-all border border-transparent hover:border-rose-500/30" data-id="${id}" title="Delete Test"><i class="bi bi-trash text-base"></i></button>
            </div>
        </div>
    `;
}

export function renderTestsTable(tests) {
    const tableContainer = document.getElementById('testsTableContainer');
    const emptyState = document.getElementById('testsEmptyState');
    const tableElem = document.getElementById('testsTabulatorTable');

    if (!tableElem) return;

    if (!tests.length) {
        if (emptyState) emptyState.classList.remove('d-none');
        if (tableContainer) tableContainer.classList.add('d-none');
        return;
    }

    if (emptyState) emptyState.classList.add('d-none');
    if (tableContainer) tableContainer.classList.remove('d-none');

    const tableData = tests.map(function(t) {
        return {
            id: t.id,
            title: t.title,
            type: humanizeUnderscores(t.test_type),
            status: t.status,
            duration: t.total_duration_minutes
        };
    });

    if (!testsTabulator) {
        testsTabulator = new Tabulator("#testsTabulatorTable", {
            data: tableData,
            layout: "fitColumns",
            responsiveLayout: "collapse",
            placeholder: "No tests found",
            columns: [
                { title: "ID", field: "id", width: 70 },
                { title: "Title <i class='bi bi-pencil ms-1 text-muted' style='font-size:0.75rem'></i>", field: "title", editor: "input", cellEdited: handleTestTitleEdit },
                { title: "Type", field: "type" },
                { title: "Status", field: "status", formatter: testStatusFormatter },
                { title: "Duration", field: "duration", formatter: (cell) => cell.getValue() + 'm' },
                { title: "Actions", field: "id", headerSort: false, formatter: testActionsFormatter, width: 280 }
            ]
        });
        document.getElementById('testsTableSearch')?.addEventListener('input', function(e) {
            testsTabulator.setFilter("title", "like", e.target.value);
        });
    } else {
        testsTabulator.replaceData(tableData);
    }
}

export async function updateTestStatus(testId, status, refreshCallback) {
    try {
        const response = await fetch(`${BASE_URL}/tests/${testId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ status: status })
        });

        if (response.ok) {
            showAlert('success', 'Status updated!');
            if (refreshCallback) await refreshCallback();
        } else {
            const res = await response.json();
            showAlert('danger', res.message || 'Failed to update status');
        }
    } catch (error) {
        showAlert('danger', 'Error: ' + error.message);
    }
}
