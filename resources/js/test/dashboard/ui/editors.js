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
    const showPassage = passageContainer && !passageContainer.classList.contains('d-none');

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
                <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded border ${isCorrect ? 'border-success bg-success-subtle' : 'border-light'}" style="transition: all 0.2s;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white bg-${isCorrect ? 'success' : 'secondary'} fw-bold" style="width: 24px; height: 24px; font-size: 12px; flex-shrink: 0;">
                        ${label}
                    </div>
                    <div class="flex-grow-1 small">${content}</div>
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
                <div class="form-control bg-white font-monospace text-center py-2 fs-5 border-warning border-opacity-50" style="max-width: 150px; letter-spacing: 2px;">
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
        mediaList.innerHTML = '<div class="col-12 text-muted small">No media found in this question.</div>';
    } else {
        foundUrls.forEach(url => {
            const col = document.createElement('div');
            col.className = 'col-md-3 col-6 mb-2';
            col.innerHTML = `
                <div class="position-relative border rounded p-1 bg-white shadow-sm d-flex align-items-center justify-content-center" style="height: 100px;">
                    <img src="${url}" class="img-fluid rounded" style="max-height: 90px; object-fit: contain;" onerror="this.src='https://placehold.co/100x100?text=Error'">
                    <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 rounded-circle shadow-sm d-flex align-items-center justify-content-center" 
                            style="width: 24px; height: 24px; margin: -10px -10px 0 0; z-index: 10;"
                            data-action="remove-media" data-url="${url}" data-is-url="true" title="Remove from all fields">
                        &times;
                    </button>
                </div>
            `;
            mediaList.appendChild(col);
        });

        foundPlaceholders.forEach(filename => {
            const col = document.createElement('div');
            col.className = 'col-md-3 col-6 mb-2';
            const predictedUrl = `/storage/media/${filename}`;
            col.innerHTML = `
                <div class="position-relative border rounded p-1 bg-light shadow-sm d-flex flex-column align-items-center justify-content-center" style="height: 100px;">
                    <img src="${predictedUrl}" class="img-fluid rounded mb-1" style="max-height: 60px; object-fit: contain;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                    <div class="text-center" style="display:none;">
                        <i class="bi bi-file-earmark-image text-muted" style="font-size: 1.5rem;"></i>
                        <div class="x-small text-truncate px-1" style="max-width: 80px;">${filename}</div>
                    </div>
                    <div class="x-small text-muted text-center fw-bold">Placeholder</div>
                    <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 rounded-circle shadow-sm d-flex align-items-center justify-content-center" 
                            style="width: 24px; height: 24px; margin: -10px -10px 0 0; z-index: 10;"
                            data-action="remove-media" data-url="${filename}" data-is-url="false" title="Remove from all fields">
                        &times;
                    </button>
                </div>
            `;
            mediaList.appendChild(col);
        });

        // Re-bind remove media buttons since they are dynamic
        mediaList.querySelectorAll('[data-action="remove-media"]').forEach(btn => {
            btn.addEventListener('click', () => {
                removeMediaFromEditModal(btn.dataset.url, btn.dataset.isUrl === 'true');
            });
        });
    }
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
