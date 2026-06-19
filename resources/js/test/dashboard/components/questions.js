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
    u.searchParams.set('per_page', String(window.__tdQuestionsPerPage || 30));
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
    const showSharedEl = document.getElementById('questionsShowSharedToggle');
    if (showSharedEl && showSharedEl.checked) {
        u.searchParams.set('show_shared', '1');
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
        tbody.innerHTML = '<tr>'
            + '<td colspan="8" class="px-5 py-12 text-center text-slate-500">'
            + '<div class="flex flex-col items-center justify-center">'
            + '<div class="w-12 h-12 rounded-full bg-slate-900/40 border border-slate-800/80 flex items-center justify-center mb-3">'
            + '<i class="bi bi-database-fill-x text-2xl text-slate-400"></i>'
            + '</div>'
            + '<p class="text-sm font-semibold text-slate-400">No questions found</p>'
            + '<p class="text-xs text-slate-500 mt-1">Populate your bank by importing items above!</p>'
            + '</div>'
            + '</td>'
            + '</tr>';
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
                ? '<span class="status-chip status-chip-shared">R&amp;W</span>'
                : '<span class="status-chip status-chip-active">Math</span>';

            const usageBadge = q.is_pretest
                ? '<span class="status-chip text-rose-700 bg-rose-50 border border-rose-100">Pretest</span>'
                : '<span class="status-chip status-chip-readonly">Active</span>';

            const status = q.is_complete
                ? ''
                : ' <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-bold bg-rose-50 text-rose-700 border border-rose-100 uppercase tracking-wide" title="Missing Domain or Difficulty"><i class="bi bi-exclamation-triangle-fill mr-1 text-[9px]"></i> Incomplete</span>';

            const stem = stripTags(q.stem || '');
            const snippet = stem.length <= 300 ? stem : stem.slice(0, 300) + '…';
            const qNum = q.question_number != null ? escapeHtml(q.question_number) : '-';

            let diffBadge = '';
            const diffLower = (q.difficulty || '').toLowerCase();
            if (diffLower === 'easy') {
                diffBadge = '<span class="status-chip text-emerald-700 bg-emerald-50 border border-emerald-100">Easy</span>';
            } else if (diffLower === 'medium') {
                diffBadge = '<span class="status-chip text-amber-700 bg-amber-50 border border-amber-100">Medium</span>';
            } else {
                diffBadge = '<span class="status-chip text-rose-700 bg-rose-50 border border-rose-100">' + escapeHtml(capitalizeFirstLetter(q.difficulty || '')) + '</span>';
            }

            const isOwner = q.created_by === window.__currentUserId || window.__currentUserRole === 'admin';
            let actionButtons = '';
            if (isOwner) {
                actionButtons = '<div class="actions-dropdown">'
                    + '<button type="button" class="px-2.5 py-1.5 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 cursor-pointer hover:bg-slate-50 flex items-center gap-1" data-dropdown-trigger="true" aria-expanded="false" aria-label="Toggle actions menu">'
                    + 'Actions <i class="bi bi-chevron-down text-[10px]"></i>'
                    + '</button>'
                    + '<div class="dropdown-menu hidden">'
                    + '<button type="button" class="dropdown-item edit-question-btn" data-id="' + escapeHtml(q.id) + '"><i class="bi bi-pencil mr-2"></i> Edit</button>'
                    + '<button type="button" class="dropdown-item text-danger delete-question-btn" data-id="' + escapeHtml(q.id) + '"><i class="bi bi-trash mr-2"></i> Delete</button>'
                    + '</div>'
                    + '</div>';
            } else {
                actionButtons = '<button type="button" class="px-2.5 py-1.5 border border-slate-200 text-slate-600 bg-white hover:bg-slate-50 rounded-lg text-xs font-bold flex items-center gap-1 edit-question-btn cursor-pointer" data-id="' + escapeHtml(q.id) + '" aria-label="View question details">'
                    + '<i class="bi bi-eye text-xs leading-none"></i> View'
                    + '</button>';
            }

            const rowClass = isOwner ? '' : 'row-shared';

            return '<tr class="' + rowClass + '">'
                + '<td class="px-5 py-3.5 font-mono font-bold text-slate-400 text-center">' + escapeHtml(q.id) + '</td>'
                + '<td class="px-5 py-3.5 text-center">'
                + '<div class="flex items-center justify-center gap-2">'
                + '<span class="font-bold text-slate-700">' + qNum + '</span>'
                + status
                + '</div>'
                + '</td>'
                + '<td class="px-5 py-3.5 text-center">' + secBadge + '</td>'
                + '<td class="px-5 py-3.5 text-slate-500 font-medium stem-column" title="' + escapeHtml(stem) + '">' + escapeHtml(snippet) + '</td>'
                + '<td class="px-5 py-3.5 text-center">' + usageBadge + '</td>'
                + '<td class="px-5 py-3.5"><span class="text-slate-600 font-semibold text-[11px] block truncate" title="' + escapeHtml(humanizeUnderscores(q.skill_domain || '')) + '">' + escapeHtml(humanizeUnderscores(q.skill_domain || '')) + '</span></td>'
                + '<td class="px-5 py-3.5 text-center">' + diffBadge + '</td>'
                + '<td class="px-5 py-3.5 text-center">'
                + '<div class="flex justify-center gap-1.5">'
                + actionButtons
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
    let html = '<div class="flex flex-wrap justify-between items-center w-full gap-3 px-2">';
    html += '<span class="text-xs font-bold text-slate-500">Page ' + cur + ' of ' + last + ' <span class="mx-1 text-slate-300">•</span> ' + total + ' questions</span>';
    html += '<div class="flex gap-2">';
    html += '<button type="button" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer" data-q-page="prev"' + (cur <= 1 ? ' disabled' : '') + '>Previous</button>';
    html += '<button type="button" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer" data-q-page="next"' + (cur >= last ? ' disabled' : '') + '>Next</button>';
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
            sprHintContainer.classList.add('hidden');
        } else {
            sprHintContainer.classList.remove('hidden');
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
            passageContainer.classList.remove('hidden');
        } else {
            passageContainer.classList.add('hidden');
        }

        // Populate Choices
        if (question.question_type === 'multiple_choice') {
            document.getElementById('editMcqChoicesContainer').classList.remove('hidden');
            document.getElementById('editSprAnswersContainer').classList.add('hidden');
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
            document.getElementById('editMcqChoicesContainer').classList.add('hidden');
            document.getElementById('editSprAnswersContainer').classList.remove('hidden');
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
