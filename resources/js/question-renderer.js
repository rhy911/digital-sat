import katex from 'katex';
import renderMathInElement from 'katex/contrib/auto-render';
import 'katex/dist/katex.min.css';

window.katex = katex;
window.renderMathInElement = renderMathInElement;

export const defaultMathOptions = {
    delimiters: [
        { left: '$$', right: '$$', display: false },
        { left: '$', right: '$', display: false },
        { left: '\\(', right: '\\)', display: false },
        { left: '\\[', right: '\\]', display: true },
    ],
    ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code', 'option'],
    throwOnError: false,
    trust: true,
    preProcess: (math) => (typeof math === 'string' ? math.trim() : math),
};

let runtimePromise = null;

const CONTAINER_SELECTORS = [
    '[data-math-container]',
    '.question',
    '.passage-container',
    '.question-content',
    '.question-card',
    '.sd-modal-body',
    '.edit-question-preview-pane',
    '.math-content',
].join(', ');

export function getMathContainer(formula) {
    return formula.closest(CONTAINER_SELECTORS) || formula.parentElement;
}

function pendingFormulas(target) {
    return [
        ...(target.matches?.('.latex-pending') ? [target] : []),
        ...target.querySelectorAll('.latex-pending'),
    ].filter((formula) => formula.dataset.mathState === 'pending');
}

function showFormulaError(formula, options) {
    formula.replaceChildren();
    formula.dataset.mathState = 'error';

    const message = document.createElement('span');
    message.className = 'latex-error-message';
    message.setAttribute('role', 'status');
    message.textContent = 'Unable to display question. ';

    const retry = document.createElement('button');
    retry.className = 'latex-retry';
    retry.type = 'button';
    retry.textContent = 'Retry';
    retry.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        formula.textContent = formula.dataset.latexSource;
        formula.dataset.mathState = 'pending';

        const container = getMathContainer(formula);
        if (container) {
            container.dataset.mathContainerState = 'pending';
        }
        smartRenderMath(container || formula, options);
    });

    formula.append(message, retry);
}

export function ensureMathRuntime() {
    if (!runtimePromise) {
        runtimePromise = Promise.resolve({
            katex,
            renderMathInElement,
            options: defaultMathOptions,
        });
    }
    return runtimePromise;
}

export function smartRenderMath(element, options = {}) {
    const target = element || document.body;
    if (!target) return Promise.resolve();

    const formulas = pendingFormulas(target);
    const containers = new Set();

    formulas.forEach((formula) => {
        formula.dataset.latexSource ??= formula.textContent;
        const container = getMathContainer(formula);
        if (container) {
            container.dataset.mathContainerState = 'pending';
            containers.add(container);
        }
    });

    return ensureMathRuntime().then(({ renderMathInElement: render }) => {
        render(target, { ...defaultMathOptions, ...options });

        return new Promise((resolve) => {
            requestAnimationFrame(() => {
                formulas.forEach((formula) => {
                    if (formula.querySelector('.katex-error') || !formula.querySelector('.katex')) {
                        showFormulaError(formula, options);
                        return;
                    }

                    formula.dataset.mathState = 'ready';
                });

                containers.forEach((container) => {
                    const stillPending = container.querySelector('.latex-pending[data-math-state="pending"]');
                    if (!stillPending) {
                        delete container.dataset.mathContainerState;
                    }
                });

                resolve();
            });
        });
    }).catch((err) => {
        console.error('Failed to render math:', err);
        formulas.forEach((formula) => showFormulaError(formula, options));
        containers.forEach((container) => {
            delete container.dataset.mathContainerState;
        });
    });
}

// Global aliases for legacy callers and inline triggers
window.ensureMathRuntime = ensureMathRuntime;
window.smartRenderMath = smartRenderMath;

// Automatically render initial question content if present on load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        smartRenderMath(document.body);
    });
} else {
    smartRenderMath(document.body);
}
