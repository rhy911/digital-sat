import { dashboardJsonResponse, dashboardResourceUrl, QUESTION_UPDATE_URL_TEMPLATE } from './core/config.js';
import { showAlert, showCustomConfirm } from './utils/custom-alert.js';
import {
    initEditModalEditors, refreshEditMediaList, updateEditQuestionPreview,
    getEditStemEditor, getEditPassageEditor, getEditExplanationEditor,
} from './ui/editors.js';
import { refreshQuestionsTableOnly } from './dashboard-data.js';

let editQuestionInitialState = null;
let allowEditQuestionCloseOnce = false;

function serializeEditQuestionForm() {
    const stemEditor = getEditStemEditor();
    const passageEditor = getEditPassageEditor();
    const explanationEditor = getEditExplanationEditor();
    const state = {
        question_type: document.getElementById('editQuestionType')?.value || '',
        difficulty: document.getElementById('editDifficulty')?.value || '',
        skill_domain: document.getElementById('editSkillDomain')?.value || '',
        skill_subdomain: (document.getElementById('editSkillSubdomain')?.value || '').trim(),
        spr_hint: (document.getElementById('editSprHint')?.value || '').trim(),
        stem: (stemEditor ? stemEditor.value() : (document.getElementById('editQuestionStem')?.value || '')).trim(),
        passage_content: (passageEditor ? passageEditor.value() : (document.getElementById('editPassageContent')?.value || '')).trim(),
        explanation: (explanationEditor ? explanationEditor.value() : (document.getElementById('editExplanation')?.value || '')).trim(),
        is_pretest: !!document.getElementById('editIsPretest')?.checked,
        calculator_allowed: !!document.getElementById('editCalculatorAllowed')?.checked,
        spr_answers: (document.getElementById('editSprAnswers')?.value || '').trim(),
        rationales: ['A', 'B', 'C', 'D'].map(label => (document.getElementById(`editRationale${label}`)?.value || '').trim()),
        choices: ['A', 'B', 'C', 'D'].map(label => ({
            label,
            content: (document.getElementById(`editChoice${label}Content`)?.value || '').trim(),
            is_correct: !!document.getElementById(`editChoice${label}Correct`)?.checked,
        })),
    };

    return JSON.stringify(state);
}

function editQuestionHasUnsavedChanges() {
    const question = window.__editingQuestion;
    const isOwner = question && (question.created_by === window.__currentUserId || window.__currentUserRole === 'admin');
    return isOwner && editQuestionInitialState !== null && serializeEditQuestionForm() !== editQuestionInitialState;
}

/**
 * Wires up the edit-question modal's full lifecycle: opening it (loading editors,
 * toggling read-only mode for non-owners), the unsaved-changes confirmation on close,
 * and its submit handler.
 */
export function initEditQuestionModal() {
    window.addEventListener('close-modal', function (event) {
        if (event.detail !== 'editQuestionModal') return;
        if (allowEditQuestionCloseOnce) {
            allowEditQuestionCloseOnce = false;
            editQuestionInitialState = null;
            return;
        }
        if (!editQuestionHasUnsavedChanges()) {
            editQuestionInitialState = null;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        showCustomConfirm('You have unsaved changes. Close without updating this question?', 'warning', 'Unsaved Changes')
            .then(confirmed => {
                if (!confirmed) return;
                allowEditQuestionCloseOnce = true;
                window.dispatchEvent(new CustomEvent('close-modal', { detail: 'editQuestionModal' }));
            });
    }, true);

    // Modal listeners
    window.addEventListener('open-modal', function (e) {
        if (e.detail !== 'editQuestionModal') return;
        const loader = document.getElementById('editQuestionModalLoader');
        if (loader) {
            loader.style.opacity = '1';
            loader.style.pointerEvents = 'auto';
            loader.style.display = 'flex';
        }

        const question = window.__editingQuestion;
        if (!question) return;

        // Delay heavy initialization to ensure 60fps modal entry transition
        setTimeout(() => {
            let stemEditor, passageEditor, explanationEditor;
            try {
                initEditModalEditors();

                stemEditor = getEditStemEditor();
                passageEditor = getEditPassageEditor();
                explanationEditor = getEditExplanationEditor();

                if (stemEditor) { stemEditor.value(question.stem || ''); stemEditor.codemirror.refresh(); }
                if (passageEditor) {
                    const pContent = question.passage_content || (question.passage ? (typeof question.passage === 'string' ? question.passage : question.passage.content) : '');
                    passageEditor.value(pContent || ''); passageEditor.codemirror.refresh();
                }
                if (explanationEditor) {
                    const expContent = question.explanation ? (question.explanation.explanation || '') : '';
                    explanationEditor.value(expContent); explanationEditor.codemirror.refresh();
                }

                refreshEditMediaList();
                updateEditQuestionPreview();

                // Disablement & Mode Toggling based on ownership
                const isOwner = question.created_by === window.__currentUserId || window.__currentUserRole === 'admin';
                const titleText = document.getElementById('editQuestionModalTitleText');
                const titleIconEdit = document.getElementById('editQuestionModalIconEdit');
                const titleIconView = document.getElementById('editQuestionModalIconView');
                const submitBtn = document.querySelector('#editQuestionForm button[type="submit"]');

                if (titleText) titleText.textContent = isOwner ? 'Edit Question' : 'View Question';
                if (titleIconEdit) titleIconEdit.classList.toggle('hidden', !isOwner);
                if (titleIconView) titleIconView.classList.toggle('hidden', isOwner);
                if (submitBtn) {
                    if (isOwner) submitBtn.classList.remove('hidden');
                    else submitBtn.classList.add('hidden');
                }

                // Disable form fields
                const formElements = document.querySelectorAll('#editQuestionForm input, #editQuestionForm select, #editQuestionForm textarea:not(.easy-mde-textarea)');
                formElements.forEach(el => {
                    if (el.id === 'editQuestionId' || el.name === '_token') return;
                    el.disabled = !isOwner;
                });

                // Set EasyMDE read-only mode
                if (stemEditor) stemEditor.codemirror.setOption('readOnly', isOwner ? false : 'nocursor');
                if (passageEditor) passageEditor.codemirror.setOption('readOnly', isOwner ? false : 'nocursor');
                if (explanationEditor) explanationEditor.codemirror.setOption('readOnly', isOwner ? false : 'nocursor');

                // Toggle toolbar styling for readonly state
                document.querySelectorAll('#editQuestionModal .editor-toolbar').forEach(tb => {
                    tb.style.pointerEvents = isOwner ? 'auto' : 'none';
                    tb.style.opacity = isOwner ? '1' : '0.5';
                });

                editQuestionInitialState = serializeEditQuestionForm();

                setTimeout(() => {
                    if (stemEditor) stemEditor.codemirror.refresh();
                    if (passageEditor) passageEditor.codemirror.refresh();
                    if (explanationEditor) explanationEditor.codemirror.refresh();
                }, 100);
            } catch (err) {
                console.error("Failed to initialize edit question editors:", err);
            } finally {
                // ALWAYS hide the loader overlay, even if initialization failed!
                setTimeout(() => {
                    if (loader) {
                        loader.style.opacity = '0';
                        loader.style.pointerEvents = 'none'; // prevent blocking any clicks
                        setTimeout(() => {
                            loader.style.display = 'none';
                        }, 300);
                    }
                }, 150);
            }
        }, 250); // Exact time for Alpine modal enter transition to complete
    });

    document.getElementById('editQuestionForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        try {
            const stemEditor = getEditStemEditor();
            const passageEditor = getEditPassageEditor();
            const explanationEditor = getEditExplanationEditor();

            if (stemEditor) document.getElementById('editQuestionStem').value = stemEditor.value();
            if (passageEditor) document.getElementById('editPassageContent').value = passageEditor.value();
            if (explanationEditor) document.getElementById('editExplanation').value = explanationEditor.value();

            const stemVal = stemEditor ? stemEditor.value() : document.getElementById('editQuestionStem').value;
            if (!stemVal || stemVal.trim() === '') { showAlert('danger', 'Question stem is required.'); return; }

            const id = document.getElementById('editQuestionId').value;
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            if (data.question_type === 'multiple_choice') {
                data.choices = [];
                ['A', 'B', 'C', 'D'].forEach((label, index) => {
                    const contentInput = document.getElementById(`editChoice${label}Content`);
                    const isCorrectRadio = document.getElementById(`editChoice${label}Correct`);
                    if (contentInput) {
                        data.choices.push({ label, content: contentInput.value, is_correct: isCorrectRadio ? isCorrectRadio.checked : false, order: index + 1 });
                    }
                });
            }

            // Remove flat choice fields to prevent server parsing conflicts
            for (let key in data) {
                if (key.startsWith('choices[')) {
                    delete data[key];
                }
            }

            data.is_pretest = document.getElementById('editIsPretest').checked ? 1 : 0;
            data.calculator_allowed = document.getElementById('editCalculatorAllowed').checked ? 1 : 0;

            const response = await fetch(dashboardResourceUrl(QUESTION_UPDATE_URL_TEMPLATE, 'questions', id), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify(data)
            });

            await dashboardJsonResponse(response, 'PUT');
            showAlert('success', 'Question updated successfully!');
            editQuestionInitialState = serializeEditQuestionForm();
            allowEditQuestionCloseOnce = true;
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'editQuestionModal' }));
            await refreshQuestionsTableOnly();
        } catch (error) { showAlert('danger', 'Submission error: ' + error.message); }
    });
}
