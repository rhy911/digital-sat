import { SKILL_DOMAINS, BULK_STORE_URL } from '../core/config.js';
import { 
    getPremiumToolbar, compileMarkdownToHtml, getTomSelectValue, showAlert, showCustomConfirm 
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

    const displayIndex = [...document.querySelectorAll('.builder-block')].indexOf(block) + 1;
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
                <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded border ${isCorrect ? 'border-success bg-success-subtle' : 'border-light'}" style="transition: all 0.2s;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white bg-${isCorrect ? 'success' : 'secondary'} fw-bold" style="width: 24px; height: 24px; font-size: 12px; flex-shrink: 0;">
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
        <div class="card-header bg-dark text-white py-2 px-3 d-flex justify-content-between align-items-center">
            <span class="fw-bold small text-warning"><i class="bi bi-file-earmark-text"></i> Live Preview Q#${displayIndex}</span>
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

export function updateSidebarNavigator() {
    const navigator = document.getElementById('builderSidebarNavigator');
    if (!navigator) return;

    const blocks = document.querySelectorAll('.builder-block');
    if (blocks.length === 0) {
        navigator.innerHTML = `
            <div class="text-muted text-center py-4 small">
                <i class="bi bi-layers fs-3 d-block mb-2 text-warning"></i>
                Add a question to start indexing
            </div>
        `;
        return;
    }

    navigator.innerHTML = '';
    blocks.forEach((block, i) => {
        const index = block.dataset.index;
        const qType = block.querySelector('.builder-format-mcq').checked ? 'MCQ' : 'SPR';
        const difficulty = block.querySelector('.builder-difficulty').value || 'N/A';
        const domainVal = block.querySelector('.builder-domain').value || '';

        let domainLabel = 'No Domain';
        if (domainVal) {
            domainLabel = domainVal.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
        }

        const item = document.createElement('a');
        item.href = "javascript:void(0)";
        item.className = "list-group-item list-group-item-action border rounded-3 p-2 d-flex flex-column gap-1";
        item.dataset.targetIndex = index;
        item.style.transition = "all 0.2s";

        item.innerHTML = `
            <div class="d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark small">Q#${i + 1} <span class="badge bg-secondary font-monospace" style="font-size: 8px;">${qType}</span></span>
                <span class="badge bg-light text-secondary font-monospace text-capitalize" style="font-size: 8px; border: 1px solid #dee2e6;">${difficulty}</span>
            </div>
            <div class="text-muted text-truncate" style="font-size: 9px;">
                ${domainLabel}
            </div>
        `;

        item.onclick = function () {
            block.scrollIntoView({ behavior: 'smooth', block: 'center' });
            navigator.querySelectorAll('.list-group-item').forEach(el => el.classList.remove('active', 'bg-warning', 'text-dark', 'border-warning'));
            item.classList.add('active', 'bg-warning', 'text-dark', 'border-warning');
        };

        navigator.appendChild(item);
    });
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

    block.scrollIntoView({ behavior: 'smooth' });

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
            if (el.classList.contains('builder-difficulty') || el.classList.contains('builder-domain')) {
                updateSidebarNavigator();
            }
        });
        el.addEventListener('change', () => {
            debouncedUpdateLivePreview(block);
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

        const blocks = document.querySelectorAll('.builder-block');
        blocks.forEach((b, i) => b.querySelector('.text-secondary').textContent = `Question #${i + 1}`);

        // Renumber preview cards
        if (drawer) {
            const previewCards = drawer.querySelectorAll('[data-preview-index]');
            previewCards.forEach((card, i) => {
                const textEl = card.querySelector('.card-header span.fw-bold');
                if (textEl) {
                    textEl.innerHTML = `<i class="bi bi-file-earmark-text"></i> Live Preview Q#${i + 1}`;
                }
            });
        }

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
