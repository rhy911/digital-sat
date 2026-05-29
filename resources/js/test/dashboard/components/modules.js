import { escapeHtml, capitalizeFirstLetter, showTableLoader, hideTableLoader, showAlert } from '../utils/helpers.js';
import { BASE_URL } from '../core/config.js';

export function moduleDifficultyBadgeClass(level) {
    if (level === 'hard') {
        return 'bg-rose-950/30 text-rose-400 border-rose-500/20';
    }
    if (level === 'easy') {
        return 'bg-emerald-950/30 text-emerald-400 border-emerald-500/20';
    }
    return 'bg-indigo-950/30 text-indigo-400 border-indigo-500/20';
}

let localAllModules = [];

function renderModuleRowHtml(mod) {
    const diffClass = moduleDifficultyBadgeClass(mod.difficulty_level);

    let linkedHtml = '';
    const sections = mod.sections || [];
    if (sections.length === 0) {
        linkedHtml = '<span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-950/30 text-amber-400 border border-amber-500/20">'
            + '<i class="bi bi-unlock mr-1.5"></i> Standalone'
            + '</span>';
    } else {
        linkedHtml = '<div class="flex flex-col gap-1.5">' + sections.map(function (sec) {
            const testTitle = (sec.test && sec.test.title) ? sec.test.title : 'Test';
            return '<div>'
                + '<span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-950/30 text-emerald-400 border border-emerald-500/20">'
                + '<i class="bi bi-tag mr-1.5"></i> ' + escapeHtml(testTitle) + ' &raquo; <span class="ml-1 opacity-80">' + escapeHtml(sec.name) + '</span>'
                + '</span>'
                + '</div>';
        }).join('') + '</div>';
    }

    const isOwner = mod.created_by === window.__currentUserId || window.__currentUserRole === 'admin';
    const creatorName = mod.created_by_name || (mod.creator ? (mod.creator.username || mod.creator.email) : 'Admin');

    const createdByHtml = '<span class="text-xs font-semibold text-slate-350 truncate max-w-[110px] block" title="' + escapeHtml(creatorName) + '">' + escapeHtml(creatorName) + '</span>';

    const publicHtml = isOwner
        ? '<div class="flex items-center"><input type="checkbox" data-id="' + escapeHtml(mod.id) + '" class="w-4 h-4 text-indigo-600 border-slate-800 bg-slate-400/60 rounded-xs cursor-pointer module-public-toggle" ' + (mod.is_public ? 'checked' : '') + '></div>'
        : '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 uppercase tracking-wider"><i class="bi bi-globe mr-1"></i> Shared</span>';

    const actionsHtml = isOwner
        ? '<div class="flex justify-end gap-1">'
          + '<button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-indigo-400 hover:bg-indigo-500/10 rounded-xl clone-module-btn transition-all cursor-pointer" data-id="' + escapeHtml(mod.id) + '" title="Clone Module"><i class="bi bi-copy"></i></button>'
          + '<button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl delete-module-btn transition-all cursor-pointer" data-id="' + escapeHtml(mod.id) + '" title="Delete"><i class="bi bi-trash"></i></button>'
          + '</div>'
        : '<div class="text-slate-500 text-[11px] font-semibold text-right pr-2">Read-Only</div>';

    const rowClass = isOwner ? 'hover:bg-slate-800/20 border-b border-slate-800/30 transition-colors' : 'hover:bg-slate-800/20 border-b border-slate-800/30 transition-colors row-shared opacity-80 border-dashed';

    return '<tr class="' + rowClass + '">'
        + '<td class="px-6 py-4 font-semibold text-slate-500">' + escapeHtml(mod.id) + '</td>'
        + '<td class="px-6 py-4">'
        + '<code class="font-mono text-xs bg-slate-800/60 px-2 py-1 rounded-lg text-indigo-400 font-bold border border-slate-700/40">' + escapeHtml(mod.key || 'N/A') + '</code>'
        + '</td>'
        + '<td class="px-6 py-4">' + linkedHtml + '</td>'
        + '<td class="px-6 py-4">'
        + '<span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-800/60 text-slate-300 border border-slate-700/40">Module ' + escapeHtml(mod.module_number) + '</span>'
        + '</td>'
        + '<td class="px-6 py-4">'
        + '<span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold border ' + diffClass + '">'
        + escapeHtml(capitalizeFirstLetter(mod.difficulty_level))
        + '</span>'
        + '</td>'
        + '<td class="px-6 py-4">' + createdByHtml + '</td>'
        + '<td class="px-6 py-4">' + publicHtml + '</td>'
        + '<td class="px-6 py-4 font-bold text-slate-300">' + escapeHtml(mod.duration_minutes) + '<span class="text-[10px] ml-0.5 opacity-40 text-slate-400 uppercase tracking-tighter">min</span></td>'
        + '<td class="px-6 py-4 font-extrabold text-white">' + escapeHtml(mod.total_questions) + '</td>'
        + '<td class="px-6 py-4 text-right">' + actionsHtml + '</td>'
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
        + '<div class="w-20 h-20 rounded-full bg-slate-900 flex items-center justify-center mb-6">'
        + '<i class="bi bi-inbox text-4xl text-slate-500"></i>'
        + '</div>'
        + '<h4 class="text-lg font-bold text-white">No modules found</h4>'
        + '<p class="text-sm text-slate-400 mt-1 max-w-xs mx-auto">You haven\'t created any reusable modules yet. Create one to start building your tests.</p>'
        + '<button class="mt-8 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl transition-all shadow-lg shadow-indigo-600/20" onclick="window.dispatchEvent(new CustomEvent(\'open-offcanvas\', { detail: \'createModuleOffcanvas\' }))">'
        + 'Create Your First Module'
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
    html += '<span class="text-xs font-extrabold text-slate-500 uppercase tracking-wide">Page ' + cur + ' of ' + last + ' <span class="mx-1 text-slate-700">•</span> ' + total + ' modules</span>';
    html += '<div class="flex gap-2">';
    html += '<button type="button" class="px-3 py-1.5 text-xs font-extrabold uppercase tracking-wider rounded-xl border border-slate-800/80 bg-slate-900/60 text-slate-200 hover:bg-slate-800 hover:text-white transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer" data-m-page="prev"' + (cur <= 1 ? ' disabled' : '') + '>Previous</button>';
    html += '<button type="button" class="px-3 py-1.5 text-xs font-extrabold uppercase tracking-wider rounded-xl border border-slate-800/80 bg-slate-900/60 text-slate-200 hover:bg-slate-800 hover:text-white transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer" data-m-page="next"' + (cur >= last ? ' disabled' : '') + '>Next</button>';
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
