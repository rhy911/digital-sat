document.addEventListener('DOMContentLoaded', function() {
    const TEST_DASHBOARD_TAB_KEY = 'testDashboardActiveTab';

    function rememberTestDashboardTab() {
        const activeBtn = document.querySelector('#dashboardTabs .nav-link.active');
        if (!activeBtn) {
            return;
        }
        const target = activeBtn.getAttribute('data-bs-target');
        if (target) {
            sessionStorage.setItem(TEST_DASHBOARD_TAB_KEY, target);
        }
    }

    const SKILL_DOMAINS = {
        reading_writing: [
            { value: 'craft_and_structure', label: 'Craft and Structure' },
            { value: 'information_and_ideas', label: 'Information and Ideas' },
            { value: 'standard_english_conventions', label: 'Standard English Conventions' },
            { value: 'expression_of_ideas', label: 'Expression of Ideas' }
        ],
        math: [
            { value: 'algebra', label: 'Algebra' },
            { value: 'advanced_math', label: 'Advanced Math' },
            { value: 'problem_solving_data_analysis', label: 'Problem-Solving and Data Analysis' },
            { value: 'geometry_trigonometry', label: 'Geometry and Trigonometry' }
        ]
    };

    const config = window.TestDashboardConfig || {};
    const {
        SNAPSHOT_URL,
        QUESTIONS_LIST_URL,
        QUESTIONS_SEARCH_URL,
        CSV_BULK_URL,
        BULK_PREVIEW_URL,
        CSV_BULK_PREVIEW_URL,
        QUESTIONS_PER_PAGE = 25,
        BULK_STORE_URL,
        MEDIA_UPLOAD_URL,
        TESTS_STORE_URL,
        SECTIONS_STORE_URL,
        MODULES_STORE_URL,
        QUESTIONS_ATTACH_URL,
        BASE_URL,
    } = config;

    window.__tdQuestionsPage = 1;
    window.__tdQuestionsPerPage = QUESTIONS_PER_PAGE;
    window.__tdQuestionsQuery = '';

    function showAlert(type, message) {
        const container = document.getElementById('alert-container');
        if (!container) return;
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show shadow-sm`;
        alert.role = 'alert';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        container.appendChild(alert);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    }

    function escapeHtml(str) {
        if (str == null) {
            return '';
        }
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function capitalizeFirstLetter(string) {
        if (!string) return '';
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    function stripTags(html) {
        return String(html).replace(/<[^>]*>/g, '');
    }

    function humanizeUnderscores(value) {
        if (!value) {
            return '';
        }
        return value.split('_').map(function (w) {
            return w.charAt(0).toUpperCase() + w.slice(1);
        }).join(' ');
    }

    function getTomSelectValue(selectId) {
        const el = document.getElementById(selectId);
        if (!el || !el.tomselect) {
            return '';
        }
        const v = el.tomselect.getValue();
        return Array.isArray(v) ? (v[0] || '') : (v || '');
    }

    function optionExistsInSelect(selectEl, value) {
        if (value === '' || value == null) {
            return true;
        }
        const s = String(value);
        return Array.from(selectEl.options).some(function (o) {
            return o.value === s;
        });
    }

    function destroyTomSelectIfAny(selectEl) {
        if (selectEl && selectEl.tomselect) {
            selectEl.tomselect.destroy();
        }
    }

    function initTomSelectOn(selectEl) {
        if (!selectEl || selectEl.tomselect) {
            return;
        }
        new TomSelect(selectEl, {
            create: false,
            sortField: { field: 'text', order: 'asc' }
        });
    }

    function captureTomSelectPreservation(submittedForm) {
        const ids = ['sectionTest', 'moduleSection', 'questionModule', 'bulkQuestionModule', 'questionPassage', 'answerQuestionId', 'explanationQuestionId'];
        const preserve = {};
        ids.forEach(function (id) {
            const el = document.getElementById(id);
            if (!el || (submittedForm && submittedForm.querySelector('#' + id))) {
                return;
            }
            preserve[id] = getTomSelectValue(id);
        });
        return preserve;
    }

    function rebuildSectionTestTomSelect(tests, preserved) {
        const el = document.getElementById('sectionTest');
        if (!el) {
            return;
        }
        destroyTomSelectIfAny(el);
        el.innerHTML = '<option value="">Search test...</option>';
        tests.forEach(function (t) {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.title + ' (ID:' + t.id + ')';
            el.appendChild(opt);
        });
        initTomSelectOn(el);
        if (preserved && optionExistsInSelect(el, preserved)) {
            el.tomselect.setValue(String(preserved), true);
        }
    }

    function rebuildModuleSectionTomSelect(tests, preserved) {
        const el = document.getElementById('moduleSection');
        if (!el) {
            return;
        }
        destroyTomSelectIfAny(el);
        el.innerHTML = '<option value="">Search section...</option>';
        tests.forEach(function (test) {
            (test.sections || []).forEach(function (section) {
                const opt = document.createElement('option');
                opt.value = section.id;
                opt.setAttribute('data-type', section.type);
                opt.textContent = test.title + ' - ' + section.name + ' (ID:' + section.id + ')';
                el.appendChild(opt);
            });
        });
        initTomSelectOn(el);
        if (preserved && optionExistsInSelect(el, preserved)) {
            el.tomselect.setValue(String(preserved), true);
        }
    }

    function rebuildQuestionModuleTomSelect(tests, preserved, selectId) {
        selectId = selectId || 'questionModule';
        const el = document.getElementById(selectId);
        if (!el) {
            return;
        }
        destroyTomSelectIfAny(el);
        el.innerHTML = '<option value="">Search module...</option>';
        tests.forEach(function (test) {
            (test.sections || []).forEach(function (section) {
                (section.modules || []).forEach(function (mod) {
                    const opt = document.createElement('option');
                    opt.value = mod.id;
                    opt.setAttribute('data-section-type', section.type);
                    opt.textContent = test.title + ' - ' + section.name + ' - Mod ' + mod.module_number + ' (' + humanizeUnderscores(mod.difficulty_level) + ')';
                    el.appendChild(opt);
                });
            });
        });
        initTomSelectOn(el);
        if (preserved && optionExistsInSelect(el, preserved)) {
            el.tomselect.setValue(String(preserved), true);
            if (selectId === 'questionModule') {
                autoFetchSectionType(el);
            }
        }
    }

    function rebuildQuestionPassageTomSelect(passages, preserved) {
        const el = document.getElementById('questionPassage');
        if (!el) {
            return;
        }
        destroyTomSelectIfAny(el);
        el.innerHTML = '<option value="">No passage (Standalone) / Search passage...</option>';
        (passages || []).forEach(function (p) {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = stripTags(p.content || '').slice(0, 80) + (stripTags(p.content || '').length > 80 ? '…' : '');
            el.appendChild(opt);
        });
        initTomSelectOn(el);
        if (preserved && optionExistsInSelect(el, preserved)) {
            el.tomselect.setValue(String(preserved), true);
        }
    }

    function initRemoteQuestionPicker(selectId, preservedValue) {
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

    function initRemoteQuestionPickers(preserve) {
        const p = preserve || {};
        initRemoteQuestionPicker('answerQuestionId', p.answerQuestionId);
        initRemoteQuestionPicker('explanationQuestionId', p.explanationQuestionId);
    }

    function questionsListFetchUrl() {
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

    function renderQuestionsPagination(meta) {
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
    }

    function bindQuestionsPaginationOnce() {
        const wrap = document.getElementById('questionsPoolPagination');
        if (!wrap || wrap.dataset.bound === '1') {
            return;
        }
        wrap.dataset.bound = '1';
        wrap.addEventListener('click', async function (e) {
            const btn = e.target.closest('[data-q-page]');
            if (!btn || btn.disabled) {
                return;
            }
            const dir = btn.getAttribute('data-q-page');
            const cur = window.__tdQuestionsPage || 1;
            if (dir === 'prev') {
                window.__tdQuestionsPage = Math.max(1, cur - 1);
            } else if (dir === 'next') {
                window.__tdQuestionsPage = cur + 1;
            }
            try {
                await refreshQuestionsTableOnly();
            } catch (err) {
                showAlert('danger', err.message || 'Failed to load page');
            }
        });
    }

    async function refreshQuestionsTableOnly() {
        const response = await fetch(questionsListFetchUrl(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });
        if (!response.ok) {
            throw new Error('Questions list failed (' + response.status + ')');
        }
        const listJson = await response.json();
        const last = listJson.last_page || 1;
        if ((listJson.current_page || 1) > last) {
            window.__tdQuestionsPage = last;
            return refreshQuestionsTableOnly();
        }
        renderQuestionsTable(listJson.data || []);
        renderQuestionsPagination(listJson);
        const qBadge = document.getElementById('questionsPoolCountBadge');
        if (qBadge && listJson.total != null) {
            qBadge.textContent = listJson.total + ' Total';
        }
    }

    function renderTestsTable(tests) {
        const tbody = document.getElementById('testsTableBody');
        if (!tbody) {
            return;
        }
        if (!tests.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No tests found</td></tr>';
            return;
        }
        tbody.innerHTML = tests.map(function (t) {
            const badge = t.status === 'active' ? 'success' : 'secondary';
            const draftSel = t.status === 'draft' ? ' selected' : '';
            const activeSel = t.status === 'active' ? ' selected' : '';
            const archSel = t.status === 'archived' ? ' selected' : '';
            return '<tr>'
                + '<td>' + escapeHtml(t.id) + '</td>'
                + '<td><strong>' + escapeHtml(t.title) + '</strong></td>'
                + '<td>' + escapeHtml(humanizeUnderscores(t.test_type)) + '</td>'
                + '<td><span class="badge bg-' + badge + '">' + escapeHtml(humanizeUnderscores(t.status)) + '</span></td>'
                + '<td>' + escapeHtml(t.total_duration_minutes) + 'm</td>'
                + '<td><div class="d-flex gap-2">'
                + '<select class="form-select form-select-sm status-select" data-test-id="' + escapeHtml(t.id) + '">'
                + '<option value="draft"' + draftSel + '>Draft</option>'
                + '<option value="active"' + activeSel + '>Active</option>'
                + '<option value="archived"' + archSel + '>Archived</option>'
                + '</select>'
                + '<button type="button" class="btn btn-sm btn-outline-danger delete-test-btn" data-id="' + escapeHtml(t.id) + '">Delete</button>'
                + '</div></td></tr>';
        }).join('');
    }

    function renderSectionsTable(tests) {
        const tbody = document.getElementById('sectionsTableBody');
        if (!tbody) {
            return;
        }
        const rows = [];
        tests.forEach(function (test) {
            (test.sections || []).forEach(function (section) {
                rows.push('<tr>'
                    + '<td>' + escapeHtml(section.id) + '</td>'
                    + '<td>' + escapeHtml(test.title) + '</td>'
                    + '<td><strong>' + escapeHtml(section.name) + '</strong></td>'
                    + '<td>' + escapeHtml(humanizeUnderscores(section.type)) + '</td>'
                    + '<td>' + escapeHtml(section.order) + '</td>'
                    + '<td><button type="button" class="btn btn-sm btn-outline-danger delete-section-btn" data-id="' + escapeHtml(section.id) + '">Delete</button></td>'
                    + '</tr>');
            });
        });
        tbody.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No sections yet</td></tr>';
    }

    function moduleDifficultyBadgeClass(level) {
        if (level === 'hard') {
            return 'danger';
        }
        if (level === 'easy') {
            return 'success';
        }
        return 'primary';
    }

    function renderModulesTable(tests) {
        const tbody = document.getElementById('modulesTableBody');
        if (!tbody) {
            return;
        }
        const rows = [];
        tests.forEach(function (test) {
            (test.sections || []).forEach(function (section) {
                (section.modules || []).forEach(function (mod) {
                    const diffClass = moduleDifficultyBadgeClass(mod.difficulty_level);
                    rows.push('<tr>'
                        + '<td>' + escapeHtml(mod.id) + '</td>'
                        + '<td><small>' + escapeHtml(test.title) + '</small><br><strong>' + escapeHtml(section.name) + '</strong></td>'
                        + '<td>' + escapeHtml(mod.module_number) + '</td>'
                        + '<td><span class="badge bg-secondary">' + escapeHtml(mod.order) + '</span></td>'
                        + '<td><span class="badge bg-' + diffClass + '">' + escapeHtml(humanizeUnderscores(mod.difficulty_level)) + '</span></td>'
                        + '<td>' + escapeHtml(mod.duration_minutes) + 'm</td>'
                        + '<td>' + escapeHtml(mod.total_questions) + '</td>'
                        + '<td><button type="button" class="btn btn-sm btn-outline-danger delete-module-btn" data-id="' + escapeHtml(mod.id) + '">Delete</button></td>'
                        + '</tr>');
                });
            });
        });
        tbody.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No modules yet</td></tr>';
    }

    function renderQuestionsTable(questions) {
        const tbody = document.getElementById('questionsTableBody');
        if (!tbody) {
            return;
        }
        if (!questions.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No questions found</td></tr>';
            return;
        }
        tbody.innerHTML = questions.map(function (q) {
            const sec = q.section_type === 'reading_writing' ? 'R&W' : 'Math';
            const pre = q.is_pretest ? '<span class="text-danger">● Yes</span>' : 'No';
            const status = q.is_complete ? '' : ' <span class="badge bg-warning text-dark" title="Missing Domain or Difficulty">Incomplete</span>';
            const stem = stripTags(q.stem || '');
            const snippet = stem.length <= 40 ? stem : stem.slice(0, 40) + '…';
            const qNum = q.question_number != null ? '<strong>' + q.question_number + '</strong>' : '-';
            return '<tr>'
                + '<td>' + escapeHtml(q.id) + '</td>'
                + '<td>' + qNum + status + '</td>'
                + '<td><small>' + escapeHtml(sec) + '</small></td>'
                + '<td>' + escapeHtml(snippet) + '</td>'
                + '<td>' + pre + '</td>'
                + '<td><small>' + escapeHtml(q.skill_domain || '') + '</small></td>'
                + '<td><small class="badge bg-light text-dark border">' + escapeHtml(capitalizeFirstLetter(q.difficulty || '')) + '</small></td>'
                + '<td>'
                + '<div class="d-flex gap-1">'
                + '<button type="button" class="btn btn-sm btn-outline-primary edit-question-btn" data-id="' + escapeHtml(q.id) + '">Edit</button>'
                + '<button type="button" class="btn btn-sm btn-outline-danger delete-question-btn" data-id="' + escapeHtml(q.id) + '">×</button>'
                + '</div>'
                + '</td>'
                + '</tr>';
        }).join('');
    }

    function rebuildAllTomSelects(payload, preserve) {
        const p = preserve || {};
        const tests = payload.tests || [];
        const passages = payload.passages || [];
        rebuildSectionTestTomSelect(tests, p.sectionTest);
        rebuildModuleSectionTomSelect(tests, p.moduleSection);
        rebuildQuestionModuleTomSelect(tests, p.questionModule, 'questionModule');
        rebuildQuestionModuleTomSelect(tests, p.bulkQuestionModule, 'bulkQuestionModule');
        rebuildQuestionPassageTomSelect(passages, p.questionPassage);
        initRemoteQuestionPickers(p);
    }

    async function refreshTestDashboardData(preserveTomSelects) {
        const [snapRes, listRes] = await Promise.all([
            fetch(SNAPSHOT_URL, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }),
            fetch(questionsListFetchUrl(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
        ]);
        if (!snapRes.ok) {
            throw new Error('Snapshot request failed (' + snapRes.status + ')');
        }
        let payload = { tests: [], passages: [] };
        try {
            payload = await snapRes.json();
        } catch (e) {
            console.error('Snapshot JSON parse failed');
        }

        renderTestsTable(payload.tests || []);
        renderSectionsTable(payload.tests || []);
        renderModulesTable(payload.tests || []);
        let listJson = { data: [], total: 0, current_page: 1, last_page: 1 };
        if (listRes.ok) {
            try {
                listJson = await listRes.json();
            } catch (e) { /* ignore */ }
        }
        const last = listJson.last_page || 1;
        if ((listJson.current_page || 1) > last) {
            window.__tdQuestionsPage = last;
            await refreshQuestionsTableOnly();
        } else {
            renderQuestionsTable(listJson.data || []);
            renderQuestionsPagination(listJson);
            const qBadge = document.getElementById('questionsPoolCountBadge');
            if (qBadge && listJson.total != null) {
                qBadge.textContent = listJson.total + ' Total';
            }
        }
        rebuildAllTomSelects(payload, preserveTomSelects);
    }

    window.refreshTestDashboardData = function() {
        rememberTestDashboardTab();
        window.location.reload();
    };
    window.captureTomSelectPreservation = captureTomSelectPreservation;

    function initTestDashboardDelegatedActions() {
        const root = document.getElementById('dashboardTabContent');
        if (!root || root.dataset.delegatedActionsBound === '1') {
            return;
        }
        root.dataset.delegatedActionsBound = '1';

        root.addEventListener('change', function (e) {
            const sel = e.target.closest('select.status-select[data-test-id]');
            if (!sel) {
                return;
            }
            updateTestStatus(sel.getAttribute('data-test-id'), sel.value);
        });

        root.addEventListener('click', async function (e) {
            const btn = e.target.closest('.delete-test-btn, .delete-section-btn, .delete-module-btn, .delete-question-btn, .edit-question-btn');
            if (!btn) {
                return;
            }

            const id = btn.getAttribute('data-id');

            if (btn.classList.contains('edit-question-btn')) {
                openEditQuestionModal(id);
                return;
            }

            if (!confirm('Permanently delete this item?')) {
                return;
            }

            let deleteChildren = false;
            let url;
            if (btn.classList.contains('delete-test-btn')) {
                url = `${BASE_URL}/tests/${id}`;
                if (confirm('Also delete all sections, modules, and questions inside this test?')) {
                    deleteChildren = true;
                }
            } else if (btn.classList.contains('delete-section-btn')) {
                url = `${BASE_URL}/sections/${id}`;
                if (confirm('Also delete all modules and questions inside this section?')) {
                    deleteChildren = true;
                }
            } else if (btn.classList.contains('delete-module-btn')) {
                url = `${BASE_URL}/modules/${id}`;
                if (confirm('Also delete all questions linked to this module?')) {
                    deleteChildren = true;
                }
            } else if (btn.classList.contains('delete-question-btn')) {
                url = `${BASE_URL}/questions/${id}`;
            } else {
                return;
            }

            const preserve = captureTomSelectPreservation(null);
            try {
                const finalUrl = deleteChildren ? `${url}?delete_children=1` : url;
                const response = await fetch(finalUrl, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                if (response.ok) {
                    showAlert('success', 'Deleted successfully');
                    await refreshTestDashboardData(preserve);
                } else {
                    let msg = 'Delete failed';
                    try {
                        const j = await response.json();
                        msg = j.message || msg;
                    } catch (err) { /* ignore */ }
                    showAlert('danger', msg);
                }
            } catch (error) {
                showAlert('danger', 'Error: ' + error.message);
            }
        });
    }

    async function openEditQuestionModal(id) {
        try {
            const response = await fetch(`${BASE_URL}/questions/${id}`);
            if (!response.ok) throw new Error('Failed to fetch question data');
            const result = await response.json();
            const question = result.data;

            document.getElementById('editQuestionId').value = question.id;
            document.getElementById('editQuestionIdDisplay').textContent = question.id;
            document.getElementById('editQuestionStem').value = question.stem;
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
            if (sectionType === 'reading_writing' && question.passage) {
                passageContainer.classList.remove('d-none');
                document.getElementById('editPassageContent').value = question.passage.content;
            } else {
                passageContainer.classList.add('d-none');
                document.getElementById('editPassageContent').value = '';
            }

            const modal = new bootstrap.Modal(document.getElementById('editQuestionModal'));
            
            // Populate Choices
            if (question.question_type === 'multiple_choice') {
                document.getElementById('editMcqChoicesContainer').classList.remove('d-none');
                document.getElementById('editSprAnswersContainer').classList.add('d-none');
                // Clear choices first
                ['A','B','C','D'].forEach(lbl => {
                    const ci = document.getElementById(`editChoice${lbl}Content`);
                    const cr = document.getElementById(`editChoice${lbl}Correct`);
                    if(ci) ci.value = '';
                    if(cr) cr.checked = false;
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

            // Populate Explanation
            if (question.explanation) {
                document.getElementById('editExplanation').value = question.explanation.explanation;
                document.getElementById('editRationaleA').value = question.explanation.rationale_a || '';
                document.getElementById('editRationaleB').value = question.explanation.rationale_b || '';
                document.getElementById('editRationaleC').value = question.explanation.rationale_c || '';
                document.getElementById('editRationaleD').value = question.explanation.rationale_d || '';
            } else {
                document.getElementById('editExplanation').value = '';
                document.getElementById('editRationaleA').value = '';
                document.getElementById('editRationaleB').value = '';
                document.getElementById('editRationaleC').value = '';
                document.getElementById('editRationaleD').value = '';
            }

            // Populate Media List
            refreshEditMediaList(question);

            modal.show();
        } catch (error) {
            showAlert('danger', error.message);
        }
    }

    function refreshEditMediaList() {
        const mediaList = document.getElementById('editMediaList');
        if (!mediaList) return;
        mediaList.innerHTML = '';

        const fields = [
            document.getElementById('editQuestionStem')?.value || '',
            document.getElementById('editPassageContent')?.value || '',
            document.getElementById('editExplanation')?.value || '',
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
                                onclick="removeMediaFromEditModal('${url}', true)" title="Remove from all fields">
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
                                onclick="removeMediaFromEditModal('${filename}', false)" title="Remove from all fields">
                            &times;
                        </button>
                    </div>
                `;
                mediaList.appendChild(col);
            });
        }
    }

    window.removeMediaFromEditModal = function(identifier, isUrl) {
        if (!confirm('Are you sure you want to remove this media from all fields?')) return;
        
        const textareas = [
            'editQuestionStem', 'editPassageContent', 'editExplanation',
            'editRationaleA', 'editRationaleB', 'editRationaleC', 'editRationaleD'
        ];
        
        const escaped = identifier.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = isUrl ? new RegExp(`!\\[.*?\\]\\(\\s*${escaped}\\s*\\)`, 'g') : new RegExp(`\\[Media:\\s*${escaped}\\s*\\]`, 'gi');
        
        textareas.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = el.value.replace(regex, '').trim();
        });

        ['A', 'B', 'C', 'D'].forEach(lbl => {
            const el = document.getElementById(`editChoice${lbl}Content`);
            if (el) el.value = el.value.replace(regex, '').trim();
        });

        refreshEditMediaList();
    };

    document.getElementById('editQuestionMediaUpload')?.addEventListener('change', async function(e) {
        if (!this.files || !this.files.length) return;
        
        const formData = new FormData();
        formData.append('image', this.files[0]);

        try {
            const response = await fetch(MEDIA_UPLOAD_URL, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            });
            const result = await response.json();
            if (response.ok) {
                const uploadedFilename = this.files[0].name.trim();
                const markdown = result.markdown;
                
                const textareas = [
                    'editQuestionStem', 'editPassageContent', 'editExplanation',
                    'editRationaleA', 'editRationaleB', 'editRationaleC', 'editRationaleD'
                ];
                
                let replacedAny = false;
                const placeholderRegex = new RegExp(`\\[Media:\\s*${uploadedFilename.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*\\]`, 'gi');

                textareas.forEach(id => {
                    const el = document.getElementById(id);
                    if (el && placeholderRegex.test(el.value)) {
                        el.value = el.value.replace(placeholderRegex, markdown);
                        replacedAny = true;
                    }
                });

                ['A', 'B', 'C', 'D'].forEach(lbl => {
                    const el = document.getElementById(`editChoice${lbl}Content`);
                    if (el && placeholderRegex.test(el.value)) {
                        el.value = el.value.replace(placeholderRegex, markdown);
                        replacedAny = true;
                    }
                });

                if (!replacedAny) {
                    const stem = document.getElementById('editQuestionStem');
                    if (stem) stem.value += '\n' + markdown;
                }

                refreshEditMediaList();
                showAlert('success', replacedAny ? 'Placeholder replaced with image' : 'Media uploaded and inserted into stem');
            } else {
                showAlert('danger', result.message || 'Upload failed');
            }
        } catch (error) {
            showAlert('danger', error.message);
        } finally {
            this.value = '';
        }
    });

    document.getElementById('editQuestionForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const id = document.getElementById('editQuestionId').value;
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        if (data.question_type === 'multiple_choice') {
            data.choices = [];
            ['A', 'B', 'C', 'D'].forEach((label, index) => {
                const contentInput = document.getElementById(`editChoice${label}Content`);
                if (contentInput) {
                    data.choices.push({
                        label: label,
                        content: contentInput.value,
                        order: index + 1
                    });
                }
            });
        }

        data.is_pretest = document.getElementById('editIsPretest').checked ? 1 : 0;
        data.calculator_allowed = document.getElementById('editCalculatorAllowed').checked ? 1 : 0;

        try {
            const response = await fetch(`${BASE_URL}/questions/${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (response.ok) {
                showAlert('success', 'Question updated successfully');
                bootstrap.Modal.getInstance(document.getElementById('editQuestionModal')).hide();
                await refreshQuestionsTableOnly();
            } else {
                showAlert('danger', result.message || 'Update failed');
            }
        } catch (error) {
            showAlert('danger', error.message);
        }
    });

    window.updateSectionName = function(select) {
        const nameInput = document.getElementById('sectionName');
        if (!nameInput) return;
        if (select.value === 'reading_writing') {
            nameInput.value = 'Reading and Writing';
        } else if (select.value === 'math') {
            nameInput.value = 'Math';
        }
    };

    window.autoFetchSectionType = function(select) {
        const selectedOption = select.options[select.selectedIndex];
        const sectionType = selectedOption.getAttribute('data-section-type');
        const sectionTypeSelect = document.getElementById('qSectionType');
        
        if (sectionType && sectionTypeSelect) {
            sectionTypeSelect.value = sectionType;
            updateSkillDomains(sectionTypeSelect);
        }
    };

    async function updateTestStatus(testId, status) {
        const preserve = captureTomSelectPreservation(null);
        try {
            const response = await fetch(`${BASE_URL}/tests/${testId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ status: status })
            });

            if (response.ok) {
                showAlert('success', 'Status updated!');
                try {
                    await refreshTestDashboardData(preserve);
                } catch (err) {
                    rememberTestDashboardTab();
                    window.location.reload();
                }
            } else {
                const res = await response.json();
                showAlert('danger', res.message || 'Failed to update status');
            }
        } catch (error) {
            showAlert('danger', 'Error: ' + error.message);
        }
    }

    window.applyModuleDefaults = function(select) {
        const selectedOption = select.options[select.selectedIndex];
        const type = selectedOption.getAttribute('data-type');
        const durationInput = document.getElementById('moduleDuration');
        const questionsInput = document.getElementById('totalQuestions');

        if (!durationInput || !questionsInput) return;

        if (type === 'reading_writing') {
            durationInput.value = 32;
            questionsInput.value = 27;
        } else if (type === 'math') {
            durationInput.value = 35;
            questionsInput.value = 22;
        }
    };

    window.updateSkillDomains = function(select) {
        const domainSelect = document.getElementById('skillDomain');
        if (!domainSelect) return;
        const type = select.value;

        const questionTypeSelect = document.getElementById('questionType');
        const sprOption = questionTypeSelect?.querySelector?.('option[value="student_produced_response"]');
        const sprHintContainer = document.getElementById('sprHintContainer');

        if (type === 'reading_writing') {
            if (questionTypeSelect && questionTypeSelect.value === 'student_produced_response') {
                questionTypeSelect.value = 'multiple_choice';
            }
            if (sprOption) sprOption.style.display = 'none';
            if (sprHintContainer) sprHintContainer.classList.add('d-none');
        } else {
            if (sprOption) sprOption.style.display = 'block';
            if (sprHintContainer) sprHintContainer.classList.remove('d-none');
        }

        domainSelect.innerHTML = '<option value="">Select domain...</option>';

        if (type && SKILL_DOMAINS[type]) {
            SKILL_DOMAINS[type].forEach(domain => {
                const opt = document.createElement('option');
                opt.value = domain.value;
                opt.textContent = domain.label;
                domainSelect.appendChild(opt);
            });
        }
    };

    initTestDashboardDelegatedActions();

    document.querySelectorAll('#dashboardTabs [data-bs-toggle="tab"]').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function (e) {
            const target = e.target.getAttribute('data-bs-target');
            if (target) {
                sessionStorage.setItem(TEST_DASHBOARD_TAB_KEY, target);
            }
        });
    });

    document.querySelectorAll('.tom-select').forEach(function (el) {
        if (el.classList.contains('tom-select-remote-question')) {
            return;
        }
        new TomSelect(el, {
            create: false,
            sortField: { field: 'text', order: 'asc' }
        });
    });
    initRemoteQuestionPickers({});
    bindQuestionsPaginationOnce();

    document.getElementById('questionsTableFilterBtn')?.addEventListener('click', async function () {
        window.__tdQuestionsQuery = (document.getElementById('questionsTableFilter')?.value || '').trim();
        window.__tdQuestionsSection = document.getElementById('questionsTableSectionFilter')?.value || '';
        window.__tdQuestionsModule = document.getElementById('questionsTableModuleFilter')?.value || '';
        window.__tdQuestionsStatus = document.getElementById('questionsTableStatusFilter')?.value || '';
        window.__tdQuestionsPage = 1;
        try {
            await refreshQuestionsTableOnly();
        } catch (err) {
            showAlert('danger', err.message || 'Failed to filter');
        }
    });

    document.getElementById('questionsTableFilterClearBtn')?.addEventListener('click', async function () {
        const inp = document.getElementById('questionsTableFilter');
        if (inp) inp.value = '';
        const sec = document.getElementById('questionsTableSectionFilter');
        if (sec) sec.value = '';
        const mod = document.getElementById('questionsTableModuleFilter');
        if (mod) mod.value = '';
        const stat = document.getElementById('questionsTableStatusFilter');
        if (stat) stat.value = '';
        window.__tdQuestionsQuery = '';
        window.__tdQuestionsSection = '';
        window.__tdQuestionsModule = '';
        window.__tdQuestionsStatus = '';
        window.__tdQuestionsPage = 1;
        try {
            await refreshQuestionsTableOnly();
        } catch (err) {
            showAlert('danger', err.message || 'Failed to clear filter');
        }
    });

    window.__testDashboardEditors = {};

    const savedTab = sessionStorage.getItem(TEST_DASHBOARD_TAB_KEY);
    if (savedTab) {
        const trigger = document.querySelector('#dashboardTabs [data-bs-target="' + savedTab + '"]');
        if (trigger && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            bootstrap.Tab.getOrCreateInstance(trigger).show();
        }
    }

    function setupForm(formId, url) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(data)
                });

                let result = {};
                try {
                    result = await response.json();
                } catch (parseErr) {
                    showAlert('danger', 'Unexpected response from server (status ' + response.status + ').');
                    return;
                }

                if (response.ok) {
                    showAlert('success', result.message || 'Created successfully!');
                    const preserve = captureTomSelectPreservation(form);
                    form.reset();
                    try {
                        await refreshTestDashboardData(preserve);
                    } catch (err) {
                        showAlert('danger', 'Saved, but refresh failed — reloading page. ' + err.message);
                        rememberTestDashboardTab();
                        window.location.reload();
                    }
                } else {
                    let msg = result.message || 'Validation failed';
                    if (result.errors && typeof result.errors === 'object') {
                        const parts = Object.values(result.errors).flat();
                        if (parts.length) {
                            msg = parts.join(' ');
                        }
                    }
                    showAlert('danger', msg);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        });
    }

    setupForm('testForm', TESTS_STORE_URL);
    setupForm('sectionForm', SECTIONS_STORE_URL);
    setupForm('moduleForm', MODULES_STORE_URL);
    setupForm('attachQuestionForm', QUESTIONS_ATTACH_URL);

    // --- Bulk Import Helpers ---
    function setBulkQuestionsJson(obj) {
        const ta = document.getElementById('bulkQuestionsJson');
        if (ta) {
            ta.value = JSON.stringify(obj, null, 2);
        }
    }

    function downloadJsonFile(filename, obj) {
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

    function csvEscapeCell(val) {
        const s = String(val);
        if (/[",\n\r]/.test(s)) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    function downloadCsvFile(filename, rows) {
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

    // --- Event Listeners for Bulk Import ---
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

    const previewModal = new bootstrap.Modal(document.getElementById('importPreviewModal'));

    function renderPreview(items) {
        const container = document.getElementById('previewContent');
        if (!items || !items.length) {
            container.innerHTML = '<div class="alert alert-warning">No items found to preview.</div>';
            return;
        }

        const processMedia = (text) => {
            if (!text || typeof text !== 'string') return text;
            return text.replace(/(?<!\!)\[Media:([^\]]+)\]/gi, (match, filename) => {
                return `<img src="/storage/media/${filename}" alt="${filename}" class="question-media img-fluid mb-2 d-block mx-auto" style="max-height: 300px;">`;
            });
        };

        let html = '';
        items.forEach((item, index) => {
            item.stem = processMedia(item.stem);
            if (item.passage) {
                if (typeof item.passage === 'string') {
                    item.passage = processMedia(item.passage);
                } else if (item.passage.content) {
                    item.passage.content = processMedia(item.passage.content);
                }
            }
            if (item.choices) {
                item.choices.forEach(c => {
                    if (c.content) c.content = processMedia(c.content);
                });
            }
            if (item.explanation) item.explanation = processMedia(item.explanation);

            const sectionBadge = item.section_type 
                ? `<span class="badge bg-secondary">${item.section_type === 'reading_writing' ? 'Reading & Writing' : 'Math'}</span>` 
                : '';
            
            html += `
                <div class="card mb-4 shadow-sm border-info">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-info text-dark">Item ${index + 1}</span>
                            ${sectionBadge}
                            <span class="badge bg-primary">${humanizeUnderscores(item.skill_domain)}</span>
                            ${item.skill_subdomain ? `<span class="badge bg-outline-primary border border-primary text-primary">${item.skill_subdomain}</span>` : ''}
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-dark">${humanizeUnderscores(item.difficulty)}</span>
                            ${item.is_pretest ? '<span class="badge bg-danger">Pretest</span>' : ''}
                            ${item.external_id ? `<small class="text-muted">ID: ${item.external_id}</small>` : ''}
                        </div>
                    </div>
                    <div class="card-body">
                        ${item.passage ? `
                            <div class="p-3 mb-3 bg-white border-start border-4 border-info rounded-end shadow-sm">
                                <h6 class="text-info mb-2"><i class="bi bi-justify-left"></i> Passage</h6>
                                <div class="passage-content small" style="max-height: 200px; overflow-y: auto;">
                                    ${item.passage.content || item.passage}
                                </div>
                                ${item.passage.source_title ? `<div class="mt-2 text-muted x-small">Source: ${item.passage.source_title}</div>` : ''}
                            </div>
                        ` : ''}
                        <div class="mb-4">
                            <h6 class="fw-bold mb-2">Question Stem:</h6>
                            <div class="p-3 bg-white border rounded shadow-sm">${item.stem}</div>
                        </div>
                        ${item.choices ? `
                            <h6 class="fw-bold mb-2">Choices:</h6>
                            <div class="row g-3 mb-4">
                                ${item.choices.map(c => `
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded h-100 ${c.is_correct ? 'bg-success bg-opacity-10 border-success shadow-sm' : 'bg-white'}">
                                            <div class="d-flex align-items-center gap-2">
                                                <strong class="rounded-circle bg-light border px-2 py-1">${c.label}</strong>
                                                <div class="flex-grow-1">${c.content}</div>
                                                ${c.is_correct ? '<i class="bi bi-check-circle-fill text-success"></i>' : ''}
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                        ${item.spr_correct_answers ? `
                            <div class="mb-4">
                                <h6 class="fw-bold mb-2">Accepted Answers (SPR):</h6>
                                <div class="p-3 bg-success bg-opacity-10 border border-success rounded d-flex flex-wrap gap-2">
                                    ${item.spr_correct_answers.map(ans => `<span class="badge bg-success">${ans}</span>`).join('')}
                                </div>
                                ${item.spr_hint ? `<div class="mt-1 small text-muted italic">Hint: ${item.spr_hint}</div>` : ''}
                            </div>
                        ` : ''}
                        ${item.explanation ? `
                            <div class="mt-3 p-3 bg-light border rounded small">
                                <h6 class="fw-bold text-muted mb-2"><i class="bi bi-info-circle"></i> Explanation:</h6>
                                <div class="text-muted">${item.explanation}</div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
        previewModal.show();
        if (window.smartRenderMath) {
            window.smartRenderMath(container);
        } else if (window.renderMathInElement) {
            window.renderMathInElement(container, {
                delimiters: [
                    {left: "$", right: "$", display: false}
                ],
                throwOnError : false
            });
        }
    }

    async function handlePreview(isCsv) {
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
            if (response.ok) renderPreview(result.data.items);
            else showAlert('danger', result.message || 'Preview failed');
        } catch (error) {
            showAlert('danger', 'Error: ' + error.message);
        }
    }

    document.getElementById('bulkPreviewBtn')?.addEventListener('click', () => handlePreview(false));
    document.getElementById('bulkCsvPreviewBtn')?.addEventListener('click', () => handlePreview(true));

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
                await refreshTestDashboardData(captureTomSelectPreservation(null));
            } else {
                showAlert('danger', result.message || 'Bulk import failed');
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
                await refreshTestDashboardData(captureTomSelectPreservation(null));
            } else showAlert('danger', result.message || 'CSV import failed');
        } catch (error) { showAlert('danger', 'Error: ' + error.message); }
    });

    // --- Easy Builder Logic ---
    let builderBlockCount = 0;
    const builderEditors = {};

    window.addBuilderBlock = function() {
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
            toolbar: ["bold", "italic", { name: "underline", action: (editor) => editor.codemirror.replaceSelection(`<u>${editor.codemirror.getSelection()}</u>`), className: "fa fa-underline", title: "Underline" }, "heading", "|", "quote", "unordered-list", "ordered-list", "|", "preview"],
            status: false
        });
        
        block.scrollIntoView({ behavior: 'smooth' });

        const imgInput = block.querySelector('.builder-image-input');
        const imgBtn = block.querySelector('.upload-image-btn');
        imgBtn.onclick = () => imgInput.click();
        imgInput.onchange = async function() {
            if (!this.files?.[0]) return;
            const fd = new FormData();
            fd.append('image', this.files[0]);
            imgBtn.disabled = true;
            try {
                const res = await fetch(MEDIA_UPLOAD_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: fd });
                const result = await res.json();
                if (result.success) {
                    const mde = builderEditors[`stem_${block.dataset.index}`];
                    if (mde) mde.codemirror.replaceRange(`\n${result.markdown}\n`, mde.codemirror.getCursor());
                } else showAlert('danger', result.message || 'Upload failed');
            } catch (err) { showAlert('danger', 'Error: ' + err.message); }
            finally { imgBtn.disabled = false; this.value = ''; }
        };

        block.querySelector('.remove-block-btn').onclick = function() {
            if (builderEditors[`stem_${block.dataset.index}`]) { builderEditors[`stem_${block.dataset.index}`].toTextArea(); delete builderEditors[`stem_${block.dataset.index}`]; }
            if (builderEditors[`passage_${block.dataset.index}`]) { builderEditors[`passage_${block.dataset.index}`].toTextArea(); delete builderEditors[`passage_${block.dataset.index}`]; }
            block.remove();
            const blocks = document.querySelectorAll('.builder-block');
            blocks.forEach((b, i) => b.querySelector('.text-secondary').textContent = `Question #${i + 1}`);
        };
    };

    function syncBuilderBlockDomain(block) {
        const moduleId = getTomSelectValue('builderModuleId');
        if (!moduleId) return;
        const type = document.getElementById('builderModuleId').selectedOptions[0].getAttribute('data-section-type');
        const passageContainer = block.querySelector('.builder-passage-container');
        const index = block.dataset.index;

        if (type === 'reading_writing') {
            passageContainer.classList.remove('d-none');
            if (!builderEditors[`passage_${index}`]) {
                builderEditors[`passage_${index}`] = new EasyMDE({
                    element: block.querySelector('.builder-passage'),
                    placeholder: "Enter passage content...",
                    minHeight: "150px",
                    toolbar: ["bold", "italic", { name: "underline", action: (editor) => editor.codemirror.replaceSelection(`<u>${editor.codemirror.getSelection()}</u>`), className: "fa fa-underline", title: "Underline" }, "|", "unordered-list", "|", "preview"],
                    status: false
                });
            }
        } else passageContainer.classList.add('d-none');

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

    document.getElementById('addBuilderBlockBtn')?.addEventListener('click', window.addBuilderBlock);
    document.getElementById('builderModuleId')?.addEventListener('change', () => document.querySelectorAll('.builder-block').forEach(syncBuilderBlockDomain));

    document.getElementById('clearBuilderBtn')?.addEventListener('click', function() {
        if (confirm('Clear all questions in builder?')) {
            Object.values(builderEditors).forEach(mde => mde.toTextArea());
            for (const key in builderEditors) delete builderEditors[key];
            document.getElementById('builderBlocksContainer').innerHTML = '';
            builderBlockCount = 0;
        }
    });

    document.getElementById('submitBuilderBtn')?.addEventListener('click', async function() {
        const moduleId = getTomSelectValue('builderModuleId');
        const blocks = document.querySelectorAll('.builder-block');
        if (!moduleId || blocks.length === 0) { showAlert('danger', 'Module and at least one block required.'); return; }

        const items = [];
        let isValid = true;
        blocks.forEach(block => {
            const index = block.dataset.index;
            const stem = builderEditors[`stem_${index}`]?.value().trim();
            if (!stem) { block.classList.add('border-danger'); isValid = false; }
            else block.classList.remove('border-danger');

            const choices = [];
            const correctLabel = block.querySelector('.builder-correct-radio:checked')?.value || 'A';
            block.querySelectorAll('.builder-choice-content').forEach(input => {
                const label = input.getAttribute('data-label');
                const content = input.value.trim();
                if (!content) isValid = false;
                choices.push({ label, content, is_correct: label === correctLabel, order: label.charCodeAt(0) - 64 });
            });

            const item = { stem, question_type: 'multiple_choice', difficulty: block.querySelector('.builder-difficulty').value || null, skill_domain: block.querySelector('.builder-domain').value || null, choices, explanation: block.querySelector('.builder-explanation').value.trim() };
            const type = document.getElementById('builderModuleId').selectedOptions[0].getAttribute('data-section-type');
            if (type === 'reading_writing' && builderEditors[`passage_${index}`]) {
                const passageContent = builderEditors[`passage_${index}`].value().trim();
                if (passageContent) item.passage = { content: passageContent };
            }
            items.push(item);
        });

        if (!isValid) { showAlert('danger', 'Fill in all required fields.'); return; }

        try {
            const response = await fetch(BULK_STORE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify({ module_id: moduleId, start_position: document.getElementById('builderStartPosition').value || 1, items })
            });
            if (response.ok) {
                showAlert('success', `Imported ${items.length} questions.`);
                Object.values(builderEditors).forEach(mde => mde.toTextArea());
                for (const key in builderEditors) delete builderEditors[key];
                document.getElementById('builderBlocksContainer').innerHTML = '';
                builderBlockCount = 0;
                await refreshTestDashboardData(captureTomSelectPreservation(null));
            } else showAlert('danger', 'Import failed.');
        } catch (error) { showAlert('danger', 'Error: ' + error.message); }
    });

    document.getElementById('bulkZipImportBtn')?.addEventListener('click', async function() {
        const fileInput = document.getElementById('bulkZipFile');
        const moduleId = getTomSelectValue('bulkQuestionModule');
        if (!moduleId || !fileInput.files[0]) { showAlert('danger', 'Module and ZIP file required.'); return; }
        const fd = new FormData();
        fd.append('zip_file', fileInput.files[0]);
        fd.append('module_id', moduleId);
        fd.append('start_position', document.getElementById('bulkStartPosition')?.value || 1);
        this.disabled = true; this.innerHTML = 'Importing...';
        try {
            const response = await fetch(`${BASE_URL}/questions/bulk-zip`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: fd
            });
            const result = await response.json();
            if (response.ok) { showAlert('success', result.message); fileInput.value = ''; await refreshTestDashboardData(captureTomSelectPreservation(null)); }
            else showAlert('danger', result.message || 'ZIP import failed.');
        } catch (err) { showAlert('danger', 'Error: ' + err.message); }
        finally { this.disabled = false; this.innerHTML = 'Import ZIP'; }
    });

});
