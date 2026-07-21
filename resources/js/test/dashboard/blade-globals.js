import { SKILL_DOMAINS } from './core/config.js';

/**
 * Small DOM-binding helpers invoked directly from onchange="" attributes in the
 * dashboard's Blade templates (section/module/question create forms).
 */
export function initBladeGlobalHelpers() {
    window.updateSectionName = function (select) {
        const nameInput = document.getElementById('sectionName');
        if (!nameInput) return;
        nameInput.value = select.value === 'reading_writing' ? 'Reading and Writing' : 'Math';
    };

    window.autoFetchSectionType = function (select) {
        const sectionType = select.options[select.selectedIndex].getAttribute('data-section-type');
        const sectionTypeSelect = document.getElementById('qSectionType');
        if (sectionType && sectionTypeSelect) {
            sectionTypeSelect.value = sectionType;
            window.updateSkillDomains(sectionTypeSelect);
        }
    };

    window.applyModuleDefaults = function (select) {
        const type = select.options[select.selectedIndex].getAttribute('data-type');
        const durationInput = document.getElementById('moduleDuration');
        const questionsInput = document.getElementById('totalQuestions');
        if (durationInput && questionsInput) {
            if (type === 'reading_writing') { durationInput.value = 32; questionsInput.value = 27; }
            else if (type === 'math') { durationInput.value = 35; questionsInput.value = 22; }
        }
    };

    window.updateSkillDomains = function (select) {
        const domainSelect = document.getElementById('skillDomain');
        if (!domainSelect) return;
        const type = select.value;
        const qTypeSelect = document.getElementById('questionType');
        const sprOption = qTypeSelect?.querySelector?.('option[value="student_produced_response"]');
        const sprHintContainer = document.getElementById('sprHintContainer');

        if (type === 'reading_writing') {
            if (qTypeSelect && qTypeSelect.value === 'student_produced_response') qTypeSelect.value = 'multiple_choice';
            if (sprOption) sprOption.style.display = 'none';
            if (sprHintContainer) sprHintContainer.classList.add('hidden');
        } else {
            if (sprOption) sprOption.style.display = 'block';
            if (sprHintContainer) sprHintContainer.classList.remove('hidden');
        }

        domainSelect.innerHTML = '<option value="">Select domain...</option>';
        if (type && SKILL_DOMAINS[type]) {
            SKILL_DOMAINS[type].forEach(domain => {
                const opt = document.createElement('option');
                opt.value = domain.value; opt.textContent = domain.label;
                domainSelect.appendChild(opt);
            });
        }
    };
}
