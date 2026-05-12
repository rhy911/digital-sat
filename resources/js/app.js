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
            { left: '$', right: '$', display: false },
            { left: '\\(', right: '\\)', display: false },
            { left: '\\[', right: '\\]', display: true }
        ],
        ignoredTags: ["script", "noscript", "style", "textarea", "pre", "code", "option"],
        throwOnError: false
    };

    const finalOptions = { ...defaultOptions, ...options };

    function looksLikeCurrency(content) {
        // Match numbers, decimals, common currency suffixes (k, m, b)
        // Example: 100, 99.99, 20k, 1.2m
        return /^\d+([.,]\d+)?([kmb])?$/i.test(content.trim());
    }

    function looksLikeMath(content) {
        // Heuristic: contains LaTeX commands, symbols, or operators
        return /\\[a-zA-Z]+/.test(content) || // \frac, \alpha, etc.
               /[\^_=+\-]/.test(content) ||    // exponents, subscripts, equals, plus, minus
               /[{}]/.test(content) ||         // braces
               /[<>\/\\*]/.test(content);      // inequalities, slash, backslash, asterisk
    }

    // Pre-process text nodes to escape single '$' that look like currency
    // so KaTeX's auto-render doesn't pick them up.
    // However, KaTeX's auto-render is quite strict about matching delimiters.
    // A better approach is to wrap suspected currency in a tag or slightly modify the text.
    // Alternatively, we can use a custom pre-processor that only allows $...$ if it looks like math.
    
    // For simplicity and following the requested pipeline, we'll wrap the element
    // and use a more selective approach if we were building a custom parser,
    // but here we can utilize KaTeX's preProcess callback if available, 
    // or just let it run and then we fix false positives? 
    // Actually, KaTeX's renderMathInElement doesn't have a simple "skip this match" hook per-match.
    
    // Let's implement the pipeline: 
    // 1. Find all potential math strings using the delimiters.
    // 2. If it's $...$ and looksLikeCurrency(content) is true, we escape the dollars.

    const walk = (node) => {
        if (node.nodeType === 3) { // Text node
            let text = node.nodeValue;
            // This regex finds $...$ matches. 
            // We use a non-greedy match to find pairs.
            const newText = text.replace(/\$([^\$]+)\$/g, (match, content) => {
                if (looksLikeCurrency(content) && !looksLikeMath(content)) {
                    // It's probably currency like "$100$". 
                    // Wait, usually currency is just "$100". 
                    // But if they did "$100$", we treat as text.
                    return `<span>$</span>${content}<span>$</span>`; 
                }
                return match;
            });
            
            // Note: Single $ (like "$100") is already ignored by KaTeX because it lacks a closing $.
            // The biggest risk is something like "$x$ and $y$ are..." being misidentified.
            
            if (newText !== text) {
                const span = document.createElement('span');
                span.innerHTML = text.replace(/\$([^\$]+)\$/g, (match, content) => {
                    if (looksLikeCurrency(content) && !looksLikeMath(content)) {
                        return `\$${content}\$`; // Don't let KaTeX see it as a pair if it's currency
                    }
                    return match;
                });
                // node.parentNode.replaceChild(span, node); // This is risky during walk
            }
        } else if (node.nodeType === 1 && !finalOptions.ignoredTags.includes(node.tagName.toLowerCase())) {
            for (let i = 0; i < node.childNodes.length; i++) {
                walk(node.childNodes[i]);
            }
        }
    };

    // Actually, KaTeX's auto-render handles escaping. 
    // A more surgical way is to provide a custom filter.
    // But since we are using the CDN version, we'll just use the heuristic.
    
    window.renderMathInElement(element, finalOptions);
}

// Allow Blade inline scripts to reuse this safely
window.initDropdownToggle = initDropdownToggle;
window.initRadioToggleSection = initRadioToggleSection;
window.initAjaxLogout = initAjaxLogout;
window.initHomeDashboardPage = initHomeDashboardPage;
window.initPracticeDashboardPage = initPracticeDashboardPage;
window.smartRenderMath = smartRenderMath;