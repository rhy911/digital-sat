import { SKILL_DOMAINS, BULK_STORE_URL, BASE_URL, QUESTIONS_LIST_URL } from '../core/config.js';
import { 
    getPremiumToolbar, compileMarkdownToHtml, getTomSelectValue, showAlert, showCustomConfirm,
    stripTags, humanizeUnderscores
} from '../utils/helpers.js';

let builderBlockCount = 0;
const builderEditors = {};
const debouncers = {};

export function debouncedUpdateLivePreview(block) {
    const index = block.dataset.index;
    if (debouncers[index]) clearTimeout(debouncers[index]);
    debouncers[index] = setTimeout(() => {
        renderLivePreviewCard(block);
    }, 250);
}

export function renderLivePreviewCard(block) {
    const index = block.dataset.index;
    const drawer = document.getElementById('builderLivePreviewDrawer');
    if (!drawer) return;

    // Remove placeholder if it exists
    const placeholder = drawer.querySelector('.text-slate-500.text-center, .text-muted.text-center');
    if (placeholder) placeholder.remove();

    let previewCard = drawer.querySelector(`[data-preview-index="${index}"]`);
    if (!previewCard) {
        previewCard = document.createElement('div');
        previewCard.className = "card mb-3 border-0 shadow-sm rounded-3 live-preview-card overflow-hidden";
        previewCard.style.borderLeft = "4px solid #ffc107";
        previewCard.dataset.previewIndex = index;
        drawer.appendChild(previewCard);
    }

    const existingQs = window.__builderExistingQuestions || [];
    let qNum = 1;
    const qId = block.dataset.questionId;
    if (qId) {
        const qIndex = existingQs.findIndex(q => String(q.id) === String(qId));
        qNum = qIndex !== -1 ? (existingQs[qIndex].question_number || (qIndex + 1)) : 1;
    } else {
        const allBlocks = [...document.querySelectorAll('.builder-block')];
        const newBlocks = allBlocks.filter(b => !b.dataset.questionId);
        const draftIndex = newBlocks.indexOf(block);
        qNum = existingQs.length + draftIndex + 1;
    }

    const stemValue = builderEditors[`stem_${index}`] ? builderEditors[`stem_${index}`].value() : '';
    const passageValue = builderEditors[`passage_${index}`] ? builderEditors[`passage_${index}`].value() : '';
    const qType = block.querySelector('.builder-format-mcq').checked ? 'multiple_choice' : 'student_produced_response';

    let passageHtml = '';
    const type = document.getElementById('builderModuleId').selectedOptions[0]?.getAttribute('data-section-type');
    if (type === 'reading_writing' && passageValue.trim()) {
        passageHtml = `<div class="passage-preview p-3 mb-3 bg-light rounded-3 small border-start border-3 border-secondary">${compileMarkdownToHtml(passageValue)}</div>`;
    }

    let questionBodyHtml = '';
    if (qType === 'multiple_choice') {
        const correctLabel = block.querySelector('.builder-correct-radio:checked')?.value || 'A';
        let choicesHtml = '';
        block.querySelectorAll('.builder-choice-content').forEach(input => {
            const label = input.getAttribute('data-label');
            const rawVal = input.value.trim();
            const content = rawVal ? compileMarkdownToHtml(rawVal) : `<span class="text-muted italic">Option ${label} content...</span>`;
            const isCorrect = label === correctLabel;

            choicesHtml += `
                <div class="flex items-center gap-2 mb-2 p-2 rounded border ${isCorrect ? 'border-success bg-success-subtle' : 'border-light'}" style="transition: all 0.2s;">
                    <div class="rounded-circle flex items-center justify-center text-white bg-${isCorrect ? 'success' : 'secondary'} fw-bold" style="width: 24px; height: 24px; font-size: 12px; flex-shrink: 0;">
                        ${label}
                    </div>
                    <div class="grow small">${content}</div>
                </div>
            `;
        });
        questionBodyHtml = `<div class="choices-preview mt-3">${choicesHtml}</div>`;
    } else {
        const sprVal = block.querySelector('.builder-spr-answers').value.trim() || '______';
        questionBodyHtml = `
            <div class="answer-input-container p-3 bg-light rounded-3 mt-3 border border-warning border-opacity-25">
                <label class="d-block mb-2 fw-bold text-dark small"><i class="bi bi-pencil-fill text-warning"></i> Student Produced Response:</label>
                <div class="form-control live-preview-spr-input font-monospace text-center py-2 fs-5 border-warning border-opacity-50" style="max-width: 150px; letter-spacing: 2px;">
                    ${sprVal}
                </div>
            </div>
        `;
    }

    const explanationValue = block.querySelector('.builder-explanation').value.trim();
    let explanationHtml = '';
    if (explanationValue) {
        explanationHtml = `
            <div class="explanation-preview p-2 mt-2 bg-light rounded small text-muted border-top border-light">
                <strong>Explanation:</strong> ${compileMarkdownToHtml(explanationValue)}
            </div>
        `;
    }

    previewCard.innerHTML = `
        <div class="card-header bg-dark text-white py-2 px-3 flex justify-between items-center">
            <span class="fw-bold small text-warning"><i class="bi bi-file-earmark-text"></i> Live Preview Q#${qNum}</span>
            <span class="badge bg-secondary font-monospace" style="font-size: 10px;">${qType === 'multiple_choice' ? 'MCQ' : 'SPR'}</span>
        </div>
        <div class="card-body p-3">
            ${passageHtml}
            <div class="stem-preview fw-semibold text-dark">${stemValue ? compileMarkdownToHtml(stemValue) : '<span class="text-muted italic">Enter question stem to view preview...</span>'}</div>
            ${questionBodyHtml}
            ${explanationHtml}
        </div>
    `;

    if (window.smartRenderMath) {
        window.smartRenderMath(previewCard);
    }
}

export function syncLivePreviewScroll(block) {
    if (!block) return;
    const index = block.dataset.index;
    const previewCard = document.querySelector(`[data-preview-index="${index}"]`);
    const drawer = document.getElementById('builderLivePreviewDrawer');
    if (previewCard && drawer) {
        const drawerRect = drawer.getBoundingClientRect();
        const cardRect = previewCard.getBoundingClientRect();
        const relativeTop = cardRect.top - drawerRect.top + drawer.scrollTop;
        drawer.scrollTo({
            top: relativeTop - 10,
            behavior: 'smooth'
        });
    }
}

export function highlightSidebarItem(block) {
    const navigator = document.getElementById('builderSidebarNavigator');
    if (!navigator) return;
    
    navigator.querySelectorAll('.list-group-item').forEach(el => {
        el.classList.remove('border-amber-500/50', 'bg-amber-500/5');
    });

    const qId = block.dataset.questionId;
    const index = block.dataset.index;
    
    let targetItem = null;
    if (qId) {
        targetItem = navigator.querySelector(`[data-nav-question-id="${qId}"]`);
    } else {
        targetItem = navigator.querySelector(`[data-nav-index="${index}"]`);
    }
    
    if (targetItem) {
        targetItem.classList.add('border-amber-500/50', 'bg-amber-500/5');
        targetItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

export function bindBlockInteractiveEvents(block) {
    const handleActive = () => {
        syncLivePreviewScroll(block);
        highlightSidebarItem(block);
    };

    block.addEventListener('focusin', handleActive);
    block.addEventListener('click', handleActive);
}

export function refreshQuestionBlockNumbers() {
    const existingQs = window.__builderExistingQuestions || [];
    const blocks = document.querySelectorAll('.builder-block');
    const drawer = document.getElementById('builderLivePreviewDrawer');
    const newBlocks = [...blocks].filter(b => !b.dataset.questionId);

    blocks.forEach((block) => {
        const index = block.dataset.index;
        let qNum = 1;
        const qId = block.dataset.questionId;

        if (qId) {
            const qIndex = existingQs.findIndex(q => String(q.id) === String(qId));
            qNum = qIndex !== -1 ? (existingQs[qIndex].question_number || (qIndex + 1)) : 1;
        } else {
            const draftIndex = newBlocks.indexOf(block);
            qNum = existingQs.length + draftIndex + 1;
        }

        const titleEl = block.querySelector('.text-sm.font-extrabold.text-white') || block.querySelector('.text-secondary');
        if (titleEl) {
            if (qId) {
                titleEl.innerHTML = `<i class="bi bi-question-circle text-amber-400"></i> Question #${qNum} <span class="bg-indigo-500/20 text-indigo-300 font-extrabold px-1.5 py-0.5 text-[10px] rounded uppercase ml-2">Existing ID: ${qId}</span>`;
            } else {
                titleEl.innerHTML = `<i class="bi bi-question-circle text-amber-400"></i> Question #${qNum}`;
            }
        }

        if (drawer) {
            const previewCard = drawer.querySelector(`[data-preview-index="${index}"]`);
            if (previewCard) {
                const textEl = previewCard.querySelector('.card-header span.fw-bold');
                if (textEl) {
                    textEl.innerHTML = `<i class="bi bi-file-earmark-text"></i> Live Preview Q#${qNum}`;
                }
            }
        }
    });
}

export function isBlockUnchanged(block) {
    const qId = block.dataset.questionId;
    if (!qId) return false; // Drafts are not considered loaded questions

    window.__builderLoadedQuestionsOriginal = window.__builderLoadedQuestionsOriginal || {};
    const original = window.__builderLoadedQuestionsOriginal[qId];
    if (!original) return true; // Safe fallback

    const index = block.dataset.index;
    const stem = builderEditors[`stem_${index}`] ? builderEditors[`stem_${index}`].value().trim() : '';
    const passageContent = builderEditors[`passage_${index}`] ? builderEditors[`passage_${index}`].value().trim() : '';
    const difficulty = block.querySelector('.builder-difficulty').value;
    const skillDomain = block.querySelector('.builder-domain').value;
    const questionType = block.querySelector('.builder-format-mcq').checked ? 'multiple_choice' : 'student_produced_response';
    const explanation = block.querySelector('.builder-explanation').value.trim();

    if (stem !== original.stem) return false;
    if (passageContent !== original.passage_content) return false;
    if (difficulty !== original.difficulty) return false;
    if (skillDomain !== original.skill_domain) return false;
    if (questionType !== original.question_type) return false;
    if (explanation !== original.explanation) return false;

    if (questionType === 'multiple_choice') {
        const correctChoice = block.querySelector('.builder-correct-radio:checked')?.value || '';
        const originalCorrect = original.choices.find(c => c.is_correct)?.label || '';
        if (correctChoice !== originalCorrect) return false;

        for (const choice of original.choices) {
            const input = block.querySelector(`.builder-choice-content[data-label="${choice.label}"]`);
            if (input && input.value.trim() !== choice.content.trim()) return false;
        }
    } else {
        const sprAnswers = block.querySelector('.builder-spr-answers').value.trim();
        if (sprAnswers !== original.spr_answers) return false;
    }

    return true;
}

export function clearUnchangedQuestions() {
    const blocks = document.querySelectorAll('.builder-block');
    let clearedCount = 0;

    blocks.forEach(block => {
        if (isBlockUnchanged(block)) {
            const index = block.dataset.index;
            if (builderEditors[`stem_${index}`]) { builderEditors[`stem_${index}`].toTextArea(); delete builderEditors[`stem_${index}`]; }
            if (builderEditors[`passage_${index}`]) { builderEditors[`passage_${index}`].toTextArea(); delete builderEditors[`passage_${index}`]; }
            block.remove();

            const previewCard = document.querySelector(`[data-preview-index="${index}"]`);
            if (previewCard) previewCard.remove();

            clearedCount++;
        }
    });

    if (clearedCount > 0) {
        triggerBuilderAutoSave();
        refreshQuestionBlockNumbers();
        updateSidebarNavigator();

        const drawer = document.getElementById('builderLivePreviewDrawer');
        if (drawer && drawer.querySelectorAll('[data-preview-index]').length === 0) {
            drawer.innerHTML = `
                <div class="text-slate-500 text-center py-12 text-xs font-medium">
                    <i class="bi bi-file-earmark-richtext text-3xl block mb-2 text-slate-650"></i>
                    Live compilation of STEM and formulas will appear here in real-time
                </div>
            `;
        }
        showAlert('success', `Cleared ${clearedCount} unchanged opened question(s).`);
    } else {
        showAlert('info', 'No unchanged opened question blocks found.');
    }
}

export function updateSidebarNavigator() {
    const navigator = document.getElementById('builderSidebarNavigator');
    if (!navigator) return;

    const existingQs = window.__builderExistingQuestions || [];
    const blocks = document.querySelectorAll('.builder-block');

    if (existingQs.length === 0 && blocks.length === 0) {
        navigator.innerHTML = `
            <div class="text-slate-500 text-center py-8 text-xs font-medium">
                <i class="bi bi-layers text-2xl block mb-2 text-slate-600"></i>
                No questions. Add a question to start.
            </div>
        `;
        return;
    }

    navigator.innerHTML = '';

    // Create a container for existing questions
    if (existingQs.length > 0) {
        const existingHeader = document.createElement('div');
        existingHeader.className = "text-[10px] font-extrabold text-slate-400 uppercase tracking-widest px-2 py-1 mb-1.5 flex justify-between items-center";
        existingHeader.innerHTML = `<span>Module Questions (${existingQs.length})</span>`;
        navigator.appendChild(existingHeader);

        existingQs.forEach((q, i) => {
            const qId = q.id;
            // Check if this existing question is loaded in the workspace
            const loadedBlock = document.querySelector(`.builder-block[data-question-id="${qId}"]`);
            const isLoaded = !!loadedBlock;

            const item = document.createElement('a');
            item.href = "javascript:void(0)";
            item.dataset.navQuestionId = qId;
            
            if (isLoaded) {
                item.className = "list-group-item list-group-item-action border border-indigo-500/30 bg-indigo-500/5 rounded-xl p-2.5 flex flex-col gap-1 mb-2 hover:bg-indigo-500/10 transition-all duration-150 active-question-item";
            } else {
                item.className = "list-group-item list-group-item-action border border-slate-800/80 bg-slate-950/20 hover:bg-slate-850/30 rounded-xl p-2.5 flex flex-col gap-1 mb-2 transition-all duration-150";
            }

            const stemText = stripTags(q.stem || '');
            const snippet = stemText.length <= 60 ? stemText : stemText.slice(0, 60) + '…';

            const difficultyBadge = q.difficulty
                ? `<span class="bg-slate-800 text-slate-400 font-mono text-[9px] px-1.5 py-0.5 rounded capitalize">${q.difficulty}</span>`
                : '';

            item.innerHTML = `
                <div class="flex items-center justify-between gap-2">
                    <span class="font-extrabold text-xs ${isLoaded ? 'text-indigo-400' : 'text-slate-350'}">
                        Q#${q.question_number || (i + 1)} 
                        <span class="font-extrabold px-1 py-0.5 text-[8px] rounded uppercase ${isLoaded ? 'bg-indigo-500/20 text-indigo-300' : 'bg-slate-800 text-slate-500'}">
                            ${isLoaded ? 'Editing' : 'Stored'}
                        </span>
                    </span>
                    <span class="text-[9px] font-mono text-slate-500">ID: ${q.id}</span>
                </div>
                <div class="text-[10px] ${isLoaded ? 'text-indigo-300/80' : 'text-slate-450'} truncate mt-0.5">
                    ${snippet || '(Empty stem)'}
                </div>
                <div class="flex items-center justify-between gap-1 mt-1">
                    <span class="text-[9px] text-slate-500 truncate max-w-[120px]">${humanizeUnderscores(q.skill_domain || 'No Domain')}</span>
                    ${difficultyBadge}
                </div>
            `;

            item.onclick = async function () {
                if (isLoaded) {
                    renderLivePreviewCard(loadedBlock);
                    loadedBlock.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    syncLivePreviewScroll(loadedBlock);
                    // Highlight the item
                    navigator.querySelectorAll('.list-group-item').forEach(el => el.classList.remove('border-amber-500/50', 'bg-amber-500/5'));
                    item.classList.add('border-amber-500/50', 'bg-amber-500/5');
                } else {
                    item.innerHTML = `
                        <div class="text-slate-500 text-center py-2 text-xs font-medium">
                            <div class="animate-spin inline-block w-4 h-4 border-2 border-amber-500 border-t-transparent rounded-full mr-2 align-middle"></div>
                            <span class="align-middle">Loading details...</span>
                        </div>
                    `;
                    await loadExistingQuestionIntoWorkspace(qId);
                }
            };

            navigator.appendChild(item);
        });
    }

    // Get any new blocks (blocks that do not have data-question-id)
    const newBlocks = [...blocks].filter(b => !b.dataset.questionId);
    if (newBlocks.length > 0) {
        const draftHeader = document.createElement('div');
        draftHeader.className = "text-[10px] font-extrabold text-slate-450 uppercase tracking-widest px-2 py-1 mt-3 mb-1.5";
        draftHeader.textContent = `New Drafts (${newBlocks.length})`;
        navigator.appendChild(draftHeader);

        newBlocks.forEach((block, i) => {
            const index = block.dataset.index;
            const qType = block.querySelector('.builder-format-mcq').checked ? 'MCQ' : 'SPR';
            const difficulty = block.querySelector('.builder-difficulty').value || 'N/A';
            const domainVal = block.querySelector('.builder-domain').value || '';
            const stemVal = builderEditors[`stem_${index}`] ? builderEditors[`stem_${index}`].value() : '';
            const stemText = stripTags(stemVal || '');
            const snippet = stemText.length <= 60 ? stemText : stemText.slice(0, 60) + '…';

            let domainLabel = 'No Domain';
            if (domainVal) {
                domainLabel = domainVal.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
            }

            const item = document.createElement('a');
            item.href = "javascript:void(0)";
            item.dataset.navIndex = index;
            item.className = "list-group-item list-group-item-action border border-slate-800/80 bg-slate-950/20 hover:bg-slate-850/30 rounded-xl p-2.5 flex flex-col gap-1 mb-2 transition-all duration-150";

            item.innerHTML = `
                <div class="flex items-center justify-between gap-2">
                    <span class="font-extrabold text-xs text-amber-400">
                        Q#${existingQs.length + i + 1} 
                        <span class="bg-amber-500/10 border border-amber-500/20 text-amber-400 font-extrabold px-1 py-0.5 text-[8px] rounded uppercase">Draft</span>
                    </span>
                    <span class="badge bg-slate-800 text-slate-400 font-mono text-[8px] uppercase">${qType}</span>
                </div>
                <div class="text-[10px] text-slate-450 truncate mt-0.5">
                    ${snippet || '(Empty stem)'}
                </div>
                <div class="flex items-center justify-between gap-1 mt-1">
                    <span class="text-[9px] text-slate-500 truncate max-w-[120px]">${domainLabel}</span>
                    <span class="bg-slate-800 text-slate-400 font-mono text-[9px] px-1.5 py-0.5 rounded capitalize">${difficulty}</span>
                </div>
            `;

            item.onclick = function () {
                renderLivePreviewCard(block);
                block.scrollIntoView({ behavior: 'smooth', block: 'start' });
                syncLivePreviewScroll(block);
                navigator.querySelectorAll('.list-group-item').forEach(el => el.classList.remove('border-amber-500/50', 'bg-amber-500/5'));
                item.classList.add('border-amber-500/50', 'bg-amber-500/5');
            };

            navigator.appendChild(item);
        });
    }
}

export function addBuilderBlock() {
    builderBlockCount++;
    const template = document.getElementById('builderBlockTemplate').innerHTML;
    const html = template.replace(/{INDEX}/g, builderBlockCount).replace(/{DISPLAY_INDEX}/g, builderBlockCount);

    const container = document.getElementById('builderBlocksContainer');
    const div = document.createElement('div');
    div.innerHTML = html;
    const block = div.firstElementChild;
    container.appendChild(block);

    const stemTextarea = block.querySelector('.builder-stem');
    const passageTextarea = block.querySelector('.builder-passage');
    stemTextarea.id = `stem_${builderBlockCount}`;
    passageTextarea.id = `passage_${builderBlockCount}`;

    syncBuilderBlockDomain(block);

    builderEditors[`stem_${builderBlockCount}`] = new EasyMDE({
        element: stemTextarea,
        placeholder: "Enter question stem...",
        minHeight: "100px",
        toolbar: getPremiumToolbar('builderStem', () => debouncedUpdateLivePreview(block)),
        status: false
    });

    // Listen to change to update preview in real-time
    builderEditors[`stem_${builderBlockCount}`].codemirror.on('change', () => {
        debouncedUpdateLivePreview(block);
        if (window.triggerBuilderAutoSave) window.triggerBuilderAutoSave();
    });

    bindBlockInteractiveEvents(block);
    refreshQuestionBlockNumbers();

    renderLivePreviewCard(block);
    block.scrollIntoView({ behavior: 'smooth', block: 'start' });
    setTimeout(() => syncLivePreviewScroll(block), 100);

    // Bind format toggles
    const mcqRadio = block.querySelector('.builder-format-mcq');
    const sprRadio = block.querySelector('.builder-format-spr');
    const mcqContainer = block.querySelector('.builder-mcq-container');
    const sprContainer = block.querySelector('.builder-spr-container');

    const updateFormatView = () => {
        if (mcqRadio.checked) {
            mcqContainer.classList.remove('hidden');
            sprContainer.classList.add('hidden');
        } else {
            mcqContainer.classList.add('hidden');
            sprContainer.classList.remove('hidden');
        }
        debouncedUpdateLivePreview(block);
        updateSidebarNavigator();
    };

    mcqRadio.addEventListener('change', updateFormatView);
    sprRadio.addEventListener('change', updateFormatView);

    // Bind other changes
    block.querySelectorAll('input, select, textarea').forEach(el => {
        if (el.classList.contains('builder-stem') || el.classList.contains('builder-passage')) return;
        el.addEventListener('input', () => {
            debouncedUpdateLivePreview(block);
            triggerBuilderAutoSave();
            if (el.classList.contains('builder-difficulty') || el.classList.contains('builder-domain')) {
                updateSidebarNavigator();
            }
        });
        el.addEventListener('change', () => {
            debouncedUpdateLivePreview(block);
            triggerBuilderAutoSave();
            if (el.classList.contains('builder-difficulty') || el.classList.contains('builder-domain')) {
                updateSidebarNavigator();
            }
        });
    });

    block.querySelector('.remove-block-btn').onclick = function () {
        const index = block.dataset.index;
        if (builderEditors[`stem_${index}`]) { builderEditors[`stem_${index}`].toTextArea(); delete builderEditors[`stem_${index}`]; }
        if (builderEditors[`passage_${index}`]) { builderEditors[`passage_${index}`].toTextArea(); delete builderEditors[`passage_${index}`]; }
        block.remove();
        triggerBuilderAutoSave();

        // Remove preview card
        const previewCard = document.querySelector(`[data-preview-index="${index}"]`);
        if (previewCard) previewCard.remove();

        const drawer = document.getElementById('builderLivePreviewDrawer');
        if (drawer && drawer.querySelectorAll('[data-preview-index]').length === 0) {
            drawer.innerHTML = `
                <div class="text-slate-500 text-center py-12 text-xs font-medium">
                    <i class="bi bi-file-earmark-richtext text-3xl block mb-2 text-slate-650"></i>
                    Live compilation of STEM and formulas will appear here in real-time
                </div>
            `;
        }

        refreshQuestionBlockNumbers();
        updateSidebarNavigator();
    };

    // Render initial preview
    debouncedUpdateLivePreview(block);
    updateSidebarNavigator();
}

export function syncBuilderBlockDomain(block) {
    const moduleId = getTomSelectValue('builderModuleId');
    if (!moduleId) return;
    const type = document.getElementById('builderModuleId').selectedOptions[0].getAttribute('data-section-type');
    const passageContainer = block.querySelector('.builder-passage-container');
    const index = block.dataset.index;

    if (type === 'reading_writing') {
        passageContainer.classList.remove('hidden');
        if (!builderEditors[`passage_${index}`]) {
            builderEditors[`passage_${index}`] = new EasyMDE({
                element: block.querySelector('.builder-passage'),
                placeholder: "Enter passage content...",
                minHeight: "150px",
                toolbar: getPremiumToolbar('builderPassage', () => debouncedUpdateLivePreview(block)),
                status: false
            });

            builderEditors[`passage_${index}`].codemirror.on('change', () => {
                debouncedUpdateLivePreview(block);
                if (window.triggerBuilderAutoSave) window.triggerBuilderAutoSave();
            });
        }
    } else passageContainer.classList.add('hidden');

    const domainSelect = block.querySelector('.builder-domain');
    const currentVal = domainSelect.value;
    domainSelect.innerHTML = '<option value="">Select domain...</option>';
    if (type && SKILL_DOMAINS[type]) {
        SKILL_DOMAINS[type].forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.value; opt.textContent = d.label;
            if (d.value === currentVal) opt.selected = true;
            domainSelect.appendChild(opt);
        });
    }
}

export function getBuilderEditors() { return builderEditors; }
export function resetBuilderBlockCount() { builderBlockCount = 0; }

export function clearBuilderWorkspace() {
    // Destroy all EasyMDE editors to prevent memory leaks
    Object.keys(builderEditors).forEach(key => {
        if (builderEditors[key]) {
            builderEditors[key].toTextArea();
            delete builderEditors[key];
        }
    });
    
    // Clear containers
    const container = document.getElementById('builderBlocksContainer');
    if (container) container.innerHTML = '';
    
    const previewDrawer = document.getElementById('builderLivePreviewDrawer');
    if (previewDrawer) {
        previewDrawer.innerHTML = `
            <div class="text-slate-500 text-center py-12 text-xs font-medium">
                <i class="bi bi-file-earmark-richtext text-3xl block mb-2 text-slate-600"></i>
                Live compilation of STEM and formulas will appear here in real-time
            </div>
        `;
    }
    
    // Reset block count
    resetBuilderBlockCount();
    
    // Clear localStorage draft
    localStorage.removeItem('sat_builder_draft');
    
    // Clear existing questions list
    window.__builderExistingQuestions = [];
    window.__builderLoadedQuestionsOriginal = {};
    
    // Update navigator
    updateSidebarNavigator();
}

export async function fetchModuleQuestions(moduleId) {
    const navigator = document.getElementById('builderSidebarNavigator');
    if (navigator) {
        navigator.innerHTML = `
            <div class="text-slate-500 text-center py-8 text-xs font-medium">
                <div class="animate-spin inline-block w-5 h-5 border-2 border-amber-500 border-t-transparent rounded-full mb-2"></div>
                <div>Fetching existing questions...</div>
            </div>
        `;
    }
    
    try {
        let url;
        try {
            url = new URL(QUESTIONS_LIST_URL);
        } catch (e) {
            url = new URL(QUESTIONS_LIST_URL, window.location.origin);
        }
        url.searchParams.set('module_id', moduleId);
        url.searchParams.set('per_page', '100');

        const response = await fetch(url.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        if (!response.ok) throw new Error('Failed to fetch module questions');
        const json = await response.json();
        window.__builderExistingQuestions = json.data || [];
        
        // Auto set Start Position to next number
        const startPosInput = document.getElementById('builderStartPosition');
        if (startPosInput) {
            startPosInput.value = (window.__builderExistingQuestions.length + 1);
        }
        
        updateSidebarNavigator();
    } catch (err) {
        console.error(err);
        showAlert('danger', 'Error loading module questions: ' + err.message);
        updateSidebarNavigator();
    }
}

export async function loadExistingQuestionIntoWorkspace(qId) {
    try {
        const response = await fetch(`${BASE_URL}/questions/${qId}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        if (!response.ok) throw new Error('Failed to fetch question details');
        const result = await response.json();
        const question = result.data;

        if (!question) throw new Error('No question data found');

        // Store original question data for "Clear Unchanged" comparison
        window.__builderLoadedQuestionsOriginal = window.__builderLoadedQuestionsOriginal || {};
        const pContent = question.passage_content || (question.passage ? (typeof question.passage === 'string' ? question.passage : question.passage.content) : '') || '';
        window.__builderLoadedQuestionsOriginal[question.id] = {
            stem: question.stem || '',
            passage_content: pContent,
            difficulty: question.difficulty || '',
            skill_domain: question.skill_domain || '',
            question_type: question.question_type || 'multiple_choice',
            explanation: question.explanation?.explanation || '',
            choices: (question.answer_choices || question.answerChoices || []).map(c => ({ label: c.label, content: c.content || '', is_correct: c.is_correct === 1 || c.is_correct === true })),
            spr_answers: (question.spr_correct_answers || question.sprCorrectAnswers || []).map(a => a.answer).join(', ')
        };

        // Add a block
        builderBlockCount++;
        const template = document.getElementById('builderBlockTemplate').innerHTML;
        const html = template.replace(/{INDEX}/g, builderBlockCount).replace(/{DISPLAY_INDEX}/g, builderBlockCount);

        const container = document.getElementById('builderBlocksContainer');
        const div = document.createElement('div');
        div.innerHTML = html;
        const block = div.firstElementChild;
        
        // Set the ID attribute so we know it's an existing question
        block.dataset.questionId = question.id;
        
        // Update the display title
        const titleEl = block.querySelector('.text-sm.font-extrabold.text-white');
        if (titleEl) {
            titleEl.innerHTML = `<i class="bi bi-question-circle text-amber-400"></i> Question #${builderBlockCount} <span class="bg-indigo-500/20 text-indigo-300 font-extrabold px-1.5 py-0.5 text-[10px] rounded uppercase ml-2">Existing ID: ${question.id}</span>`;
        }

        container.appendChild(block);

        const stemTextarea = block.querySelector('.builder-stem');
        const passageTextarea = block.querySelector('.builder-passage');
        stemTextarea.id = `stem_${builderBlockCount}`;
        passageTextarea.id = `passage_${builderBlockCount}`;

        // Initialize Skill/Domain dropdown options based on section type
        syncBuilderBlockDomain(block);

        // Prepopulate basic values
        block.querySelector('.builder-domain').value = question.skill_domain || '';
        block.querySelector('.builder-difficulty').value = question.difficulty || '';
        block.querySelector('.builder-explanation').value = question.explanation?.explanation || '';

        // Initialize EasyMDE for Stem
        builderEditors[`stem_${builderBlockCount}`] = new EasyMDE({
            element: stemTextarea,
            placeholder: "Enter question stem...",
            minHeight: "100px",
            toolbar: getPremiumToolbar('builderStem', () => debouncedUpdateLivePreview(block)),
            status: false
        });
        builderEditors[`stem_${builderBlockCount}`].value(question.stem || '');

        // Listen to change to update preview in real-time
        builderEditors[`stem_${builderBlockCount}`].codemirror.on('change', () => {
            debouncedUpdateLivePreview(block);
            if (window.triggerBuilderAutoSave) window.triggerBuilderAutoSave();
        });

        bindBlockInteractiveEvents(block);

        // Initialize Passage if it's Reading & Writing
        const type = document.getElementById('builderModuleId').selectedOptions[0]?.getAttribute('data-section-type');
        const passageContainer = block.querySelector('.builder-passage-container');
        if (type === 'reading_writing') {
            passageContainer.classList.remove('hidden');
            const pContent = question.passage_content || (question.passage ? (typeof question.passage === 'string' ? question.passage : question.passage.content) : '');
            
            builderEditors[`passage_${builderBlockCount}`] = new EasyMDE({
                element: passageTextarea,
                placeholder: "Enter passage content...",
                minHeight: "150px",
                toolbar: getPremiumToolbar('builderPassage', () => debouncedUpdateLivePreview(block)),
                status: false
            });
            builderEditors[`passage_${builderBlockCount}`].value(pContent || '');

            builderEditors[`passage_${builderBlockCount}`].codemirror.on('change', () => {
                debouncedUpdateLivePreview(block);
                if (window.triggerBuilderAutoSave) window.triggerBuilderAutoSave();
            });
        } else {
            passageContainer.classList.add('hidden');
        }

        // Prepopulate MCQ Choices or SPR answers
        const mcqRadio = block.querySelector('.builder-format-mcq');
        const sprRadio = block.querySelector('.builder-format-spr');
        const mcqContainer = block.querySelector('.builder-mcq-container');
        const sprContainer = block.querySelector('.builder-spr-container');

        const updateFormatView = () => {
            if (mcqRadio.checked) {
                mcqContainer.classList.remove('hidden');
                sprContainer.classList.add('hidden');
            } else {
                mcqContainer.classList.add('hidden');
                sprContainer.classList.remove('hidden');
            }
            debouncedUpdateLivePreview(block);
            updateSidebarNavigator();
        };

        mcqRadio.addEventListener('change', updateFormatView);
        sprRadio.addEventListener('change', updateFormatView);

        if (question.question_type === 'multiple_choice') {
            mcqRadio.checked = true;
            sprRadio.checked = false;
            
            const rawChoices = question.answer_choices || question.answerChoices || [];
            rawChoices.forEach(choice => {
                const contentInput = block.querySelector(`.builder-choice-content[data-label="${choice.label}"]`);
                const correctRadio = block.querySelector(`.builder-correct-radio[value="${choice.label}"]`);
                if (contentInput) contentInput.value = choice.content || '';
                if (correctRadio && (choice.is_correct || choice.is_correct === 1)) {
                    correctRadio.checked = true;
                }
            });
        } else {
            mcqRadio.checked = false;
            sprRadio.checked = true;
            
            const answers = (question.spr_correct_answers || question.sprCorrectAnswers || []).map(a => a.answer).join(', ');
            block.querySelector('.builder-spr-answers').value = answers || '';
        }

        updateFormatView();

        // Bind other changes
        block.querySelectorAll('input, select, textarea').forEach(el => {
            if (el.classList.contains('builder-stem') || el.classList.contains('builder-passage')) return;
            el.addEventListener('input', () => {
                debouncedUpdateLivePreview(block);
                triggerBuilderAutoSave();
                if (el.classList.contains('builder-difficulty') || el.classList.contains('builder-domain')) {
                    updateSidebarNavigator();
                }
            });
            el.addEventListener('change', () => {
                debouncedUpdateLivePreview(block);
                triggerBuilderAutoSave();
                if (el.classList.contains('builder-difficulty') || el.classList.contains('builder-domain')) {
                    updateSidebarNavigator();
                }
            });
        });

        // Bind remove button (just removes the block from editor workspace, doesn't delete it from database!)
        block.querySelector('.remove-block-btn').onclick = function () {
            const index = block.dataset.index;
            if (builderEditors[`stem_${index}`]) { builderEditors[`stem_${index}`].toTextArea(); delete builderEditors[`stem_${index}`]; }
            if (builderEditors[`passage_${index}`]) { builderEditors[`passage_${index}`].toTextArea(); delete builderEditors[`passage_${index}`]; }
            block.remove();
            triggerBuilderAutoSave();

            const previewCard = document.querySelector(`[data-preview-index="${index}"]`);
            if (previewCard) previewCard.remove();

            const drawer = document.getElementById('builderLivePreviewDrawer');
            if (drawer && drawer.querySelectorAll('[data-preview-index]').length === 0) {
                drawer.innerHTML = `
                    <div class="text-slate-500 text-center py-12 text-xs font-medium">
                        <i class="bi bi-file-earmark-richtext text-3xl block mb-2 text-slate-650"></i>
                        Live compilation of STEM and formulas will appear here in real-time
                    </div>
                `;
            }

            refreshQuestionBlockNumbers();
            updateSidebarNavigator();
        };

        // Scroll to block and update active navigator selection
        renderLivePreviewCard(block);
        block.scrollIntoView({ behavior: 'smooth', block: 'start' });
        syncLivePreviewScroll(block);
        refreshQuestionBlockNumbers();
        updateSidebarNavigator();

    } catch (err) {
        console.error(err);
        showAlert('danger', 'Error loading question details: ' + err.message);
        updateSidebarNavigator();
    }
}

export async function submitBuilderQuestions() {
    const moduleId = getTomSelectValue('builderModuleId');
    if (!moduleId) {
        showAlert('danger', 'Please select a target module first.');
        return;
    }

    const blocks = document.querySelectorAll('.builder-block');
    if (blocks.length === 0) {
        showAlert('danger', 'Please add at least one question to save.');
        return;
    }

    // Prepare save list
    const existingQuestionsToUpdate = [];
    const newQuestionsToCreate = [];

    let hasValidationErrors = false;

    blocks.forEach(block => {
        const index = block.dataset.index;
        const qId = block.dataset.questionId; // present if existing question

        const stem = builderEditors[`stem_${index}`] ? builderEditors[`stem_${index}`].value().trim() : '';
        const passageContent = builderEditors[`passage_${index}`] ? builderEditors[`passage_${index}`].value().trim() : '';
        const difficulty = block.querySelector('.builder-difficulty').value;
        const skillDomain = block.querySelector('.builder-domain').value;
        const questionType = block.querySelector('.builder-format-mcq').checked ? 'multiple_choice' : 'student_produced_response';
        const explanation = block.querySelector('.builder-explanation').value.trim();

        if (!stem) {
            block.scrollIntoView({ behavior: 'smooth', block: 'center' });
            showAlert('danger', 'Question stem is required.');
            hasValidationErrors = true;
            return;
        }

        const type = document.getElementById('builderModuleId').selectedOptions[0]?.getAttribute('data-section-type');
        if (type === 'reading_writing' && !passageContent) {
            block.scrollIntoView({ behavior: 'smooth', block: 'center' });
            showAlert('danger', 'Passage content is required for Reading & Writing questions.');
            hasValidationErrors = true;
            return;
        }

        let choices = [];
        let correctChoice = '';
        let sprAnswers = '';

        if (questionType === 'multiple_choice') {
            correctChoice = block.querySelector('.builder-correct-radio:checked')?.value || '';
            if (!correctChoice) {
                block.scrollIntoView({ behavior: 'smooth', block: 'center' });
                showAlert('danger', 'Please select the correct choice for MCQ questions.');
                hasValidationErrors = true;
                return;
            }

            let missingChoice = false;
            block.querySelectorAll('.builder-choice-content').forEach(input => {
                const label = input.getAttribute('data-label');
                const content = input.value.trim();
                if (!content) {
                    missingChoice = true;
                }
                choices.push({
                    label,
                    content,
                    is_correct: label === correctChoice
                });
            });

            if (missingChoice) {
                block.scrollIntoView({ behavior: 'smooth', block: 'center' });
                showAlert('danger', 'Please fill in all choice options (A, B, C, D) for MCQ questions.');
                hasValidationErrors = true;
                return;
            }
        } else {
            sprAnswers = block.querySelector('.builder-spr-answers').value.trim();
            if (!sprAnswers) {
                block.scrollIntoView({ behavior: 'smooth', block: 'center' });
                showAlert('danger', 'Accepted answers are required for SPR questions.');
                hasValidationErrors = true;
                return;
            }
        }

        const questionData = {
            stem,
            question_type: questionType,
            difficulty,
            skill_domain: skillDomain,
            passage_content: passageContent,
            explanation,
            choices,
            correct_choice: correctChoice,
            spr_answers: sprAnswers
        };

        if (qId) {
            questionData.id = qId;
            existingQuestionsToUpdate.push(questionData);
        } else {
            // For new questions, the bulk-store endpoint expects spr_correct_answers as array
            const newQItem = {
                stem,
                question_type: questionType,
                difficulty: difficulty || 'medium',
                skill_domain: skillDomain || (type === 'math' ? 'algebra' : 'information_and_ideas'),
                explanation
            };

            if (type === 'reading_writing' && passageContent) {
                newQItem.passage = {
                    content: passageContent,
                    passage_type: 'single',
                    genre: 'humanities'
                };
            }

            if (questionType === 'multiple_choice') {
                newQItem.choices = choices;
            } else {
                newQItem.spr_correct_answers = sprAnswers.split(/[|,;]+/).map(a => a.trim()).filter(Boolean);
            }

            newQuestionsToCreate.push(newQItem);
        }
    });

    if (hasValidationErrors) return;

    // Show loading spinner
    const submitBtn = document.getElementById('submitBuilderBtn');
    const originalBtnHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-2 text-white"></span> Saving...`;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        // 1. Save / Update existing questions
        for (const eq of existingQuestionsToUpdate) {
            const response = await fetch(`${BASE_URL}/questions/${eq.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(eq)
            });

            if (!response.ok) {
                const result = await response.json();
                throw new Error(`Failed to update existing question ID ${eq.id}: ${result.message || response.statusText}`);
            }
        }

        // 2. Save / Create new questions
        if (newQuestionsToCreate.length > 0) {
            const startPosition = parseInt(document.getElementById('builderStartPosition').value) || 1;
            const bulkPayload = {
                module_id: moduleId,
                start_position: startPosition,
                items: newQuestionsToCreate
            };

            const response = await fetch(BULK_STORE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(bulkPayload)
            });

            if (!response.ok) {
                const result = await response.json();
                throw new Error(`Failed to save new questions: ${result.message || result.errors ? Object.values(result.errors).flat().join(' ') : response.statusText}`);
            }
        }

        showAlert('success', 'All questions saved successfully!');
        
        // Clear workspace
        clearBuilderWorkspace();

        // Refresh database and TomSelect elements
        if (window.refreshTestDashboardData) {
            await window.refreshTestDashboardData();
        }
        
        // Re-fetch module questions to refresh lists
        await fetchModuleQuestions(moduleId);

    } catch (err) {
        console.error(err);
        showAlert('danger', err.message || 'Error occurred while saving questions.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnHtml;
    }
}

export async function handleClearBuilder() {
    if (await showCustomConfirm('Are you sure you want to clear all questions from the workspace? Unsaved changes will be lost.', 'warning', 'Clear Workspace')) {
        clearBuilderWorkspace();
    }
}

let autoSaveTimeout = null;

export function triggerBuilderAutoSave() {
    if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(() => {
        saveBuilderDraft();
    }, 1000); // Debounce for 1 second
}

// Expose to window so builder.js's in-editor onchanges can call it
window.triggerBuilderAutoSave = triggerBuilderAutoSave;

export function saveBuilderDraft() {
    const moduleId = getTomSelectValue('builderModuleId');
    const startPos = document.getElementById('builderStartPosition')?.value || '1';
    const blocks = document.querySelectorAll('.builder-block');
    
    // If no module is selected and no blocks, just remove draft
    if (!moduleId && blocks.length === 0) {
        localStorage.removeItem('sat_builder_draft');
        return;
    }

    const blocksData = [];
    blocks.forEach(block => {
        const index = block.dataset.index;
        const qId = block.dataset.questionId;
        const stem = builderEditors[`stem_${index}`] ? builderEditors[`stem_${index}`].value() : '';
        const passageContent = builderEditors[`passage_${index}`] ? builderEditors[`passage_${index}`].value() : '';
        const difficulty = block.querySelector('.builder-difficulty').value;
        const skillDomain = block.querySelector('.builder-domain').value;
        const questionType = block.querySelector('.builder-format-mcq').checked ? 'multiple_choice' : 'student_produced_response';
        const explanation = block.querySelector('.builder-explanation').value;

        let choices = [];
        let correctChoice = '';
        let sprAnswers = '';

        if (questionType === 'multiple_choice') {
            correctChoice = block.querySelector('.builder-correct-radio:checked')?.value || '';
            block.querySelectorAll('.builder-choice-content').forEach(input => {
                choices.push({
                    label: input.getAttribute('data-label'),
                    content: input.value
                });
            });
        } else {
            sprAnswers = block.querySelector('.builder-spr-answers').value;
        }

        blocksData.push({
            id: qId || null,
            stem,
            passage_content: passageContent,
            difficulty,
            skill_domain: skillDomain,
            question_type: questionType,
            choices,
            correct_choice: correctChoice,
            spr_answers: sprAnswers
        });
    });

    const draft = {
        moduleId,
        startPosition: startPos,
        blocks: blocksData,
        originalQuestionsData: window.__builderLoadedQuestionsOriginal || {}
    };

    localStorage.setItem('sat_builder_draft', JSON.stringify(draft));

    // Show indicator
    const indicator = document.getElementById('builderAutoSaveIndicator');
    if (indicator) {
        const timeEl = indicator.querySelector('.time');
        if (timeEl) {
            const now = new Date();
            const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            timeEl.textContent = timeStr;
        }
        indicator.classList.remove('opacity-0');
        
        // Fade out after 3 seconds
        if (window.__autoSaveFadeTimeout) clearTimeout(window.__autoSaveFadeTimeout);
        window.__autoSaveFadeTimeout = setTimeout(() => {
            indicator.classList.add('opacity-0');
        }, 3000);
    }
}

export async function restoreBuilderDraft() {
    const raw = localStorage.getItem('sat_builder_draft');
    if (!raw) return;

    try {
        const draft = JSON.parse(raw);
        if (!draft) return;

        window.__builderLoadedQuestionsOriginal = draft.originalQuestionsData || {};

        const moduleId = draft.moduleId;
        if (!moduleId) return;

        // 1. Set module select
        const moduleSelect = document.getElementById('builderModuleId');
        if (moduleSelect && moduleSelect.tomselect) {
            moduleSelect.tomselect.setValue(moduleId, true);
        } else if (moduleSelect) {
            moduleSelect.value = moduleId;
        }

        // Fetch the module's questions list in background so the sidebar navigator is populated correctly!
        await fetchModuleQuestions(moduleId);

        // 2. Set start position
        const startPosInput = document.getElementById('builderStartPosition');
        if (startPosInput && draft.startPosition) {
            startPosInput.value = draft.startPosition;
        }

        // 3. Reconstruct blocks
        const blocks = draft.blocks || [];
        if (blocks.length === 0) return;

        // Clear initial blank placeholder if there is any
        const container = document.getElementById('builderBlocksContainer');
        if (container) container.innerHTML = '';
        resetBuilderBlockCount();

        for (const bData of blocks) {
            builderBlockCount++;
            const template = document.getElementById('builderBlockTemplate').innerHTML;
            const html = template.replace(/{INDEX}/g, builderBlockCount).replace(/{DISPLAY_INDEX}/g, builderBlockCount);

            const div = document.createElement('div');
            div.innerHTML = html;
            const block = div.firstElementChild;

            if (bData.id) {
                block.dataset.questionId = bData.id;
                const titleEl = block.querySelector('.text-sm.font-extrabold.text-white');
                if (titleEl) {
                    titleEl.innerHTML = `<i class="bi bi-question-circle text-amber-400"></i> Question #${builderBlockCount} <span class="bg-indigo-500/20 text-indigo-300 font-extrabold px-1.5 py-0.5 text-[10px] rounded uppercase ml-2">Existing ID: ${bData.id}</span>`;
                }
            }

            container.appendChild(block);

            const stemTextarea = block.querySelector('.builder-stem');
            const passageTextarea = block.querySelector('.builder-passage');
            stemTextarea.id = `stem_${builderBlockCount}`;
            passageTextarea.id = `passage_${builderBlockCount}`;

            syncBuilderBlockDomain(block);

            // Populate values
            block.querySelector('.builder-domain').value = bData.skill_domain || '';
            block.querySelector('.builder-difficulty').value = bData.difficulty || '';
            block.querySelector('.builder-explanation').value = bData.explanation || '';

            // EasyMDE Stem
            builderEditors[`stem_${builderBlockCount}`] = new EasyMDE({
                element: stemTextarea,
                placeholder: "Enter question stem...",
                minHeight: "100px",
                toolbar: getPremiumToolbar('builderStem', () => debouncedUpdateLivePreview(block)),
                status: false
            });
            builderEditors[`stem_${builderBlockCount}`].value(bData.stem || '');
            builderEditors[`stem_${builderBlockCount}`].codemirror.on('change', () => {
                debouncedUpdateLivePreview(block);
                triggerBuilderAutoSave();
            });

            bindBlockInteractiveEvents(block);

            // EasyMDE Passage
            const type = document.getElementById('builderModuleId').selectedOptions[0]?.getAttribute('data-section-type');
            const passageContainer = block.querySelector('.builder-passage-container');
            if (type === 'reading_writing') {
                passageContainer.classList.remove('hidden');
                builderEditors[`passage_${builderBlockCount}`] = new EasyMDE({
                    element: passageTextarea,
                    placeholder: "Enter passage content...",
                    minHeight: "150px",
                    toolbar: getPremiumToolbar('builderPassage', () => debouncedUpdateLivePreview(block)),
                    status: false
                });
                builderEditors[`passage_${builderBlockCount}`].value(bData.passage_content || '');
                builderEditors[`passage_${builderBlockCount}`].codemirror.on('change', () => {
                    debouncedUpdateLivePreview(block);
                    triggerBuilderAutoSave();
                });
            } else {
                passageContainer.classList.add('hidden');
            }

            // Populate MCQ/SPR
            const mcqRadio = block.querySelector('.builder-format-mcq');
            const sprRadio = block.querySelector('.builder-format-spr');
            const mcqContainer = block.querySelector('.builder-mcq-container');
            const sprContainer = block.querySelector('.builder-spr-container');

            const updateFormatView = () => {
                if (mcqRadio.checked) {
                    mcqContainer.classList.remove('hidden');
                    sprContainer.classList.add('hidden');
                } else {
                    mcqContainer.classList.add('hidden');
                    sprContainer.classList.remove('hidden');
                }
                debouncedUpdateLivePreview(block);
                updateSidebarNavigator();
            };

            mcqRadio.addEventListener('change', updateFormatView);
            sprRadio.addEventListener('change', updateFormatView);

            if (bData.question_type === 'multiple_choice') {
                mcqRadio.checked = true;
                sprRadio.checked = false;
                
                const rawChoices = bData.choices || [];
                rawChoices.forEach(choice => {
                    const contentInput = block.querySelector(`.builder-choice-content[data-label="${choice.label}"]`);
                    if (contentInput) contentInput.value = choice.content || '';
                });
                
                if (bData.correct_choice) {
                    const correctRadio = block.querySelector(`.builder-correct-radio[value="${bData.correct_choice}"]`);
                    if (correctRadio) correctRadio.checked = true;
                }
            } else {
                mcqRadio.checked = false;
                sprRadio.checked = true;
                block.querySelector('.builder-spr-answers').value = bData.spr_answers || '';
            }

            updateFormatView();

            // Bind listeners for autosave
            block.querySelectorAll('input, select, textarea').forEach(el => {
                if (el.classList.contains('builder-stem') || el.classList.contains('builder-passage')) return;
                el.addEventListener('input', () => {
                    debouncedUpdateLivePreview(block);
                    triggerBuilderAutoSave();
                    if (el.classList.contains('builder-difficulty') || el.classList.contains('builder-domain')) {
                        updateSidebarNavigator();
                    }
                });
                el.addEventListener('change', () => {
                    debouncedUpdateLivePreview(block);
                    triggerBuilderAutoSave();
                    if (el.classList.contains('builder-difficulty') || el.classList.contains('builder-domain')) {
                        updateSidebarNavigator();
                    }
                });
            });

            // Bind remove button
            block.querySelector('.remove-block-btn').onclick = function () {
                const index = block.dataset.index;
                if (builderEditors[`stem_${index}`]) { builderEditors[`stem_${index}`].toTextArea(); delete builderEditors[`stem_${index}`]; }
                if (builderEditors[`passage_${index}`]) { builderEditors[`passage_${index}`].toTextArea(); delete builderEditors[`passage_${index}`]; }
                block.remove();

                const previewCard = document.querySelector(`[data-preview-index="${index}"]`);
                if (previewCard) previewCard.remove();

                const drawer = document.getElementById('builderLivePreviewDrawer');
                if (drawer && drawer.querySelectorAll('[data-preview-index]').length === 0) {
                    drawer.innerHTML = `
                        <div class="text-slate-500 text-center py-12 text-xs font-medium">
                            <i class="bi bi-file-earmark-richtext text-3xl block mb-2 text-slate-650"></i>
                            Live compilation of STEM and formulas will appear here in real-time
                        </div>
                    `;
                }

                refreshQuestionBlockNumbers();
                updateSidebarNavigator();
                triggerBuilderAutoSave();
            };

            debouncedUpdateLivePreview(block);
        }

        refreshQuestionBlockNumbers();
        updateSidebarNavigator();

    } catch (e) {
        console.error("Failed to restore builder draft:", e);
    }
}
