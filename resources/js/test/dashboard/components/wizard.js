import { BASE_URL } from '../core/config.js';
import { showAlert } from '../utils/helpers.js';

const WORKFLOWS = {
    full_length: {
        label: 'Full SAT',
        title: 'New Full Practice Test',
        breakDuration: 10,
        populateFromPool: false,
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
        label: 'Short Test',
        title: 'New Short Practice Test',
        breakDuration: 0,
        populateFromPool: false,
        rows: [
            ['reading_writing', 1, 'standard', 20, 15],
            ['math', 1, 'standard', 20, 12],
        ],
    },
    module_only: {
        label: 'Module Only',
        title: 'New Single Module Test',
        breakDuration: 0,
        populateFromPool: false,
        rows: [
            ['reading_writing', 1, 'standard', 32, 27],
        ],
    },
    custom_test: {
        label: 'Custom Test from Pool',
        title: 'New Custom Pool Test',
        breakDuration: 0,
        populateFromPool: true,
        rows: [
            ['reading_writing', 1, 'standard', 20, 10],
            ['math', 1, 'standard', 20, 10],
        ],
    },
};

let currentWorkflow = null;

export function initQuickAuthorWizard() {
    const fullSatBtn = document.getElementById('wizard-btn-full-sat');
    const shortTestBtn = document.getElementById('wizard-btn-short-test');
    const moduleOnlyBtn = document.getElementById('wizard-btn-module-only');
    const customBtn = document.getElementById('wizard-btn-custom');
    const configFlow = document.getElementById('wizard-config-flow');
    const loadingEl = document.getElementById('wizard-loading');
    const optionsGrid = fullSatBtn?.parentElement;
    const backBtn = document.getElementById('wizard-btn-back');
    const createBtn = document.getElementById('wizard-btn-create-configured');
    const addRowBtn = document.getElementById('wizard-btn-add-row');
    const shortCounts = document.getElementById('wizard-short-counts');
    const rwCountInput = document.getElementById('wizard-short-rw-count');
    const mathCountInput = document.getElementById('wizard-short-math-count');

    if (!fullSatBtn || !shortTestBtn || !moduleOnlyBtn || !customBtn || !configFlow) return;

    fullSatBtn.addEventListener('click', () => openWorkflow('full_length'));
    shortTestBtn.addEventListener('click', () => openWorkflow('short_test'));
    moduleOnlyBtn.addEventListener('click', () => openWorkflow('module_only'));
    customBtn.addEventListener('click', () => openWorkflow('custom_test'));

    backBtn?.addEventListener('click', () => {
        configFlow.classList.add('hidden');
        loadingEl?.classList.add('hidden');
        optionsGrid?.classList.remove('hidden');
        currentWorkflow = null;
    });

    addRowBtn?.addEventListener('click', () => {
        addModuleRow(['reading_writing', nextModuleNumber('reading_writing'), 'standard', 20, 10], true);
    });

    createBtn?.addEventListener('click', createConfiguredTest);
    rwCountInput?.addEventListener('input', rebuildShortRows);
    mathCountInput?.addEventListener('input', rebuildShortRows);

    function openWorkflow(type) {
        currentWorkflow = type;
        const preset = WORKFLOWS[type];
        document.getElementById('wizard-config-title').value = preset.title;
        document.getElementById('wizard-config-label').textContent = preset.label;
        shortCounts?.classList.toggle('hidden', type !== 'short_test');
        optionsGrid?.classList.add('hidden');
        loadingEl?.classList.add('hidden');
        configFlow.classList.remove('hidden');
        renderRows(preset.rows);
    }

    function rebuildShortRows() {
        if (currentWorkflow !== 'short_test') return;

        const rwCount = clampInteger(rwCountInput?.value, 0, 10);
        const mathCount = clampInteger(mathCountInput?.value, 0, 10);
        const rows = [];

        for (let index = 1; index <= rwCount; index++) {
            rows.push(['reading_writing', index, 'standard', 20, 15]);
        }

        for (let index = 1; index <= mathCount; index++) {
            rows.push(['math', index, 'standard', 20, 12]);
        }

        renderRows(rows);
    }

    function showLoading(show) {
        if (show) {
            configFlow.classList.add('hidden');
            loadingEl?.classList.remove('hidden');
        } else {
            configFlow.classList.remove('hidden');
            loadingEl?.classList.add('hidden');
        }
    }

    async function createConfiguredTest() {
        if (!currentWorkflow) return;

        const payload = buildPayload();
        if (!payload) return;

        showLoading(true);
        try {
            const response = await fetch(`${BASE_URL}/tests/generate-configured`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const result = await response.json();
            if (response.ok) {
                showAlert('success', 'Test created successfully. Refreshing...');
                window.location.reload();
                return;
            }

            const details = result.errors ? Object.values(result.errors).flat().join(' ') : '';
            showAlert('danger', details || result.message || 'Generation failed');
            showLoading(false);
        } catch (error) {
            showAlert('danger', 'Error: ' + error.message);
            showLoading(false);
        }
    }

    function buildPayload() {
        const title = document.getElementById('wizard-config-title').value.trim();
        if (!title) {
            showAlert('danger', 'Please enter a test title.');
            return null;
        }

        const modules = collectRows();
        if (modules.length === 0) {
            showAlert('danger', 'Please add at least one module row.');
            return null;
        }

        const invalid = modules.find(row => row.duration_minutes < 1 || row.total_questions < 1);
        if (invalid) {
            showAlert('danger', 'Duration and question count must be positive numbers.');
            return null;
        }

        const preset = WORKFLOWS[currentWorkflow];
        return {
            title,
            test_type: currentWorkflow,
            status: 'draft',
            break_duration_minutes: preset.breakDuration,
            populate_from_pool: preset.populateFromPool,
            modules,
        };
    }
}

function renderRows(rows) {
    const tbody = document.getElementById('wizard-module-rows');
    if (!tbody) return;

    tbody.innerHTML = '';
    rows.forEach(row => addModuleRow(row));
}

function addModuleRow(row, focus = false) {
    const tbody = document.getElementById('wizard-module-rows');
    if (!tbody) return;

    const tr = document.createElement('tr');
    tr.className = 'wizard-module-row';
    tr.innerHTML = `
        <td class="px-3 py-3">
            <select class="wizard-section w-full bg-white border border-slate-200 rounded-lg px-2 py-2 text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none">
                <option value="reading_writing">R&W</option>
                <option value="math">Math</option>
            </select>
        </td>
        <td class="px-3 py-3">
            <input type="number" min="1" max="10" class="wizard-module-number w-20 bg-white border border-slate-200 rounded-lg px-2 py-2 text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none">
        </td>
        <td class="px-3 py-3">
            <select class="wizard-difficulty w-full bg-white border border-slate-200 rounded-lg px-2 py-2 text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none">
                <option value="standard">Standard</option>
                <option value="easy">Easy</option>
                <option value="hard">Hard</option>
            </select>
        </td>
        <td class="px-3 py-3">
            <input type="number" min="1" max="240" class="wizard-duration w-24 bg-white border border-slate-200 rounded-lg px-2 py-2 text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none">
        </td>
        <td class="px-3 py-3">
            <input type="number" min="1" max="100" class="wizard-questions w-24 bg-white border border-slate-200 rounded-lg px-2 py-2 text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none">
        </td>
        <td class="px-3 py-3 text-right">
            <button type="button" class="wizard-remove-row px-3 py-2 rounded-lg bg-rose-50 text-rose-700 border border-rose-100 hover:bg-rose-100" aria-label="Remove module row">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;

    const [section, moduleNumber, difficulty, duration, questions] = row;
    tr.querySelector('.wizard-section').value = section;
    tr.querySelector('.wizard-module-number').value = moduleNumber;
    tr.querySelector('.wizard-difficulty').value = difficulty;
    tr.querySelector('.wizard-duration').value = duration;
    tr.querySelector('.wizard-questions').value = questions;
    tr.querySelector('.wizard-remove-row').addEventListener('click', () => tr.remove());

    tbody.appendChild(tr);
    if (focus) tr.querySelector('.wizard-section')?.focus();
}

function collectRows() {
    return Array.from(document.querySelectorAll('#wizard-module-rows .wizard-module-row')).map(row => ({
        section_type: row.querySelector('.wizard-section').value,
        module_number: clampInteger(row.querySelector('.wizard-module-number').value, 1, 10),
        difficulty_level: row.querySelector('.wizard-difficulty').value,
        duration_minutes: clampInteger(row.querySelector('.wizard-duration').value, 0, 240),
        total_questions: clampInteger(row.querySelector('.wizard-questions').value, 0, 100),
    }));
}

function nextModuleNumber(sectionType) {
    const sectionRows = collectRows().filter(row => row.section_type === sectionType);
    if (sectionRows.length === 0) return 1;
    return Math.max(...sectionRows.map(row => row.module_number)) + 1;
}

function clampInteger(value, min, max) {
    const parsed = Number.parseInt(value, 10);
    if (Number.isNaN(parsed)) return min;
    return Math.max(min, Math.min(max, parsed));
}
