import { BASE_URL } from '../core/config.js';
import { showAlert, escapeHtml, humanizeUnderscores, showTableLoader, hideTableLoader, formatDateToShort } from '../utils/helpers.js';

let localAllTests = [];
let currentFilteredTests = [];
let currentTestsRenderId = 0;

if (typeof window.__tdTestsPage === 'undefined') {
    window.__tdTestsPage = 1;
}
if (typeof window.__tdTestsPerPage === 'undefined') {
    window.__tdTestsPerPage = 30;
}

function renderTestRowHtml(t) {
    const isOwner = t.is_owner;
    const creatorName = t.created_by_name || 'Admin';
    const createdByHtml = `<span class="text-xs font-medium text-slate-600 truncate max-w-[110px] block" title="${escapeHtml(creatorName)}">${escapeHtml(creatorName)}</span>`;

    // Title input/span
    const titleHtml = isOwner
        ? `<input type="text" class="test-title-input w-full bg-transparent border-0 hover:bg-slate-100 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none rounded-lg px-2 py-1 font-semibold text-slate-800 transition-all" value="${escapeHtml(t.title)}" data-id="${t.id}">`
        : `<span class="px-2 py-1 font-semibold text-slate-800">${escapeHtml(t.title)}</span>`;

    // Public toggle checkbox
    const publicHtml = isOwner
        ? `<div class="flex items-center justify-center"><input type="checkbox" data-id="${t.id}" class="w-4 h-4 text-indigo-600 border-slate-300 bg-white rounded cursor-pointer test-public-checkbox" ${t.is_public ? 'checked' : ''} title="${t.is_public ? 'Public (Click to make Private)' : 'Private (Click to make Public)'}" aria-label="Toggle public visibility"></div>`
        : `<div class="flex items-center justify-center"><input type="checkbox" checked disabled class="w-4 h-4 text-slate-400 border-slate-200 bg-slate-100 rounded cursor-not-allowed opacity-60" title="Shared (View only)" aria-label="Shared resource"></div>`;

    let chipClass = 'status-chip status-chip-readonly';
    if (t.status === 'active') chipClass = 'status-chip status-chip-active';
    if (t.status === 'draft') chipClass = 'status-chip status-chip-draft';
    if (t.status === 'archived') chipClass = 'status-chip status-chip-archived';
    const statusHtml = `<div class="flex items-center justify-center w-full h-full"><span class="${chipClass}">${escapeHtml(humanizeUnderscores(t.status))}</span></div>`;

    // Actions dropdown
    const actionsHtml = isOwner
        ? `<div class="actions-dropdown">
            <button type="button" class="px-2.5 py-1.5 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 cursor-pointer hover:bg-slate-50 flex items-center gap-1" data-dropdown-trigger="true" aria-expanded="false" aria-label="Toggle actions menu">
                Actions <i class="bi bi-chevron-down text-[10px]"></i>
            </button>
            <div class="dropdown-menu hidden">
                ${t.status !== 'active' ? `<button type="button" class="dropdown-item change-test-status-btn" data-id="${t.id}" data-status="active"><i class="bi bi-send-check mr-2"></i> Publish</button>` : ''}
                ${t.status !== 'draft' ? `<button type="button" class="dropdown-item change-test-status-btn" data-id="${t.id}" data-status="draft"><i class="bi bi-pencil mr-2"></i> Return to draft</button>` : ''}
                ${t.status !== 'archived' ? `<button type="button" class="dropdown-item change-test-status-btn" data-id="${t.id}" data-status="archived"><i class="bi bi-archive mr-2"></i> Archive</button>` : ''}
                <button type="button" class="dropdown-item clone-test-btn" data-id="${t.id}"><i class="bi bi-copy mr-2"></i> Clone</button>
                <button type="button" class="dropdown-item text-danger delete-test-btn" data-id="${t.id}"><i class="bi bi-trash mr-2"></i> Delete</button>
            </div>
          </div>`
        : `<span class="status-chip status-chip-readonly">Read-Only</span>`;

    const rowClass = isOwner ? '' : 'row-shared';

    return `<tr class="${rowClass}">
        <td class="font-semibold text-slate-400 text-center">${escapeHtml(t.id)}</td>
        <td>${titleHtml}</td>
        <td>${escapeHtml(t.type)}</td>
        <td class="text-center font-semibold text-slate-500">${escapeHtml(t.created_at || 'N/A')}</td>
        <td>${createdByHtml}</td>
        <td class="text-center">${publicHtml}</td>
        <td class="text-center">${statusHtml}</td>
        <td class="font-bold text-slate-700 text-center">${escapeHtml(t.duration)}<span class="text-[10px] ml-0.5 opacity-50 uppercase tracking-tighter">min</span></td>
        <td class="text-center">${actionsHtml}</td>
    </tr>`;
}

function _renderTestsChunked(tbody, items, emptyHtml) {
    currentTestsRenderId++;
    const renderId = currentTestsRenderId;
    
    if (!items.length) {
        tbody.innerHTML = emptyHtml;
        return;
    }
    
    tbody.innerHTML = '';
    let index = 0;
    const chunkSize = 20;
    
    function renderChunk() {
        if (renderId !== currentTestsRenderId) return;
        
        const chunk = items.slice(index, index + chunkSize);
        if (!chunk.length) return;
        
        tbody.insertAdjacentHTML('beforeend', chunk.map(renderTestRowHtml).join(''));
        index += chunkSize;
        
        if (index < items.length) {
            requestAnimationFrame(() => setTimeout(renderChunk, 0));
        }
    }
    
    renderChunk();
}

function renderTestsPage() {
    const tbody = document.getElementById('testsTableBody');
    if (!tbody) return;

    const page = window.__tdTestsPage || 1;
    const perPage = window.__tdTestsPerPage || 30;
    
    const start = (page - 1) * perPage;
    const end = page * perPage;
    const sliced = currentFilteredTests.slice(start, end);

    const emptyHtml = '<tr>'
        + '<td colspan="9" class="px-6 py-20 text-center">'
        + '<div class="flex flex-col items-center justify-center">'
        + '<div class="w-20 h-20 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center mb-6">'
        + '<i class="bi bi-inbox text-4xl text-slate-400"></i>'
        + '</div>'
        + '<h4 class="text-lg font-bold text-slate-800">No tests found</h4>'
        + '<p class="text-sm text-slate-500 mt-1 max-w-xs mx-auto">Create one practice test to get started.</p>'
        + '</div>'
        + '</td>'
        + '</tr>';

    _renderTestsChunked(tbody, sliced, emptyHtml);
    renderTestsPagination(currentFilteredTests.length);
}

export function renderTestsPagination(total) {
    const wrap = document.getElementById('testsPoolPagination');
    if (!wrap) return;
    
    if (total === 0) {
        wrap.innerHTML = '';
        return;
    }
    
    const cur = window.__tdTestsPage || 1;
    const perPage = window.__tdTestsPerPage || 30;
    const last = Math.ceil(total / perPage) || 1;
    
    let html = '<div class="flex flex-wrap justify-between items-center w-full gap-3 px-2">';
    html += '<span class="text-xs font-semibold text-slate-600">Page ' + cur + ' of ' + last + ' <span class="mx-1 text-slate-300">•</span> ' + total + ' tests</span>';
    html += '<div class="flex gap-2">';
    html += '<button type="button" class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors duration-150 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer" data-t-page="prev"' + (cur <= 1 ? ' disabled' : '') + '>Previous</button>';
    html += '<button type="button" class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors duration-150 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer" data-t-page="next"' + (cur >= last ? ' disabled' : '') + '>Next</button>';
    html += '</div></div>';
    wrap.innerHTML = html;
    
    if (wrap.dataset.bound !== '1') {
        wrap.dataset.bound = '1';
        wrap.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-t-page]');
            if (!btn || btn.disabled) return;
            
            const dir = btn.getAttribute('data-t-page');
            const curPage = window.__tdTestsPage || 1;
            
            showTableLoader('testsTableContainer');
            
            setTimeout(() => {
                if (dir === 'prev') {
                    window.__tdTestsPage = Math.max(1, curPage - 1);
                } else if (dir === 'next') {
                    window.__tdTestsPage = curPage + 1;
                }
                renderTestsPage();
                hideTableLoader('testsTableContainer');
            }, 400);
        });
    }
}

function applyTestsFilterAndSearch() {
    const searchInput = document.getElementById('testsTableSearch');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const showShared = document.getElementById('testsShowSharedToggle')?.checked;

    let filtered = localAllTests;
    if (window.__currentUserRole === 'teacher' && !showShared) {
        filtered = localAllTests.filter(t => t.is_owner);
    }

    if (query) {
        filtered = filtered.filter(t => {
            const title = (t.title || '').toLowerCase();
            const type = (t.type || '').toLowerCase();
            const id = String(t.id);
            return title.includes(query) || type.includes(query) || id.includes(query);
        });
    }

    currentFilteredTests = filtered;
}

export function renderTestsTable(tests) {
    localAllTests = tests.map(function(t) {
        return {
            id: t.id,
            title: t.title,
            type: humanizeUnderscores(t.test_type || t.type),
            status: t.status,
            duration: t.total_duration_minutes || t.duration || 0,
            created_by: t.created_by,
            created_by_name: t.created_by_name || (t.creator ? (t.creator.username || t.creator.email) : 'Admin'),
            created_at: formatDateToShort(t.created_at),
            is_public: t.is_public,
            is_owner: t.is_owner !== undefined ? t.is_owner : (t.created_by === window.__currentUserId || window.__currentUserRole === 'admin')
        };
    });
    
    const tableContainer = document.getElementById('testsTableContainer');
    const emptyState = document.getElementById('testsEmptyState');
    if (!tableContainer) return;

    applyTestsFilterAndSearch();

    if (!currentFilteredTests.length && !tests.length) {
        if (emptyState) emptyState.classList.remove('hidden');
        tableContainer.classList.add('hidden');
        return;
    }

    if (emptyState) emptyState.classList.add('hidden');
    tableContainer.classList.remove('hidden');

    window.__tdTestsPage = 1;
    renderTestsPage();
    initTestsEvents();
}

let eventsBound = false;
function initTestsEvents() {
    if (eventsBound) return;
    eventsBound = true;

    const searchInput = document.getElementById('testsTableSearch');
    if (searchInput) {
        let testsSearchTimeout = null;
        searchInput.addEventListener('input', function(e) {
            showTableLoader('testsTableContainer');
            if (testsSearchTimeout) clearTimeout(testsSearchTimeout);
            testsSearchTimeout = setTimeout(() => {
                applyTestsFilterAndSearch();
                window.__tdTestsPage = 1;
                renderTestsPage();
                setTimeout(() => {
                    hideTableLoader('testsTableContainer');
                }, 200);
            }, 400);
        });
    }

    document.getElementById('testsShowSharedToggle')?.addEventListener('change', function() {
        showTableLoader('testsTableContainer');
        setTimeout(() => {
            applyTestsFilterAndSearch();
            window.__tdTestsPage = 1;
            renderTestsPage();
            hideTableLoader('testsTableContainer');
        }, 300);
    });

    const tbody = document.getElementById('testsTableBody');
    if (tbody) {
        // Toggle Public visibility
        tbody.addEventListener('change', function(e) {
            const checkbox = e.target.closest('.test-public-checkbox');
            if (!checkbox) return;
            
            const testId = checkbox.dataset.id;
            const checked = checkbox.checked;
            
            fetch(`${BASE_URL}/tests/${testId}`, {
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
                    const t = localAllTests.find(item => String(item.id) === String(testId));
                    if (t) t.is_public = checked;
                } else {
                    showAlert('danger', res.message || 'Failed to update visibility');
                    checkbox.checked = !checked;
                }
            }).catch(() => {
                showAlert('danger', 'Error updating test visibility');
                checkbox.checked = !checked;
            });
        });

        // Edit Title on change/blur
        tbody.addEventListener('change', function(e) {
            const input = e.target.closest('.test-title-input');
            if (!input) return;

            const testId = input.dataset.id;
            const newTitle = input.value.trim();
            if (!newTitle) {
                showAlert('danger', 'Title cannot be empty');
                input.value = input.defaultValue || '';
                return;
            }

            fetch(`${BASE_URL}/tests/${testId}`, {
                method: 'PUT',
                headers: { 
                    'Content-Type': 'application/json', 
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 
                    'Accept': 'application/json' 
                },
                body: JSON.stringify({ title: newTitle })
            }).then(res => res.json()).then(res => {
                if (res.status === 'success') {
                    showAlert('success', 'Test title updated successfully!');
                    input.defaultValue = newTitle;
                    const t = localAllTests.find(item => String(item.id) === String(testId));
                    if (t) t.title = newTitle;
                } else {
                    showAlert('danger', res.message || 'Failed to update title');
                    input.value = input.defaultValue || '';
                }
            }).catch(() => {
                showAlert('danger', 'Error updating test title');
                input.value = input.defaultValue || '';
            });
        });
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
