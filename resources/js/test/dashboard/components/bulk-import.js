import { 
    BULK_PREVIEW_URL, CSV_BULK_PREVIEW_URL, BULK_STORE_URL, CSV_BULK_URL, BASE_URL 
} from '../core/config.js';
import { 
    showAlert, showCustomConfirm, getTomSelectValue, humanizeUnderscores, 
    processMedia, captureTomSelectPreservation 
} from '../utils/helpers.js';

export function setBulkQuestionsJson(obj) {
    const ta = document.getElementById('bulkQuestionsJson');
    if (ta) {
        ta.value = JSON.stringify(obj, null, 2);
    }
}

export function downloadJsonFile(filename, obj) {
    const blob = new Blob([JSON.stringify(obj, null, 2)], { type: 'application/json;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

export function csvEscapeCell(val) {
    const s = String(val);
    if (/[",\n\r]/.test(s)) {
        return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
}

export function downloadCsvFile(filename, rows) {
    const lines = rows.map(function (row) {
        return row.map(csvEscapeCell).join(',');
    });
    const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

export function validateItemLocal(item, sectionType) {
    const blockers = {};
    const warnings = {};

    if (!item.stem || item.stem.trim() === '') {
        blockers.stem = "Question stem is required.";
    }

    const qType = item.question_type || '';
    if (qType !== 'multiple_choice' && qType !== 'student_produced_response') {
        blockers.question_type = "Question type must be multiple_choice or student_produced_response.";
    }

    if (sectionType === 'reading_writing') {
        if (qType === 'student_produced_response') {
            blockers.question_type = "Reading & Writing section only allows multiple choice questions.";
        }
        const passageContent = item.passage_content || (item.passage ? (typeof item.passage === 'string' ? item.passage : item.passage.content) : '') || '';
        if (passageContent.trim() === '') {
            blockers.passage_content = "Passage content is required for Reading & Writing questions.";
        }
    }

    if (qType === 'multiple_choice') {
        const correctChoice = String(item.correct_choice || '').trim().toUpperCase();
        if (!['A', 'B', 'C', 'D'].includes(correctChoice)) {
            blockers.correct_choice = "Correct choice is required and must be A, B, C, or D.";
        }
        if (!item.choices || item.choices.length < 4 || item.choices.some(c => !c.content || c.content.trim() === '')) {
            blockers.choices = "Multiple choice questions require choices A, B, C, and D with text.";
        }
    } else if (qType === 'student_produced_response') {
        const sprAnswers = item.spr_correct_answers;
        if (!sprAnswers || sprAnswers.length === 0 || sprAnswers.every(ans => !ans || ans.trim() === '')) {
            blockers.spr_correct_answers = "At least one accepted correct answer is required for SPR.";
        }
    }

    const difficulty = String(item.difficulty || '').trim().toLowerCase();
    if (!['easy', 'medium', 'hard'].includes(difficulty)) {
        warnings.difficulty = "Difficulty is missing or invalid. Will default to 'medium'.";
    }

    const domain = String(item.skill_domain || '').trim();
    if (!domain) {
        warnings.skill_domain = "Domain is missing. Will default based on section type.";
    }

    return { blockers, warnings };
}

export function flatToStructured(flat) {
    const item = {
        question_type: flat.question_type || 'multiple_choice',
        difficulty: flat.difficulty || 'medium',
        skill_domain: flat.skill_domain || '',
        stem: flat.stem || '',
        explanation: flat.explanation || '',
    };

    if (flat.passage_content && flat.passage_content.trim() !== '') {
        item.passage = {
            content: flat.passage_content,
            source_title: flat.passage_source_title || ''
        };
        item.passage_content = flat.passage_content;
    }

    if (item.question_type === 'multiple_choice') {
        item.choices = [
            { label: 'A', content: flat.choice_a || '', is_correct: flat.correct_choice === 'A', order: 1 },
            { label: 'B', content: flat.choice_b || '', is_correct: flat.correct_choice === 'B', order: 2 },
            { label: 'C', content: flat.choice_c || '', is_correct: flat.correct_choice === 'C', order: 3 },
            { label: 'D', content: flat.choice_d || '', is_correct: flat.correct_choice === 'D', order: 4 },
        ];
        item.correct_choice = flat.correct_choice;
    } else {
        let spr = flat.spr_correct_answers || '';
        if (Array.isArray(spr)) {
            item.spr_correct_answers = spr;
        } else {
            item.spr_correct_answers = spr.split(/[|;]/).map(a => a.trim()).filter(Boolean);
        }
    }

    return item;
}

export function structuredToFlat(item, index) {
    const flat = {
        row_index: index + 1,
        question_type: item.question_type || 'multiple_choice',
        difficulty: item.difficulty || 'medium',
        skill_domain: item.skill_domain || '',
        stem: item.stem || '',
        passage_content: item.passage_content || (item.passage ? (typeof item.passage === 'string' ? item.passage : item.passage.content) : '') || '',
        explanation: item.explanation || '',
        choice_a: '',
        choice_b: '',
        choice_c: '',
        choice_d: '',
        correct_choice: item.correct_choice || '',
        spr_correct_answers: '',
        original_errors: item.errors || []
    };

    if (item.choices && Array.isArray(item.choices)) {
        item.choices.forEach(c => {
            const label = c.label.toUpperCase();
            if (label === 'A') flat.choice_a = c.content || '';
            if (label === 'B') flat.choice_b = c.content || '';
            if (label === 'C') flat.choice_c = c.content || '';
            if (label === 'D') flat.choice_d = c.content || '';
            if (c.is_correct) flat.correct_choice = label;
        });
    }

    if (item.spr_correct_answers) {
        if (Array.isArray(item.spr_correct_answers)) {
            flat.spr_correct_answers = item.spr_correct_answers.join('|');
        } else {
            flat.spr_correct_answers = item.spr_correct_answers;
        }
    }

    return flat;
}

let validationGridTable = null;

function createCellFormatter(fieldName) {
    return function (cell) {
        const rowData = cell.getRow().getData();
        const value = cell.getValue() || '';
        
        const moduleSelect = document.getElementById('bulkQuestionModule');
        let sectionType = 'reading_writing';
        if (moduleSelect && moduleSelect.selectedOptions && moduleSelect.selectedOptions[0]) {
            sectionType = moduleSelect.selectedOptions[0].getAttribute('data-section-type') || 'reading_writing';
        }

        const struct = flatToStructured(rowData);
        const { blockers, warnings } = validateItemLocal(struct, sectionType);

        const cellEl = cell.getElement();
        cellEl.style.position = "relative";
        cellEl.removeAttribute('title');

        let errorMsg = null;
        let isBlocker = false;

        if (fieldName === 'stem' && blockers.stem) {
            errorMsg = blockers.stem;
            isBlocker = true;
        } else if (fieldName === 'question_type' && blockers.question_type) {
            errorMsg = blockers.question_type;
            isBlocker = true;
        } else if (fieldName === 'passage_content' && blockers.passage_content) {
            errorMsg = blockers.passage_content;
            isBlocker = true;
        } else if (fieldName === 'correct_choice' && blockers.correct_choice && rowData.question_type === 'multiple_choice') {
            errorMsg = blockers.correct_choice;
            isBlocker = true;
        } else if (fieldName === 'spr_correct_answers' && blockers.spr_correct_answers && rowData.question_type === 'student_produced_response') {
            errorMsg = blockers.spr_correct_answers;
            isBlocker = true;
        } else if (['choice_a', 'choice_b', 'choice_c', 'choice_d'].includes(fieldName) && blockers.choices && rowData.question_type === 'multiple_choice') {
            errorMsg = blockers.choices;
            isBlocker = true;
        } else if (fieldName === 'difficulty' && warnings.difficulty) {
            errorMsg = warnings.difficulty;
            isBlocker = false;
        } else if (fieldName === 'skill_domain' && warnings.skill_domain) {
            errorMsg = warnings.skill_domain;
            isBlocker = false;
        }

        if (errorMsg) {
            cellEl.style.backgroundColor = isBlocker ? "#f8d7da" : "#fff3cd";
            cellEl.style.color = isBlocker ? "#842029" : "#664d03";
            cellEl.style.fontWeight = "bold";
            cellEl.setAttribute('title', errorMsg);
        } else {
            cellEl.style.backgroundColor = "";
            cellEl.style.color = "";
            cellEl.style.fontWeight = "";
        }

        return value;
    };
}

export function openValidationGrid(items) {
    const container = document.getElementById('validation-grid-container');
    if (!container) return;

    container.classList.remove('hidden');
    container.scrollIntoView({ behavior: 'smooth' });

    const flatItems = items.map((item, idx) => structuredToFlat(item, idx));

    if (validationGridTable) {
        validationGridTable.destroy();
    }

    validationGridTable = new Tabulator("#validation-grid", {
        data: flatItems,
        layout: "fitColumns",
        reactiveData: true,
        columns: [
            { title: "Idx", field: "row_index", width: 50, headerSort: false, frozen: true },
            {
                title: "Type",
                field: "question_type",
                width: 120,
                editor: "list",
                editorParams: { values: ["multiple_choice", "student_produced_response"] },
                formatter: createCellFormatter('question_type'),
                headerSort: false
            },
            {
                title: "Stem",
                field: "stem",
                editor: "textarea",
                formatter: createCellFormatter('stem'),
                headerSort: false,
                width: 200
            },
            {
                title: "Passage",
                field: "passage_content",
                editor: "textarea",
                formatter: createCellFormatter('passage_content'),
                headerSort: false,
                width: 180
            },
            {
                title: "Choice A",
                field: "choice_a",
                editor: "input",
                formatter: createCellFormatter('choice_a'),
                headerSort: false
            },
            {
                title: "Choice B",
                field: "choice_b",
                editor: "input",
                formatter: createCellFormatter('choice_b'),
                headerSort: false
            },
            {
                title: "Choice C",
                field: "choice_c",
                editor: "input",
                formatter: createCellFormatter('choice_c'),
                headerSort: false
            },
            {
                title: "Choice D",
                field: "choice_d",
                editor: "input",
                formatter: createCellFormatter('choice_d'),
                headerSort: false
            },
            {
                title: "Correct MCQ",
                field: "correct_choice",
                editor: "input",
                formatter: createCellFormatter('correct_choice'),
                width: 110,
                headerSort: false
            },
            {
                title: "SPR Answers",
                field: "spr_correct_answers",
                editor: "input",
                formatter: createCellFormatter('spr_correct_answers'),
                width: 120,
                headerSort: false
            },
            {
                title: "Difficulty",
                field: "difficulty",
                width: 100,
                editor: "list",
                editorParams: { values: ["easy", "medium", "hard"] },
                formatter: createCellFormatter('difficulty'),
                headerSort: false
            },
            {
                title: "Domain",
                field: "skill_domain",
                editor: "input",
                formatter: createCellFormatter('skill_domain'),
                headerSort: false,
                width: 130
            },
            {
                title: "Explanation",
                field: "explanation",
                editor: "textarea",
                headerSort: false,
                width: 150
            }
        ]
    });

    validationGridTable.on("cellEdited", function (cell) {
        cell.getRow().reformat();
        updateGridStatusCounts();
    });

    updateGridStatusCounts();
}

export function updateGridStatusCounts() {
    if (!validationGridTable) return;
    const rows = validationGridTable.getData();
    
    const moduleSelect = document.getElementById('bulkQuestionModule');
    let sectionType = 'reading_writing';
    if (moduleSelect && moduleSelect.selectedOptions && moduleSelect.selectedOptions[0]) {
        sectionType = moduleSelect.selectedOptions[0].getAttribute('data-section-type') || 'reading_writing';
    }

    let blockerCount = 0;
    let warningCount = 0;

    rows.forEach(rowData => {
        const struct = flatToStructured(rowData);
        const { blockers, warnings } = validateItemLocal(struct, sectionType);
        blockerCount += Object.keys(blockers).length;
        warningCount += Object.keys(warnings).length;
    });

    const blockerBadge = document.getElementById('gridBlockerCount');
    const warningBadge = document.getElementById('gridWarningCount');
    const statusAlert = document.getElementById('gridStatusAlert');
    const statusTitle = document.getElementById('gridStatusTitle');
    const statusMsg = document.getElementById('gridStatusMsg');
    const importApprovedBtn = document.getElementById('gridImportApprovedBtn');

    if (blockerBadge) blockerBadge.textContent = `${blockerCount} Blocker(s)`;
    if (warningBadge) warningBadge.textContent = `${warningCount} Warning(s)`;

    if (blockerCount > 0) {
        statusAlert.className = "p-4 rounded-xl mb-5 bg-rose-500/5 border border-rose-500/15 text-rose-300 flex items-center justify-between shadow-xl gap-4";
        statusTitle.textContent = "Blocker Errors Found";
        statusMsg.textContent = "You must resolve all blockers highlighted in red before those rows can be imported.";
        importApprovedBtn.textContent = "Import Approved Rows";
        importApprovedBtn.className = "px-6 py-3 bg-amber-700 hover:bg-amber-800 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl cursor-pointer";
    } else if (warningCount > 0) {
        statusAlert.className = "p-4 rounded-xl mb-5 bg-amber-500/5 border border-amber-500/15 text-amber-300 flex items-center justify-between shadow-xl gap-4";
        statusTitle.textContent = "Warnings Present";
        statusMsg.textContent = "Grid has minor warnings highlighted in yellow. You are ready to import all rows; missing details will use defaults.";
        importApprovedBtn.textContent = "Import All Rows";
        importApprovedBtn.className = "px-6 py-3 bg-emerald-700 hover:bg-emerald-800 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl cursor-pointer";
    } else {
        statusAlert.className = "p-4 rounded-xl mb-5 bg-emerald-500/5 border border-emerald-500/15 text-emerald-300 flex items-center justify-between shadow-xl gap-4";
        statusTitle.textContent = "Validation Passed Successfully";
        statusMsg.textContent = "All rows are 100% valid! You are ready to import.";
        importApprovedBtn.textContent = "Import All Rows";
        importApprovedBtn.className = "px-6 py-3 bg-emerald-700 hover:bg-emerald-800 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl cursor-pointer";
    }
}

export function renderPreview(items) {
    const container = document.getElementById('previewContent');
    if (!container) return;

    if (!items || !items.length) {
        container.innerHTML = '<div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-sm font-semibold">No items found to preview.</div>';
        return;
    }

    let html = '';
    items.forEach((item, index) => {
        const stemProcessed = processMedia(item.stem || '');
        let passageHtml = '';
        if (item.passage) {
            let content = '';
            let title = '';
            if (typeof item.passage === 'string') {
                content = processMedia(item.passage);
            } else if (item.passage.content) {
                content = processMedia(item.passage.content);
                title = item.passage.source_title || '';
            }
            if (content.trim()) {
                passageHtml = `
                    <div class="p-3 mb-3 bg-slate-900/40 border-l-4 border-indigo-500 rounded-r-lg shadow-sm">
                        <h6 class="text-indigo-400 text-xs font-bold mb-1.5 flex items-center gap-1"><i class="bi bi-justify-left"></i> Passage</h6>
                        <div class="passage-content text-slate-300 text-xs leading-relaxed" style="max-height: 150px; overflow-y: auto;">
                            ${content}
                        </div>
                        ${title ? `<div class="mt-2 text-slate-500 text-[10px]">Source: ${title}</div>` : ''}
                    </div>
                `;
            }
        }

        const sectionBadge = item.section_type
            ? `<span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700/60 font-semibold text-[10px]">${item.section_type === 'reading_writing' ? 'Reading & Writing' : 'Math'}</span>`
            : '';

        let choicesHtml = '';
        if (item.choices && item.choices.length) {
            choicesHtml = `
                <h6 class="text-xs font-bold text-slate-400 mb-2">Choices:</h6>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 mb-4">
                    ${item.choices.map(c => {
                        const content = processMedia(c.content || '');
                        return `
                            <div class="p-3 border rounded-xl h-100 ${c.is_correct ? 'bg-emerald-500/10 border-emerald-500/30 shadow-sm' : 'bg-slate-900/20 border-slate-800/80'}">
                                <div class="flex items-center gap-2">
                                    <strong class="flex items-center justify-center w-6 h-6 rounded-full bg-slate-800/80 border border-slate-700/60 text-xs text-slate-300">${c.label}</strong>
                                    <div class="grow text-xs text-slate-200">${content}</div>
                                    ${c.is_correct ? '<i class="bi bi-check-circle-fill text-emerald-400"></i>' : ''}
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        }

        let sprHtml = '';
        if (item.spr_correct_answers && item.spr_correct_answers.length) {
            sprHtml = `
                <div class="mb-4">
                    <h6 class="text-xs font-bold text-slate-400 mb-2">Accepted Answers (SPR):</h6>
                    <div class="p-3 bg-emerald-500/5 border border-emerald-500/15 rounded-xl flex flex-wrap gap-2">
                        ${item.spr_correct_answers.map(ans => `<span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">${ans}</span>`).join('')}
                    </div>
                    ${item.spr_hint ? `<div class="mt-1.5 text-xs text-slate-500 italic">Hint: ${item.spr_hint}</div>` : ''}
                </div>
            `;
        }

        let expHtml = '';
        if (item.explanation) {
            expHtml = `
                <div class="mt-3 p-3 bg-slate-900/40 border border-slate-800/60 rounded-xl text-xs">
                    <h6 class="font-bold text-slate-400 mb-1.5 flex items-center gap-1"><i class="bi bi-info-circle"></i> Explanation:</h6>
                    <div class="text-slate-400 leading-relaxed">${processMedia(item.explanation)}</div>
                </div>
            `;
        }

        html += `
            <div class="dash-panel p-5 mb-4">
                <div class="flex justify-between items-center flex-wrap gap-2 border-b border-slate-800/80 pb-3 mb-3">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-extrabold text-[10px]">Item ${index + 1}</span>
                        ${sectionBadge}
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-extrabold text-[10px] uppercase">${humanizeUnderscores(item.skill_domain || '')}</span>
                        ${item.skill_subdomain ? `<span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700/60 font-semibold text-[10px]">${item.skill_subdomain}</span>` : ''}
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700/60 font-bold text-[10px] uppercase">${humanizeUnderscores(item.difficulty || '')}</span>
                        ${item.is_pretest ? '<span class="inline-flex items-center px-2 py-0.5 rounded bg-rose-500/10 text-rose-400 border border-rose-500/20 font-extrabold text-[10px] uppercase">Pretest</span>' : ''}
                        ${item.external_id ? `<small class="text-slate-500 text-[10px]">ID: ${item.external_id}</small>` : ''}
                    </div>
                </div>
                <div class="space-y-4">
                    ${passageHtml}
                    <div>
                        <h6 class="text-xs font-bold text-slate-400 mb-2">Question Stem:</h6>
                        <div class="p-3.5 bg-slate-900/20 border border-slate-800/80 rounded-xl text-slate-200 text-xs leading-relaxed">${stemProcessed}</div>
                    </div>
                    ${choicesHtml}
                    ${sprHtml}
                    ${expHtml}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'importPreviewModal' }));

    if (window.smartRenderMath) {
        window.smartRenderMath(container);
    } else if (window.renderMathInElement) {
        window.renderMathInElement(container, {
            delimiters: [
                { left: '$$', right: '$$', display: false },
                { left: '\\\\[', right: '\\\\]', display: true },
            ],
            throwOnError: false
        });
    }
}

export async function handlePreview(isCsv) {
    const fileInput = document.getElementById(isCsv ? 'bulkCsvFile' : 'bulkJsonFile');
    const file = fileInput?.files?.[0];
    const ta = document.getElementById('bulkQuestionsJson');
    const url = isCsv ? CSV_BULK_PREVIEW_URL : BULK_PREVIEW_URL;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };

    const formModule = getTomSelectValue('bulkQuestionModule');
    if (!formModule) {
        showAlert('danger', 'Please select a Target Module in STEP 1 before previewing.');
        return;
    }

    try {
        let response;
        if (file) {
            const fd = new FormData();
            fd.append(isCsv ? 'csv_file' : 'json_file', file);
            fd.append('module_id', formModule);
            response = await fetch(url, { method: 'POST', headers: headers, body: fd });
        } else if (!isCsv && ta?.value.trim()) {
            let parsed = JSON.parse(ta.value.trim());
            const payload = Array.isArray(parsed) ? { items: parsed, module_id: formModule } : { ...parsed, module_id: formModule };
            response = await fetch(url, {
                method: 'POST',
                headers: { ...headers, 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
        } else {
            showAlert('danger', isCsv ? 'Select a CSV file first.' : 'Select a JSON file or content.');
            return;
        }

        const result = await response.json();
        if (response.ok) {
            openValidationGrid(result.data.items);
        } else {
            showAlert('danger', result.message || 'Preview failed');
        }
    } catch (error) {
        showAlert('danger', 'Error: ' + error.message);
    }
}

export function initPremiumDropzones() {
    const dropzones = document.querySelectorAll('.file-dropzone');
    dropzones.forEach(zone => {
        const input = zone.querySelector('input[type="file"]');
        const display = zone.querySelector('.file-name-display');
        const icon = zone.querySelector('.bi');
        const instruction = zone.querySelector('.drag-instruction');

        if (!input || !display) return;

        ['dragenter', 'dragover'].forEach(eventName => {
            zone.addEventListener(eventName, (e) => {
                e.preventDefault();
                zone.classList.add('border-indigo-500', 'bg-indigo-500/5', 'shadow-lg');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, (e) => {
                e.preventDefault();
                zone.classList.remove('border-indigo-500', 'bg-indigo-500/5', 'shadow-lg');
            }, false);
        });

        zone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length) {
                input.files = files;
                input.dispatchEvent(new Event('change'));
            }
        });

        input.addEventListener('change', (e) => {
            const file = e.target.files?.[0];
            if (file) {
                display.textContent = '✓ ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                display.classList.remove('hidden');
                if (icon) icon.classList.replace('text-slate-500', 'text-emerald-400');
                if (instruction) instruction.classList.add('text-emerald-400');
            } else {
                display.classList.add('hidden');
                display.textContent = '';
                if (icon) icon.classList.replace('text-emerald-400', 'text-slate-500');
                if (instruction) instruction.classList.remove('text-emerald-400');
            }
        });
    });
}

export function initBulkImport() {
    initPremiumDropzones();

    document.getElementById('bulkJsonFile')?.addEventListener('change', function (e) {
        const f = e.target.files && e.target.files[0];
        const ta = document.getElementById('bulkQuestionsJson');
        if (!f || !ta) {
            return;
        }
        const reader = new FileReader();
        reader.onload = function () {
            try {
                const parsed = JSON.parse(reader.result);
                ta.value = JSON.stringify(parsed, null, 2);
            } catch (err) {
                showAlert('warning', 'Could not parse file for preview: ' + err.message);
            }
        };
        reader.onerror = function () {
            showAlert('danger', 'Could not read the selected file.');
        };
        reader.readAsText(f, 'UTF-8');
    });

    document.getElementById('bulkLoadExampleRwBtn')?.addEventListener('click', function () {
        setBulkQuestionsJson(window.TestDashboardExamples?.RW_JSON);
    });
    document.getElementById('bulkLoadExampleMathBtn')?.addEventListener('click', function () {
        setBulkQuestionsJson(window.TestDashboardExamples?.MATH_JSON);
    });
    document.getElementById('bulkDownloadRwSampleBtn')?.addEventListener('click', function () {
        downloadJsonFile('bulk-sample-reading-writing.json', window.TestDashboardExamples?.RW_JSON);
    });
    document.getElementById('bulkDownloadMathSampleBtn')?.addEventListener('click', function () {
        downloadJsonFile('bulk-sample-math.json', window.TestDashboardExamples?.MATH_JSON);
    });

    document.getElementById('bulkDownloadRwSampleCsvBtn')?.addEventListener('click', function () {
        const header = [
            'question_type', 'difficulty', 'skill_domain', 'stem', 'passage_content', 'passage_genre',
            'correct_choice', 'choice_a_content', 'choice_b_content', 'choice_c_content', 'choice_d_content', 'explanation'
        ];
        const row = [
            'multiple_choice', 'medium', 'information_and_ideas', 'Which choice best describes the **main idea** of the text?',
            '<p>The researcher noted that early observations were incomplete, yet they shaped every later hypothesis.</p>',
            'natural_science', 'B', 'Early observations were useless.', 'Initial incomplete work still influenced later science.',
            'Later teams refused to use older data.', 'Hypotheses are never revised.', 'The passage stresses that early incomplete observations still shaped later hypotheses.'
        ];
        downloadCsvFile('bulk-sample-reading-writing.csv', [header, row]);
    });

    document.getElementById('bulkDownloadMathSampleCsvBtn')?.addEventListener('click', function () {
        const header = [
            'question_type', 'difficulty', 'skill_domain', 'stem',
            'correct_choice', 'choice_a_content', 'choice_b_content', 'choice_c_content', 'choice_d_content', 'explanation',
            'spr_correct_answers', 'spr_hint'
        ];
        downloadCsvFile('bulk-sample-math.csv', [
            header,
            ['multiple_choice', 'easy', 'algebra', 'What is 2 + 2?', 'B', '3', '4', '5', '6', 'Sum is 4.', '', ''],
            ['student_produced_response', 'medium', 'advanced_math', 'If x^2 = 9, positive x?', '', '', '', '', '', '', '3|3.0', 'One positive number']
        ]);
    });

    document.getElementById('bulkPreviewBtn')?.addEventListener('click', () => handlePreview(false));
    document.getElementById('bulkCsvPreviewBtn')?.addEventListener('click', () => handlePreview(true));

    document.getElementById('bulkClearEditorBtn')?.addEventListener('click', function () {
        const ta = document.getElementById('bulkQuestionsJson');
        if (ta) ta.value = '';
    });

    document.getElementById('bulkImportSubmitBtn')?.addEventListener('click', async function () {
        const ta = document.getElementById('bulkQuestionsJson');
        const fileInput = document.getElementById('bulkJsonFile');
        const file = fileInput?.files?.[0];
        const formModule = getTomSelectValue('bulkQuestionModule');
        const formStart = document.getElementById('bulkStartPosition')?.value || 1;

        if (!formModule) { showAlert('danger', 'Please select a Target Module in STEP 1.'); return; }

        try {
            let response;
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf };

            if (file) {
                const fd = new FormData();
                fd.append('json_file', file);
                fd.append('module_id', formModule);
                fd.append('start_position', formStart);
                response = await fetch(BULK_STORE_URL, { method: 'POST', headers: headers, body: fd });
            } else {
                const parsed = JSON.parse(ta.value.trim() || '{}');
                const payload = { module_id: formModule, start_position: formStart, items: Array.isArray(parsed) ? parsed : (parsed.items || []) };
                response = await fetch(BULK_STORE_URL, {
                    method: 'POST',
                    headers: { ...headers, 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
            }

            const result = await response.json();
            if (response.ok) {
                showAlert('success', result.message || 'Bulk import completed.');
                if (window.refreshTestDashboardData) {
                    await window.refreshTestDashboardData(captureTomSelectPreservation(null));
                } else {
                    window.location.reload();
                }
            } else {
                if (result.data && result.data.items) {
                    showAlert('warning', 'Import failed due to validation errors. We loaded them into the validation grid below for correction.');
                    openValidationGrid(result.data.items);
                } else {
                    showAlert('danger', result.message || 'Bulk import failed');
                }
            }
        } catch (error) { showAlert('danger', 'Error: ' + error.message); }
    });

    document.getElementById('bulkCsvImportSubmitBtn')?.addEventListener('click', async function () {
        const fileInput = document.getElementById('bulkCsvFile');
        const file = fileInput?.files?.[0];
        const formModule = getTomSelectValue('bulkQuestionModule');
        const formStart = document.getElementById('bulkStartPosition')?.value || 1;

        if (!file || !formModule) { showAlert('danger', 'CSV file and Target Module are required.'); return; }

        const fd = new FormData();
        fd.append('csv_file', file);
        fd.append('module_id', formModule);
        fd.append('start_position', formStart);

        try {
            const response = await fetch(CSV_BULK_URL, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: fd
            });
            const result = await response.json();
            if (response.ok) {
                showAlert('success', result.message || 'CSV import completed.');
                if (window.refreshTestDashboardData) {
                    await window.refreshTestDashboardData(captureTomSelectPreservation(null));
                } else {
                    window.location.reload();
                }
            } else {
                if (result.data && result.data.items) {
                    showAlert('warning', 'CSV Import failed due to validation errors. We loaded them into the validation grid below for correction.');
                    openValidationGrid(result.data.items);
                } else {
                    showAlert('danger', result.message || 'CSV import failed');
                }
            }
        } catch (error) { showAlert('danger', 'Error: ' + error.message); }
    });

    document.getElementById('bulkZipImportBtn')?.addEventListener('click', async function () {
        const fileInput = document.getElementById('bulkZipFile');
        const moduleId = getTomSelectValue('bulkQuestionModule');
        if (!moduleId || !fileInput.files[0]) { showAlert('danger', 'Module and ZIP file required.'); return; }
        const fd = new FormData();
        fd.append('zip_file', fileInput.files[0]);
        fd.append('module_id', moduleId);
        fd.append('start_position', document.getElementById('bulkStartPosition')?.value || 1);
        
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = 'Importing...';
        
        try {
            const response = await fetch(`${BASE_URL}/questions/bulk-zip`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: fd
            });
            const result = await response.json();
            if (response.ok) {
                showAlert('success', result.message);
                fileInput.value = '';
                if (window.refreshTestDashboardData) {
                    await window.refreshTestDashboardData(captureTomSelectPreservation(null));
                } else {
                    window.location.reload();
                }
            } else {
                showAlert('danger', result.message || 'ZIP import failed.');
            }
        } catch (err) {
            showAlert('danger', 'Error: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-arrow-up text-lg leading-none"></i> Import ZIP Package';
        }
    });

    // Grid buttons bindings
    document.getElementById('gridCloseBtn')?.addEventListener('click', () => {
        document.getElementById('validation-grid-container').classList.add('hidden');
    });
    
    document.getElementById('gridCancelBtn')?.addEventListener('click', async () => {
        if (await showCustomConfirm('Discard import items and reset the validation grid?', 'warning', 'Discard Import')) {
            document.getElementById('validation-grid-container').classList.add('hidden');
            if (validationGridTable) validationGridTable.destroy();
            validationGridTable = null;
        }
    });
    
    document.getElementById('gridRevalidateBtn')?.addEventListener('click', () => {
        if (validationGridTable) {
            validationGridTable.redraw(true);
            updateGridStatusCounts();
            showAlert('success', 'Grid visual validations re-rendered successfully.');
        }
    });
    
    document.getElementById('gridImportApprovedBtn')?.addEventListener('click', async function () {
        if (!validationGridTable) return;

        const formModule = getTomSelectValue('bulkQuestionModule');
        const formStart = document.getElementById('bulkStartPosition')?.value || 1;
        
        const moduleSelect = document.getElementById('bulkQuestionModule');
        let sectionType = 'reading_writing';
        if (moduleSelect && moduleSelect.selectedOptions && moduleSelect.selectedOptions[0]) {
            sectionType = moduleSelect.selectedOptions[0].getAttribute('data-section-type') || 'reading_writing';
        }

        const rows = validationGridTable.getData();
        const approvedItems = [];
        const blockedIndices = [];

        rows.forEach(rowData => {
            const struct = flatToStructured(rowData);
            const { blockers } = validateItemLocal(struct, sectionType);
            if (Object.keys(blockers).length === 0) {
                approvedItems.push(struct);
            } else {
                blockedIndices.push(rowData.row_index);
            }
        });

        if (approvedItems.length === 0) {
            showAlert('danger', 'No approved rows found. Please fix the blocker errors (red cells) before importing.');
            return;
        }

        let confirmMsg = `Import ${approvedItems.length} approved question(s) into Target Module?`;
        if (blockedIndices.length > 0) {
            confirmMsg = `Import ${approvedItems.length} approved question(s) and skip ${blockedIndices.length} blocked row(s) (Q#${blockedIndices.join(', Q#')})?`;
        }

        if (await showCustomConfirm(confirmMsg, 'info', 'Confirm Import')) {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            try {
                const response = await fetch(BULK_STORE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        module_id: formModule,
                        start_position: formStart,
                        items: approvedItems
                    })
                });

                const result = await response.json();
                if (response.ok) {
                    showAlert('success', result.message || 'Import completed successfully!');
                    document.getElementById('validation-grid-container').classList.add('hidden');
                    if (validationGridTable) validationGridTable.destroy();
                    validationGridTable = null;

                    // Clear the file input and textarea
                    const jsonInput = document.getElementById('bulkJsonFile');
                    if (jsonInput) jsonInput.value = '';
                    const csvInput = document.getElementById('bulkCsvFile');
                    if (csvInput) csvInput.value = '';
                    const ta = document.getElementById('bulkQuestionsJson');
                    if (ta) ta.value = '';

                    const dropzones = document.querySelectorAll('.file-dropzone');
                    dropzones.forEach(zone => {
                        const display = zone.querySelector('.file-name-display');
                        const icon = zone.querySelector('.bi');
                        const instruction = zone.querySelector('.drag-instruction');
                        if (display) {
                            display.classList.add('hidden');
                            display.textContent = '';
                        }
                        if (icon) icon.classList.replace('text-success', 'text-slate-500');
                        if (instruction) instruction.classList.remove('text-success');
                    });

                    if (window.refreshTestDashboardData) {
                        await window.refreshTestDashboardData(captureTomSelectPreservation(null));
                    } else {
                        window.location.reload();
                    }
                } else {
                    showAlert('danger', result.message || 'Import submission failed.');
                }
            } catch (err) {
                showAlert('danger', 'Import error: ' + err.message);
            }
        }
    });
}
