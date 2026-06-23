import { BASE_URL } from '../core/config.js';
import { humanizeUnderscores, showTableLoader, hideTableLoader, escapeHtml, showAlert, formatDateToShort } from '../utils/helpers.js';

let localAllSections = [];
let currentFilteredSections = [];
let currentSectionsRenderId = 0;

if (typeof window.__tdSectionsPage === 'undefined') {
    window.__tdSectionsPage = 1;
}
if (typeof window.__tdSectionsPerPage === 'undefined') {
    window.__tdSectionsPerPage = 30;
}

function renderSectionRowHtml(s) {
    const isOwner = s.is_owner;
    const creatorName = s.created_by_name || 'Admin';
    const createdByHtml = `<span class="text-xs font-semibold text-slate-600 truncate max-w-[110px] block" title="${escapeHtml(creatorName)}">${escapeHtml(creatorName)}</span>`;

    // Section Name input/span
    const nameHtml = isOwner
        ? `<input type="text" class="section-name-input w-full bg-transparent border-0 hover:bg-slate-100 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none rounded-lg px-2 py-1 font-semibold text-slate-800 transition-all" value="${escapeHtml(s.name)}" data-id="${s.id}">`
        : `<span class="px-2 py-1 font-semibold text-slate-800">${escapeHtml(s.name)}</span>`;

    // Public toggle checkbox
    const publicHtml = isOwner
        ? `<div class="flex items-center justify-center"><input type="checkbox" data-id="${s.id}" class="w-4 h-4 text-indigo-600 border-slate-300 bg-white rounded cursor-pointer section-public-checkbox" ${s.is_public ? 'checked' : ''} title="${s.is_public ? 'Public (Click to make Private)' : 'Private (Click to make Public)'}" aria-label="Toggle public visibility"></div>`
        : `<div class="flex items-center justify-center"><input type="checkbox" checked disabled class="w-4 h-4 text-slate-400 border-slate-200 bg-slate-100 rounded cursor-not-allowed opacity-60" title="Shared (View only)" aria-label="Shared resource"></div>`;

    // Actions dropdown
    const actionsHtml = isOwner
        ? `<div class="actions-dropdown">
            <button type="button" class="px-2.5 py-1.5 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 cursor-pointer hover:bg-slate-50 flex items-center gap-1" data-dropdown-trigger="true" aria-expanded="false" aria-label="Toggle actions menu">
                Actions <i class="bi bi-chevron-down text-[10px]"></i>
            </button>
            <div class="dropdown-menu hidden">
                <button type="button" class="dropdown-item reuse-section-btn" data-id="${s.id}"><i class="bi bi-copy mr-2"></i>Reuse in test</button>
                <button type="button" class="dropdown-item text-danger delete-section-btn" data-id="${s.id}"><i class="bi bi-trash mr-2"></i> Delete</button>
            </div>
          </div>`
        : `<button type="button" class="min-h-9 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 reuse-section-btn" data-id="${s.id}">Reuse</button>`;

    const rowClass = isOwner ? '' : 'row-shared';

    return `<tr class="${rowClass}">
        <td class="font-semibold text-slate-400 text-center">${escapeHtml(s.id)}</td>
        <td>${escapeHtml(s.test_title)}</td>
        <td>${nameHtml}</td>
        <td class="text-center font-semibold text-slate-500">${escapeHtml(s.created_at || 'N/A')}</td>
        <td>${createdByHtml}</td>
        <td class="text-center">${publicHtml}</td>
        <td class="text-center font-semibold text-slate-750">${escapeHtml(s.order)}</td>
        <td class="text-center">${actionsHtml}</td>
    </tr>`;
}

function _renderSectionsChunked(tbody, items, emptyHtml) {
    currentSectionsRenderId++;
    const renderId = currentSectionsRenderId;
    
    if (!items.length) {
        tbody.innerHTML = emptyHtml;
        return;
    }
    
    tbody.innerHTML = '';
    let index = 0;
    const chunkSize = 20;
    
    function renderChunk() {
        if (renderId !== currentSectionsRenderId) return;
        
        const chunk = items.slice(index, index + chunkSize);
        if (!chunk.length) return;
        
        tbody.insertAdjacentHTML('beforeend', chunk.map(renderSectionRowHtml).join(''));
        index += chunkSize;
        
        if (index < items.length) {
            requestAnimationFrame(() => setTimeout(renderChunk, 0));
        }
    }
    
    renderChunk();
}

function renderSectionsPage() {
    const tbody = document.getElementById('sectionsTableBody');
    if (!tbody) return;

    const page = window.__tdSectionsPage || 1;
    const perPage = window.__tdSectionsPerPage || 30;
    
    const start = (page - 1) * perPage;
    const end = page * perPage;
    const sliced = currentFilteredSections.slice(start, end);

    const emptyHtml = '<tr>'
        + '<td colspan="9" class="px-6 py-20 text-center">'
        + '<div class="flex flex-col items-center justify-center">'
        + '<div class="w-20 h-20 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center mb-6">'
        + '<i class="bi bi-inbox text-4xl text-slate-400"></i>'
        + '</div>'
        + '<h4 class="text-lg font-bold text-slate-800">No sections found</h4>'
        + '<p class="text-sm text-slate-500 mt-1 max-w-xs mx-auto">Create one section to assign modules.</p>'
        + '</div>'
        + '</td>'
        + '</tr>';

    _renderSectionsChunked(tbody, sliced, emptyHtml);
    renderSectionsPagination(currentFilteredSections.length);
}

export function renderSectionsPagination(total) {
    const wrap = document.getElementById('sectionsPoolPagination');
    if (!wrap) return;
    
    if (total === 0) {
        wrap.innerHTML = '';
        return;
    }
    
    const cur = window.__tdSectionsPage || 1;
    const perPage = window.__tdSectionsPerPage || 30;
    const last = Math.ceil(total / perPage) || 1;
    
    let html = '<div class="flex flex-wrap justify-between items-center w-full gap-3 px-2">';
    html += '<span class="text-xs font-semibold text-slate-600">Page ' + cur + ' of ' + last + ' <span class="mx-1 text-slate-300">•</span> ' + total + ' sections</span>';
    html += '<div class="flex gap-2">';
    html += '<button type="button" class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors duration-150 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer" data-s-page="prev"' + (cur <= 1 ? ' disabled' : '') + '>Previous</button>';
    html += '<button type="button" class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors duration-150 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer" data-s-page="next"' + (cur >= last ? ' disabled' : '') + '>Next</button>';
    html += '</div></div>';
    wrap.innerHTML = html;
    
    if (wrap.dataset.bound !== '1') {
        wrap.dataset.bound = '1';
        wrap.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-s-page]');
            if (!btn || btn.disabled) return;
            
            const dir = btn.getAttribute('data-s-page');
            const curPage = window.__tdSectionsPage || 1;
            
            showTableLoader('sectionsTableContainer');
            
            setTimeout(() => {
                if (dir === 'prev') {
                    window.__tdSectionsPage = Math.max(1, curPage - 1);
                } else if (dir === 'next') {
                    window.__tdSectionsPage = curPage + 1;
                }
                renderSectionsPage();
                hideTableLoader('sectionsTableContainer');
            }, 400);
        });
    }
}

function applySectionsFilterAndSearch() {
    const searchInput = document.getElementById('sectionsTableSearch');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const showShared = document.getElementById('sectionsShowSharedToggle')?.checked;

    let filtered = localAllSections;
    if (window.__currentUserRole === 'teacher' && !showShared) {
        filtered = localAllSections.filter(s => s.is_owner);
    }

    if (query) {
        filtered = filtered.filter(s => {
            const name = (s.name || '').toLowerCase();
            const testTitle = (s.test_title || '').toLowerCase();
            const type = (s.type || '').toLowerCase();
            const id = String(s.id);
            return name.includes(query) || testTitle.includes(query) || type.includes(query) || id.includes(query);
        });
    }

    currentFilteredSections = filtered;
}

export function renderSectionsTable(tests) {
    const sectionsData = [];
    tests.forEach(test => {
        (test.sections || []).forEach(section => {
            sectionsData.push({
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

    localAllSections = sectionsData;
    const tableContainer = document.getElementById('sectionsTableContainer');
    const emptyState = document.getElementById('sectionsEmptyState');
    if (!tableContainer) return;

    applySectionsFilterAndSearch();

    if (!currentFilteredSections.length && !sectionsData.length) {
        if (emptyState) emptyState.classList.remove('hidden');
        tableContainer.classList.add('hidden');
        return;
    }

    if (emptyState) emptyState.classList.add('hidden');
    tableContainer.classList.remove('hidden');

    window.__tdSectionsPage = 1;
    renderSectionsPage();
    initSectionsEvents();
}

let eventsBound = false;
function initSectionsEvents() {
    if (eventsBound) return;
    eventsBound = true;

    const searchInput = document.getElementById('sectionsTableSearch');
    if (searchInput) {
        let sectionsSearchTimeout = null;
        searchInput.addEventListener('input', function(e) {
            showTableLoader('sectionsTableContainer');
            if (sectionsSearchTimeout) clearTimeout(sectionsSearchTimeout);
            sectionsSearchTimeout = setTimeout(() => {
                applySectionsFilterAndSearch();
                window.__tdSectionsPage = 1;
                renderSectionsPage();
                setTimeout(() => {
                    hideTableLoader('sectionsTableContainer');
                }, 200);
            }, 400);
        });
    }

    document.getElementById('sectionsShowSharedToggle')?.addEventListener('change', function() {
        showTableLoader('sectionsTableContainer');
        setTimeout(() => {
            applySectionsFilterAndSearch();
            window.__tdSectionsPage = 1;
            renderSectionsPage();
            hideTableLoader('sectionsTableContainer');
        }, 300);
    });

    const tbody = document.getElementById('sectionsTableBody');
    if (tbody) {
        // Toggle Public visibility
        tbody.addEventListener('change', function(e) {
            const checkbox = e.target.closest('.section-public-checkbox');
            if (!checkbox) return;
            
            const sectionId = checkbox.dataset.id;
            const checked = checkbox.checked;
            
            fetch(`${BASE_URL}/sections/${sectionId}`, {
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
                    const s = localAllSections.find(item => String(item.id) === String(sectionId));
                    if (s) s.is_public = checked;
                } else {
                    showAlert('danger', res.message || 'Failed to update visibility');
                    checkbox.checked = !checked;
                }
            }).catch(() => {
                showAlert('danger', 'Error updating section visibility');
                checkbox.checked = !checked;
            });
        });

        // Edit Section Name on change/blur
        tbody.addEventListener('change', function(e) {
            const input = e.target.closest('.section-name-input');
            if (!input) return;

            const sectionId = input.dataset.id;
            const newName = input.value.trim();
            if (!newName) {
                showAlert('danger', 'Section name cannot be empty');
                input.value = input.defaultValue || '';
                return;
            }

            fetch(`${BASE_URL}/sections/${sectionId}`, {
                method: 'PUT',
                headers: { 
                    'Content-Type': 'application/json', 
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 
                    'Accept': 'application/json' 
                },
                body: JSON.stringify({ name: newName })
            }).then(res => res.json()).then(res => {
                if (res.status === 'success') {
                    showAlert('success', 'Section name updated successfully!');
                    input.defaultValue = newName;
                    const s = localAllSections.find(item => String(item.id) === String(sectionId));
                    if (s) s.name = newName;
                } else {
                    showAlert('danger', res.message || 'Failed to update section name');
                    input.value = input.defaultValue || '';
                }
            }).catch(() => {
                showAlert('danger', 'Error updating section name');
                input.value = input.defaultValue || '';
            });
        });
    }
}
