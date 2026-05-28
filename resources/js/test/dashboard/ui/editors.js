import { getPremiumToolbar, compileMarkdownToHtml, processMedia, showAlert, showCustomConfirm } from '../utils/helpers.js';
import { MEDIA_UPLOAD_URL, SKILL_DOMAINS, BASE_URL } from '../core/config.js';

let editPassageEditor, editStemEditor, editExplanationEditor;
let editPreviewDebouncer = null;

export function initEditModalEditors() {
    const stemEl = document.getElementById('editQuestionStem');
    const passageEl = document.getElementById('editPassageContent');
    const explanationEl = document.getElementById('editExplanation');

    if (stemEl && !editStemEditor) {
        editStemEditor = new EasyMDE({
            element: stemEl,
            placeholder: "Enter question stem...",
            minHeight: "120px",
            toolbar: getPremiumToolbar('editStem', () => debouncedEditQuestionPreview()),
            status: false,
            autoDownloadFontAwesome: false
        });
        editStemEditor.codemirror.on('change', () => {
            debouncedEditQuestionPreview();
        });
    }

    if (passageEl && !editPassageEditor) {
        editPassageEditor = new EasyMDE({
            element: passageEl,
            placeholder: "Enter passage content...",
            minHeight: "150px",
            toolbar: getPremiumToolbar('editPassage', () => debouncedEditQuestionPreview()),
            status: false,
            autoDownloadFontAwesome: false
        });
        editPassageEditor.codemirror.on('change', () => {
            debouncedEditQuestionPreview();
        });
    }

    if (explanationEl && !editExplanationEditor) {
        editExplanationEditor = new EasyMDE({
            element: explanationEl,
            placeholder: "Enter explanation...",
            minHeight: "100px",
            toolbar: getPremiumToolbar('editExplanation', () => debouncedEditQuestionPreview()),
            status: false,
            autoDownloadFontAwesome: false
        });
        editExplanationEditor.codemirror.on('change', () => {
            debouncedEditQuestionPreview();
        });
    }
}

export function debouncedEditQuestionPreview() {
    if (editPreviewDebouncer) clearTimeout(editPreviewDebouncer);
    editPreviewDebouncer = setTimeout(() => {
        updateEditQuestionPreview();
    }, 200);
}

export function updateEditQuestionPreview() {
    const previewContainer = document.getElementById('editQuestionPreviewContent');
    if (!previewContainer) return;

    const qTypeSelect = document.getElementById('editQuestionType');
    const qType = qTypeSelect ? qTypeSelect.value : 'multiple_choice';

    const stemValue = editStemEditor ? editStemEditor.value() : (document.getElementById('editQuestionStem')?.value || '');
    const passageValue = editPassageEditor ? editPassageEditor.value() : (document.getElementById('editPassageContent')?.value || '');
    const explanationValue = editExplanationEditor ? editExplanationEditor.value() : (document.getElementById('editExplanation')?.value || '');

    // Check if reading_writing / passage container is visible
    const passageContainer = document.getElementById('editPassageContainer');
    const showPassage = passageContainer && !passageContainer.classList.contains('hidden');

    let passageHtml = '';
    if (showPassage && passageValue.trim()) {
        passageHtml = `<div class="edit-passage-preview p-3 mb-3 bg-light rounded-3 small border-start border-3 border-secondary">${compileMarkdownToHtml(processMedia(passageValue))}</div>`;
    }

    const stemHtml = compileMarkdownToHtml(processMedia(stemValue));

    let questionBodyHtml = '';
    if (qType === 'multiple_choice') {
        let choicesHtml = '';
        ['A', 'B', 'C', 'D'].forEach(label => {
            const contentInput = document.getElementById(`editChoice${label}Content`);
            const correctRadio = document.getElementById(`editChoice${label}Correct`);

            const rawContent = contentInput ? contentInput.value.trim() : '';
            const content = rawContent ? compileMarkdownToHtml(processMedia(rawContent)) : `<span class="text-muted italic">Option ${label} content...</span>`;
            const isCorrect = correctRadio ? correctRadio.checked : false;

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
        const sprAnswersInput = document.getElementById('editSprAnswers');
        const sprVal = sprAnswersInput ? sprAnswersInput.value.trim() : '';
        questionBodyHtml = `
            <div class="answer-input-container p-3 bg-light rounded-3 mt-3 border border-warning border-opacity-25">
                <label class="d-block mb-2 fw-bold text-dark small"><i class="bi bi-pencil-fill text-warning"></i> Student Produced Response:</label>
                <div class="form-control bg-white font-monospace text-black text-center py-2 fs-5 border-warning border-opacity-50" style="max-width: 150px; letter-spacing: 2px;">
                    ${sprVal || '______'}
                </div>
            </div>
        `;
    }

    let explanationHtml = '';
    if (explanationValue.trim()) {
        explanationHtml = `
            <div class="explanation-preview p-2 mt-3 bg-light rounded small text-muted border-top border-light">
                <strong>Explanation:</strong> ${compileMarkdownToHtml(processMedia(explanationValue))}
            </div>
        `;
    }

    previewContainer.innerHTML = `
        ${passageHtml}
        <div class="edit-stem-preview fw-semibold text-dark">
            ${stemHtml || '<span class="text-muted italic">Enter question stem to view preview...</span>'}
        </div>
        ${questionBodyHtml}
        ${explanationHtml}
    `;

    // Update preview type badge
    const badge = document.getElementById('editPreviewTypeBadge');
    if (badge) {
        badge.textContent = qType === 'multiple_choice' ? 'MCQ' : 'SPR';
    }

    if (window.smartRenderMath) {
        window.smartRenderMath(previewContainer);
    }
}

export function refreshEditMediaList() {
    const mediaList = document.getElementById('editMediaList');
    if (!mediaList) return;
    mediaList.innerHTML = '';

    const fields = [
        editStemEditor ? editStemEditor.value() : (document.getElementById('editQuestionStem')?.value || ''),
        editPassageEditor ? editPassageEditor.value() : (document.getElementById('editPassageContent')?.value || ''),
        editExplanationEditor ? editExplanationEditor.value() : (document.getElementById('editExplanation')?.value || ''),
        document.getElementById('editRationaleA')?.value || '',
        document.getElementById('editRationaleB')?.value || '',
        document.getElementById('editRationaleC')?.value || '',
        document.getElementById('editRationaleD')?.value || ''
    ];

    ['A', 'B', 'C', 'D'].forEach(lbl => {
        const el = document.getElementById(`editChoice${lbl}Content`);
        if (el) fields.push(el.value);
    });

    const allText = fields.join(' ');
    const mdRegex = /!\[.*?\]\((.*?)\)/g;
    const placeholderRegex = /\[Media:([^\]]+)\]/gi;

    const foundUrls = new Set();
    const foundPlaceholders = new Set();

    let match;
    while ((match = mdRegex.exec(allText)) !== null) {
        foundUrls.add(match[1].trim());
    }
    while ((match = placeholderRegex.exec(allText)) !== null) {
        foundPlaceholders.add(match[1].trim());
    }

    if (foundUrls.size === 0 && foundPlaceholders.size === 0) {
        mediaList.innerHTML = '<div class="text-slate-500 text-xs font-semibold py-4">No media found in this question.</div>';
    } else {
        foundUrls.forEach(url => {
            const col = document.createElement('div');
            col.className = 'relative group border border-slate-800/80 rounded-xl p-2 bg-slate-900/60 shadow-md flex items-center justify-center h-28 transition-all hover:border-indigo-500/30';
            col.innerHTML = `
                <img src="${url}" class="max-h-24 rounded-lg object-contain w-full h-full" onerror="this.src='https://placehold.co/100x100?text=Error'">
                <button type="button" class="absolute -top-2 -right-2 w-6 h-6 bg-rose-600 hover:bg-rose-500 text-white rounded-full shadow-lg flex items-center justify-center text-xs font-black transition-all cursor-pointer hover:scale-110" 
                        data-action="remove-media" data-url="${url}" data-is-url="true" title="Remove from all fields">
                    &times;
                </button>
            `;
            mediaList.appendChild(col);
        });

        foundPlaceholders.forEach(filename => {
            const col = document.createElement('div');
            col.className = 'relative group border border-slate-800/80 rounded-xl p-2 bg-slate-900/60 shadow-md flex flex-col items-center justify-center h-28 transition-all hover:border-indigo-500/30';
            const predictedUrl = `/storage/media/${filename}`;
            col.innerHTML = `
                <img src="${predictedUrl}" class="max-h-16 rounded-lg object-contain w-full mb-1" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                <div class="flex flex-col items-center justify-center" style="display:none;">
                    <i class="bi bi-file-earmark-image text-slate-500" style="font-size: 1.5rem;"></i>
                    <div class="text-[9px] text-slate-400 text-truncate px-1 max-w-[80px] font-mono">${filename}</div>
                </div>
                <div class="text-[9px] text-slate-500 font-extrabold uppercase tracking-wider mt-1">Placeholder</div>
                <button type="button" class="absolute -top-2 -right-2 w-6 h-6 bg-rose-600 hover:bg-rose-500 text-white rounded-full shadow-lg flex items-center justify-center text-xs font-black transition-all cursor-pointer hover:scale-110" 
                        data-action="remove-media" data-url="${filename}" data-is-url="false" title="Remove from all fields">
                    &times;
                </button>
            `;
            mediaList.appendChild(col);
        });
    }
        // Re-bind remove media buttons since they are dynamic
        mediaList.querySelectorAll('[data-action="remove-media"]').forEach(btn => {
            btn.addEventListener('click', () => {
                removeMediaFromEditModal(btn.dataset.url, btn.dataset.isUrl === 'true');
            });
        });
}

export async function removeMediaFromEditModal(identifier, isUrl) {
    if (!await showCustomConfirm('Are you sure you want to remove this media from all fields?', 'warning', 'Remove Media')) return;

    const textareas = [
        'editQuestionStem', 'editPassageContent', 'editExplanation',
        'editRationaleA', 'editRationaleB', 'editRationaleC', 'editRationaleD'
    ];

    const escaped = identifier.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = isUrl ? new RegExp(`!\\[.*?\\]\\(\\s*${escaped}\\s*\\)`, 'g') : new RegExp(`\\[Media:\\s*${escaped}\\s*\\]`, 'gi');

    // Handle EasyMDE editors
    if (editStemEditor) editStemEditor.value(editStemEditor.value().replace(regex, '').trim());
    if (editPassageEditor) editPassageEditor.value(editPassageEditor.value().replace(regex, '').trim());
    if (editExplanationEditor) editExplanationEditor.value(editExplanationEditor.value().replace(regex, '').trim());

    // Handle static textareas
    textareas.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = el.value.replace(regex, '').trim();
    });

    ['A', 'B', 'C', 'D'].forEach(lbl => {
        const el = document.getElementById(`editChoice${lbl}Content`);
        if (el) el.value = el.value.replace(regex, '').trim();
    });

    refreshEditMediaList();
    debouncedEditQuestionPreview();
}

export function getEditStemEditor() { return editStemEditor; }
export function getEditPassageEditor() { return editPassageEditor; }
export function getEditExplanationEditor() { return editExplanationEditor; }
