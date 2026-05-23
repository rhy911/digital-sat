import { escapeHtml, capitalizeFirstLetter } from '../utils/helpers.js';

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

    return '<tr class="hover:bg-slate-800/20 border-b border-slate-800/30 transition-colors">'
        + '<td class="px-6 py-4 font-semibold text-slate-400">' + escapeHtml(mod.id) + '</td>'
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
        + '<td class="px-6 py-4 font-bold text-slate-300">' + escapeHtml(mod.duration_minutes) + '<span class="text-[10px] ml-0.5 opacity-40 text-slate-400 uppercase tracking-tighter">min</span></td>'
        + '<td class="px-6 py-4 font-extrabold text-white">' + escapeHtml(mod.total_questions) + '</td>'
        + '<td class="px-6 py-4 text-right">'
        + '<div class="flex justify-end gap-1">'
        + '<button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-indigo-400 hover:bg-indigo-500/10 rounded-xl clone-module-btn transition-all" data-id="' + escapeHtml(mod.id) + '" title="Clone Module">'
        + '<i class="bi bi-copy"></i>'
        + '</button>'
        + '<button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl delete-module-btn transition-all" data-id="' + escapeHtml(mod.id) + '" title="Delete">'
        + '<i class="bi bi-trash"></i>'
        + '</button>'
        + '</div>'
        + '</td>'
        + '</tr>';
}

export function renderModulesTable(allModules) {
    localAllModules = allModules;
    const tbody = document.getElementById('modulesTableBody');
    if (!tbody) {
        return;
    }
    if (!allModules.length) {
        tbody.innerHTML = '<tr>'
            + '<td colspan="8" class="px-6 py-20 text-center">'
            + '<div class="flex flex-col items-center justify-center">'
            + '<div class="w-20 h-20 rounded-full bg-slate-900 flex items-center justify-center mb-6">'
            + '<i class="bi bi-inbox text-4xl text-slate-500"></i>'
            + '</div>'
            + '<h4 class="text-lg font-bold text-white">No modules found</h4>'
            + '<p class="text-sm text-slate-400 mt-1 max-w-xs mx-auto">You haven\'t created any reusable modules yet. Create one to start building your tests.</p>'
            + '<button class="mt-8 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl transition-all shadow-lg shadow-indigo-600/20" data-bs-toggle="offcanvas" data-bs-target="#createModuleOffcanvas">'
            + 'Create Your First Module'
            + '</button>'
            + '</div>'
            + '</td>'
            + '</tr>';
        return;
    }
    tbody.innerHTML = allModules.map(renderModuleRowHtml).join('');
}

export function initModulesSearch() {
    const searchInput = document.getElementById('modulesTableSearch');
    if (!searchInput) return;

    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase().trim();
        const tbody = document.getElementById('modulesTableBody');
        if (!tbody) return;

        if (!query) {
            tbody.innerHTML = localAllModules.map(renderModuleRowHtml).join('');
            return;
        }

        const filtered = localAllModules.filter(mod => {
            const key = (mod.key || '').toLowerCase();
            const id = String(mod.id);
            const type = ('module ' + mod.module_number).toLowerCase();
            const diff = (mod.difficulty_level || '').toLowerCase();
            
            return key.includes(query) || id.includes(query) || type.includes(query) || diff.includes(query);
        });

        if (!filtered.length) {
            tbody.innerHTML = '<tr>'
                + '<td colspan="8" class="px-6 py-20 text-center">'
                + '<div class="flex flex-col items-center justify-center">'
                + '<h4 class="text-slate-450 font-bold">No matching modules found</h4>'
                + '</div>'
                + '</td>'
                + '</tr>';
            return;
        }

        tbody.innerHTML = filtered.map(renderModuleRowHtml).join('');
    });
}
