import { BASE_URL } from '../core/config.js';
import { humanizeUnderscores, showTableLoader, hideTableLoader, escapeHtml, showAlert, formatDateToShort } from '../utils/helpers.js';

let sectionsTabulator = null;

export function handleSectionNameEdit(cell) {
    const data = cell.getRow().getData();
    fetch(`${BASE_URL}/sections/${data.id}`, {
        method: 'PUT',
        headers: { 
            'Content-Type': 'application/json', 
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 
            'Accept': 'application/json' 
        },
        body: JSON.stringify({ name: data.name })
    }).then(res => res.json()).then(res => {
        if (!res.success) cell.restoreOldValue();
    }).catch(() => cell.restoreOldValue());
}

export function sectionCreatedByFormatter(cell) {
    const data = cell.getRow().getData();
    const name = data.created_by_name || 'Admin';
    return `<span class="text-xs font-semibold text-slate-350 truncate max-w-[110px] block" title="${escapeHtml(name)}">${escapeHtml(name)}</span>`;
}

export function sectionPublicFormatter(cell) {
    const data = cell.getRow().getData();
    if (data.is_owner) {
        return `<div class="flex items-center justify-center h-full"><input type="checkbox" class="w-4 h-4 text-emerald-600 border-slate-800 bg-slate-400/60 rounded-xs cursor-pointer section-public-checkbox" ${data.is_public ? 'checked' : ''}></div>`;
    } else {
        return `<span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 uppercase tracking-wider"><i class="bi bi-globe mr-1"></i> Shared</span>`;
    }
}

export function sectionActionsFormatter(cell) {
    const id = cell.getValue();
    const data = cell.getRow().getData();
    if (!data.is_owner) {
        return `<span class="text-xs font-semibold text-slate-500">Read-Only</span>`;
    }
    return `<button type="button" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl delete-section-btn transition-all cursor-pointer" data-id="${id}"><i class="bi bi-trash"></i></button>`;
}

let currentSectionsData = null;

export function renderSectionsTable(tests) {
    if (currentSectionsData === tests && sectionsTabulator) {
        setTimeout(() => {
            if (sectionsTabulator) {
                sectionsTabulator.redraw(true);
                const el = document.getElementById('sectionsTabulatorTable');
                if (el) {
                    el.classList.remove('opacity-0');
                    el.classList.add('opacity-100');
                }
            }
        }, 50);
        return;
    }
    currentSectionsData = tests;
    const tableContainer = document.getElementById('sectionsTableContainer');
    const emptyState = document.getElementById('sectionsEmptyState');
    const tableElem = document.getElementById('sectionsTabulatorTable');

    if (!tableElem) return;

    const tableData = [];
    tests.forEach(test => {
        (test.sections || []).forEach(section => {
            tableData.push({
                id: section.id,
                test_title: test.title,
                name: section.name,
                type: humanizeUnderscores(section.type),
                order: section.order,
                created_by: section.created_by,
                created_by_name: section.created_by_name || (section.creator ? (section.creator.username || section.creator.email) : 'Admin'),
                created_at: formatDateToShort(section.created_at),
                is_public: section.is_public,
                is_owner: section.is_owner !== undefined ? section.is_owner : (section.created_by === window.__currentUserId || window.__currentUserRole === 'admin')
            });
        });
    });

    if (!tableData.length) {
        if (emptyState) emptyState.classList.remove('hidden');
        if (tableContainer) tableContainer.classList.add('hidden');
        return;
    }

    if (emptyState) emptyState.classList.add('hidden');
    if (tableContainer) tableContainer.classList.remove('hidden');

    let sectionsSearchTimeout = null;

    if (!sectionsTabulator) {
        sectionsTabulator = new Tabulator("#sectionsTabulatorTable", {
            data: tableData,
            minHeight: 400,
            layout: "fitColumns",
            responsiveLayout: "collapse",
            pagination: true,
            paginationSize: 30,
            paginationCounter: "rows",
            placeholder: "No sections found",
            rowFormatter: function(row) {
                const data = row.getData();
                if (!data.is_owner) {
                    row.getElement().classList.add("row-shared");
                }
            },
            columns: [
                { title: "ID", field: "id", width: 70 },
                { title: "Test Title", field: "test_title" },
                { 
                    title: "Section Name <i class='bi bi-pencil ms-1 text-muted' style='font-size:0.75rem'></i>", 
                    field: "name", 
                    editable: function(cell) {
                        return cell.getRow().getData().is_owner;
                    },
                    editor: "input", 
                    cellEdited: handleSectionNameEdit 
                },
                { title: "Type", field: "type" },
                { title: "Created At", field: "created_at", width: 140 },
                { title: "Created By", field: "is_owner", formatter: sectionCreatedByFormatter, headerSort: false, width: 140 },
                { 
                    title: "Public", 
                    field: "is_public", 
                    formatter: sectionPublicFormatter, 
                    headerSort: false, 
                    width: 95,
                    cellClick: function(e, cell) {
                        const target = e.target;
                        if (target.classList.contains('section-public-checkbox')) {
                            const data = cell.getRow().getData();
                            const checked = target.checked;
                            
                            fetch(`${BASE_URL}/sections/${data.id}`, {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ is_public: checked })
                            }).then(res => res.json()).then(res => {
                                if (res.status === 'success') {
                                    showAlert('success', 'Section visibility updated!');
                                    data.is_public = checked;
                                    cell.getRow().update(data);
                                } else {
                                    showAlert('danger', res.message || 'Failed to update visibility');
                                    target.checked = !checked;
                                }
                            }).catch(() => {
                                showAlert('danger', 'Error updating section visibility');
                                target.checked = !checked;
                            });
                        }
                    }
                },
                { title: "Order", field: "order", width: 90 },
                { title: "Actions", field: "id", headerSort: false, formatter: sectionActionsFormatter, width: 100 }
            ],
            pageChanged: function(page) {
                showTableLoader('sectionsTableContainer');
                setTimeout(() => {
                    hideTableLoader('sectionsTableContainer');
                }, 400);
            }
        });
        const updateFilters = () => {
            const searchVal = document.getElementById('sectionsTableSearch')?.value?.toLowerCase()?.trim() || '';
            const showShared = document.getElementById('sectionsShowSharedToggle')?.checked;
            
            sectionsTabulator.clearFilter();
            
            if (searchVal) {
                sectionsTabulator.setFilter(function(data) {
                    const nameMatch = data.name ? data.name.toLowerCase().includes(searchVal) : false;
                    const testTitleMatch = data.test_title ? data.test_title.toLowerCase().includes(searchVal) : false;
                    return nameMatch || testTitleMatch;
                });
            }
            if (window.__currentUserRole === 'teacher' && !showShared) {
                sectionsTabulator.setFilter("is_owner", "==", true);
            }
        };

        document.getElementById('sectionsShowSharedToggle')?.addEventListener('change', function() {
            showTableLoader('sectionsTableContainer');
            updateFilters();
            setTimeout(() => hideTableLoader('sectionsTableContainer'), 200);
        });

        document.getElementById('sectionsTableSearch')?.addEventListener('input', function(e) {
            showTableLoader('sectionsTableContainer');
            if (sectionsSearchTimeout) clearTimeout(sectionsSearchTimeout);
            sectionsSearchTimeout = setTimeout(() => {
                updateFilters();
                setTimeout(() => hideTableLoader('sectionsTableContainer'), 200);
            }, 400);
        });

        if (window.__currentUserRole === 'teacher' && !document.getElementById('sectionsShowSharedToggle')?.checked) {
            sectionsTabulator.setFilter("is_owner", "==", true);
        }

        setTimeout(() => {
            if (sectionsTabulator) {
                sectionsTabulator.redraw(true);
                const el = document.getElementById('sectionsTabulatorTable');
                if (el) {
                    el.classList.remove('opacity-0');
                    el.classList.add('opacity-100');
                }
            }
        }, 100);
    } else {
        sectionsTabulator.replaceData(tableData);
        setTimeout(() => {
            if (sectionsTabulator) {
                sectionsTabulator.redraw(true);
                const el = document.getElementById('sectionsTabulatorTable');
                if (el) {
                    el.classList.remove('opacity-0');
                    el.classList.add('opacity-100');
                }
            }
        }, 50);
    }
}
