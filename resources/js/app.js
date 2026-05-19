import './bootstrap';
import 'bootstrap';

export function initDropdownToggle({
    triggerId = 'userDropdown',
    menuId = 'dropdownMenu',
    openClass = 'show',
} = {}) {
    const triggerEl = document.getElementById(triggerId);
    const menuEl = document.getElementById(menuId);

    if (!triggerEl || !menuEl) return { triggerEl: null, menuEl: null };

    const onTriggerClick = (e) => {
        e.stopPropagation();
        menuEl.classList.toggle(openClass);
    };

    const onDocumentClick = (e) => {
        if (!triggerEl.contains(e.target)) {
            menuEl.classList.remove(openClass);
        }
    };

    triggerEl.addEventListener('click', onTriggerClick);
    document.addEventListener('click', onDocumentClick);

    return {
        triggerEl,
        menuEl,
        destroy() {
            triggerEl.removeEventListener('click', onTriggerClick);
            document.removeEventListener('click', onDocumentClick);
        }
    };
}

export function initRadioToggleSection({
    activeRadioId,
    pastRadioId,
    activeContainerId,
    pastContainerId,
    activeLabelText = 'Active',
    pastLabelText = 'Past',
    checkedPrefix = '✓ ',
    hiddenClass = 'd-none',
} = {}) {
    const activeRadioEl = document.getElementById(activeRadioId);
    const pastRadioEl = document.getElementById(pastRadioId);
    const activeContainerEl = document.getElementById(activeContainerId);
    const pastContainerEl = document.getElementById(pastContainerId);
    const activeLabelEl = document.querySelector(`label[for="${activeRadioId}"]`);
    const pastLabelEl = document.querySelector(`label[for="${pastRadioId}"]`);

    if (!activeRadioEl || !pastRadioEl || !activeContainerEl || !pastContainerEl || !activeLabelEl || !pastLabelEl) {
        return;
    }

    const setActiveState = (isActive) => {
        activeContainerEl.classList.toggle(hiddenClass, !isActive);
        pastContainerEl.classList.toggle(hiddenClass, isActive);
        activeLabelEl.textContent = isActive ? `${checkedPrefix}${activeLabelText}` : activeLabelText;
        pastLabelEl.textContent = isActive ? pastLabelText : `${checkedPrefix}${pastLabelText}`;
    };

    activeRadioEl.addEventListener('change', () => setActiveState(true));
    pastRadioEl.addEventListener('change', () => setActiveState(false));
}

export function initAjaxLogout({
    formEl,
    redirectTo = '/',
    tokenStorageKey = 'api_token',
} = {}) {
    if (!formEl) return;

    formEl.addEventListener('submit', async (e) => {
        e.preventDefault();

        try {
            const response = await fetch(formEl.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': formEl.querySelector('input[name="_token"]')?.value,
                    Accept: 'application/json',
                },
            });

            if (response.ok) {
                localStorage.removeItem(tokenStorageKey);
                window.location.href = redirectTo;
            }
        } catch (error) {
            console.error('Logout error:', error);
        }
    });
}

export function initHomeDashboardPage() {
    const { menuEl } = initDropdownToggle({
        triggerId: 'userDropdown',
        menuId: 'dropdownMenu',
        openClass: 'show',
    });

    const logoutForm = menuEl?.querySelector?.('form');
    initAjaxLogout({ formEl: logoutForm, redirectTo: '/', tokenStorageKey: 'api_token' });

    // Teacher-assigned tests toggle
    initRadioToggleSection({
        activeRadioId: 'btnradio1',
        pastRadioId: 'btnradio2',
        activeContainerId: 'active-tests',
        pastContainerId: 'past-tests',
    });

    // Practice tests toggle
    initRadioToggleSection({
        activeRadioId: 'btnradio3',
        pastRadioId: 'btnradio4',
        activeContainerId: 'practice-active',
        pastContainerId: 'practice-past',
    });
}

export function initPracticeDashboardPage() {
    const { menuEl } = initDropdownToggle({
        triggerId: 'userDropdown',
        menuId: 'dropdownMenu',
        openClass: 'show',
    });

    const logoutForm = menuEl?.querySelector?.('form');
    initAjaxLogout({ formEl: logoutForm, redirectTo: '/', tokenStorageKey: 'api_token' });
}

export function smartRenderMath(element, options = {}) {
    if (!window.renderMathInElement) return;

    const defaultOptions = {
        delimiters: [
            { left: '$$', right: '$$', display: false },
            { left: '\\\\[', right: '\\\\]', display: true },
        ],
        ignoredTags: ["script", "noscript", "style", "textarea", "pre", "code", "option"],
        throwOnError: false,
        trust: true,
        preProcess: (math) => math.trim()
    };

    const finalOptions = { ...defaultOptions, ...options };

    window.renderMathInElement(element || document.body, finalOptions);
}

window.initDropdownToggle = initDropdownToggle;
window.initRadioToggleSection = initRadioToggleSection;
window.initAjaxLogout = initAjaxLogout;
window.initHomeDashboardPage = initHomeDashboardPage;
window.initPracticeDashboardPage = initPracticeDashboardPage;
window.smartRenderMath = smartRenderMath;