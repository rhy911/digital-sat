import { BASE_URL } from '../core/config.js';
import { humanizeUnderscores } from '../utils/helpers.js';

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

export function sectionActionsFormatter(cell) {
    return `<button type="button" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl delete-section-btn transition-all" data-id="${cell.getValue()}"><i class="bi bi-trash"></i></button>`;
}

let currentSectionsData = null;

export function renderSectionsTable(tests) {
    if (currentSectionsData === tests && sectionsTabulator) return;
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
                order: section.order
            });
        });
    });

    if (!tableData.length) {
        if (emptyState) emptyState.classList.remove('d-none');
        if (tableContainer) tableContainer.classList.add('d-none');
        return;
    }

    if (emptyState) emptyState.classList.add('d-none');
    if (tableContainer) tableContainer.classList.remove('d-none');

    if (!sectionsTabulator) {
        sectionsTabulator = new Tabulator("#sectionsTabulatorTable", {
            data: tableData,
            layout: "fitColumns",
            responsiveLayout: "collapse",
            pagination: true,
            paginationSize: 25,
            paginationCounter: "rows",
            placeholder: "No sections found",
            columns: [
                { title: "ID", field: "id", width: 70 },
                { title: "Test Title", field: "test_title" },
                { title: "Section Name <i class='bi bi-pencil ms-1 text-muted' style='font-size:0.75rem'></i>", field: "name", editor: "input", cellEdited: handleSectionNameEdit },
                { title: "Type", field: "type" },
                { title: "Order", field: "order", width: 90 },
                { title: "Actions", field: "id", headerSort: false, formatter: sectionActionsFormatter }
            ]
        });
        document.getElementById('sectionsTableSearch')?.addEventListener('input', function(e) {
            sectionsTabulator.setFilter("name", "like", e.target.value);
        });
    } else {
        sectionsTabulator.replaceData(tableData);
    }
}
