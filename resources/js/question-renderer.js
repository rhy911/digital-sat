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

    return ensureMathRuntime().then(({ renderMathInElement: render }) => {
        render(target, { ...defaultMathOptions, ...options });
    }).catch((err) => {
        console.error('Failed to render math:', err);
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
