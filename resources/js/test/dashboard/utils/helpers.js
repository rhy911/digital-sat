import { MEDIA_UPLOAD_URL } from '../core/config.js';

const loadedStyles = new Set();
const loadingScripts = new Map();

export function loadStyle(href) {
    if (loadedStyles.has(href) || document.querySelector(`link[href="${href}"]`)) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
    loadedStyles.add(href);
}

export function loadScript(src) {
    if (loadingScripts.has(src)) return loadingScripts.get(src);
    if (document.querySelector(`script[src="${src}"]`)) {
        return Promise.resolve();
    }
    
    const promise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Failed to load script ${src}`));
        document.body.appendChild(script);
    });
    
    loadingScripts.set(src, promise);
    return promise;
}

export async function loadHeavyDependencies() {
    // Load CSS
    loadStyle('https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css');
    loadStyle('https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css');
    loadStyle('https://cdn.jsdelivr.net/npm/tabulator-tables@5.5.2/dist/css/tabulator_bootstrap5.min.css');
    loadStyle('https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css');

    // Load independent JS
    const tomSelectP = loadScript('https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js');
    const tabulatorP = loadScript('https://cdn.jsdelivr.net/npm/tabulator-tables@5.5.2/dist/js/tabulator.min.js');
    const markedP = loadScript('https://cdn.jsdelivr.net/npm/marked@12.0.0/marked.min.js');
    
    // Load dependent JS
    const katexP = loadScript('https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js')
        .then(() => loadScript('https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js'));
        
    const easymdeP = markedP.then(() => loadScript('https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js'));

    await Promise.all([tomSelectP, tabulatorP, katexP, easymdeP]);
}

export function compileMarkdownToHtml(text) {
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

export function processMedia(text) {
    if (!text || typeof text !== 'string') return text;
    // Convert backslash delimiters to $$ for consistent preview rendering
    text = text.replace(/\\\(/g, '$$').replace(/\\\)/g, '$$');
    return text.replace(/(?<!\!)\[Media:([^\]]+)\]/gi, (match, filename) => {
        return `<img src="/storage/media/${filename}" alt="${filename}" class="question-media img-fluid mb-2 d-block mx-auto" style="max-height: 300px;">`;
    });
}

export function getPremiumToolbar(activeEditorKey, changeCallback) {
    const toggleBold = window.EasyMDE ? window.EasyMDE.toggleBold : (e) => e.toggleBold();
    const toggleItalic = window.EasyMDE ? window.EasyMDE.toggleItalic : (e) => e.toggleItalic();
    const toggleHeadingSmaller = window.EasyMDE ? window.EasyMDE.toggleHeadingSmaller : (e) => e.toggleHeadingSmaller();
    const toggleBlockquote = window.EasyMDE ? window.EasyMDE.toggleBlockquote : (e) => e.toggleBlockquote();
    const toggleUnorderedList = window.EasyMDE ? window.EasyMDE.toggleUnorderedList : (e) => e.toggleUnorderedList();
    const toggleOrderedList = window.EasyMDE ? window.EasyMDE.toggleOrderedList : (e) => e.toggleOrderedList();
    const togglePreview = window.EasyMDE ? window.EasyMDE.togglePreview : (e) => e.togglePreview();

    return [
        { name: "bold", action: toggleBold, className: "bi bi-type-bold", title: "Bold" },
        { name: "italic", action: toggleItalic, className: "bi bi-type-italic", title: "Italic" },
        {
            name: "underline",
            action: (editor) => {
                editor.codemirror.replaceSelection(`<u>${editor.codemirror.getSelection()}</u>`);
                if (changeCallback) changeCallback();
            },
            className: "bi bi-type-underline",
            title: "Underline"
        },
        { name: "heading", action: toggleHeadingSmaller, className: "bi bi-type-h1", title: "Heading" }, "|",
        { name: "quote", action: toggleBlockquote, className: "bi bi-chat-left-quote", title: "Quote" },
        { name: "unordered-list", action: toggleUnorderedList, className: "bi bi-list-ul", title: "Generic List" },
        { name: "ordered-list", action: toggleOrderedList, className: "bi bi-list-ol", title: "Numbered List" }, "|",
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
            className: "bi bi-plus-circle",
            title: "Insert LaTeX ($$)"
        },
        {
            name: "upload-image",
            className: "bi bi-upload",
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
            className: "bi bi-image",
            title: "Upload and Insert Image"
        },
        "|", { name: "preview", action: togglePreview, className: "bi bi-eye text-indigo-400 font-bold", title: "Toggle Preview", noDisable: true }
    ];
}

export function getOrCreateAlertModal() {
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
                <input type="text" id="customAlertInput" class="custom-alert-input hidden" placeholder="Enter value...">
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
                display: flex; align-items: center; justify-content: center; opacity: 1; transition: opacity 0.2s ease;
                will-change: opacity;
            }
            .custom-alert-modal.hidden { display: none !important; opacity: 0; }
            .custom-alert-backdrop {
                position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(8, 12, 21, 0.7);
            }
            .custom-alert-box {
                position: relative; background: #111827; border-radius: 16px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                width: 90%; max-width: 440px; padding: 28px; border: 1px solid rgba(255, 255, 255, 0.08);
                display: flex; flex-direction: column; align-items: center; text-align: center;
                transform: scale(1); transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1); z-index: 1;
                will-change: transform;
                transform-gpu: translate3d(0,0,0);
            }
            .custom-alert-modal.hidden .custom-alert-box { transform: scale(0.95); }
            .custom-alert-icon {
                display: flex; align-items: center; justify-content: center; width: 56px; height: 56px;
                border-radius: 50%; background-color: rgba(99, 102, 241, 0.1); color: #818cf8; margin-bottom: 18px;
            }
            .custom-alert-icon.warning { background-color: rgba(245, 158, 11, 0.1); color: #fbbf24; }
            .custom-alert-icon.error { background-color: rgba(239, 68, 68, 0.1); color: #f87171; }
            .custom-alert-icon.success { background-color: rgba(16, 185, 129, 0.1); color: #34d399; }
            .custom-alert-content { margin-bottom: 24px; width: 100%; }
            .custom-alert-title { font-size: 1.2rem; font-weight: 700; color: #f8fafc; margin-bottom: 10px; font-family: system-ui, -apple-system, sans-serif; }
            .custom-alert-message { font-size: 0.95rem; color: #94a3b8; line-height: 1.6; margin: 0; font-family: system-ui, -apple-system, sans-serif; }
            .custom-alert-input {
                width: 100%; margin-top: 16px; padding: 10px 14px; background: #1e293b; color: #ffffff;
                border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 8px; font-size: 0.95rem; outline: none;
                transition: all 0.2s ease; font-family: system-ui, -apple-system, sans-serif;
            }
            .custom-alert-input:focus {
                border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25); background: #1e293b;
            }
            .custom-alert-actions { display: flex; gap: 12px; width: 100%; justify-content: center; }
            .custom-alert-btn { flex: 1; max-width: 160px; padding: 10px 18px; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.15s ease; border: none; outline: none; display: inline-flex; align-items: center; justify-content: center; }
            .custom-alert-btn.btn-primary { background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); color: #ffffff; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
            .custom-alert-btn.btn-primary:hover { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); transform: translateY(-1px); box-shadow: 0 6px 12px rgba(79, 70, 229, 0.4); }
            .custom-alert-btn.btn-secondary { background-color: #1e293b; color: #e2e8f0; border: 1px solid rgba(255, 255, 255, 0.08); }
            .custom-alert-btn.btn-secondary:hover { background-color: #334155; color: #ffffff; transform: translateY(-1px); }
        `;
        document.head.appendChild(style);
    }

    document.body.appendChild(modal);
    return modal;
}

export function showCustomAlert(message, type = 'info', title = 'Notification') {
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

export function showCustomConfirm(message, type = 'warning', title = 'Confirm Action') {
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

export function showCustomPrompt(message, defaultValue = '', title = 'Input Required') {
    return new Promise((resolve) => {
        const modal = getOrCreateAlertModal();
        const titleEl = modal.querySelector('#customAlertTitle');
        const msgEl = modal.querySelector('#customAlertMessage');
        const inputEl = modal.querySelector('#customAlertInput');
        const iconEl = modal.querySelector('#customAlertIcon');
        const confirmBtn = modal.querySelector('#customAlertConfirmBtn');
        const cancelBtn = modal.querySelector('#customAlertCancelBtn');

        titleEl.textContent = title;
        msgEl.textContent = message;

        inputEl.classList.remove('hidden');
        inputEl.value = defaultValue;

        cancelBtn.classList.remove('hidden');
        cancelBtn.textContent = 'Cancel';
        confirmBtn.className = 'custom-alert-btn btn-primary';
        confirmBtn.textContent = 'OK';

        iconEl.className = 'custom-alert-icon info';
        iconEl.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
        `;

        modal.classList.remove('hidden');

        setTimeout(() => {
            inputEl.focus();
            inputEl.select();
        }, 50);

        const handleConfirm = () => {
            const val = inputEl.value;
            cleanup();
            resolve(val);
        };

        const handleCancel = () => {
            cleanup();
            resolve(null);
        };

        const handleKeyDown = (e) => {
            if (e.key === 'Enter') {
                handleConfirm();
            }
        };

        const cleanup = () => {
            modal.classList.add('hidden');
            inputEl.classList.add('hidden');
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', handleCancel);
            inputEl.removeEventListener('keydown', handleKeyDown);
        };

        confirmBtn.addEventListener('click', handleConfirm);
        cancelBtn.addEventListener('click', handleCancel);
        inputEl.addEventListener('keydown', handleKeyDown);
    });
}

export function showAlert(type, message) {
    let mappedType = type;
    if (type === 'danger') mappedType = 'error';
    showCustomAlert(message, mappedType);
}

export function escapeHtml(str) {
    if (str == null) {
        return '';
    }
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

export function capitalizeFirstLetter(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

export function stripTags(html) {
    return String(html).replace(/<[^>]*>/g, '');
}

export function humanizeUnderscores(value) {
    if (!value) {
        return '';
    }
    return value.split('_').map(function (w) {
        return w.charAt(0).toUpperCase() + w.slice(1);
    }).join(' ');
}

export function getTomSelectValue(selectId) {
    const el = document.getElementById(selectId);
    if (!el || !el.tomselect) {
        return '';
    }
    const v = el.tomselect.getValue();
    return Array.isArray(v) ? (v[0] || '') : (v || '');
}

export function optionExistsInSelect(selectEl, value) {
    if (value === '' || value == null) {
        return true;
    }
    const s = String(value);
    return Array.from(selectEl.options).some(function (o) {
        return o.value === s;
    });
}

export function destroyTomSelectIfAny(selectEl) {
    if (selectEl && selectEl.tomselect) {
        selectEl.tomselect.destroy();
    }
}

export function initTomSelectOn(selectEl) {
    if (!selectEl || selectEl.tomselect) {
        return;
    }
    if (typeof TomSelect === 'undefined') {
        console.error('TomSelect is not loaded');
        return;
    }
    new TomSelect(selectEl, {
        create: false,
        sortField: { field: 'text', order: 'asc' }
    });
}

export function showToast(message, type = 'info') {
    // Basic toast implementation if needed, or just use showAlert
    showAlert(type, message);
}

export function confirmAction(message) {
    return showCustomConfirm(message);
}

export function captureTomSelectPreservation(submittedForm) {
    const ids = [
        'sectionTest', 'moduleSection', 'questionModule', 'bulkQuestionModule',
        'questionPassage', 'answerQuestionId', 'explanationQuestionId',
        'builderModuleId', 'questionsTableModuleFilter'
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

export function rebuildSectionTestTomSelect(tests, preserved, selectId) {
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

export function rebuildModuleSectionTomSelect(tests, preserved, selectId) {
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


export function rebuildQuestionModuleTomSelect(tests, preserved, selectId) {
    selectId = selectId || 'questionModule';
    const el = document.getElementById(selectId);
    if (!el) {
        return;
    }
    destroyTomSelectIfAny(el);
    el.innerHTML = selectId === 'questionsTableModuleFilter' ? '<option value="">All Modules</option>' : '<option value="">Search module...</option>';
    let hasData = false;
    tests.forEach(function (test) {
        (test.sections || []).forEach(function (section) {
            (section.modules || []).forEach(function (mod) {
                if (window.__currentUserRole === 'teacher' && mod.created_by !== window.__currentUserId) {
                    return;
                }
                hasData = true;
                const opt = document.createElement('option');
                opt.value = mod.id;
                opt.setAttribute('data-section-type', section.type);
                if (selectId === 'questionsTableModuleFilter') {
                    const secType = section.type === 'reading_writing' ? 'R&W' : 'Math';
                    opt.textContent = test.title + ' | ' + secType + ' - Mod ' + mod.module_number;
                } else {
                    opt.textContent = test.title + ' - ' + section.name + ' - Mod ' + mod.module_number + ' (' + humanizeUnderscores(mod.difficulty_level) + ')';
                }
                el.appendChild(opt);
            });
        });
    });
    if (!hasData) {
        const opt = document.createElement('option');
        opt.value = "";
        opt.disabled = true;
        opt.textContent = "No data yet";
        el.appendChild(opt);
    }
    initTomSelectOn(el);
    if (preserved && optionExistsInSelect(el, preserved)) {
        el.tomselect.setValue(String(preserved), true);
        if (selectId === 'questionModule' && window.autoFetchSectionType) {
            window.autoFetchSectionType(el);
        }
    }
}

export function rebuildQuestionPassageTomSelect(passages, preserved) {
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

/**
 * Display a hardware-accelerated translucent loading overlay native to the specific table container.
 * @param {string} containerId - The DOM ID of the table container element.
 */
export function showTableLoader(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    // Target the inner table wrapper to only cover the table content and remain under the title row
    const tableWrapper = container.querySelector('.overflow-x-auto') || container;
    
    let overlay = tableWrapper.querySelector('.table-loading-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'table-loading-overlay';
        overlay.innerHTML = `
            <div class="table-loading-spinner-wrapper">
                <div class="table-loading-spinner"></div>
                <div class="table-loading-text">Loading Data</div>
            </div>
        `;
        tableWrapper.appendChild(overlay);
    }
    
    // Ensure the tableWrapper is relatively positioned to anchor absolute overlay
    tableWrapper.classList.add('relative');
    
    // Force a browser reflow to trigger CSS transition correctly
    overlay.getBoundingClientRect();
    
    overlay.classList.add('show');
}

/**
 * Hide the translucent loading overlay from the specific table container with a smooth fade-out.
 * @param {string} containerId - The DOM ID of the table container element.
 */
export function hideTableLoader(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const tableWrapper = container.querySelector('.overflow-x-auto') || container;
    const overlay = tableWrapper.querySelector('.table-loading-overlay');
    if (overlay) {
        overlay.classList.remove('show');
        // Remove from DOM after CSS transition completes to free layout memory
        setTimeout(() => {
            if (!overlay.classList.contains('show')) {
                overlay.remove();
            }
        }, 300);
    }
}

/**
 * Format date string to DD/MM/YY (e.g. 29/05/26) safely and without timezone shifts.
 * @param {string} dateStr - Date string from database (Y-m-d H:i:s or ISO)
 * @returns {string} Formatted date
 */
export function formatDateToShort(dateStr) {
    if (!dateStr) return 'N/A';
    const parts = dateStr.split(/[\sT]/);
    if (parts[0]) {
        const dateParts = parts[0].split('-');
        if (dateParts.length === 3) {
            const year = dateParts[0].slice(-2);
            const month = dateParts[1];
            const day = dateParts[2];
            return `${day}/${month}/${year}`;
        }
    }
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return dateStr;
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = String(date.getFullYear()).slice(-2);
    return `${day}/${month}/${year}`;
}


