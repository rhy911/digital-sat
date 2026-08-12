import { humanizeUnderscores, stripTags } from './text.js';

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
        sortField: [{ field: '$order' }, { field: '$score' }],
        plugins: ['clear_button']
    });
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
                if (window.__currentUserRole === 'teacher' && mod.created_by !== window.__currentUserId && selectId !== 'questionsTableModuleFilter') {
                    return;
                }
                hasData = true;
                const opt = document.createElement('option');
                opt.value = mod.id;
                opt.setAttribute('data-section-type', section.type);
                const diffStr = humanizeUnderscores(mod.difficulty_level || 'standard');
                const capitalizedDiff = diffStr.charAt(0).toUpperCase() + diffStr.slice(1);
                if (selectId === 'questionsTableModuleFilter') {
                    const secType = section.type === 'reading_writing' ? 'R&W' : 'Math';
                    opt.textContent = test.title + ' | ' + secType + ' - Mod ' + mod.module_number + ' (' + capitalizedDiff + ')';
                } else {
                    opt.textContent = test.title + ' - ' + section.name + ' - Mod ' + mod.module_number + ' (' + capitalizedDiff + ')';
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
