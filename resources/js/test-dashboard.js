document.addEventListener('DOMContentLoaded', function () {
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
        SECTIONS_LINK_MODULE_URL,
        MODULES_STORE_URL,
        QUESTIONS_ATTACH_URL,
        BASE_URL,
    } = config;

    window.__tdQuestionsPage = 1;
    window.__tdQuestionsPerPage = QUESTIONS_PER_PAGE;
    window.__tdQuestionsQuery = '';

    let editPassageEditor, editStemEditor, editExplanationEditor;

    function compileMarkdownToHtml(text) {
        if (!text) return '';
        try {
            // Fix loose formatting like "** bold **" which standard marked.js ignores
            text = text.replace(/\*\*\s*([^*]+?)\s*\*\*/g, '**$1**');
            
            if (window.marked) {
                const markedOptions = { breaks: true, gfm: true };
                if (typeof window.marked.parse === 'function') {
                    return window.marked.parse(text, markedOptions);
                } else if (typeof window.marked === 'function') {
                    if (window.marked.setOptions) window.marked.setOptions(markedOptions);
                    return window.marked(text);
                }
            }
            if (window.EasyMDE && typeof window.EasyMDE.prototype.markdown === 'function') {
                return window.EasyMDE.prototype.markdown(text);
            }
        } catch (e) {
            console.error('Markdown compile failed', e);
        }
        return text.replace(/\n/g, '<br>');
    }

    function processMedia(text) {
        if (!text || typeof text !== 'string') return text;
        // Convert backslash delimiters to $$ for consistent preview rendering
        text = text.replace(/\\\(/g, '$$').replace(/\\\)/g, '$$');
        return text.replace(/(?<!\!)\[Media:([^\]]+)\]/gi, (match, filename) => {
            return `<img src="/storage/media/${filename}" alt="${filename}" class="question-media img-fluid mb-2 d-block mx-auto" style="max-height: 300px;">`;
        });
    }

    function getPremiumToolbar(activeEditorKey, changeCallback) {
        return [
            "bold", "italic",
            {
                name: "underline",
                action: (editor) => {
                    editor.codemirror.replaceSelection(`<u>${editor.codemirror.getSelection()}</u>`);
                    if (changeCallback) changeCallback();
                },
                className: "fa fa-underline",
                title: "Underline"
            },
            "heading", "|",
            "quote", "unordered-list", "ordered-list", "|",
            {
                name: "latex",
                action: (editor) => {
                    const cm = editor.codemirror;
                    const selection = cm.getSelection();
                    if (selection) {
                        cm.replaceSelection(`$$ ${selection} $$`);
                    } else {
                        const cursor = cm.getCursor();
                        cm.replaceRange("$$  $$", cursor);
                        cm.setCursor(cursor.line, cursor.ch + 3);
                    }
                    if (changeCallback) changeCallback();
                },
                className: "fa fa-plus-circle",
                title: "Insert LaTeX ($$)"
            },
            {
                name: "upload-image",
                className: "fa fa-upload",
                title: "Upload Image",
                action: (editor) => {
                    const fileInput = document.createElement('input');
                    fileInput.type = 'file';
                    fileInput.accept = 'image/*';
                    fileInput.style.display = 'none';
                    document.body.appendChild(fileInput);

                    fileInput.addEventListener('change', async function () {
                        if (!fileInput.files || !fileInput.files.length) {
                            fileInput.remove();
                            return;
                        }
                        const file = fileInput.files[0];
                        const formData = new FormData();
                        formData.append('image', file);

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
                                const markdown = result.markdown || `![](${result.url})`;
                                const cm = editor.codemirror;
                                const cursor = cm.getCursor();
                                cm.replaceRange(`\n${markdown}\n`, cursor);
                                if (changeCallback) changeCallback();
                                showAlert('success', 'Image uploaded and inserted successfully');
                            } else {
                                showAlert('danger', result.message || 'Upload failed');
                            }
                        } catch (error) {
                            showAlert('danger', error.message);
                        } finally {
                            fileInput.remove();
                        }
                    });

                    fileInput.click();
                },
                className: "fa fa-image",
                title: "Upload and Insert Image"
            },
            "|", "preview"
        ];
    }


    function getOrCreateAlertModal() {
        let modal = document.getElementById('customAlertModal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'customAlertModal';
        modal.className = 'custom-alert-modal hidden';
        modal.innerHTML = `
            <div class="custom-alert-backdrop"></div>
            <div class="custom-alert-box">
                <div class="custom-alert-icon" id="customAlertIcon"></div>
                <div class="custom-alert-content">
                    <h5 class="custom-alert-title" id="customAlertTitle">Notification</h5>
                    <p id="customAlertMessage" class="custom-alert-message"></p>
                </div>
                <div class="custom-alert-actions">
                    <button id="customAlertCancelBtn" class="custom-alert-btn btn-secondary hidden">Cancel</button>
                    <button id="customAlertConfirmBtn" class="custom-alert-btn btn-primary">OK</button>
                </div>
            </div>
        `;

        if (!document.querySelector('style[data-custom-alerts]')) {
            const style = document.createElement('style');
            style.setAttribute('data-custom-alerts', 'true');
            style.textContent = `
                .custom-alert-modal {
                    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 10000;
                    display: flex; align-items: center; justify-content: center; opacity: 1; transition: opacity 0.25s ease;
                }
                .custom-alert-modal.hidden { display: none !important; opacity: 0; }
                .custom-alert-backdrop {
                    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
                }
                .custom-alert-box {
                    position: relative; background: #ffffff; border-radius: 16px;
                    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
                    width: 90%; max-width: 420px; padding: 24px; border: 1px solid rgba(226, 232, 240, 0.8);
                    display: flex; flex-direction: column; align-items: center; text-align: center;
                    transform: scale(1); transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); z-index: 1;
                }
                .custom-alert-modal.hidden .custom-alert-box { transform: scale(0.9); }
                .custom-alert-icon {
                    display: flex; align-items: center; justify-content: center; width: 56px; height: 56px;
                    border-radius: 50%; background-color: rgba(30, 41, 59, 0.05); color: #1e293b; margin-bottom: 16px;
                }
                .custom-alert-icon.warning { background-color: rgba(245, 158, 11, 0.1); color: #d97706; }
                .custom-alert-icon.error { background-color: rgba(239, 68, 68, 0.1); color: #dc2626; }
                .custom-alert-icon.success { background-color: rgba(16, 185, 129, 0.1); color: #059669; }
                .custom-alert-content { margin-bottom: 24px; width: 100%; }
                .custom-alert-title { font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 8px; font-family: sans-serif; }
                .custom-alert-message { font-size: 0.95rem; color: #475569; line-height: 1.5; margin: 0; font-family: sans-serif; }
                .custom-alert-actions { display: flex; gap: 12px; width: 100%; justify-content: center; }
                .custom-alert-btn { flex: 1; max-width: 160px; padding: 10px 16px; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; border: none; outline: none; }
                .custom-alert-btn.btn-primary { background-color: #1e293b; color: #ffffff; }
                .custom-alert-btn.btn-primary:hover { background-color: #0f172a; transform: translateY(-1px); }
                .custom-alert-btn.btn-secondary { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
                .custom-alert-btn.btn-secondary:hover { background-color: #e2e8f0; color: #334155; transform: translateY(-1px); }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(modal);
        return modal;
    }

    function showCustomAlert(message, type = 'info', title = 'Notification') {
        return new Promise((resolve) => {
            const modal = getOrCreateAlertModal();
            const titleEl = modal.querySelector('#customAlertTitle');
            const msgEl = modal.querySelector('#customAlertMessage');
            const iconEl = modal.querySelector('#customAlertIcon');
            const confirmBtn = modal.querySelector('#customAlertConfirmBtn');
            const cancelBtn = modal.querySelector('#customAlertCancelBtn');

            titleEl.textContent = title;
            msgEl.textContent = message;

            cancelBtn.classList.add('hidden');
            confirmBtn.className = 'custom-alert-btn btn-primary';
            confirmBtn.textContent = 'OK';

            iconEl.className = 'custom-alert-icon ' + type;
            if (type === 'warning') {
                iconEl.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                `;
            } else if (type === 'error') {
                iconEl.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                `;
            } else if (type === 'success') {
                iconEl.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                `;
            } else {
                iconEl.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                `;
            }

            modal.classList.remove('hidden');

            const handleConfirm = () => {
                cleanup();
                resolve(true);
            };

            const cleanup = () => {
                modal.classList.add('hidden');
                confirmBtn.removeEventListener('click', handleConfirm);
            };

            confirmBtn.addEventListener('click', handleConfirm);
        });
    }

    function showCustomConfirm(message, type = 'warning', title = 'Confirm Action') {
        return new Promise((resolve) => {
            const modal = getOrCreateAlertModal();
            const titleEl = modal.querySelector('#customAlertTitle');
            const msgEl = modal.querySelector('#customAlertMessage');
            const iconEl = modal.querySelector('#customAlertIcon');
            const confirmBtn = modal.querySelector('#customAlertConfirmBtn');
            const cancelBtn = modal.querySelector('#customAlertCancelBtn');

            titleEl.textContent = title;
            msgEl.textContent = message;

            cancelBtn.classList.remove('hidden');
            cancelBtn.textContent = 'Cancel';
            confirmBtn.className = 'custom-alert-btn btn-primary';
            confirmBtn.textContent = 'Confirm';

            iconEl.className = 'custom-alert-icon ' + type;
            if (type === 'warning') {
                iconEl.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                `;
            } else {
                iconEl.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                `;
            }

            modal.classList.remove('hidden');

            const handleConfirm = () => {
                cleanup();
                resolve(true);
            };

            const handleCancel = () => {
                cleanup();
                resolve(false);
            };

            const cleanup = () => {
                modal.classList.add('hidden');
                confirmBtn.removeEventListener('click', handleConfirm);
                cancelBtn.removeEventListener('click', handleCancel);
            };

            confirmBtn.addEventListener('click', handleConfirm);
            cancelBtn.addEventListener('click', handleCancel);
        });
    }

    function showAlert(type, message) {
        let mappedType = type;
        if (type === 'danger') mappedType = 'error';
        showCustomAlert(message, mappedType);
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
        const ids = [
            'sectionTest', 'moduleSection', 'questionModule', 'bulkQuestionModule',
            'questionPassage', 'answerQuestionId', 'explanationQuestionId',
            'linkSection', 'linkTest', 'linkModule'
        ];
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

    function rebuildSectionTestTomSelect(tests, preserved, selectId) {
        selectId = selectId || 'sectionTest';
        const el = document.getElementById(selectId);
        if (!el) {
            return;
        }
        destroyTomSelectIfAny(el);
        el.innerHTML = '<option value="">' + (selectId === 'linkTest' ? 'Select test...' : 'Search test...') + '</option>';
        tests.forEach(function (t) {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.title + (selectId === 'linkTest' ? '' : ' (ID:' + t.id + ')');
            el.appendChild(opt);
        });
        initTomSelectOn(el);
        if (preserved && optionExistsInSelect(el, preserved)) {
            el.tomselect.setValue(String(preserved), true);
        }
    }

    function rebuildModuleSectionTomSelect(tests, preserved, selectId) {
        selectId = selectId || 'moduleSection';
        const el = document.getElementById(selectId);
        if (!el) {
            return;
        }
        destroyTomSelectIfAny(el);
        el.innerHTML = '<option value="">' + (selectId === 'linkSection' ? 'Select section...' : 'Search section...') + '</option>';
        tests.forEach(function (test) {
            (test.sections || []).forEach(function (section) {
                const opt = document.createElement('option');
                opt.value = section.id;
                opt.setAttribute('data-type', section.type);
                opt.textContent = test.title + ' - ' + section.name + (selectId === 'linkSection' ? '' : ' (ID:' + section.id + ')');
                el.appendChild(opt);
            });
        });
        initTomSelectOn(el);
        if (preserved && optionExistsInSelect(el, preserved)) {
            el.tomselect.setValue(String(preserved), true);
        }
    }

    function rebuildLinkModuleTomSelect(allModules, preserved) {
        const el = document.getElementById('linkModule');
        if (!el) {
            return;
        }
        destroyTomSelectIfAny(el);
        el.innerHTML = '<option value="">Select module by key/ID...</option>';
        allModules.forEach(function (mod) {
            const opt = document.createElement('option');
            opt.value = mod.id;
            opt.textContent = '[' + (mod.key || 'ID: ' + mod.id) + '] - Mod ' + mod.module_number + ' (' + capitalizeFirstLetter(mod.difficulty_level) + ')';
            el.appendChild(opt);
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

    function renderModulesTable(allModules) {
        const tbody = document.getElementById('modulesTableBody');
        if (!tbody) {
            return;
        }
        if (!allModules.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No modules found. Create one above!</td></tr>';
            return;
        }
        tbody.innerHTML = allModules.map(function (mod) {
            const diffClass = moduleDifficultyBadgeClass(mod.difficulty_level);

            let linkedHtml = '';
            const sections = mod.sections || [];
            if (sections.length === 0) {
                linkedHtml = '<span class="badge bg-warning text-dark"><i class="bi bi-unlock"></i> Standalone (Reusable)</span>';
            } else {
                linkedHtml = '<div class="d-flex flex-column gap-1">' + sections.map(function (sec) {
                    const testTitle = (sec.test && sec.test.title) ? sec.test.title : 'Test';
                    return '<div>'
                        + '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-1">'
                        + '<i class="bi bi-tag"></i> ' + escapeHtml(testTitle) + ' &raquo; <strong>' + escapeHtml(sec.name) + '</strong>'
                        + '</span>'
                        + '</div>';
                }).join('') + '</div>';
            }

            return '<tr>'
                + '<td>' + escapeHtml(mod.id) + '</td>'
                + '<td><code class="font-monospace bg-light px-2 py-1 border rounded text-dark">' + escapeHtml(mod.key || 'N/A') + '</code></td>'
                + '<td>' + linkedHtml + '</td>'
                + '<td><span class="badge bg-secondary">Mod ' + escapeHtml(mod.module_number) + '</span></td>'
                + '<td><span class="badge bg-' + diffClass + '">' + escapeHtml(capitalizeFirstLetter(mod.difficulty_level)) + '</span></td>'
                + '<td>' + escapeHtml(mod.duration_minutes) + 'm</td>'
                + '<td>' + escapeHtml(mod.total_questions) + '</td>'
                + '<td class="text-end">'
                + '<button class="btn btn-sm btn-outline-danger delete-module-btn" data-id="' + escapeHtml(mod.id) + '">'
                + '<i class="bi bi-trash"></i> Delete'
                + '</button>'
                + '</td>'
                + '</tr>';
        }).join('');
    }

    function renderQuestionsTable(questions) {
        const tbody = document.getElementById('questionsTableBody');
        if (!tbody) {
            return;
        }
        if (!questions.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5">'
                + '<i class="bi bi-database-fill-x display-6 mb-2 d-block text-secondary opacity-50"></i>'
                + 'No questions found in bank.'
                + '</td></tr>';
            return;
        }
        tbody.innerHTML = questions.map(function (q) {
            const secBadge = q.section_type === 'reading_writing'
                ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill fw-semibold">R&W</span>'
                : '<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill fw-semibold">Math</span>';

            const usageBadge = q.is_pretest
                ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill d-inline-flex align-items-center fw-semibold"><span class="spinner-grow spinner-grow-sm text-danger me-1.5" style="width: 6px; height: 6px; animation-duration: 1.5s;" role="status"></span>Pretest</span>'
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
                + '<td class="ps-3 font-monospace fw-bold text-secondary">' + escapeHtml(q.id) + '</td>'
                + '<td>'
                + '<div class="d-flex align-items-center gap-2">'
                + '<span class="fw-semibold text-dark">' + qNum + '</span>'
                + status
                + '</div>'
                + '</td>'
                + '<td>' + secBadge + '</td>'
                + '<td class="text-secondary text-truncate" style="max-width: 280px;" title="' + escapeHtml(stem) + '">' + escapeHtml(snippet) + '</td>'
                + '<td>' + usageBadge + '</td>'
                + '<td><span class="text-secondary small font-monospace">' + escapeHtml(q.skill_domain || '') + '</span></td>'
                + '<td>' + diffBadge + '</td>'
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
    }

    function rebuildAllTomSelects(payload, preserve) {
        const p = preserve || {};
        const tests = payload.tests || [];
        const passages = payload.passages || [];
        const allModules = payload.allModules || [];
        rebuildSectionTestTomSelect(tests, p.sectionTest, 'sectionTest');
        rebuildSectionTestTomSelect(tests, p.linkTest, 'linkTest');
        rebuildModuleSectionTomSelect(tests, p.moduleSection, 'moduleSection');
        rebuildModuleSectionTomSelect(tests, p.linkSection, 'linkSection');
        rebuildQuestionModuleTomSelect(tests, p.questionModule, 'questionModule');
        rebuildQuestionModuleTomSelect(tests, p.bulkQuestionModule, 'bulkQuestionModule');
        rebuildQuestionPassageTomSelect(passages, p.questionPassage);
        rebuildLinkModuleTomSelect(allModules, p.linkModule);
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
        let payload = { tests: [], passages: [], allModules: [] };
        try {
            payload = await snapRes.json();
        } catch (e) {
            console.error('Snapshot JSON parse failed');
        }

        renderTestsTable(payload.tests || []);
        renderSectionsTable(payload.tests || []);
        renderModulesTable(payload.allModules || []);
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

    window.refreshTestDashboardData = function () {
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

            if (!await showCustomConfirm('Permanently delete this item?', 'warning', 'Permanently Delete')) {
                return;
            }

            let deleteChildren = false;
            let url;
            if (btn.classList.contains('delete-test-btn')) {
                url = `${BASE_URL}/tests/${id}`;
                if (await showCustomConfirm('Also delete all sections, modules, and questions inside this test?', 'warning', 'Delete Child Elements')) {
                    deleteChildren = true;
                }
            } else if (btn.classList.contains('delete-section-btn')) {
                url = `${BASE_URL}/sections/${id}`;
                if (await showCustomConfirm('Also delete all modules and questions inside this section?', 'warning', 'Delete Child Elements')) {
                    deleteChildren = true;
                }
            } else if (btn.classList.contains('delete-module-btn')) {
                url = `${BASE_URL}/modules/${id}`;
                if (await showCustomConfirm('Also delete all questions linked to this module?', 'warning', 'Delete Child Elements')) {
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
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();

        } catch (error) {
            showAlert('danger', error.message);
        }
    }

    // Modal Visibility Listeners for Robust EasyMDE initialization
    document.getElementById('editQuestionModal')?.addEventListener('shown.bs.modal', function () {
        const question = window.__editingQuestion;
        if (!question) return;

        initEditModalEditors();

        if (editStemEditor) {
            editStemEditor.value(question.stem || '');
            editStemEditor.codemirror.refresh();
        }

        if (editPassageEditor) {
            const pContent = question.passage_content || (question.passage ? (typeof question.passage === 'string' ? question.passage : question.passage.content) : '');
            editPassageEditor.value(pContent || '');
            editPassageEditor.codemirror.refresh();
        }

        if (editExplanationEditor) {
            const expContent = question.explanation ? (question.explanation.explanation || '') : '';
            editExplanationEditor.value(expContent);
            editExplanationEditor.codemirror.refresh();
        }

        refreshEditMediaList();
        updateEditQuestionPreview();

        // Final focus/refresh to ensure it's not white
        setTimeout(() => {
            if (editStemEditor) editStemEditor.codemirror.refresh();
            if (editPassageEditor) editPassageEditor.codemirror.refresh();
            if (editExplanationEditor) editExplanationEditor.codemirror.refresh();
        }, 100);
    });


    function refreshEditMediaList() {
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

    window.removeMediaFromEditModal = async function (identifier, isUrl) {
        if (!await showCustomConfirm('Are you sure you want to remove this media from all fields?', 'warning', 'Remove Media')) return;

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

    document.getElementById('editQuestionMediaUpload')?.addEventListener('change', async function (e) {
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

    document.getElementById('editQuestionForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();

        try {
            // Ensure EasyMDE editors sync back to textareas
            if (editStemEditor) document.getElementById('editQuestionStem').value = editStemEditor.value();
            if (editPassageEditor) document.getElementById('editPassageContent').value = editPassageEditor.value();
            if (editExplanationEditor) document.getElementById('editExplanation').value = editExplanationEditor.value();

            const stemVal = editStemEditor ? editStemEditor.value() : document.getElementById('editQuestionStem').value;
            if (!stemVal || stemVal.trim() === '') {
                showAlert('danger', 'Question stem is required.');
                return;
            }

            const id = document.getElementById('editQuestionId').value;
            if (!id) {
                showAlert('danger', 'Question ID missing. Cannot update.');
                return;
            }

            const form = document.getElementById('editQuestionForm');
            if (!form) {
                showAlert('danger', 'Question Form element not found.');
                return;
            }

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            // Clean up nested choices keys produced by FormData and manually build nested choices array
            if (data.question_type === 'multiple_choice') {
                data.choices = [];
                ['A', 'B', 'C', 'D'].forEach((label, index) => {
                    const contentInput = document.getElementById(`editChoice${label}Content`);
                    const isCorrectRadio = document.getElementById(`editChoice${label}Correct`);

                    if (contentInput) {
                        data.choices.push({
                            label: label,
                            content: contentInput.value,
                            is_correct: isCorrectRadio ? isCorrectRadio.checked : false,
                            order: index + 1
                        });
                    }

                    // Remove the flat choice keys to avoid confusion
                    delete data[`choices[${index}][label]`];
                    delete data[`choices[${index}][content]`];
                });
                // correct_choice is already at top level from radio button
            } else {
                // Student Produced Response (SPR)
                // spr_answers is already in data from FormData
            }

            data.is_pretest = document.getElementById('editIsPretest').checked ? 1 : 0;
            data.calculator_allowed = document.getElementById('editCalculatorAllowed').checked ? 1 : 0;

            const response = await fetch(`${BASE_URL}/questions/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (response.ok) {
                showAlert('success', 'Question updated successfully!');
                const modalEl = document.getElementById('editQuestionModal');
                const modalInstance = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
                modalInstance.hide();
                await refreshQuestionsTableOnly();
            } else {
                showAlert('danger', result.message || 'Update failed');
            }
        } catch (error) {
            console.error('Update question failed:', error);
            showAlert('danger', 'Submission error: ' + error.message);
        }
    });

    window.updateSectionName = function (select) {
        const nameInput = document.getElementById('sectionName');
        if (!nameInput) return;
        if (select.value === 'reading_writing') {
            nameInput.value = 'Reading and Writing';
        } else if (select.value === 'math') {
            nameInput.value = 'Math';
        }
    };

    window.autoFetchSectionType = function (select) {
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

    window.applyModuleDefaults = function (select) {
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

    window.updateSkillDomains = function (select) {
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

    // Toggle Link target type in Link Reusable Module form
    function initLinkTargetToggle() {
        const sectionContainer = document.getElementById('linkSectionContainer');
        const testFieldsContainer = document.getElementById('linkTestFieldsContainer');
        const sectionSelect = document.getElementById('linkSection');
        const testSelect = document.getElementById('linkTest');
        const sectionTypeSelect = document.getElementById('linkSectionType');

        document.querySelectorAll('input[name="link_target_type"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                const val = this.value;
                if (val === 'section') {
                    if (sectionContainer) sectionContainer.classList.remove('d-none');
                    if (testFieldsContainer) testFieldsContainer.classList.add('d-none');
                    if (sectionSelect) {
                        sectionSelect.setAttribute('required', 'required');
                        if (sectionSelect.tomselect) sectionSelect.tomselect.required = true;
                    }
                    if (testSelect) {
                        testSelect.removeAttribute('required');
                        if (testSelect.tomselect) testSelect.tomselect.required = false;
                    }
                    if (sectionTypeSelect) sectionTypeSelect.removeAttribute('required');
                } else {
                    if (sectionContainer) sectionContainer.classList.add('d-none');
                    if (testFieldsContainer) testFieldsContainer.classList.remove('d-none');
                    if (sectionSelect) {
                        sectionSelect.removeAttribute('required');
                        if (sectionSelect.tomselect) sectionSelect.tomselect.required = false;
                    }
                    if (testSelect) {
                        testSelect.setAttribute('required', 'required');
                        if (testSelect.tomselect) testSelect.tomselect.required = true;
                    }
                    if (sectionTypeSelect) sectionTypeSelect.setAttribute('required', 'required');
                }
            });
        });
    }
    initLinkTargetToggle();

    let editPreviewDebouncer = null;
    function debouncedEditQuestionPreview() {
        if (editPreviewDebouncer) clearTimeout(editPreviewDebouncer);
        editPreviewDebouncer = setTimeout(() => {
            updateEditQuestionPreview();
        }, 200);
    }

    function updateEditQuestionPreview() {
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

    function initEditModalEditors() {
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

    // Call editor initialization immediately
    initEditModalEditors();

    // Bind real-time change listener triggers
    document.getElementById('editQuestionType')?.addEventListener('change', () => {
        debouncedEditQuestionPreview();
    });
    document.getElementById('editSprAnswers')?.addEventListener('input', () => {
        debouncedEditQuestionPreview();
    });
    ['A', 'B', 'C', 'D'].forEach(label => {
        document.getElementById(`editChoice${label}Content`)?.addEventListener('input', () => {
            debouncedEditQuestionPreview();
        });
        document.getElementById(`editChoice${label}Correct`)?.addEventListener('change', () => {
            debouncedEditQuestionPreview();
        });
    });

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

        form.addEventListener('submit', async function (e) {
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
    setupForm('linkModuleForm', SECTIONS_LINK_MODULE_URL);
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
                    { left: '$$', right: '$$', display: false },
                    { left: '\\\\[', right: '\\\\]', display: true },
                ],
                throwOnError: false
            });
        }
    }

    let validationGridTable = null;

    function validateItemLocal(item, sectionType) {
        const blockers = {};
        const warnings = {};

        // Stem check
        if (!item.stem || item.stem.trim() === '') {
            blockers.stem = "Question stem is required.";
        }

        // Question type check
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

        // MCQ vs SPR specific checks
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

        // Warnings
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

    function flatToStructured(flat) {
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

    function structuredToFlat(item, index) {
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

    function createCellFormatter(fieldName) {
        return function (cell) {
            const rowData = cell.getRow().getData();
            const value = cell.getValue() || '';
            const sectionType = document.getElementById('bulkQuestionModule').selectedOptions[0]?.getAttribute('data-section-type') || 'reading_writing';

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

    function openValidationGrid(items) {
        const container = document.getElementById('validation-grid-container');
        if (!container) return;

        container.classList.remove('d-none');
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

    function updateGridStatusCounts() {
        if (!validationGridTable) return;
        const rows = validationGridTable.getData();
        const sectionType = document.getElementById('bulkQuestionModule').selectedOptions[0]?.getAttribute('data-section-type') || 'reading_writing';

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
            statusAlert.className = "alert alert-danger py-3 px-4 rounded-3 mb-3 d-flex align-items-center justify-content-between border-0 shadow-sm";
            statusTitle.textContent = "Blocker Errors Found";
            statusMsg.textContent = "You must resolve all blockers highlighted in red before those rows can be imported.";
            importApprovedBtn.textContent = "Import Approved Rows";
            importApprovedBtn.className = "btn btn-warning px-4 py-2 rounded-3 shadow-sm";
        } else if (warningCount > 0) {
            statusAlert.className = "alert alert-warning py-3 px-4 rounded-3 mb-3 d-flex align-items-center justify-content-between border-0 shadow-sm";
            statusTitle.textContent = "Warnings Present";
            statusMsg.textContent = "Grid has minor warnings highlighted in yellow. You are ready to import all rows; missing details will use defaults.";
            importApprovedBtn.textContent = "Import All Rows";
            importApprovedBtn.className = "btn btn-success px-4 py-2 rounded-3 shadow-sm";
        } else {
            statusAlert.className = "alert alert-success py-3 px-4 rounded-3 mb-3 d-flex align-items-center justify-content-between border-0 shadow-sm";
            statusTitle.textContent = "Validation Passed Successfully";
            statusMsg.textContent = "All rows are 100% valid! You are ready to import.";
            importApprovedBtn.textContent = "Import All Rows";
            importApprovedBtn.className = "btn btn-success px-4 py-2 rounded-3 shadow-sm";
        }
    }

    // Grid buttons bindings
    document.getElementById('gridCloseBtn')?.addEventListener('click', () => {
        document.getElementById('validation-grid-container').classList.add('d-none');
    });
    document.getElementById('gridCancelBtn')?.addEventListener('click', async () => {
        if (await showCustomConfirm('Discard import items and reset the validation grid?', 'warning', 'Discard Import')) {
            document.getElementById('validation-grid-container').classList.add('d-none');
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
        const sectionType = document.getElementById('bulkQuestionModule').selectedOptions[0]?.getAttribute('data-section-type') || 'reading_writing';

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
                    document.getElementById('validation-grid-container').classList.add('d-none');
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
                        if (display) display.classList.add('d-none');
                        if (icon) icon.classList.replace('text-success', 'text-muted');
                        if (instruction) instruction.classList.remove('text-success');
                    });

                    await refreshTestDashboardData(captureTomSelectPreservation(null));
                } else {
                    showAlert('danger', result.message || 'Import submission failed.');
                }
            } catch (err) {
                showAlert('danger', 'Import error: ' + err.message);
            }
        }
    });

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
            if (response.ok) {
                openValidationGrid(result.data.items);
            } else {
                showAlert('danger', result.message || 'Preview failed');
            }
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
                // If import fails due to validation errors, let's open the failsafe validation grid!
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
                await refreshTestDashboardData(captureTomSelectPreservation(null));
            } else {
                // If CSV import fails due to validation errors, open validation grid!
                if (result.data && result.data.items) {
                    showAlert('warning', 'CSV Import failed due to validation errors. We loaded them into the validation grid below for correction.');
                    openValidationGrid(result.data.items);
                } else {
                    showAlert('danger', result.message || 'CSV import failed');
                }
            }
        } catch (error) { showAlert('danger', 'Error: ' + error.message); }
    });

    // --- Easy Builder Logic ---
    let builderBlockCount = 0;
    const builderEditors = {};
    const debouncers = {};

    function debouncedUpdateLivePreview(block) {
        const index = block.dataset.index;
        if (debouncers[index]) clearTimeout(debouncers[index]);
        debouncers[index] = setTimeout(() => {
            renderLivePreviewCard(block);
        }, 250);
    }

    function renderLivePreviewCard(block) {
        const index = block.dataset.index;
        const drawer = document.getElementById('builderLivePreviewDrawer');
        if (!drawer) return;

        // Remove placeholder if it exists
        const placeholder = drawer.querySelector('.text-muted.text-center');
        if (placeholder) placeholder.remove();

        let previewCard = drawer.querySelector(`[data-preview-index="${index}"]`);
        if (!previewCard) {
            previewCard = document.createElement('div');
            previewCard.className = "card mb-3 border-0 shadow-sm rounded-3 bg-white overflow-hidden";
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
                        <div class="flex-grow-1 small">${content}</div>
                    </div>
                `;
            });
            questionBodyHtml = `<div class="choices-preview mt-3">${choicesHtml}</div>`;
        } else {
            const sprVal = block.querySelector('.builder-spr-answers').value.trim() || '______';
            questionBodyHtml = `
                <div class="answer-input-container p-3 bg-light rounded-3 mt-3 border border-warning border-opacity-25">
                    <label class="d-block mb-2 fw-bold text-dark small"><i class="bi bi-pencil-fill text-warning"></i> Student Produced Response:</label>
                    <div class="form-control bg-white font-monospace text-center py-2 fs-5 border-warning border-opacity-50" style="max-width: 150px; letter-spacing: 2px;">
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

    function updateSidebarNavigator() {
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

    const scroller = document.getElementById('builderWorkspaceScroller');
    if (scroller) {
        scroller.addEventListener('scroll', () => {
            const blocks = document.querySelectorAll('.builder-block');
            if (blocks.length === 0) return;

            let closestBlock = null;
            let minDistance = Infinity;
            const scrollerRect = scroller.getBoundingClientRect();
            const scrollerCenter = scrollerRect.top + scrollerRect.height / 2;

            blocks.forEach(block => {
                const rect = block.getBoundingClientRect();
                const blockCenter = rect.top + rect.height / 2;
                const distance = Math.abs(blockCenter - scrollerCenter);
                if (distance < minDistance) {
                    minDistance = distance;
                    closestBlock = block;
                }
            });

            if (closestBlock) {
                const index = closestBlock.dataset.index;
                const navigator = document.getElementById('builderSidebarNavigator');
                if (navigator) {
                    navigator.querySelectorAll('.list-group-item').forEach(item => {
                        if (item.dataset.targetIndex === index) {
                            item.classList.add('active', 'bg-warning', 'text-dark', 'border-warning');
                            item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        } else {
                            item.classList.remove('active', 'bg-warning', 'text-dark', 'border-warning');
                        }
                    });
                }
            }
        });
    }

    window.insertLatex = function (textareaId) {
        const textarea = document.getElementById(textareaId);
        if (!textarea) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const before = text.substring(0, start);
        const after = text.substring(end, text.length);
        const latex = '$$  $$';

        textarea.value = before + latex + after;
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + 3; // Position cursor between $$ and $$
    };

    window.addBuilderBlock = function () {
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
        });

        block.scrollIntoView({ behavior: 'smooth' });

        // Bind format toggles
        const mcqRadio = block.querySelector('.builder-format-mcq');
        const sprRadio = block.querySelector('.builder-format-spr');
        const mcqContainer = block.querySelector('.builder-mcq-container');
        const sprContainer = block.querySelector('.builder-spr-container');

        const updateFormatView = () => {
            if (mcqRadio.checked) {
                mcqContainer.classList.remove('d-none');
                sprContainer.classList.add('d-none');
            } else {
                mcqContainer.classList.add('d-none');
                sprContainer.classList.remove('d-none');
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
                    <div class="text-muted text-center py-5 small">
                        <i class="bi bi-file-earmark-richtext fs-2 d-block mb-2 text-warning"></i>
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
                    toolbar: getPremiumToolbar('builderPassage', () => debouncedUpdateLivePreview(block)),
                    status: false
                });

                builderEditors[`passage_${index}`].codemirror.on('change', () => {
                    debouncedUpdateLivePreview(block);
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
    document.getElementById('builderModuleId')?.addEventListener('change', (e) => {
        document.querySelectorAll('.builder-block').forEach(syncBuilderBlockDomain);

        // Update Interactive Breadcrumb
        const select = e.target;
        const bc = document.getElementById('builderInteractiveBreadcrumb');
        if (!bc || !select.value) {
            if (bc) bc.classList.add('d-none');
            return;
        }

        bc.classList.remove('d-none');
        const selectedOpt = select.options[select.selectedIndex];
        if (!selectedOpt) return;
        const text = selectedOpt.text;
        const parts = text.split(' | ');
        if (parts.length >= 2) {
            document.getElementById('bc-test-title').textContent = parts[0].trim();
            document.getElementById('bc-test-title').title = parts[0].trim();

            const subParts = parts[1].split(' - ');
            if (subParts.length >= 2) {
                document.getElementById('bc-section-title').textContent = subParts[0].trim() + ' ⌄';
                document.getElementById('bc-module-title').textContent = subParts[1].trim() + ' ⌄';
            }
        }

        // Populate sibling dropdowns
        const secDropdown = document.getElementById('bc-section-dropdown');
        const modDropdown = document.getElementById('bc-module-dropdown');
        if (!secDropdown || !modDropdown) return;

        secDropdown.innerHTML = ''; modDropdown.innerHTML = '';

        const testTitle = parts[0].trim();
        const currentSectionText = document.getElementById('bc-section-title').textContent.replace(' ⌄', '');
        const currentModuleText = document.getElementById('bc-module-title').textContent.replace(' ⌄', '');

        const options = Array.from(select.options).filter(o => o.value !== "");
        const siblings = options.filter(o => o.text.startsWith(testTitle + ' | '));

        const sections = new Set();
        siblings.forEach(o => {
            const sp = o.text.split(' | ')[1].split(' - ');
            sections.add(sp[0].trim());
        });

        sections.forEach(sec => {
            if (sec === currentSectionText) return;
            const li = document.createElement('li');
            li.innerHTML = `<a class="dropdown-item cursor-pointer text-muted small">${sec}</a>`;
            li.addEventListener('click', () => {
                const targetOpt = options.find(o => o.text.startsWith(testTitle + ' | ' + sec));
                if (targetOpt) {
                    if (select.tomselect) select.tomselect.setValue(targetOpt.value);
                    else { select.value = targetOpt.value; select.dispatchEvent(new Event('change')); }
                }
            });
            secDropdown.appendChild(li);
        });
        if (secDropdown.children.length === 0) secDropdown.innerHTML = '<li><span class="dropdown-item text-muted small">No other sections</span></li>';

        const modules = siblings.filter(o => o.text.includes(' | ' + currentSectionText + ' - '));
        modules.forEach(o => {
            const mText = o.text.split(' - ')[1].trim();
            if (mText === currentModuleText) return;
            const li = document.createElement('li');
            li.innerHTML = `<a class="dropdown-item cursor-pointer text-muted small">${mText}</a>`;
            li.addEventListener('click', () => {
                if (select.tomselect) select.tomselect.setValue(o.value);
                else { select.value = o.value; select.dispatchEvent(new Event('change')); }
            });
            modDropdown.appendChild(li);
        });
        if (modDropdown.children.length === 0) modDropdown.innerHTML = '<li><span class="dropdown-item text-muted small">No other modules</span></li>';

        // Save to Recent Authoring Context
        if (typeof addRecentModule === 'function') {
            addRecentModule(select.value, currentModuleText, testTitle);
        }
    });

    document.getElementById('clearBuilderBtn')?.addEventListener('click', async function () {
        if (await showCustomConfirm('Clear all questions in builder?', 'warning', 'Clear Builder')) {
            Object.values(builderEditors).forEach(mde => mde.toTextArea());
            for (const key in builderEditors) delete builderEditors[key];
            document.getElementById('builderBlocksContainer').innerHTML = '';
            builderBlockCount = 0;

            const drawer = document.getElementById('builderLivePreviewDrawer');
            if (drawer) {
                drawer.innerHTML = `
                    <div class="text-muted text-center py-5 small">
                        <i class="bi bi-file-earmark-richtext fs-2 d-block mb-2 text-warning"></i>
                        Live compilation of STEM and formulas will appear here in real-time
                    </div>
                `;
            }

            updateSidebarNavigator();
        }
    });

    document.getElementById('submitBuilderBtn')?.addEventListener('click', async function () {
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

            const qType = block.querySelector('.builder-format-mcq').checked ? 'multiple_choice' : 'student_produced_response';
            let choices = [];
            let spr_correct_answers = [];

            if (qType === 'multiple_choice') {
                const correctLabel = block.querySelector('.builder-correct-radio:checked')?.value || 'A';
                block.querySelectorAll('.builder-choice-content').forEach(input => {
                    const label = input.getAttribute('data-label');
                    const content = input.value.trim();
                    if (!content) {
                        input.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                    choices.push({ label, content, is_correct: label === correctLabel, order: label.charCodeAt(0) - 64 });
                });
            } else {
                const sprInput = block.querySelector('.builder-spr-answers');
                const sprVal = sprInput.value.trim();
                if (!sprVal) {
                    sprInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    sprInput.classList.remove('is-invalid');
                    spr_correct_answers = sprVal.split(/[|;]/).map(a => a.trim()).filter(Boolean);
                }
            }

            const item = {
                stem,
                question_type: qType,
                difficulty: block.querySelector('.builder-difficulty').value || null,
                skill_domain: block.querySelector('.builder-domain').value || null,
                choices: qType === 'multiple_choice' ? choices : [],
                spr_correct_answers: qType === 'student_produced_response' ? spr_correct_answers : [],
                explanation: block.querySelector('.builder-explanation').value.trim()
            };

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

                const drawer = document.getElementById('builderLivePreviewDrawer');
                if (drawer) {
                    drawer.innerHTML = `
                        <div class="text-muted text-center py-5 small">
                            <i class="bi bi-file-earmark-richtext fs-2 d-block mb-2 text-warning"></i>
                            Live compilation of STEM and formulas will appear here in real-time
                        </div>
                    `;
                }

                updateSidebarNavigator();
                await refreshTestDashboardData(captureTomSelectPreservation(null));
            } else showAlert('danger', 'Import failed.');
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

    function initPremiumDropzones() {
        const dropzones = document.querySelectorAll('.file-dropzone');
        dropzones.forEach(zone => {
            const input = zone.querySelector('input[type="file"]');
            const display = zone.querySelector('.file-name-display');
            const icon = zone.querySelector('.bi');
            const instruction = zone.querySelector('.drag-instruction');

            if (!input || !display) return;

            // Highlight drop area when dragging over
            ['dragenter', 'dragover'].forEach(eventName => {
                zone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    zone.classList.add('border-primary', 'bg-primary-subtle', 'shadow-xs');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                zone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    zone.classList.remove('border-primary', 'bg-primary-subtle', 'shadow-xs');
                }, false);
            });

            // Handle dropped files
            zone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length) {
                    input.files = files;
                    input.dispatchEvent(new Event('change'));
                }
            });

            // Update display on selection
            input.addEventListener('change', (e) => {
                const file = e.target.files?.[0];
                if (file) {
                    display.textContent = '✓ ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                    display.classList.remove('d-none');
                    if (icon) icon.classList.replace('text-muted', 'text-success');
                    if (instruction) instruction.classList.add('text-success');
                } else {
                    display.classList.add('d-none');
                    if (icon) icon.classList.replace('text-success', 'text-muted');
                    if (instruction) instruction.classList.remove('text-success');
                }
            });
        });
    }

    initPremiumDropzones();

    // ==========================================
    // CLONE FUNCTIONALITY
    // ==========================================

    document.addEventListener('click', async function (e) {
        const cloneTestBtn = e.target.closest('.clone-test-btn');
        if (cloneTestBtn) {
            const testId = cloneTestBtn.getAttribute('data-id');
            const confirmed = await showCustomConfirm('Clone this Test (Hierarchy Only)? No questions will be copied.', 'info', 'Clone Test');
            if (!confirmed) return;

            cloneTestBtn.disabled = true;
            try {
                const response = await fetch(`${BASE_URL}/tests/${testId}/clone`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });
                const result = await response.json();
                if (response.ok) {
                    showAlert('success', result.message);
                    await refreshTestDashboardData(captureTomSelectPreservation(null));
                } else {
                    showAlert('danger', result.message);
                }
            } catch (error) {
                showAlert('danger', 'Failed to clone test: ' + error.message);
            } finally {
                cloneTestBtn.disabled = false;
            }
        }

        const cloneModuleBtn = e.target.closest('.clone-module-btn');
        if (cloneModuleBtn) {
            const moduleId = cloneModuleBtn.getAttribute('data-id');
            const confirmed = await showCustomConfirm('Clone this Module (Hierarchy Only)? No questions will be copied.', 'info', 'Clone Module');
            if (!confirmed) return;

            cloneModuleBtn.disabled = true;
            try {
                const response = await fetch(`${BASE_URL}/modules/${moduleId}/clone`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });
                const result = await response.json();
                if (response.ok) {
                    showAlert('success', result.message);
                    await refreshTestDashboardData(captureTomSelectPreservation(null));
                } else {
                    showAlert('danger', result.message);
                }
            } catch (error) {
                showAlert('danger', 'Failed to clone module: ' + error.message);
            } finally {
                cloneModuleBtn.disabled = false;
            }
        }
    });

    // ==========================================
    // QUICK AUTHORING WIZARD
    // ==========================================

    const wizardBtnFullSat = document.getElementById('wizard-btn-full-sat');
    const wizardBtnCustom = document.getElementById('wizard-btn-custom');
    const wizardCustomFlow = document.getElementById('wizard-custom-flow');
    const wizardLoading = document.getElementById('wizard-loading');

    // Store recently edited modules in localStorage
    function addRecentModule(moduleId, moduleKey, testTitle) {
        let recents = JSON.parse(localStorage.getItem('td_recent_modules') || '[]');
        recents = recents.filter(r => r.id !== moduleId);
        recents.unshift({ id: moduleId, key: moduleKey, testTitle, ts: Date.now() });
        if (recents.length > 3) recents = recents.slice(0, 3);
        localStorage.setItem('td_recent_modules', JSON.stringify(recents));
    }

    function renderRecentWork() {
        const recents = JSON.parse(localStorage.getItem('td_recent_modules') || '[]');
        const container = document.getElementById('wizard-recent-work-container');
        const list = document.getElementById('wizard-recent-work-list');
        if (!container || !list) return;

        if (recents.length === 0) {
            container.classList.add('d-none');
            return;
        }

        container.classList.remove('d-none');
        list.innerHTML = recents.map(r => `
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill resume-recent-btn" data-id="${r.id}">
                <i class="bi bi-clock-history"></i> ${r.testTitle ? r.testTitle + ' - ' : ''}${r.key || 'Module ' + r.id}
            </button>
        `).join('');
    }

    // Modal open event
    document.getElementById('quickAuthorWizardModal')?.addEventListener('show.bs.modal', function () {
        wizardCustomFlow.classList.add('d-none');
        wizardLoading.classList.add('d-none');

        // Populate Tests Dropdown
        const testSelect = document.getElementById('wizard-select-test');
        if (testSelect) {
            testSelect.innerHTML = '<option value="">Create new standalone Module...</option>' + window.__tdLatestTests.map(t => `<option value="${t.id}">${t.title}</option>`).join('');
        }

        renderRecentWork();
    });

    // Handle Recent Click
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.resume-recent-btn');
        if (btn) {
            const modId = parseInt(btn.getAttribute('data-id'), 10);
            bootstrap.Modal.getInstance(document.getElementById('quickAuthorWizardModal'))?.hide();
            // Switch to builder tab and select module
            document.getElementById('builder-tab')?.click();
            setTimeout(() => {
                const treeItem = document.querySelector(`.ws-tree-item[data-id="${modId}"][data-type="module"]`);
                if (treeItem) treeItem.click();
            }, 300);
        }
    });

    if (wizardBtnFullSat) {
        wizardBtnFullSat.addEventListener('click', async () => {
            const title = prompt("Enter a title for the new Full SAT Test:", "Digital SAT Practice Test");
            if (!title) return;

            wizardCustomFlow.classList.add('d-none');
            wizardLoading.classList.remove('d-none');

            try {
                const response = await fetch(`${BASE_URL}/tests/generate-full`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ title })
                });
                const result = await response.json();
                if (response.ok) {
                    showAlert('success', 'SAT Structure generated!');
                    await refreshTestDashboardData(captureTomSelectPreservation(null));
                    bootstrap.Modal.getInstance(document.getElementById('quickAuthorWizardModal'))?.hide();
                    document.getElementById('builder-tab')?.click();
                } else {
                    showAlert('danger', result.message);
                }
            } catch (err) {
                showAlert('danger', 'Error generating structure: ' + err.message);
            } finally {
                wizardLoading.classList.add('d-none');
            }
        });
    }

    if (wizardBtnCustom) {
        wizardBtnCustom.addEventListener('click', () => {
            wizardCustomFlow.classList.remove('d-none');
        });
    }

    const testSelect = document.getElementById('wizard-select-test');
    const stepTarget = document.getElementById('wizard-step-target');
    const stepLaunch = document.getElementById('wizard-step-launch');

    if (testSelect) {
        testSelect.addEventListener('change', () => {
            stepTarget.classList.remove('d-none');
            stepLaunch.classList.remove('d-none');
        });
    }

    document.getElementById('wizard-btn-launch')?.addEventListener('click', async () => {
        const testId = document.getElementById('wizard-select-test').value;
        const domain = document.getElementById('wizard-select-domain').value; // 'reading_writing' | 'math'
        const modPos = document.getElementById('wizard-select-module').value; // '1_standard' | '2_easy' | '2_hard'

        const [modNum, diff] = modPos.split('_');

        let targetModuleId = null;

        // Creating via endpoint directly
        wizardLoading.classList.remove('d-none');
        try {
            // First create/get section
            let sectionId = null;
            if (testId) {
                const sectionRes = await fetch(`${BASE_URL}/sections`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ test_id: testId, type: domain, name: (domain === 'math' ? 'Math' : 'Reading and Writing') })
                });
                const secData = await sectionRes.json();
                if (sectionRes.ok) sectionId = secData.data.id;
            }

            // Create module
            const modRes = await fetch(`${BASE_URL}/modules`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    test_id: sectionId ? null : testId, // if standalone
                    section_id: sectionId,
                    section_type: domain,
                    module_number: modNum,
                    difficulty_level: diff,
                    duration_minutes: domain === 'math' ? 35 : 32,
                    total_questions: domain === 'math' ? 22 : 27
                })
            });
            const modData = await modRes.json();
            if (modRes.ok) targetModuleId = modData.data.id;

            await refreshTestDashboardData(captureTomSelectPreservation(null));
            bootstrap.Modal.getInstance(document.getElementById('quickAuthorWizardModal'))?.hide();

            // Navigate and open
            document.getElementById('builder-tab')?.click();
            setTimeout(() => {
                const treeItem = document.querySelector(`.ws-tree-item[data-id="${targetModuleId}"][data-type="module"]`);
                if (treeItem) treeItem.click();
            }, 500);

        } catch (err) {
            showAlert('danger', err.message);
        } finally {
            wizardLoading.classList.add('d-none');
        }
    });

});
