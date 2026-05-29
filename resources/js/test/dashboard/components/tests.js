import { BASE_URL, TESTS_STORE_URL } from '../core/config.js';
import { showAlert, escapeHtml, humanizeUnderscores, showTableLoader, hideTableLoader, formatDateToShort } from '../utils/helpers.js';

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

export function testCreatedByFormatter(cell) {
    const data = cell.getRow().getData();
    const name = data.created_by_name || 'Admin';
    return `<span class="text-xs font-semibold text-slate-350 truncate max-w-[110px] block" title="${escapeHtml(name)}">${escapeHtml(name)}</span>`;
}

export function testPublicFormatter(cell) {
    const data = cell.getRow().getData();
    if (data.is_owner) {
        return `<div class="flex items-center justify-center h-full"><input type="checkbox" class="w-4 h-4 text-indigo-600 border-slate-800 bg-slate-400/60 rounded-xs cursor-pointer test-public-checkbox" ${data.is_public ? 'checked' : ''}></div>`;
    } else {
        return `<span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 uppercase tracking-wider"><i class="bi bi-globe mr-1"></i> Shared</span>`;
    }
}

export function testActionsFormatter(cell) {
    const id = cell.getValue();
    const data = cell.getRow().getData();
    if (!data.is_owner) {
        return `<span class="text-xs font-semibold text-slate-500">Read-Only</span>`;
    }
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

let currentTestsData = null;

export function renderTestsTable(tests) {
    if (currentTestsData === tests && testsTabulator) {
        setTimeout(() => {
            if (testsTabulator) {
                testsTabulator.redraw(true);
                const el = document.getElementById('testsTabulatorTable');
                if (el) {
                    el.classList.remove('opacity-0');
                    el.classList.add('opacity-100');
                }
            }
        }, 50);
        return;
    }
    currentTestsData = tests;
    const tableContainer = document.getElementById('testsTableContainer');
    const emptyState = document.getElementById('testsEmptyState');
    const tableElem = document.getElementById('testsTabulatorTable');

    if (!tableElem) return;

    if (!tests.length) {
        if (emptyState) emptyState.classList.remove('hidden');
        if (tableContainer) tableContainer.classList.add('hidden');
        return;
    }

    if (emptyState) emptyState.classList.add('hidden');
    if (tableContainer) tableContainer.classList.remove('hidden');

    const tableData = tests.map(function(t) {
        return {
            id: t.id,
            title: t.title,
            type: humanizeUnderscores(t.test_type),
            status: t.status,
            duration: t.total_duration_minutes,
            created_by: t.created_by,
            created_by_name: t.created_by_name || (t.creator ? (t.creator.username || t.creator.email) : 'Admin'),
            created_at: formatDateToShort(t.created_at),
            is_public: t.is_public,
            is_owner: t.is_owner !== undefined ? t.is_owner : (t.created_by === window.__currentUserId || window.__currentUserRole === 'admin')
        };
    });

    let testsSearchTimeout = null;

    if (!testsTabulator) {
        testsTabulator = new Tabulator("#testsTabulatorTable", {
            data: tableData,
            minHeight: 400,
            layout: "fitColumns",
            responsiveLayout: "collapse",
            pagination: true,
            paginationSize: 30,
            paginationCounter: "rows",
            placeholder: "No tests found",
            rowFormatter: function(row) {
                const data = row.getData();
                if (!data.is_owner) {
                    row.getElement().classList.add("row-shared");
                }
            },
            columns: [
                { title: "ID", field: "id", width: 70 },
                { 
                    title: "Title <i class='bi bi-pencil ms-1 text-muted' style='font-size:0.75rem'></i>", 
                    field: "title", 
                    editable: function(cell) {
                        return cell.getRow().getData().is_owner;
                    },
                    editor: "input", 
                    cellEdited: handleTestTitleEdit 
                },
                { title: "Type", field: "type" },
                { title: "Created At", field: "created_at", width: 140 },
                { title: "Created By", field: "is_owner", formatter: testCreatedByFormatter, headerSort: false, width: 140 },
                { 
                    title: "Public", 
                    field: "is_public", 
                    formatter: testPublicFormatter, 
                    headerSort: false, 
                    width: 95,
                    cellClick: function(e, cell) {
                        const target = e.target;
                        if (target.classList.contains('test-public-checkbox')) {
                            const data = cell.getRow().getData();
                            const checked = target.checked;
                            
                            fetch(`${BASE_URL}/tests/${data.id}`, {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ is_public: checked })
                            }).then(res => res.json()).then(res => {
                                if (res.status === 'success') {
                                    showAlert('success', 'Test visibility updated!');
                                    data.is_public = checked;
                                    cell.getRow().update(data);
                                } else {
                                    showAlert('danger', res.message || 'Failed to update visibility');
                                    target.checked = !checked;
                                }
                            }).catch(() => {
                                showAlert('danger', 'Error updating test visibility');
                                target.checked = !checked;
                            });
                        }
                    }
                },
                { title: "Status", field: "status", formatter: testStatusFormatter, width: 100 },
                { title: "Duration", field: "duration", formatter: (cell) => cell.getValue() + 'm', width: 90 },
                { title: "Actions", field: "id", headerSort: false, formatter: testActionsFormatter, width: 280 }
            ],
            pageChanged: function(page) {
                showTableLoader('testsTableContainer');
                setTimeout(() => {
                    hideTableLoader('testsTableContainer');
                }, 400);
            }
        });
        const updateFilters = () => {
            const searchVal = document.getElementById('testsTableSearch')?.value || '';
            const showShared = document.getElementById('testsShowSharedToggle')?.checked;
            
            testsTabulator.clearFilter();
            
            if (searchVal) {
                testsTabulator.setFilter("title", "like", searchVal);
            }
            if (window.__currentUserRole === 'teacher' && !showShared) {
                testsTabulator.setFilter("is_owner", "==", true);
            }
        };

        document.getElementById('testsShowSharedToggle')?.addEventListener('change', function() {
            showTableLoader('testsTableContainer');
            updateFilters();
            setTimeout(() => hideTableLoader('testsTableContainer'), 200);
        });

        document.getElementById('testsTableSearch')?.addEventListener('input', function(e) {
            const val = e.target.value;
            showTableLoader('testsTableContainer');
            if (testsSearchTimeout) clearTimeout(testsSearchTimeout);
            testsSearchTimeout = setTimeout(() => {
                updateFilters();
                setTimeout(() => hideTableLoader('testsTableContainer'), 200);
            }, 400);
        });

        if (window.__currentUserRole === 'teacher' && !document.getElementById('testsShowSharedToggle')?.checked) {
            testsTabulator.setFilter("is_owner", "==", true);
        }

        setTimeout(() => {
            if (testsTabulator) {
                testsTabulator.redraw(true);
                const el = document.getElementById('testsTabulatorTable');
                if (el) {
                    el.classList.remove('opacity-0');
                    el.classList.add('opacity-100');
                }
            }
        }, 100);
    } else {
        testsTabulator.replaceData(tableData);
        setTimeout(() => {
            if (testsTabulator) {
                testsTabulator.redraw(true);
                const el = document.getElementById('testsTabulatorTable');
                if (el) {
                    el.classList.remove('opacity-0');
                    el.classList.add('opacity-100');
                }
            }
        }, 50);
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
