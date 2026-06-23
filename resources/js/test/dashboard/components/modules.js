import { escapeHtml, capitalizeFirstLetter, showTableLoader, hideTableLoader, showAlert } from '../utils/helpers.js';
import { BASE_URL } from '../core/config.js';

export function moduleDifficultyBadgeClass(level) {
    if (level === 'hard') {
        return 'status-chip-archived text-rose-700 bg-rose-50 border-rose-100';
    }
    if (level === 'easy') {
        return 'status-chip-active text-emerald-700 bg-emerald-50 border-emerald-100';
    }
    return 'status-chip-shared text-indigo-700 bg-indigo-50 border-indigo-100';
}

let localAllModules = [];

function renderModuleRowHtml(mod) {
    const diffClass = moduleDifficultyBadgeClass(mod.difficulty_level);

    let linkedHtml = '';
    const sections = mod.sections || [];
    if (sections.length === 0) {
        linkedHtml = '<span class="status-chip status-chip-readonly">'
            + '<i class="bi bi-unlock mr-1.5"></i> Standalone'
            + '</span>';
    } else {
        linkedHtml = '<div class="flex flex-col gap-1.5">' + sections.map(function (sec) {
            const testTitle = (sec.test && sec.test.title) ? sec.test.title : 'Test';
            return '<div>'
                + '<span class="status-chip status-chip-shared">'
                + '<i class="bi bi-tag mr-1.5"></i> ' + escapeHtml(testTitle) + ' &raquo; <span class="ml-1 opacity-90 text-slate-800 font-medium normal-case">' + escapeHtml(sec.name) + '</span>'
                + '</span>'
                + '</div>';
        }).join('') + '</div>';
    }

    const isOwner = mod.created_by === window.__currentUserId || window.__currentUserRole === 'admin';
    const creatorName = mod.created_by_name || (mod.creator ? (mod.creator.username || mod.creator.email) : 'Admin');

    const createdByHtml = '<span class="text-xs font-semibold text-slate-600 truncate max-w-[110px] block" title="' + escapeHtml(creatorName) + '">' + escapeHtml(creatorName) + '</span>';

    const publicHtml = isOwner
        ? '<div class="flex items-center justify-center"><input type="checkbox" data-id="' + escapeHtml(mod.id) + '" class="w-4 h-4 text-indigo-600 border-slate-300 bg-white rounded cursor-pointer module-public-toggle" ' + (mod.is_public ? 'checked' : '') + ' title="' + (mod.is_public ? 'Public (Click to make Private)' : 'Private (Click to make Public)') + '" aria-label="Toggle public visibility"></div>'
        : '<div class="flex items-center justify-center"><input type="checkbox" checked disabled class="w-4 h-4 text-slate-400 border-slate-200 bg-slate-100 rounded cursor-not-allowed opacity-60" title="Shared (View only)" aria-label="Shared resource"></div>';

    const actionsHtml = isOwner
        ? '<div class="actions-dropdown">'
          + '<button type="button" class="px-2.5 py-1.5 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 cursor-pointer hover:bg-slate-50 flex items-center gap-1" data-dropdown-trigger="true" aria-expanded="false" aria-label="Toggle actions menu">'
          + 'Actions <i class="bi bi-chevron-down text-[10px]"></i>'
          + '</button>'
          + '<div class="dropdown-menu hidden">'
          + '<button type="button" class="dropdown-item reuse-module-btn" data-id="' + escapeHtml(mod.id) + '" data-section-id="' + escapeHtml((sections[0] && sections[0].id) || '') + '"><i class="bi bi-copy mr-2"></i>Reuse in test</button>'
          + '<button type="button" class="dropdown-item clone-module-btn" data-id="' + escapeHtml(mod.id) + '"><i class="bi bi-copy mr-2"></i> Clone</button>'
          + '<button type="button" class="dropdown-item text-danger delete-module-btn" data-id="' + escapeHtml(mod.id) + '"><i class="bi bi-trash mr-2"></i> Delete</button>'
          + '</div>'
          + '</div>'
        : '<button type="button" class="min-h-9 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 reuse-module-btn" data-id="' + escapeHtml(mod.id) + '" data-section-id="' + escapeHtml((sections[0] && sections[0].id) || '') + '">Reuse</button>';

    const rowClass = isOwner ? '' : 'row-shared';

    return '<tr class="' + rowClass + '">'
        + '<td class="font-semibold text-slate-400 text-center">' + escapeHtml(mod.id) + '</td>'
        + '<td>' + linkedHtml + '</td>'
        + '<td class="text-center">'
        + '<span class="status-chip status-chip-readonly">Module ' + escapeHtml(mod.module_number) + '</span>'
        + '</td>'
        + '<td class="text-center">'
        + '<span class="status-chip border ' + diffClass + '">'
        + escapeHtml(capitalizeFirstLetter(mod.difficulty_level))
        + '</span>'
        + '</td>'
        + '<td>' + createdByHtml + '</td>'
        + '<td class="text-center">' + publicHtml + '</td>'
        + '<td class="font-bold text-slate-700 text-center">' + escapeHtml(mod.duration_minutes) + '<span class="text-[10px] ml-0.5 opacity-50 uppercase tracking-tighter">min</span></td>'
        + '<td class="text-center font-bold text-xs">'
        + '<span class="text-slate-900 font-extrabold">' + (mod.questions_count !== undefined ? mod.questions_count : 0) + '</span>'
        + '<span class="text-slate-400 font-normal"> / ' + escapeHtml(mod.total_questions) + '</span>'
        + '</td>'
        + '<td class="text-center">' + actionsHtml + '</td>'
        + '</tr>';
}

let currentModulesData = null;
let currentModulesRenderId = 0;
let currentFilteredModules = [];

if (typeof window.__tdModulesPage === 'undefined') {
    window.__tdModulesPage = 1;
}
if (typeof window.__tdModulesPerPage === 'undefined') {
    window.__tdModulesPerPage = 30;
}

function _renderModulesChunked(tbody, items, emptyHtml) {
    currentModulesRenderId++;
    const renderId = currentModulesRenderId;
    
    if (!items.length) {
        tbody.innerHTML = emptyHtml;
        return;
    }
    
    tbody.innerHTML = '';
    let index = 0;
    const chunkSize = 20;
    
    function renderChunk() {
        if (renderId !== currentModulesRenderId) return; // Abort if a new render started
        
        const chunk = items.slice(index, index + chunkSize);
        if (!chunk.length) return;
        
        tbody.insertAdjacentHTML('beforeend', chunk.map(renderModuleRowHtml).join(''));
        index += chunkSize;
        
        if (index < items.length) {
            requestAnimationFrame(() => setTimeout(renderChunk, 0));
        }
    }
    
    renderChunk();
}

function renderModulesPage() {
    const tbody = document.getElementById('modulesTableBody');
    if (!tbody) return;

    const page = window.__tdModulesPage || 1;
    const perPage = window.__tdModulesPerPage || 30;
    
    const start = (page - 1) * perPage;
    const end = page * perPage;
    const sliced = currentFilteredModules.slice(start, end);

    const emptyHtml = '<tr>'
        + '<td colspan="8" class="px-6 py-20 text-center">'
        + '<div class="flex flex-col items-center justify-center">'
        + '<div class="w-20 h-20 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center mb-6">'
        + '<i class="bi bi-inbox text-4xl text-slate-400"></i>'
        + '</div>'
        + '<h4 class="text-lg font-bold text-slate-800">No modules found</h4>'
        + '<p class="text-sm text-slate-500 mt-1 max-w-xs mx-auto">Create one module, then link it to any section that should use it.</p>'
        + '<button class="mt-8 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-lg transition-colors shadow-sm" onclick="window.dispatchEvent(new CustomEvent(\'open-offcanvas\', { detail: \'createModuleOffcanvas\' }))">'
        + 'Create module'
        + '</button>'
        + '</div>'
        + '</td>'
        + '</tr>';

    _renderModulesChunked(tbody, sliced, emptyHtml);
    renderModulesPagination(currentFilteredModules.length);
}

export function renderModulesPagination(total) {
    const wrap = document.getElementById('modulesPoolPagination');
    if (!wrap) return;
    
    if (total === 0) {
        wrap.innerHTML = '';
        return;
    }
    
    const cur = window.__tdModulesPage || 1;
    const perPage = window.__tdModulesPerPage || 30;
    const last = Math.ceil(total / perPage) || 1;
    
    let html = '<div class="flex flex-wrap justify-between items-center w-full gap-3 px-2">';
    html += '<span class="text-xs font-semibold text-slate-600">Page ' + cur + ' of ' + last + ' <span class="mx-1 text-slate-300">•</span> ' + total + ' modules</span>';
    html += '<div class="flex gap-2">';
    html += '<button type="button" class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors duration-150 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer" data-m-page="prev"' + (cur <= 1 ? ' disabled' : '') + '>Previous</button>';
    html += '<button type="button" class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors duration-150 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer" data-m-page="next"' + (cur >= last ? ' disabled' : '') + '>Next</button>';
    html += '</div></div>';
    wrap.innerHTML = html;
    
    if (wrap.dataset.bound !== '1') {
        wrap.dataset.bound = '1';
        wrap.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-m-page]');
            if (!btn || btn.disabled) return;
            
            const dir = btn.getAttribute('data-m-page');
            const curPage = window.__tdModulesPage || 1;
            
            showTableLoader('modulesTableContainer');
            
            setTimeout(() => {
                if (dir === 'prev') {
                    window.__tdModulesPage = Math.max(1, curPage - 1);
                } else if (dir === 'next') {
                    window.__tdModulesPage = curPage + 1;
                }
                renderModulesPage();
                hideTableLoader('modulesTableContainer');
            }, 400);
        });
    }
}

function applyModulesFilterAndSearch() {
    const searchInput = document.getElementById('modulesTableSearch');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const showShared = document.getElementById('modulesShowSharedToggle')?.checked;

    let filtered = localAllModules;
    if (window.__currentUserRole === 'teacher' && !showShared) {
        filtered = localAllModules.filter(mod => mod.created_by === window.__currentUserId);
    }

    if (query) {
        filtered = filtered.filter(mod => {
            const key = (mod.key || '').toLowerCase();
            const id = String(mod.id);
            const type = ('module ' + mod.module_number).toLowerCase();
            const diff = (mod.difficulty_level || '').toLowerCase();
            
            const matchesBasic = key.includes(query) || id.includes(query) || type.includes(query) || diff.includes(query);
            if (matchesBasic) return true;
            
            const sections = mod.sections || [];
            return sections.some(sec => {
                const secName = (sec.name || '').toLowerCase();
                const secType = (sec.type || '').toLowerCase();
                const testTitle = (sec.test && sec.test.title) ? sec.test.title.toLowerCase() : '';
                const testName = (sec.test && sec.test.name) ? sec.test.name.toLowerCase() : '';
                
                return secName.includes(query) || 
                       secType.includes(query) || 
                       testTitle.includes(query) || 
                       testName.includes(query);
            });
        });
    }

    currentFilteredModules = filtered;
}

export function renderModulesTable(allModules) {
    if (currentModulesData === allModules) return;
    currentModulesData = allModules;
    localAllModules = allModules;
    
    const tbody = document.getElementById('modulesTableBody');
    if (!tbody) return;

    applyModulesFilterAndSearch();
    window.__tdModulesPage = 1;
    renderModulesPage();
}

export function initModulesSearch() {
    const searchInput = document.getElementById('modulesTableSearch');
    if (!searchInput) return;

    let modulesSearchTimeout = null;

    searchInput.addEventListener('input', function(e) {
        showTableLoader('modulesTableContainer');
        if (modulesSearchTimeout) clearTimeout(modulesSearchTimeout);
        modulesSearchTimeout = setTimeout(() => {
            applyModulesFilterAndSearch();
            window.__tdModulesPage = 1;
            renderModulesPage();
            setTimeout(() => {
                hideTableLoader('modulesTableContainer');
            }, 200);
        }, 400);
    });

    document.getElementById('modulesShowSharedToggle')?.addEventListener('change', function() {
        showTableLoader('modulesTableContainer');
        setTimeout(() => {
            applyModulesFilterAndSearch();
            window.__tdModulesPage = 1;
            renderModulesPage();
            hideTableLoader('modulesTableContainer');
        }, 300);
    });

    document.getElementById('modulesTableBody')?.addEventListener('change', function(e) {
        const checkbox = e.target.closest('.module-public-toggle');
        if (!checkbox) return;
        
        const moduleId = checkbox.dataset.id;
        const checked = checkbox.checked;
        
        fetch(`${BASE_URL}/modules/${moduleId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ is_public: checked })
        }).then(res => res.json()).then(res => {
            if (res.status === 'success') {
                showAlert('success', 'Module visibility updated!');
                const mod = localAllModules.find(m => String(m.id) === String(moduleId));
                if (mod) mod.is_public = checked;
            } else {
                showAlert('danger', res.message || 'Failed to update visibility');
                checkbox.checked = !checked;
            }
        }).catch(() => {
            showAlert('danger', 'Error updating module visibility');
            checkbox.checked = !checked;
        });
    });
}
