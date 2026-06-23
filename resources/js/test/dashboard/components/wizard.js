import { BASE_URL } from '../core/config.js';
import { showAlert } from '../utils/helpers.js';

const WORKFLOWS = {
    full_length: {
        label: 'Normal Full Test',
        title: 'New Normal Full Practice Test',
        breakDuration: 10,
        rows: [
            ['reading_writing', 1, 'standard', 32, 27],
            ['reading_writing', 2, 'standard', 32, 27],
            ['math', 1, 'standard', 35, 22],
            ['math', 2, 'standard', 35, 22],
        ],
    },
    adaptive_full_length: {
        label: 'Adaptive Full Test',
        title: 'New Adaptive Full Practice Test',
        breakDuration: 10,
        rows: [
            ['reading_writing', 1, 'standard', 32, 27],
            ['reading_writing', 2, 'easy', 32, 27],
            ['reading_writing', 2, 'hard', 32, 27],
            ['math', 1, 'standard', 35, 22],
            ['math', 2, 'easy', 35, 22],
            ['math', 2, 'hard', 35, 22],
        ],
    },
    short_test: {
        label: 'Short test',
        title: 'New Short Practice Test',
        breakDuration: 0,
        rows: [
            ['reading_writing', 1, 'standard', 20, 15],
            ['math', 1, 'standard', 20, 12],
        ],
    },
    module_only: {
        label: 'Single module',
        title: 'New Single Module Test',
        breakDuration: 0,
        rows: [['reading_writing', 1, 'standard', 32, 27]],
    },
    custom_test: {
        label: 'Custom structure',
        title: 'New Custom Practice Test',
        breakDuration: 0,
        rows: [['reading_writing', 1, 'standard', 20, 10]],
    },
};

let rowSequence = 0;

export function initQuickAuthorWizard({ onCreated } = {}) {
    const modalId = 'createTestWizardModal';
    const options = document.getElementById('wizard-options');
    const configFlow = document.getElementById('wizard-config-flow');
    const loading = document.getElementById('wizard-loading');
    const titleInput = document.getElementById('wizard-config-title');
    const configLabel = document.getElementById('wizard-config-label');
    const customizeButton = document.getElementById('wizard-toggle-customize');
    const customizePanel = document.getElementById('wizard-customize-panel');
    const populateControl = document.getElementById('wizard-populate-control');
    const populateInput = document.getElementById('wizard-populate-pool');
    const createButton = document.getElementById('wizard-btn-create-configured');
    const errorBox = document.getElementById('wizard-form-error');
    const feedback = document.getElementById('wizard-row-feedback');
    const reuseFlow = document.getElementById('wizard-reuse-flow');
    const reuseFields = document.getElementById('wizard-reuse-fields');
    const reuseSkeleton = document.getElementById('wizard-reuse-skeleton');
    const reuseEmpty = document.getElementById('wizard-reuse-empty');
    const reuseError = document.getElementById('wizard-reuse-error');
    const sourceTest = document.getElementById('wizard-source-test');
    const sourceItem = document.getElementById('wizard-source-item');
    const destinationTest = document.getElementById('wizard-destination-test');
    const destinationWrap = document.getElementById('wizard-destination-wrap');
    const derivedTitleWrap = document.getElementById('wizard-derived-title-wrap');
    const derivedTitle = document.getElementById('wizard-derived-title');
    const reuseSubmit = document.getElementById('wizard-reuse-submit');
    let currentWorkflow = null;
    let lastRemoved = null;
    let reuseCatalog = [];
    let reuseMode = null;
    let reuseKind = null;
    let fixedSourceId = null;
    let fixedSourceSectionId = null;

    if (!options || !configFlow || !titleInput || !createButton) return;

    document.getElementById('wizard-btn-full-sat')?.addEventListener('click', () => openWorkflow('full_length'));
    document.getElementById('wizard-btn-adaptive-sat')?.addEventListener('click', () => openWorkflow('adaptive_full_length'));
    document.getElementById('wizard-btn-short-test')?.addEventListener('click', () => openWorkflow('short_test'));
    document.getElementById('wizard-btn-module-only')?.addEventListener('click', () => openWorkflow('module_only'));
    document.getElementById('wizard-btn-custom')?.addEventListener('click', () => openWorkflow('custom_test'));
    document.getElementById('wizard-btn-from-section')?.addEventListener('click', () => openReuseFlow('section', 'derive'));
    document.getElementById('wizard-btn-from-module')?.addEventListener('click', () => openReuseFlow('module', 'derive'));
    document.getElementById('wizard-btn-back')?.addEventListener('click', resetWizard);
    document.getElementById('wizard-btn-add-row')?.addEventListener('click', () => {
        addModuleRow(['reading_writing', nextModuleNumber('reading_writing'), 'standard', 20, 10], true);
        updateSummary();
    });
    document.getElementById('wizard-undo-remove')?.addEventListener('click', undoRemove);
    customizeButton?.addEventListener('click', () => setCustomizeOpen(customizePanel.classList.contains('hidden')));
    createButton.addEventListener('click', createConfiguredTest);
    sourceTest?.addEventListener('change', populateSourceItems);
    sourceItem?.addEventListener('change', updateReuseSummary);
    destinationTest?.addEventListener('change', updateReuseSubmitState);
    derivedTitle?.addEventListener('input', updateReuseSubmitState);
    reuseSubmit?.addEventListener('click', submitReuse);
    document.getElementById('wizard-reuse-back')?.addEventListener('click', resetWizard);
    document.getElementById('wizard-reuse-retry')?.addEventListener('click', loadReuseCatalog);
    window.addEventListener('open-content-reuse', event => {
        const detail = event.detail || {};
        window.dispatchEvent(new CustomEvent('open-modal', { detail: modalId }));
        fixedSourceId = Number(detail.id);
        fixedSourceSectionId = Number(detail.sectionId || detail.id);
        openReuseFlow(detail.kind, 'reuse');
    });
    window.addEventListener('open-modal', event => {
        if (event.detail === modalId) resetWizard();
    });

    function resetWizard() {
        currentWorkflow = null;
        options.classList.remove('hidden');
        configFlow.classList.add('hidden');
        reuseFlow?.classList.add('hidden');
        loading.classList.add('hidden');
        loading.classList.remove('flex');
        createButton.disabled = false;
        hideError();
        feedback?.classList.add('hidden');
        feedback?.classList.remove('flex');
        fixedSourceId = null;
        fixedSourceSectionId = null;
        if (sourceTest) sourceTest.disabled = false;
        if (sourceItem) sourceItem.disabled = false;
    }

    async function openReuseFlow(kind, mode) {
        reuseKind = kind;
        reuseMode = mode;
        currentWorkflow = null;
        options.classList.add('hidden');
        configFlow.classList.add('hidden');
        reuseFlow.classList.remove('hidden');
        document.getElementById('wizard-reuse-kind').textContent = kind === 'section' ? 'Section' : 'Module';
        document.getElementById('wizard-reuse-title').textContent = mode === 'derive' ? `Create from an existing ${kind}` : `Reuse ${kind} in another test`;
        document.getElementById('wizard-reuse-help').textContent = mode === 'derive'
            ? 'Choose trusted content to copy into a new private draft.'
            : 'Choose an owned draft destination. Source content will not be changed.';
        destinationWrap.classList.toggle('hidden', mode !== 'reuse');
        destinationWrap.classList.toggle('block', mode === 'reuse');
        derivedTitleWrap.classList.toggle('hidden', mode === 'reuse');
        reuseSubmit.lastChild.textContent = '';
        reuseSubmit.childNodes[0].textContent = mode === 'derive' ? 'Create independent copy ' : 'Copy into draft ';
        await loadReuseCatalog();
    }

    async function loadReuseCatalog() {
        reuseFields.classList.add('hidden');
        reuseEmpty.classList.add('hidden');
        reuseError.classList.add('hidden');
        reuseSkeleton.classList.remove('hidden');
        reuseSubmit.disabled = true;
        try {
            const response = await fetch(`${BASE_URL}/tests/reusable-content`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('Could not load reusable content.');
            reuseCatalog = (await response.json()).tests || [];
            const available = reuseCatalog.filter(test => (test.sections || []).some(section => reuseKind === 'section' || (section.modules || []).length));
            if (!available.length) {
                reuseEmpty.classList.remove('hidden');
                return;
            }
            sourceTest.innerHTML = available.map(test => `<option value="${test.id}">${escapeOption(test.title)}</option>`).join('');
            const destinations = reuseCatalog.filter(test => test.status === 'draft' && (test.created_by === window.__currentUserId || window.__currentUserRole === 'admin'));
            destinationTest.innerHTML = '<option value="">Choose a destination draft</option>' + destinations.map(test => `<option value="${test.id}">${escapeOption(test.title)}</option>`).join('');
            populateSourceItems();
            if (reuseMode === 'reuse') selectFixedSource();
            reuseFields.classList.remove('hidden');
            requestAnimationFrame(() => (reuseMode === 'reuse' ? destinationTest : sourceTest).focus());
        } catch (error) {
            showReuseError(error.message || 'Could not load reusable content.');
        } finally {
            reuseSkeleton.classList.add('hidden');
            updateReuseSubmitState();
        }
    }

    function sourceItemsFor(test) {
        if (!test) return [];
        if (reuseKind === 'section') return (test.sections || []).map(section => ({ id: section.id, sectionId: section.id, label: section.name, section, test }));
        return (test.sections || []).flatMap(section => (section.modules || []).map(module => ({ id: module.id, sectionId: section.id, label: `${section.name} · Module ${module.module_number} · ${module.difficulty_level}`, module, section, test })));
    }

    function populateSourceItems() {
        const test = reuseCatalog.find(item => Number(item.id) === Number(sourceTest.value));
        const items = sourceItemsFor(test);
        sourceItem.innerHTML = items.map(item => `<option value="${item.id}" data-section-id="${item.sectionId}">${escapeOption(item.label)}</option>`).join('');
        updateReuseSummary();
    }

    function selectFixedSource() {
        for (const test of reuseCatalog) {
            const match = sourceItemsFor(test).find(item => Number(item.id) === fixedSourceId && (reuseKind !== 'module' || Number(item.sectionId) === fixedSourceSectionId));
            if (!match) continue;
            sourceTest.value = String(test.id);
            populateSourceItems();
            sourceItem.value = String(match.id);
            sourceTest.disabled = true;
            sourceItem.disabled = true;
            updateReuseSummary();
            return;
        }
        showReuseError('Selected source is no longer available.');
    }

    function selectedSource() {
        const test = reuseCatalog.find(item => Number(item.id) === Number(sourceTest.value));
        return sourceItemsFor(test).find(item => Number(item.id) === Number(sourceItem.value));
    }

    function updateReuseSummary() {
        const source = selectedSource();
        const summary = document.getElementById('wizard-source-summary');
        if (!source) { summary.textContent = 'Choose content to review its details.'; updateReuseSubmitState(); return; }
        const modules = reuseKind === 'section' ? (source.section.modules || []) : [source.module];
        const questions = modules.reduce((total, module) => total + Number(module.questions_count || 0), 0);
        const minutes = modules.reduce((total, module) => total + Number(module.duration_minutes || 0), 0);
        const adaptive = modules.some(module => module.difficulty_level === 'easy') && modules.some(module => module.difficulty_level === 'hard');
        summary.innerHTML = `<strong class="text-slate-900">${escapeOption(source.label)}</strong><dl class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-xs"><div><dt class="text-slate-500">Flow</dt><dd class="font-bold text-slate-800">${adaptive ? 'Adaptive' : 'Linear'}</dd></div><div><dt class="text-slate-500">Modules</dt><dd class="font-bold text-slate-800">${modules.length}</dd></div><div><dt class="text-slate-500">Questions</dt><dd class="font-bold text-slate-800">${questions}</dd></div><div><dt class="text-slate-500">Duration</dt><dd class="font-bold text-slate-800">${minutes} min</dd></div></dl><p class="mt-3 text-xs text-slate-600">Section and module settings become independent. Question-bank items remain shared.</p>`;
        if (reuseMode === 'derive' && !derivedTitle.value) derivedTitle.value = `${source.label} Practice`;
        updateReuseSubmitState();
    }

    function updateReuseSubmitState() {
        const source = selectedSource();
        reuseSubmit.disabled = !source || (reuseMode === 'derive' ? !derivedTitle.value.trim() : !destinationTest.value);
    }

    async function submitReuse() {
        const source = selectedSource();
        if (!source || reuseSubmit.disabled) return;
        reuseSubmit.disabled = true;
        reuseSubmit.setAttribute('aria-busy', 'true');
        reuseError.classList.add('hidden');
        const isModule = reuseKind === 'module';
        const url = reuseMode === 'derive'
            ? `${BASE_URL}/${isModule ? 'modules' : 'sections'}/${source.id}/derive-test`
            : `${BASE_URL}/${isModule ? 'modules' : 'sections'}/${source.id}/reuse`;
        const payload = reuseMode === 'derive' ? { title: derivedTitle.value.trim() } : { destination_test_id: Number(destinationTest.value) };
        if (isModule) payload.source_section_id = source.sectionId;
        try {
            const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: JSON.stringify(payload) });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || (result.errors ? Object.values(result.errors).flat().join(' ') : 'Copy failed.'));
            const handoffTarget = typeof onCreated === 'function' ? await onCreated(result.data) : null;
            window.dispatchEvent(new CustomEvent('close-modal', { detail: modalId }));
            showAlert('success', reuseMode === 'derive' ? `Draft created: ${result.data.title}.` : `Content copied into ${result.data.title}.`);
            resetWizard();
            window.setTimeout(() => handoffTarget?.focus({ preventScroll: true }), 100);
        } catch (error) {
            showReuseError(error.message || 'Copy failed. Please try again.');
        } finally {
            reuseSubmit.removeAttribute('aria-busy');
            updateReuseSubmitState();
        }
    }

    function showReuseError(message) {
        reuseError.textContent = message;
        reuseError.classList.remove('hidden');
        reuseError.focus();
    }

    function escapeOption(value) {
        return String(value ?? '').replace(/[&<>"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[character]);
    }

    function openWorkflow(type) {
        currentWorkflow = type;
        const preset = WORKFLOWS[type];
        titleInput.value = '';
        titleInput.placeholder = preset.title;
        configLabel.textContent = preset.label;
        populateInput.checked = false;
        populateControl?.classList.toggle('hidden', type !== 'custom_test');
        populateControl?.classList.toggle('flex', type === 'custom_test');
        options.classList.add('hidden');
        loading.classList.add('hidden');
        configFlow.classList.remove('hidden');
        renderRows(preset.rows);
        setCustomizeOpen(type === 'custom_test');
        updateSummary();
        hideError();
        requestAnimationFrame(() => titleInput.focus());
    }

    function setCustomizeOpen(open) {
        customizePanel.classList.toggle('hidden', !open);
        customizeButton.setAttribute('aria-expanded', String(open));
        customizeButton.querySelector('span').textContent = open ? 'Hide module settings' : 'Customize modules';
        if (open) {
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            requestAnimationFrame(() => customizePanel.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' }));
        }
    }

    async function createConfiguredTest() {
        if (!currentWorkflow) return;

        const preset = WORKFLOWS[currentWorkflow];
        const modules = collectRows();
        const invalid = modules.find(row => row.duration_minutes < 1 || row.total_questions < 1);
        if (modules.length === 0 || invalid) {
            showError(modules.length === 0 ? 'Add at least one module.' : 'Duration and question count must be positive numbers.');
            setCustomizeOpen(true);
            return;
        }

        const payload = {
            title: titleInput.value.trim() || preset.title,
            test_type: currentWorkflow,
            break_duration_minutes: preset.breakDuration,
            populate_from_pool: currentWorkflow === 'custom_test' && populateInput.checked,
            modules,
        };

        hideError();
        configFlow.classList.add('hidden');
        loading.classList.remove('hidden');
        loading.classList.add('flex');
        createButton.disabled = true;

        let createdTest = null;
        try {
            const response = await fetch(`${BASE_URL}/tests/generate-configured`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!response.ok) {
                const details = result.errors ? Object.values(result.errors).flat().join(' ') : '';
                throw new Error(details || result.message || 'Could not create the draft.');
            }

            createdTest = result.data;
            const handoffTarget = typeof onCreated === 'function' ? await onCreated(createdTest) : null;
            window.dispatchEvent(new CustomEvent('close-modal', { detail: modalId }));
            showAlert('success', `Draft created: ${createdTest.title}. First module selected.`);
            resetWizard();
            window.setTimeout(() => handoffTarget?.focus({ preventScroll: true }), 100);
        } catch (error) {
            if (createdTest) {
                window.dispatchEvent(new CustomEvent('close-modal', { detail: modalId }));
                document.getElementById('tests-tab')?.click();
                showAlert('warning', `Draft created: ${createdTest.title}. Open it from Practice Tests to continue.`);
                resetWizard();
                return;
            }
            loading.classList.add('hidden');
            loading.classList.remove('flex');
            configFlow.classList.remove('hidden');
            createButton.disabled = false;
            showError(error.message || 'Could not create the draft. Please try again.');
        }
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
        errorBox.focus?.();
    }

    function hideError() {
        errorBox.textContent = '';
        errorBox.classList.add('hidden');
    }

    function undoRemove() {
        if (!lastRemoved) return;
        addModuleRow(lastRemoved.row, false, lastRemoved.index);
        lastRemoved = null;
        feedback.classList.add('hidden');
        feedback.classList.remove('flex');
        updateSummary();
    }

    function removeRow(tr) {
        const rows = Array.from(document.querySelectorAll('#wizard-module-rows .wizard-module-row'));
        if (rows.length <= 1) {
            showError('A test needs at least one module.');
            return;
        }

        lastRemoved = { row: readRow(tr), index: rows.indexOf(tr) };
        tr.remove();
        feedback.classList.remove('hidden');
        feedback.classList.add('flex');
        updateSummary();
    }

    function renderRows(rows) {
        const tbody = document.getElementById('wizard-module-rows');
        tbody.innerHTML = '';
        rows.forEach(row => addModuleRow(row));
    }

    function addModuleRow(row, focus = false, insertIndex = null) {
        const tbody = document.getElementById('wizard-module-rows');
        const id = ++rowSequence;
        const tr = document.createElement('tr');
        tr.className = 'wizard-module-row';
        tr.innerHTML = `
            <td class="px-3 py-2.5"><label class="sr-only" for="wizard-section-${id}">Section</label><select id="wizard-section-${id}" class="wizard-section min-h-11 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"><option value="reading_writing">R&amp;W</option><option value="math">Math</option></select></td>
            <td class="px-3 py-2.5"><label class="sr-only" for="wizard-module-${id}">Module number</label><input id="wizard-module-${id}" type="number" min="1" max="10" class="wizard-module-number min-h-11 w-20 rounded-lg border border-slate-300 bg-white px-2 py-2 text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"></td>
            <td class="px-3 py-2.5"><label class="sr-only" for="wizard-difficulty-${id}">Difficulty</label><select id="wizard-difficulty-${id}" class="wizard-difficulty min-h-11 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"><option value="standard">Standard</option><option value="easy">Easy</option><option value="hard">Hard</option></select></td>
            <td class="px-3 py-2.5"><label class="sr-only" for="wizard-duration-${id}">Duration in minutes</label><input id="wizard-duration-${id}" type="number" min="1" max="240" class="wizard-duration min-h-11 w-24 rounded-lg border border-slate-300 bg-white px-2 py-2 text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"></td>
            <td class="px-3 py-2.5"><label class="sr-only" for="wizard-questions-${id}">Question count</label><input id="wizard-questions-${id}" type="number" min="1" max="100" class="wizard-questions min-h-11 w-24 rounded-lg border border-slate-300 bg-white px-2 py-2 text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"></td>
            <td class="px-3 py-2.5 text-right"><button type="button" class="wizard-remove-row inline-flex h-11 w-11 items-center justify-center rounded-lg text-rose-700 hover:bg-rose-50 focus-visible:ring-2 focus-visible:ring-rose-500/30"><i class="bi bi-trash" aria-hidden="true"></i></button></td>`;

        const [section, moduleNumber, difficulty, duration, questions] = row;
        tr.querySelector('.wizard-section').value = section;
        tr.querySelector('.wizard-module-number').value = moduleNumber;
        tr.querySelector('.wizard-difficulty').value = difficulty;
        tr.querySelector('.wizard-duration').value = duration;
        tr.querySelector('.wizard-questions').value = questions;
        tr.querySelectorAll('input, select').forEach(control => control.addEventListener('input', updateSummary));
        tr.querySelector('.wizard-remove-row').addEventListener('click', () => removeRow(tr));

        const reference = insertIndex === null ? null : tbody.children[insertIndex];
        tbody.insertBefore(tr, reference || null);
        updateRowLabels();
        if (focus) tr.querySelector('.wizard-section').focus();
    }

    function readRow(tr) {
        return [
            tr.querySelector('.wizard-section').value,
            clampInteger(tr.querySelector('.wizard-module-number').value, 1, 10),
            tr.querySelector('.wizard-difficulty').value,
            clampInteger(tr.querySelector('.wizard-duration').value, 0, 240),
            clampInteger(tr.querySelector('.wizard-questions').value, 0, 100),
        ];
    }

    function collectRows() {
        return Array.from(document.querySelectorAll('#wizard-module-rows .wizard-module-row')).map(tr => {
            const [sectionType, moduleNumber, difficulty, duration, questions] = readRow(tr);
            return { section_type: sectionType, module_number: moduleNumber, difficulty_level: difficulty, duration_minutes: duration, total_questions: questions };
        });
    }

    function updateRowLabels() {
        document.querySelectorAll('#wizard-module-rows .wizard-module-row').forEach(tr => {
            const section = tr.querySelector('.wizard-section').value === 'math' ? 'Math' : 'Reading and Writing';
            const moduleNumber = tr.querySelector('.wizard-module-number').value;
            const difficulty = tr.querySelector('.wizard-difficulty').value;
            tr.querySelector('.wizard-remove-row').setAttribute('aria-label', `Remove ${section} module ${moduleNumber}, ${difficulty}`);
        });
    }

    function updateSummary() {
        if (!currentWorkflow) return;
        const rows = collectRows();
        const summary = document.getElementById('wizard-module-summary');
        const totals = document.getElementById('wizard-summary-totals');
        summary.innerHTML = '';

        rows.forEach(row => {
            const item = document.createElement('div');
            item.className = 'flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm';
            const name = document.createElement('strong');
            name.className = 'text-slate-800';
            name.textContent = `${row.section_type === 'math' ? 'Math' : 'Reading & Writing'} · Module ${row.module_number} · ${capitalize(row.difficulty_level)}`;
            const meta = document.createElement('span');
            meta.className = 'text-xs font-semibold text-slate-600';
            meta.textContent = `${row.duration_minutes} min · ${row.total_questions} questions`;
            item.append(name, meta);
            summary.appendChild(item);
        });

        const sections = new Set(rows.map(row => row.section_type)).size;
        const duration = rows.reduce((total, row) => total + row.duration_minutes, 0) + WORKFLOWS[currentWorkflow].breakDuration;
        const questions = rows.reduce((total, row) => total + row.total_questions, 0);
        totals.innerHTML = '';
        [`${sections} ${sections === 1 ? 'section' : 'sections'}`, `${rows.length} ${rows.length === 1 ? 'module' : 'modules'}`, `${questions} questions`, `${duration} min`].forEach(value => {
            const chip = document.createElement('span');
            chip.className = 'rounded-full bg-slate-100 px-2.5 py-1';
            chip.textContent = value;
            totals.appendChild(chip);
        });
        updateRowLabels();
    }

    function nextModuleNumber(sectionType) {
        const rows = collectRows().filter(row => row.section_type === sectionType);
        return rows.length ? Math.min(10, Math.max(...rows.map(row => row.module_number)) + 1) : 1;
    }
}

function capitalize(value) {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function clampInteger(value, min, max) {
    const parsed = Number.parseInt(value, 10);
    return Number.isNaN(parsed) ? min : Math.max(min, Math.min(max, parsed));
}
