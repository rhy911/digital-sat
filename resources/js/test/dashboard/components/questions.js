import {
    QUESTIONS_LIST_URL, QUESTIONS_SEARCH_URL, BULK_PREVIEW_URL, CSV_BULK_PREVIEW_URL,
    BULK_STORE_URL, CSV_BULK_URL, MEDIA_UPLOAD_URL, BASE_URL, SKILL_DOMAINS
} from '../core/config.js';
import {
    showAlert, showCustomConfirm, escapeHtml, stripTags, capitalizeFirstLetter,
    humanizeUnderscores, getTomSelectValue, destroyTomSelectIfAny, initTomSelectOn,
    processMedia, compileMarkdownToHtml
} from '../utils/helpers.js';
import {
    initEditModalEditors, debouncedEditQuestionPreview, updateEditQuestionPreview,
    refreshEditMediaList, getEditStemEditor, getEditPassageEditor, getEditExplanationEditor
} from '../ui/editors.js';

export function questionsListFetchUrl() {
    let u;
    try {
        u = new URL(QUESTIONS_LIST_URL);
    } catch (e) {
        u = new URL(QUESTIONS_LIST_URL, window.location.origin);
    }
    u.searchParams.set('page', String(window.__tdQuestionsPage || 1));
    u.searchParams.set('per_page', String(window.__tdQuestionsPerPage || 25));
    if (window.__tdQuestionsQuery) {
        u.searchParams.set('q', window.__tdQuestionsQuery);
    }
    if (window.__tdQuestionsSection) {
        u.searchParams.set('section_type', window.__tdQuestionsSection);
    }
    if (window.__tdQuestionsModule) {
        u.searchParams.set('module_id', window.__tdQuestionsModule);
    }
    if (window.__tdQuestionsStatus !== undefined && window.__tdQuestionsStatus !== '') {
        u.searchParams.set('is_complete', window.__tdQuestionsStatus);
    }
    return u.toString();
}

let currentQuestionsData = null;
let currentQuestionsRenderId = 0;

export function renderQuestionsTable(questions) {
    if (currentQuestionsData === questions) return;
    currentQuestionsData = questions;
    const tbody = document.getElementById('questionsTableBody');
    if (!tbody) {
        return;
    }
    
    currentQuestionsRenderId++;
    const renderId = currentQuestionsRenderId;
    
    if (!questions.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5">'
            + '<i class="bi bi-database-fill-x display-6 mb-2 d-block text-secondary opacity-50"></i>'
            + 'No questions found in bank.'
            + '</td></tr>';
        return;
    }
    
    tbody.innerHTML = '';
    let index = 0;
    const chunkSize = 20;
    
    function renderChunk() {
        if (renderId !== currentQuestionsRenderId) return;
        
        const chunk = questions.slice(index, index + chunkSize);
        if (!chunk.length) return;
        
        const html = chunk.map(function (q) {
            const secBadge = q.section_type === 'reading_writing'
                ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill fw-semibold">R&W</span>'
                : '<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill fw-semibold">Math</span>';

            const usageBadge = q.is_pretest
                ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill fw-semibold">Pretest</span>'
                : '<span class="badge bg-light text-muted border px-2.5 py-1 rounded-pill fw-semibold">Active</span>';

            const status = q.is_complete
                ? ''
                : ' <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 rounded" title="Missing Domain or Difficulty" style="font-size: 0.7rem;"><i class="bi bi-exclamation-triangle-fill"></i> Incomplete</span>';

            const stem = stripTags(q.stem || '');
            const snippet = stem.length <= 50 ? stem : stem.slice(0, 50) + '…';
            const qNum = q.question_number != null ? escapeHtml(q.question_number) : '-';

            let diffBadge = '';
            const diffLower = (q.difficulty || '').toLowerCase();
            if (diffLower === 'easy') {
                diffBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded">Easy</span>';
            } else if (diffLower === 'medium') {
                diffBadge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0.5 rounded">Medium</span>';
            } else {
                diffBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 rounded">' + escapeHtml(capitalizeFirstLetter(q.difficulty || '')) + '</span>';
            }

            return '<tr>'
                + '<td class="p-3 font-monospace fw-bold text-secondary text-center">' + escapeHtml(q.id) + '</td>'
                + '<td class="text-center">'
                + '<div class="d-flex align-items-center justify-content-center gap-2">'
                + '<span class="fw-semibold text-dark">' + qNum + '</span>'
                + status
                + '</div>'
                + '</td>'
                + '<td class="text-center">' + secBadge + '</td>'
                + '<td class="text-secondary text-truncate" style="max-width: 280px;" title="' + escapeHtml(stem) + '">' + escapeHtml(snippet) + '</td>'
                + '<td class="text-center">' + usageBadge + '</td>'
                + '<td><span class="text-secondary small font-monospace">' + escapeHtml(q.skill_domain || '') + '</span></td>'
                + '<td class="text-center">' + diffBadge + '</td>'
                + '<td class="pe-3 text-end">'
                + '<div class="d-flex justify-content-end gap-1.5">'
                + '<button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 edit-question-btn rounded-pill px-2.5 py-1" data-id="' + escapeHtml(q.id) + '">'
                + '<i class="bi bi-pencil-square"></i> Edit'
                + '</button>'
                + '<button type="button" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center justify-content-center delete-question-btn rounded-circle" style="width: 30px; height: 30px;" data-id="' + escapeHtml(q.id) + '">'
                + '<i class="bi bi-trash"></i>'
                + '</button>'
                + '</div>'
                + '</td>'
                + '</tr>';
        }).join('');
        
        tbody.insertAdjacentHTML('beforeend', html);
        index += chunkSize;
        
        if (index < questions.length) {
            requestAnimationFrame(() => setTimeout(renderChunk, 0));
        }
    }
    
    renderChunk();
}

export function renderQuestionsPagination(meta, refreshCallback) {
    const wrap = document.getElementById('questionsPoolPagination');
    if (!wrap) {
        return;
    }
    if (!meta || meta.total === 0) {
        wrap.innerHTML = '';
        return;
    }
    const cur = meta.current_page || 1;
    const last = meta.last_page || 1;
    const total = meta.total || 0;
    let html = '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">';
    html += '<span class="small text-muted">Page ' + cur + ' of ' + last + ' · ' + total + ' questions</span>';
    html += '<div class="btn-group btn-group-sm">';
    html += '<button type="button" class="btn btn-outline-secondary" data-q-page="prev"' + (cur <= 1 ? ' disabled' : '') + '>Previous</button>';
    html += '<button type="button" class="btn btn-outline-secondary" data-q-page="next"' + (cur >= last ? ' disabled' : '') + '>Next</button>';
    html += '</div></div>';
    wrap.innerHTML = html;

    if (wrap.dataset.bound !== '1') {
        wrap.dataset.bound = '1';
        wrap.addEventListener('click', async function (e) {
            const btn = e.target.closest('[data-q-page]');
            if (!btn || btn.disabled) {
                return;
            }
            const dir = btn.getAttribute('data-q-page');
            const curPage = window.__tdQuestionsPage || 1;
            if (dir === 'prev') {
                window.__tdQuestionsPage = Math.max(1, curPage - 1);
            } else if (dir === 'next') {
                window.__tdQuestionsPage = curPage + 1;
            }
            if (refreshCallback) await refreshCallback();
        });
    }
}

export async function openEditQuestionModal(id) {
    try {
        const response = await fetch(`${BASE_URL}/questions/${id}`);
        if (!response.ok) throw new Error('Failed to fetch question data');
        const result = await response.json();
        const question = result.data;

        // Store current question for the modal listener
        window.__editingQuestion = question;

        document.getElementById('editQuestionId').value = question.id;
        document.getElementById('editQuestionIdDisplay').textContent = question.id;

        document.getElementById('editQuestionType').value = question.question_type;
        document.getElementById('editDifficulty').value = question.difficulty || '';
        document.getElementById('editSkillSubdomain').value = question.skill_subdomain || '';
        document.getElementById('editSprHint').value = question.spr_hint || '';
        document.getElementById('editIsPretest').checked = !!question.is_pretest;
        document.getElementById('editCalculatorAllowed').checked = !!question.calculator_allowed;

        const sprHintContainer = document.getElementById('editSprHintContainer');
        const sectionType = question.section_type || (question.section?.type) || '';
        if (sectionType === 'reading_writing') {
            sprHintContainer.classList.add('d-none');
        } else {
            sprHintContainer.classList.remove('d-none');
        }

        const domainSelect = document.getElementById('editSkillDomain');
        domainSelect.innerHTML = '<option value="">Select domain...</option>';
        if (SKILL_DOMAINS[sectionType]) {
            SKILL_DOMAINS[sectionType].forEach(domain => {
                const opt = document.createElement('option');
                opt.value = domain.value;
                opt.textContent = domain.label;
                if (domain.value === question.skill_domain) opt.selected = true;
                domainSelect.appendChild(opt);
            });
        }

        const passageContainer = document.getElementById('editPassageContainer');
        if (sectionType === 'reading_writing' && (question.passage || question.passage_content)) {
            passageContainer.classList.remove('d-none');
        } else {
            passageContainer.classList.add('d-none');
        }

        // Populate Choices
        if (question.question_type === 'multiple_choice') {
            document.getElementById('editMcqChoicesContainer').classList.remove('d-none');
            document.getElementById('editSprAnswersContainer').classList.add('d-none');
            // Clear choices first
            ['A', 'B', 'C', 'D'].forEach(lbl => {
                const ci = document.getElementById(`editChoice${lbl}Content`);
                const cr = document.getElementById(`editChoice${lbl}Correct`);
                if (ci) ci.value = '';
                if (cr) cr.checked = false;
            });
            const rawChoices = question.answer_choices || question.answerChoices || [];
            rawChoices.forEach(choice => {
                const contentInput = document.getElementById(`editChoice${choice.label}Content`);
                const correctRadio = document.getElementById(`editChoice${choice.label}Correct`);
                if (contentInput) contentInput.value = choice.content;
                if (correctRadio && (choice.is_correct || choice.is_correct === 1)) correctRadio.checked = true;
            });
        } else {
            document.getElementById('editMcqChoicesContainer').classList.add('d-none');
            document.getElementById('editSprAnswersContainer').classList.remove('d-none');
            const answers = (question.spr_correct_answers || []).map(a => a.answer).join(', ');
            document.getElementById('editSprAnswers').value = answers;
        }

        // Populate static rationales
        if (question.explanation) {
            document.getElementById('editRationaleA').value = question.explanation.rationale_a || '';
            document.getElementById('editRationaleB').value = question.explanation.rationale_b || '';
            document.getElementById('editRationaleC').value = question.explanation.rationale_c || '';
            document.getElementById('editRationaleD').value = question.explanation.rationale_d || '';
        } else {
            document.getElementById('editRationaleA').value = '';
            document.getElementById('editRationaleB').value = '';
            document.getElementById('editRationaleC').value = '';
            document.getElementById('editRationaleD').value = '';
        }

        const modalEl = document.getElementById('editQuestionModal');
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'editQuestionModal' }));

    } catch (error) {
        showAlert('danger', error.message);
    }
}

export function initRemoteQuestionPicker(selectId, preservedValue) {
    const el = document.getElementById(selectId);
    if (!el) {
        return;
    }
    destroyTomSelectIfAny(el);
    el.innerHTML = '<option value="">Search question (type to load)...</option>';
    const pinnedId = preservedValue != null && preservedValue !== '' ? String(preservedValue) : '';
    const ts = new TomSelect(el, {
        valueField: 'value',
        labelField: 'text',
        searchField: 'text',
        preload: 'focus',
        loadThrottle: 250,
        maxOptions: 50,
        create: false,
        load: function (query, callback) {
            const params = new URLSearchParams();
            params.set('q', query || '');
            if (pinnedId !== '') {
                params.set('id', pinnedId);
            }
            fetch(QUESTIONS_SEARCH_URL + '?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (j) { callback((j && j.data) ? j.data : []); })
                .catch(function () { callback(); });
        }
    });
    if (pinnedId !== '') {
        fetch(QUESTIONS_SEARCH_URL + '?q=&id=' + encodeURIComponent(pinnedId), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                (j.data || []).forEach(function (opt) {
                    ts.addOption(opt);
                });
                ts.setValue(pinnedId, true);
            })
            .catch(function () { /* ignore */ });
    }
}
