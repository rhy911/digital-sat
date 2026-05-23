import { BASE_URL } from '../core/config.js';
import { showAlert, showCustomConfirm } from '../utils/helpers.js';

export function initQuickAuthorWizard() {
    const fullSatBtn = document.getElementById('wizard-btn-full-sat');
    const shortTestBtn = document.getElementById('wizard-btn-short-test');
    const moduleOnlyBtn = document.getElementById('wizard-btn-module-only');
    const customBtn = document.getElementById('wizard-btn-custom');
    const customFlow = document.getElementById('wizard-custom-flow');
    const loadingEl = document.getElementById('wizard-loading');
    const optionsGrid = fullSatBtn?.parentElement;

    // Custom flow navigation fields
    const testSelect = document.getElementById('wizard-select-test');
    const targetStep = document.getElementById('wizard-step-target');
    const launchStep = document.getElementById('wizard-step-launch');
    const launchBtn = document.getElementById('wizard-btn-launch');
    const backBtn = document.getElementById('wizard-btn-back');

    if (!fullSatBtn || !shortTestBtn || !customBtn) return;

    fullSatBtn.addEventListener('click', () => generateStructure('full_length'));
    shortTestBtn.addEventListener('click', () => generateStructure('short_test'));
    moduleOnlyBtn?.addEventListener('click', () => generateStructure('module_only'));

    customBtn.addEventListener('click', () => {
        optionsGrid.classList.add('d-none');
        customFlow.classList.remove('d-none');
        populateTestSelect();
    });

    backBtn?.addEventListener('click', () => {
        customFlow.classList.add('d-none');
        optionsGrid.classList.remove('d-none');
        if (testSelect) {
            testSelect.value = '';
            targetStep?.classList.add('d-none');
            launchStep?.classList.add('d-none');
        }
    });

    async function generateStructure(testType) {
        const title = await promptForTitle(testType);
        if (!title) return;

        showLoading(true);
        try {
            const response = await fetch(`${BASE_URL}/tests/generate-full`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ title, test_type: testType })
            });

            const result = await response.json();
            if (response.ok) {
                showAlert('success', 'Structure generated! Refreshing...');
                window.location.reload();
            } else {
                showAlert('danger', result.message || 'Generation failed');
                showLoading(false);
            }
        } catch (error) {
            showAlert('danger', 'Error: ' + error.message);
            showLoading(false);
        }
    }

    function showLoading(show) {
        if (show) {
            optionsGrid.classList.add('d-none');
            customFlow.classList.add('d-none');
            loadingEl.classList.remove('d-none');
        } else {
            optionsGrid.classList.remove('d-none');
            loadingEl.classList.add('d-none');
        }
    }

    async function promptForTitle(testType) {
        let defaultTitle = 'New Full Practice Test';
        if (testType === 'short_test') defaultTitle = 'New Short Practice Test';
        if (testType === 'module_only') defaultTitle = 'New Single Module Test';
        
        // Using native prompt for simplicity in this wizard, or could be a custom modal step
        const title = prompt('Enter a title for the new test:', defaultTitle);
        return title?.trim();
    }

    function populateTestSelect() {
        const select = document.getElementById('wizard-select-test');
        if (!select || !window.__tdLatestTests) return;

        // Keep only first option
        select.innerHTML = '<option value="">Choose a test...</option>';
        window.__tdLatestTests.forEach(test => {
            const opt = document.createElement('option');
            opt.value = test.id;
            opt.textContent = test.title;
            select.appendChild(opt);
        });
    }

    // Custom flow handling
    testSelect?.addEventListener('change', () => {
        if (testSelect.value) {
            targetStep.classList.remove('d-none');
            launchStep.classList.remove('d-none');
        } else {
            targetStep.classList.add('d-none');
            launchStep.classList.add('d-none');
        }
    });

    launchBtn?.addEventListener('click', () => {
        const testId = testSelect.value;
        const domain = document.getElementById('wizard-select-domain').value;
        const modulePos = document.getElementById('wizard-select-module').value;

        // Logic to find or create section/module and redirect to builder
        // This part needs to be integrated with existing builder logic
        showAlert('info', 'Redirecting to builder... (In development)');
    });
}
